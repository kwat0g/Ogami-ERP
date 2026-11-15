<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use App\Common\DemoData\DemoSeedContext;
use App\Common\DemoData\DemoSeeder;
use App\Modules\Accounting\Enums\InvoiceStatus;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\Customer;
use App\Modules\Accounting\Models\Invoice;
use App\Modules\Accounting\Services\InvoicePaymentSubmissionService;
use App\Modules\Accounting\Services\InvoiceService;
use App\Modules\Auth\Models\User;
use App\Modules\B2B\Models\CustomerPortalUser;
use App\Modules\CRM\Enums\SalesOrderStatus;
use App\Modules\CRM\Models\PriceAgreement;
use App\Modules\CRM\Models\Product;
use App\Modules\CRM\Models\SalesOrder;
use App\Modules\CRM\Services\SalesOrderService;
use App\Modules\MRP\Enums\MrpRunTrigger;
use App\Modules\Production\Models\ProductRouting;
use App\Modules\MRP\Services\MrpAutomationService;
use App\Modules\MRP\Services\CapacityPlanningService;
use App\Modules\Production\Enums\WorkOrderStatus;
use App\Modules\Production\Enums\ProductionScheduleStatus;
use App\Modules\Production\Events\WorkOrderCompleted;
use App\Modules\Production\Models\ProductionSchedule;
use App\Modules\Production\Models\WoOperation;
use App\Modules\Production\Models\WorkOrder;
use App\Modules\Production\Models\WorkOrderCrew;
use App\Modules\Production\Models\WorkOrderOutput;
use App\Modules\Production\Services\WorkOrderAssignmentService;
use App\Modules\Production\Services\WorkOrderOutputService;
use App\Modules\Production\Services\WorkOrderService;
use App\Modules\Production\Services\WoOperationService;
use App\Modules\Quality\Enums\InspectionEntityType;
use App\Modules\Quality\Enums\InspectionMode;
use App\Modules\Quality\Enums\InspectionStage;
use App\Modules\Quality\Enums\InspectionStatus;
use App\Modules\Quality\Listeners\TriggerOutgoingQC;
use App\Modules\Quality\Models\Inspection;
use App\Modules\Quality\Models\InspectionMeasurement;
use App\Modules\Quality\Services\InspectionService;
use App\Modules\SupplyChain\Enums\DeliveryProofType;
use App\Modules\SupplyChain\Enums\DeliveryStatus;
use App\Modules\SupplyChain\Controllers\VehicleController;
use App\Modules\SupplyChain\Models\Delivery;
use App\Modules\SupplyChain\Models\DriverCredential;
use App\Modules\SupplyChain\Models\Vehicle;
use App\Modules\SupplyChain\Services\DeliveryService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use RuntimeException;

/** Creates one fully linked, completed BB-001 order-to-cash history chain. */
final class OrderToCashFulfillmentLayer extends DemoSeeder
{
    private const ORDER_MARKER = 'Monthly replenishment order for BB-001, customer cycle 01';

    private const OUTPUT_KEY = 'o2c-bb001-fulfilled-batch-1';

    public function __construct(
        private readonly SalesOrderService $salesOrders,
        private readonly MrpAutomationService $mrp,
        private readonly CapacityPlanningService $capacity,
        private readonly WorkOrderAssignmentService $assignments,
        private readonly WorkOrderService $workOrders,
        private readonly WoOperationService $operations,
        private readonly WorkOrderOutputService $outputs,
        private readonly InspectionService $inspections,
        private readonly TriggerOutgoingQC $outgoingQc,
        private readonly DeliveryService $deliveries,
        private readonly InvoiceService $invoices,
        private readonly InvoicePaymentSubmissionService $payments,
    ) {}

    public function key(): string
    {
        return 'order-to-cash-fulfillment';
    }

    /** @return list<string> */
    public function modules(): array
    {
        return ['CRM', 'MRP', 'Production', 'Quality', 'Inventory', 'SupplyChain', 'Accounting', 'Dashboard'];
    }

    /** @return list<string> */
    public function dependencies(): array
    {
        return ['order-to-cash-history', 'opening-stock-balances', 'quality-inspection-specs'];
    }

    protected function seedDemoData(DemoSeedContext $context): void
    {
        $asOf = $context->options->asOf->setTimezone('Asia/Manila')->startOfDay();
        $orderDay = $this->businessDay($asOf, -3);
        $shipDay = $this->businessDay($asOf, -1);
        $product = Product::query()->active()->where('part_number', 'BB-001')->first();
        if (! $product instanceof Product || ! $product->activeBom()->exists()
            || ! ProductRouting::query()->where('product_id', $product->id)->where('is_active', true)->exists()) {
            throw new RuntimeException('O2C fulfillment requires the active BB-001 product, BOM, and routing.');
        }

        $agreement = PriceAgreement::query()->activeOn($asOf)
            ->where('product_id', $product->id)
            ->whereHas('customer', static fn ($query) => $query->active())
            ->orderBy('customer_id')->first();
        $customer = $agreement instanceof PriceAgreement
            ? Customer::query()->active()->find((int) $agreement->customer_id)
            : null;
        if (! $customer instanceof Customer) {
            throw new RuntimeException('O2C fulfillment requires an active BB-001 customer price agreement effective on the as-of date.');
        }

        $sales = $this->actor('crm.sales_orders.create', 'crm.sales_orders.confirm');
        $planner = $this->actor('mrp.plans.run');
        $productionManager = $this->actor('production.work_orders.lifecycle', 'production.wo.record');
        $warehouse = $this->actor('inventory.issue.create');
        $qcMaker = $this->actor('quality.inspections.manage');
        $qcChecker = $this->actor('quality.inspections.review', excluding: (int) $qcMaker->id);
        $deliveryManager = $this->actor('supply_chain.deliveries.create', 'supply_chain.deliveries.confirm');
        $fleetManager = $this->actor('supply_chain.fleet.manage');
        $invoiceFinalizer = $this->actor('accounting.invoices.create', 'accounting.invoices.collect');

        $order = $this->ensureOrder($context, $customer, $product, $sales, $orderDay, $asOf);
        $line = $order->items()->firstOrFail();
        if ($order->status === SalesOrderStatus::Draft) {
            $context->withoutNotifications(fn () => $context->at($orderDay->setTime(9, 30), fn () => $context->asUser($sales, fn () => $this->salesOrders->confirm($order))));
            $order->refresh();
        }
        if (! in_array($order->status, [SalesOrderStatus::Confirmed, SalesOrderStatus::InProduction, SalesOrderStatus::PartiallyDelivered, SalesOrderStatus::Delivered, SalesOrderStatus::Invoiced, SalesOrderStatus::Paid, SalesOrderStatus::Closed], true)) {
            throw new RuntimeException("Seed-owned O2C order {$order->so_number} is not in a fulfillable confirmed state.");
        }

        $hasOrderWork = WorkOrder::query()->where('sales_order_id', $order->id)->whereNull('parent_wo_id')->exists();
        if (! $hasOrderWork) {
            $this->ensureMrp($context, $order, $planner, $orderDay->setTime(10, 0));
        }
        $workOrder = WorkOrder::query()->with('creator')->where('sales_order_id', $order->id)
            ->where('sales_order_item_id', $line->id)->whereNull('parent_wo_id')
            ->orderByDesc('id')->first();
        if (! $workOrder instanceof WorkOrder) {
            throw new RuntimeException("MRP did not create an order-linked BB-001 work order for {$order->so_number}.");
        }

        $base = ($workOrder->actual_start?->toImmutable() ?? $asOf->subDays(2)->setTime(8, 0))->addMinutes(10);
        if ($workOrder->status !== WorkOrderStatus::Completed && $workOrder->status !== WorkOrderStatus::Closed) {
            $slot = $this->ensureScheduled($context, $workOrder, $orderDay->setTime(10, 15));
            $crew = $this->ensureCrew($context, $workOrder, $slot, $productionManager);
            if ($slot->status === ProductionScheduleStatus::Pending) {
                $context->withoutNotifications(fn () => $context->at($slot->scheduled_start->toImmutable(), fn () => $context->asUser($planner, fn () => $this->capacity->confirm([(int) $slot->id], (int) $planner->id))));
            } elseif (! in_array($slot->status, [ProductionScheduleStatus::Confirmed, ProductionScheduleStatus::Executed], true)) {
                throw new RuntimeException("The BB-001 production schedule {$slot->id} is not in a fulfillable state.");
            }
            $workOrder->refresh();

            if ($workOrder->status === WorkOrderStatus::Confirmed) {
                $memberIds = $crew->members()->pluck('employee_id')->map(static fn ($id): int => (int) $id)->all();
                $context->withoutNotifications(fn () => $context->at($slot->scheduled_start->toImmutable(), fn () => $context->asUser($productionManager, fn () => $this->workOrders->start(
                    $workOrder->fresh(), (int) $productionManager->id,
                    ['expected_crew_id' => $crew->id, 'presence_confirmed_employee_ids' => $memberIds],
                ))));
            }

            $this->ensureMaterialHandovers($context, $workOrder, $crew, $warehouse);
            $this->passStartInspection($context, $workOrder, $crew, $productionManager, $qcMaker, $qcChecker, $base);
            $this->runOperations($context, $workOrder, $crew, $productionManager, $qcMaker, $qcChecker, $base->addMinutes(10));
            $output = WorkOrderOutput::query()->where('work_order_id', $workOrder->id)
                ->where('idempotency_key', self::OUTPUT_KEY)->firstOrFail();
            if ($workOrder->fresh()->status === WorkOrderStatus::InProgress) {
                $context->withoutNotifications(fn () => $context->at($base->addMinutes(65), fn () => $context->asUser($productionManager, fn () => $this->workOrders->complete($workOrder->fresh()))));
            }
        }

        $workOrder->refresh();
        $context->withoutNotifications(fn () => $context->at($base->addMinutes(66), fn () => $this->outgoingQc->handle(new WorkOrderCompleted($workOrder->fresh()))));
        foreach (WorkOrderOutput::query()->where('work_order_id', $workOrder->id)->orderBy('id')->get() as $index => $batch) {
            $this->passOutgoingInspection($context, $batch, $qcMaker, $qcChecker, $base->addMinutes(70 + ($index * 5)));
        }
        $output = WorkOrderOutput::query()->where('work_order_id', $workOrder->id)
            ->where('idempotency_key', self::OUTPUT_KEY)->firstOrFail();
        $outgoing = Inspection::query()->where('work_order_output_id', $output->id)
            ->where('stage', InspectionStage::Outgoing->value)->firstOrFail();
        if ($outgoing->status !== InspectionStatus::Passed || ! $outgoing->canReleaseOutgoing()) {
            throw new RuntimeException("BB-001 output {$output->batch_code} has no checked outgoing release quantity.");
        }

        $delivery = $this->ensureDelivery($context, $order, $sales, $deliveryManager, $shipDay);
        $this->shipAndConfirm($context, $delivery, $deliveryManager, $fleetManager, $shipDay);
        $delivery->refresh();
        if ($delivery->status !== DeliveryStatus::Confirmed) {
            throw new RuntimeException("O2C delivery {$delivery->delivery_number} did not reach confirmed status.");
        }

        $invoice = Invoice::query()->where('delivery_id', $delivery->id)->first();
        if (! $invoice instanceof Invoice) {
            throw new RuntimeException("Confirmed delivery {$delivery->delivery_number} did not create its linked invoice through the delivery handoff.");
        }
        $this->settleInvoice($context, $invoice, $customer, $invoiceFinalizer, $shipDay);

        $liveOrder = SalesOrder::query()->where('notes', 'OGI-O2C-LIVE:SO-DRAFT-0001')->first();
        if ($liveOrder instanceof SalesOrder && $liveOrder->status !== SalesOrderStatus::Draft) {
            throw new RuntimeException('O2C fulfillment must preserve the separate live-start BB-001 draft order.');
        }

        $context->records()->remember('o2c.fulfillment.sales_order', $order->fresh());
        $context->records()->remember('o2c.fulfillment.work_order', $workOrder->fresh());
        $context->records()->remember('o2c.fulfillment.delivery', $delivery->fresh());
        $context->records()->remember('o2c.fulfillment.invoice', $invoice->fresh());
    }

    private function ensureOrder(
        DemoSeedContext $context,
        Customer $customer,
        Product $product,
        User $sales,
        CarbonImmutable $orderDay,
        CarbonImmutable $deliveryDay,
    ): SalesOrder {
        $existing = SalesOrder::query()->where('notes', self::ORDER_MARKER)->first();
        if ($existing instanceof SalesOrder) {
            if ((int) $existing->customer_id !== (int) $customer->id || $existing->items()->where('product_id', $product->id)->count() !== 1) {
                throw new RuntimeException('Seed-owned fulfilled BB-001 order has drifted from its customer/product relationship.');
            }

            return $existing;
        }

        $items = [[
            'product_id' => (int) $product->id,
            'quantity' => '500',
            'delivery_date' => $deliveryDay->toDateString(),
        ]];
        $quote = $context->at($orderDay->setTime(9, 0), fn (): array => $this->salesOrders->quoteOrder((int) $customer->id, $items));
        if (! is_string($quote['signature'] ?? null) || strlen($quote['signature']) !== 64) {
            throw new RuntimeException('O2C fulfillment could not obtain a signed price quote for BB-001.');
        }

        return $context->withoutNotifications(fn () => $context->at($orderDay->setTime(9, 15), fn () => $context->asUser($sales, fn (): SalesOrder => $this->salesOrders->create([
            'customer_id' => (int) $customer->id,
            'date' => $orderDay->toDateString(),
            'payment_method' => 'bank_transfer',
            'payment_terms_days' => (int) $customer->payment_terms_days,
            'notes' => self::ORDER_MARKER,
            'items' => $items,
            'quote_signature' => $quote['signature'],
            'submission_source' => 'internal',
        ], (int) $sales->id))));
    }

    private function ensureMrp(DemoSeedContext $context, SalesOrder $order, User $planner, CarbonImmutable $at): void
    {
        $context->withoutNotifications(fn () => $context->at($at, fn () => $context->asUser($planner, fn () => $this->mrp->run(
            [(int) $order->id], MrpRunTrigger::Manual, (int) $planner->id, 'o2c_fulfillment',
        ))));
    }

    private function ensureScheduled(DemoSeedContext $context, WorkOrder $workOrder, CarbonImmutable $at): ProductionSchedule
    {
        $slot = $workOrder->schedules()->where('reserves_capacity', true)->whereIn('status', ['pending', 'confirmed'])->latest('id')->first();
        if ($slot instanceof ProductionSchedule) {
            return $slot;
        }
        if ($workOrder->status !== WorkOrderStatus::Planned) {
            throw new RuntimeException("O2C work order {$workOrder->wo_number} has no active schedule in its current state.");
        }

        $context->withoutNotifications(fn () => $context->at($at, fn () => $this->capacity->run([(int) $workOrder->id])));
        $slot = $workOrder->schedules()->where('reserves_capacity', true)->where('status', 'pending')->latest('id')->first();
        if (! $slot instanceof ProductionSchedule) {
            $message = (string) ($workOrder->fresh()->scheduling_diagnostic['reasons'][0] ?? 'No capacity slot was returned.');
            throw new RuntimeException("Capacity planning could not schedule O2C work order {$workOrder->wo_number}: {$message}");
        }

        return $slot;
    }

    private function ensureCrew(DemoSeedContext $context, WorkOrder $workOrder, ProductionSchedule $slot, User $manager): WorkOrderCrew
    {
        $crew = $this->assignments->currentCrew($workOrder);
        if ($crew instanceof WorkOrderCrew) {
            return $crew;
        }
        $worker = $this->assignments->eligibleWorkers($workOrder, $slot->scheduled_start, $slot->scheduled_end)->first();
        if (! $worker) {
            throw new RuntimeException("No employee is eligible for the scheduled BB-001 work order {$workOrder->wo_number}.");
        }

        return $context->withoutNotifications(fn () => $context->at($slot->scheduled_start->toImmutable(), fn () => $context->asUser($manager, fn () => $this->assignments->assign($workOrder, [
            'employee_ids' => [(int) $worker->id],
            'recorder_employee_id' => (int) $worker->id,
            'minimum_workers' => 1,
            'maximum_workers' => 2,
            'expected_crew_id' => null,
            'reason' => 'Assign one eligible operator to complete the historical BB-001 customer order.',
        ], $manager))));
    }

    private function ensureMaterialHandovers(DemoSeedContext $context, WorkOrder $workOrder, WorkOrderCrew $crew, User $warehouse): void
    {
        $recipientId = (int) $crew->recorder_employee_id;
        foreach (\App\Modules\Inventory\Models\StockMovement::query()->forWorkOrder((int) $workOrder->id)->pendingHandover()->orderBy('id')->get() as $movement) {
            // The issue movement is written by WorkOrderService::start; confirm each actual issue through the warehouse handover endpoint.
            $at = $movement->created_at->toImmutable()->addMinutes(2);
            $request = Request::create('/api/v1/inventory/stock-movements/'.$movement->hash_id.'/handover', 'POST', [
                'received_by_employee_id' => app('hashids')->encode($recipientId),
                'expected_quantity' => bcadd((string) $movement->quantity, '0', 3),
            ]);
            $request->setUserResolver(static fn (): User => $warehouse);
            $context->withoutNotifications(fn () => $context->at($at, fn () => $context->asUser($warehouse, fn () => app(\App\Modules\Inventory\Controllers\MaterialHandoverController::class)->store($request, $movement))));
        }
    }

    private function passStartInspection(DemoSeedContext $context, WorkOrder $workOrder, WorkOrderCrew $crew, User $productionManager, User $maker, User $checker, CarbonImmutable $at): void
    {
        $inspection = Inspection::query()->where('stage', InspectionStage::InProcess->value)
            ->where('entity_type', InspectionEntityType::WorkOrder->value)->where('entity_id', $workOrder->id)
            ->whereNull('wo_operation_id')->where('status', '!=', InspectionStatus::Cancelled->value)->first();
        if ($inspection instanceof Inspection) {
            if ($inspection->startup_trial_required) {
                $trial = WorkOrderOutput::query()->where('work_order_id', $workOrder->id)
                    ->where('trial_inspection_id', $inspection->id)->first();
                if (! $trial instanceof WorkOrderOutput) {
                    $sampleQuantity = min((int) $inspection->sample_size, (int) $workOrder->quantity_target);
                    $context->withoutNotifications(fn () => $context->at($at, fn () => $context->asUser($productionManager, fn () => $this->outputs->recordTrial(
                        $workOrder->fresh(),
                        [
                            'trial_quantity' => $sampleQuantity,
                            'crew_id' => (int) $crew->id,
                            'recording_reason' => 'The production manager records the operator startup sample for the customer replenishment run.',
                        ],
                        (int) $productionManager->id,
                        'o2c-bb001-startup-trial-1',
                    ))));
                }
            }

            $this->passInspection($context, $inspection, $maker, $checker, $at);

            if ($inspection->startup_trial_required && $inspection->fresh()->status === InspectionStatus::Passed) {
                $trial = WorkOrderOutput::query()->where('work_order_id', $workOrder->id)
                    ->where('trial_inspection_id', $inspection->id)->firstOrFail();
                if ($trial->trial_settled_at === null) {
                    $context->withoutNotifications(fn () => $context->at($at->addMinutes(5), fn () => $context->asUser($checker, fn () => $this->outputs->settleTrial(
                        $inspection->fresh(), $checker, (int) $trial->trial_quantity,
                    ))));
                }
            }
        }
    }

    private function runOperations(DemoSeedContext $context, WorkOrder $workOrder, WorkOrderCrew $crew, User $productionManager, User $maker, User $checker, CarbonImmutable $at): void
    {
        $workOrder->refresh();
        $remainingGood = max(0, (int) $workOrder->quantity_target - (int) $workOrder->quantity_good);
        $operator = $crew->members()->with('employee')->first()?->employee;
        if (! $operator) {
            throw new RuntimeException("The current BB-001 crew for {$workOrder->wo_number} has no employee operator.");
        }

        $operations = $workOrder->operations()->with('routingOperation')->orderBy('sequence')->get();
        $finalOperationId = $operations->filter(static fn (WoOperation $operation): bool => $operation->status->value !== 'skipped')->last()?->id;
        foreach ($operations as $index => $operation) {
            $stepAt = $at->addMinutes($index * 12);
            if ($operation->status->value === 'completed' || $operation->status->value === 'skipped') {
                continue;
            }
            if ($operation->status->value === 'pending' && bccomp((string) $operation->routingOperation?->setup_time_minutes, '0', 2) > 0) {
                $context->withoutNotifications(fn () => $context->at($stepAt, fn () => $context->asUser($productionManager, fn () => $this->operations->startSetup($operation, $operator))));
                $context->withoutNotifications(fn () => $context->at($stepAt->addMinutes(2), fn () => $this->operations->endSetup($operation->fresh())));
                $operation->refresh();
            }
            if (in_array($operation->status->value, ['pending', 'setup'], true)) {
                $context->withoutNotifications(fn () => $context->at($stepAt->addMinutes(3), fn () => $context->asUser($productionManager, fn () => $this->operations->startOperation($operation->fresh(), $operator))));
                $operation->refresh();
            }
            $remainingAtOperation = max(0, $remainingGood - (int) $operation->qty_completed);
            if ($operation->status->value === 'in_progress' && $remainingAtOperation > 0) {
                $context->withoutNotifications(fn () => $context->at($stepAt->addMinutes(5), fn () => $context->asUser($productionManager, fn () => $this->operations->recordOutput(
                    $operation, (float) $remainingAtOperation, 0, null,
                    'Operator reports the remaining good quantity after startup trial pieces at this routing operation.',
                ))));
            }
            if ($operation->routingOperation?->qc_required) {
                $inspection = Inspection::query()->where('wo_operation_id', $operation->id)
                    ->where('status', '!=', InspectionStatus::Cancelled->value)->first();
                if (! $inspection instanceof Inspection) {
                    $inspection = $this->inspections->create([
                        'stage' => InspectionStage::InProcess->value,
                        'product_id' => (int) $workOrder->product_id,
                        'batch_quantity' => (int) $operation->qty_completed,
                        'entity_type' => InspectionEntityType::WorkOrder->value,
                        'entity_id' => (int) $workOrder->id,
                        'wo_operation_id' => (int) $operation->id,
                        'notes' => 'Required by the active BB-001 routing operation.',
                    ], $workOrder->creator);
                }
                $this->passInspection($context, $inspection, $maker, $checker, $stepAt->addMinutes(8));
            }
            if ($operation->fresh()->status->value === 'in_progress') {
                if ((int) $operation->id === (int) $finalOperationId) {
                    $this->ensureCanonicalOutput($context, $workOrder, $crew, $productionManager, $stepAt->addMinutes(11));
                }
                $context->withoutNotifications(fn () => $context->at($stepAt->addMinutes(12), fn () => $this->operations->completeOperation($operation->fresh())));
            }
        }
    }

    private function ensureCanonicalOutput(DemoSeedContext $context, WorkOrder $workOrder, WorkOrderCrew $crew, User $productionManager, CarbonImmutable $at): WorkOrderOutput
    {
        $existing = WorkOrderOutput::query()->where('work_order_id', $workOrder->id)
            ->where('idempotency_key', self::OUTPUT_KEY)->first();
        if ($existing instanceof WorkOrderOutput) {
            return $existing;
        }

        $fresh = $workOrder->fresh();

        return $context->withoutNotifications(fn () => $context->at($at, fn () => $context->asUser($productionManager, fn () => $this->outputs->record(
            $fresh,
            [
                'good_count' => max(0, (int) $fresh->quantity_target - (int) $fresh->quantity_good),
                'reject_count' => 0,
                'crew_id' => (int) $crew->id,
                'recording_reason' => 'Production manager records the accepted BB-001 batch after the final operation output and QC release.',
                'remarks' => 'Accepted batch is reconciled with the completed routing quantity before operation closure.',
            ],
            (int) $productionManager->id,
            self::OUTPUT_KEY,
        ))));
    }

    private function passInspection(DemoSeedContext $context, Inspection $inspection, User $maker, User $checker, CarbonImmutable $at): void
    {
        $inspection = $inspection->fresh(['measurements']);
        if (in_array($inspection->status, [InspectionStatus::Passed, InspectionStatus::Failed], true)) {
            return;
        }
        if ($inspection->status !== InspectionStatus::AwaitingReview) {
            $rows = InspectionMeasurement::query()->where('inspection_id', $inspection->id)
                ->orderBy('sample_index')->orderBy('id')->get();
            if ($rows->isEmpty()) {
                throw new RuntimeException("Inspection {$inspection->inspection_number} has no specification measurements.");
            }
            if ($inspection->usesChecklistCapture()) {
                $items = $rows->groupBy(static fn (InspectionMeasurement $row): string => $row->checklistKey())
                    ->map(static fn ($group): array => ['id' => (int) $group->first()->id, 'result' => 'passed'])
                    ->values()->all();
                $context->withoutNotifications(fn () => $context->at($at, fn () => $context->asUser($maker, fn () => $this->inspections->recordItemResults(
                    $inspection, ['items' => $items, 'submit' => false], $maker,
                ))));
                $inspection->refresh();
                $context->withoutNotifications(fn () => $context->at($at->addMinute(), fn () => $context->asUser($maker, fn () => $this->inspections->recordBatchResult(
                    $inspection, [
                        'coverage_confirmed' => true,
                        'inspected_quantity' => min((int) $inspection->batch_quantity, max(1, (int) $inspection->sample_size)),
                        'sample_defect_count' => 0,
                        'result' => 'passed',
                        'critical_defect' => false,
                    ], $maker,
                ))));
            } else {
                $patches = [];
                foreach ($rows as $row) {
                    $patch = ['is_pass' => true];
                    if ($row->requiresSizeReading()) {
                        if ($row->nominal_value === null) {
                            throw new RuntimeException("Inspection measurement {$row->id} requires a reading but has no nominal value.");
                        }
                        $patch['measured_value'] = (string) $row->nominal_value;
                    }
                    $patches[(int) $row->id] = $patch;
                }
                $context->withoutNotifications(fn () => $context->at($at, fn () => $context->asUser($maker, function () use ($inspection, $patches, $maker): void {
                    $this->inspections->recordMeasurements($inspection, $patches, $maker);
                    if ($inspection->inspection_mode === InspectionMode::LotChecklist) {
                        $requirements = InspectionMeasurement::query()->where('inspection_id', $inspection->id)
                            ->orderBy('sample_index')->orderBy('id')->get()
                            ->groupBy(static fn (InspectionMeasurement $row): string => $row->checklistKey())
                            ->map(static fn ($group): array => ['id' => (int) $group->first()->id, 'result' => 'passed'])
                            ->values()->all();
                        $this->inspections->recordItemResults($inspection->fresh(), ['items' => $requirements, 'submit' => false], $maker);
                        $this->inspections->recordBatchResult($inspection->fresh(), [
                            'coverage_confirmed' => true,
                            'inspected_quantity' => min((int) $inspection->batch_quantity, max(1, (int) $inspection->sample_size)),
                            'sample_defect_count' => 0,
                            'result' => 'passed',
                            'critical_defect' => false,
                        ], $maker);
                    } else {
                        $this->inspections->complete($inspection->fresh(), $maker);
                    }
                })));
            }
            $inspection->refresh();
        }
        if ($inspection->status === InspectionStatus::AwaitingReview) {
            $decision = $inspection->proposed_result?->value ?? (string) $inspection->proposed_result;
            $context->withoutNotifications(fn () => $context->at($at->addMinutes(2), fn () => $context->asUser($checker, fn () => $this->inspections->review(
                $inspection, $decision, null, $checker, (int) $inspection->result_version,
            ))));
            $inspection->refresh();
        }
        if ($inspection->status !== InspectionStatus::Passed) {
            throw new RuntimeException("Inspection {$inspection->inspection_number} did not pass recorded BB-001 evidence.");
        }
    }

    private function passOutgoingInspection(DemoSeedContext $context, WorkOrderOutput $output, User $maker, User $checker, CarbonImmutable $at): void
    {
        $inspection = Inspection::query()->where('work_order_output_id', $output->id)
            ->where('stage', InspectionStage::Outgoing->value)->first();
        if (! $inspection instanceof Inspection) {
            throw new RuntimeException("Outgoing QC handoff did not create an inspection for batch {$output->batch_code}.");
        }
        $this->passInspection($context, $inspection, $maker, $checker, $at);
    }

    private function ensureDelivery(DemoSeedContext $context, SalesOrder $order, User $sales, User $deliveryManager, CarbonImmutable $shipDay): Delivery
    {
        $delivery = Delivery::query()->where('sales_order_id', $order->id)->whereNotIn('status', [DeliveryStatus::Cancelled->value])->orderBy('id')->first();
        if (! $delivery instanceof Delivery) {
            $delivery = $context->withoutNotifications(fn () => $context->at($shipDay->setTime(7, 0), fn () => $context->asUser($sales, fn () => $this->deliveries->draftOrderSupply($order->fresh(), $sales))));
        }
        if (! $delivery instanceof Delivery) {
            throw new RuntimeException("Approved BB-001 production and outgoing QC did not satisfy order supply for {$order->so_number}.");
        }

        return $delivery;
    }

    private function shipAndConfirm(DemoSeedContext $context, Delivery $delivery, User $deliveryManager, User $fleetManager, CarbonImmutable $shipDay): void
    {
        if ($delivery->status === DeliveryStatus::Confirmed) {
            return;
        }
        $driver = User::query()->where('is_active', true)->whereHas('role', static fn ($query) => $query->where('slug', 'driver'))->orderBy('id')->first();
        if (! $driver) {
            throw new RuntimeException('Delivery fulfillment requires an active driver account.');
        }
        $vehicle = $this->ensureAvailableVehicle($context, $fleetManager, $shipDay);
        $this->ensureDriverCredential($driver, $fleetManager, $shipDay);
        $context->withoutNotifications(fn () => $context->at($shipDay->setTime(7, 30), fn () => $context->asUser($deliveryManager, fn () => $this->deliveries->saveShipmentPlan($delivery, [
            'vehicle_id' => (int) $vehicle->id,
            'driver_id' => (int) $driver->id,
            'reason' => 'Customer confirmed an earlier receiving slot; shipment brought forward from the requested delivery date.',
            'scheduled_date' => $shipDay->toDateString(),
            'expected_delivery_date' => $shipDay->toDateString(),
            'carrier' => 'Ogami Corporation delivery fleet',
            'shipment_weight_kg' => '25.00',
            'dispatch' => false,
        ], $deliveryManager, UploadedFile::fake()->image('bb001-loading.jpg')))));
        $delivery->refresh();
        if ($delivery->status === DeliveryStatus::Scheduled) {
            $context->withoutNotifications(fn () => $context->at($shipDay->setTime(8, 0), fn () => $context->asUser($deliveryManager, fn () => $this->deliveries->updateStatus($delivery, DeliveryStatus::Loading))));
        }
        if ($delivery->fresh()->status === DeliveryStatus::Loading) {
            $context->withoutNotifications(fn () => $context->at($shipDay->setTime(8, 15), fn () => $context->asUser($deliveryManager, fn () => $this->deliveries->updateStatus($delivery->fresh(), DeliveryStatus::InTransit))));
        }
        if ($delivery->fresh()->status === DeliveryStatus::InTransit) {
            $context->withoutNotifications(fn () => $context->at($shipDay->setTime(11, 0), fn () => $context->asUser($driver, fn () => $this->deliveries->uploadProof(
                $delivery->fresh(), UploadedFile::fake()->createWithContent('bb001-signed-delivery-receipt.pdf', "%PDF-1.4\nSigned delivery receipt for BB-001 customer replenishment.\n%%EOF"),
                DeliveryProofType::SignedDeliveryReceipt, $driver, 'Signed delivery receipt for the BB-001 customer replenishment shipment.',
            ))));
        }
        if ($delivery->fresh()->status === DeliveryStatus::Delivered) {
            $context->withoutNotifications(fn () => $context->at($shipDay->setTime(11, 15), fn () => $context->asUser($deliveryManager, fn () => $this->deliveries->confirm(
                $delivery->fresh(), $deliveryManager, ['receiver_name' => 'Receiving Clerk', 'receiver_position' => 'Warehouse Receiver'],
            ))));
        }
    }

    private function ensureAvailableVehicle(DemoSeedContext $context, User $fleetManager, CarbonImmutable $shipDay): Vehicle
    {
        $available = Vehicle::query()->where('status', 'available')->whereNotNull('capacity_kg')->orderBy('id')->first();
        if ($available instanceof Vehicle) {
            return $available;
        }

        $plateNumber = 'OGM-TRK-001';
        $existing = Vehicle::query()->where('plate_number', $plateNumber)->first();
        if ($existing instanceof Vehicle) {
            throw new RuntimeException("Fleet vehicle {$plateNumber} exists but is not available for the customer shipment.");
        }

        $request = Request::create('/api/v1/supply-chain/vehicles', 'POST', [
            'plate_number' => $plateNumber,
            'name' => 'Ogami 5-Ton Delivery Truck 01',
            'vehicle_type' => 'truck',
            'capacity_kg' => '5000.00',
            'status' => 'available',
            'notes' => 'Company delivery fleet unit for FCIE and regional customer routes.',
        ]);
        $request->setUserResolver(static fn (): User => $fleetManager);
        $context->withoutNotifications(fn () => $context->at($this->businessDay($shipDay, -5)->setTime(9, 0), fn () => $context->asUser($fleetManager, fn () => app(VehicleController::class)->store($request))));

        return Vehicle::query()->where('plate_number', $plateNumber)->where('status', 'available')->firstOrFail();
    }

    private function ensureDriverCredential(User $driver, User $administrator, CarbonImmutable $shipDay): void
    {
        if (DriverCredential::query()->where('user_id', $driver->id)->whereDate('expires_at', '>=', $shipDay->toDateString())->exists()) {
            return;
        }
        $request = Request::create('/api/v1/supply-chain/drivers/'.$driver->hash_id.'/credential', 'PUT', [
            'license_number' => 'OGM-DRV-'.str_pad((string) $driver->id, 5, '0', STR_PAD_LEFT),
            'expires_at' => $shipDay->addYears(2)->toDateString(),
        ]);
        $request->setUserResolver(static fn (): User => $administrator);
        app(\App\Modules\SupplyChain\Controllers\DriverCredentialController::class)->store($request, $driver);
    }

    private function settleInvoice(DemoSeedContext $context, Invoice $invoice, Customer $customer, User $finance, CarbonImmutable $date): void
    {
        if ($invoice->status === InvoiceStatus::Paid) {
            return;
        }
        if ($invoice->status === InvoiceStatus::Draft) {
            $context->withoutNotifications(fn () => $context->at($date->setTime(12, 0), fn () => $context->asUser($finance, fn () => $this->invoices->finalize($invoice, $finance))));
            $invoice->refresh();
        }
        if (! in_array($invoice->status, [InvoiceStatus::Finalized, InvoiceStatus::Partial], true)) {
            throw new RuntimeException("Delivery invoice {$invoice->invoice_number} is not collectible from {$invoice->status->value}.");
        }
        $portal = CustomerPortalUser::query()->where('customer_id', $customer->id)->where('is_active', true)->orderBy('id')->first();
        if (! $portal) {
            throw new RuntimeException("Customer {$customer->name} has no active portal account to submit its demo payment.");
        }
        $submission = $invoice->paymentSubmissions()->where('status', 'pending')->first();
        if (! $submission) {
            $submission = $context->withoutNotifications(fn () => $context->at($date->setTime(12, 15), fn () => $this->payments->submit($invoice->fresh(), [
                'amount' => number_format((float) $invoice->balance, 2, '.', ''),
                'sending_bank' => 'BDO Unibank',
                'reference_number' => 'BB001-'.str_pad((string) $invoice->id, 8, '0', STR_PAD_LEFT),
                'payment_date' => $date->toDateString(),
                'idempotency_key' => sprintf('b0010000-%04x-4000-8000-%012x', $invoice->id % 65536, $invoice->id),
            ], UploadedFile::fake()->createWithContent('bb001-payment-advice.pdf', "%PDF-1.4\nPayment advice for BB-001 replenishment.\n%%EOF"), $portal)));
        }
        if ($submission->status->value === 'pending') {
            $bankRoot = Account::query()->where('code', '1020')->where('is_active', true)->first();
            $bank = $bankRoot?->children()->where('is_active', true)->whereDoesntHave('children')->orderBy('code')->first() ?? $bankRoot;
            if (! $bank) {
                throw new RuntimeException('O2C collection requires the active Cash in Bank posting account 1020.');
            }
            $context->withoutNotifications(fn () => $context->at($date->setTime(12, 30), fn () => $context->asUser($finance, fn () => $this->payments->verify(
                $invoice->fresh(), $submission->fresh(), app('hashids')->encode((int) $bank->id), $finance,
            ))));
        }
    }

    private function actor(string $permission, ?string $secondPermission = null, ?int $excluding = null): User
    {
        $query = User::query()->where('is_active', true)->whereHas('role.permissions', static fn ($permissions) => $permissions->where('slug', $permission));
        if ($secondPermission !== null) {
            $query->whereHas('role.permissions', static fn ($permissions) => $permissions->where('slug', $secondPermission));
        }
        if ($excluding !== null) {
            $query->whereKeyNot($excluding);
        }
        $user = $query->orderBy('id')->first();
        if (! $user instanceof User) {
            throw new RuntimeException('O2C fulfillment requires an active authorized actor with '.$permission.($secondPermission ? ' and '.$secondPermission : '').'.');
        }

        return $user;
    }

    private function businessDay(CarbonImmutable $date, int $offset): CarbonImmutable
    {
        $direction = $offset < 0 ? -1 : 1;
        $remaining = abs($offset);
        $day = $date->setTimezone('Asia/Manila')->startOfDay();
        while ($remaining > 0) {
            $day = $day->addDays($direction);
            if (! $day->isWeekend()) {
                $remaining--;
            }
        }

        while ($day->isWeekend()) {
            $day = $day->addDays($direction);
        }

        return $day;
    }
}
