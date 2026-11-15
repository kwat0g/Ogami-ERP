import { useEffect, useMemo, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useLocation, useNavigate, useSearchParams } from 'react-router-dom';
import { AxiosError } from 'axios';
import toast from 'react-hot-toast';
import { LuSearch, LuBan, LuPencil, LuPlus, LuTrash2 } from '@/lib/icons';
import { warehouseApi } from '@/api/inventory/warehouse';
import { warehouseMapApi } from '@/api/inventory/warehouseWms';
import { Chip } from '@/components/ui/Chip';
import { ConfirmDialog } from '@/components/ui/ConfirmDialog';
import { EmptyState } from '@/components/ui/EmptyState';
import { Modal } from '@/components/ui/Modal';
import { Panel } from '@/components/ui/Panel';
import { SkeletonBlock } from '@/components/ui/Skeleton';
import { Button } from '@/components/ui/Button';
import { Input } from '@/components/ui/Input';
import { SearchableSelect } from '@/components/ui/SearchableSelect';
import { SegmentedControl } from '@/components/ui/SegmentedControl';
import { PageHeader } from '@/components/layout/PageHeader';
import { usePermission } from '@/hooks/usePermission';
import { formatDate } from '@/lib/formatDate';
import { focusRingInset } from '@/lib/focus';
import { focusRing } from '@/lib/focus';
import { cn } from '@/lib/cn';
import { StockCountManager } from './stock-count';
import { WarehouseLocationForm, WarehouseZoneForm } from './map-management';
import type { WarehouseMapLocation, WarehouseMapZone } from '@/types/warehouse';

const STATUS_COLORS: Record<string, string> = {
  empty: 'bg-surface text-muted border border-subtle',
  ok: 'bg-success-bg/10 text-success-fg border border-success/20',
  low: 'bg-warning-bg/10 text-warning-fg border border-warning/20',
  full: 'bg-accent/10 text-accent border border-accent/20',
  blocked: 'bg-danger-bg/10 text-danger-fg border border-danger/20 line-through',
};

// Solid swatch for the legend (tint + border, no text colour).
const STATUS_SWATCH: Record<string, string> = {
  empty: 'bg-surface border border-subtle',
  ok: 'bg-success-bg/50 border border-success/40',
  low: 'bg-warning-bg/50 border border-warning/40',
  full: 'bg-accent/40 border border-accent/40',
  blocked: 'bg-danger-bg/50 border border-danger/40',
};

/**
 * Warehouse Map + Stock Count merged into one page (2026-08-08): the bin map
 * is the visual floor view, and the physical-count workflow lives behind the
 * Map | Stock Count toggle. Deep-linkable via ?view=count; the
 * /inventory/stock-count path (used by the barcode scanner) aliases into this
 * page in count view. The Stock Count tab only appears for users with
 * inventory.stock_count.view.
 */
export default function WarehouseMapPage() {
  const queryClient = useQueryClient();
  const { can } = usePermission();
  const navigate = useNavigate();
  const { pathname } = useLocation();
  const [searchParams, setSearchParams] = useSearchParams();
  const linkedBinId = searchParams.get('location_id');

  const canCount = can('inventory.stock_count.view');
  const canMap = can('inventory.warehouse.manage');
  const rawView =
    pathname === '/inventory/stock-count' || searchParams.get('view') === 'count' ? 'count' : 'map';
  const view = rawView === 'count' && !canCount ? 'map' : rawView;
  const setView = (v: 'map' | 'count') => {
    // Always land on the canonical /inventory/warehouse-map URL so the
    // /inventory/stock-count alias is normalised away on toggle.
    navigate(
      v === 'count'
        ? canMap
          ? '/inventory/warehouse-map?view=count'
          : '/inventory/stock-count'
        : '/inventory/warehouse-map',
      {
        replace: true,
      },
    );
  };

  // A user without stock_count access landing on ?view=count is forced to map
  // view; drop the param so the URL matches what is actually rendered.
  useEffect(() => {
    if (rawView === 'count' && !canCount && pathname !== '/inventory/stock-count') {
      navigate('/inventory/warehouse-map', { replace: true });
    }
  }, [rawView, canCount, pathname, navigate]);

  const [activeWh, setActiveWh] = useState<string | null>(searchParams.get('warehouse_id'));
  const [activeZone, setActiveZone] = useState<string | null>(searchParams.get('zone_id'));
  const [selectedBinId, setSelectedBinId] = useState<string | null>(null);
  const [binQuery, setBinQuery] = useState('');
  const [zoneForm, setZoneForm] = useState<{ mode: 'create' | 'edit'; existing: WarehouseMapZone | null } | null>(null);
  const [locationForm, setLocationForm] = useState<{ mode: 'create' | 'edit'; existing: WarehouseMapLocation | null } | null>(null);
  const [deleteTarget, setDeleteTarget] = useState<
    | { kind: 'zone'; id: string; name: string }
    | { kind: 'location'; id: string; code: string }
    | null
  >(null);

  const { data, isLoading, isError, refetch } = useQuery({
    queryKey: ['warehouse', 'map'],
    queryFn: () => warehouseMapApi.map(),
    enabled: canMap && view === 'map',
  });
  useEffect(() => {
    if (!linkedBinId || !data) return;
    for (const warehouse of data) {
      for (const currentZone of warehouse.zones) {
        const location = currentZone.locations.find((bin) => bin.id === linkedBinId);
        if (location) {
          setActiveWh(warehouse.id);
          setActiveZone(currentZone.id);
          setSelectedBinId(location.id);
          return;
        }
      }
    }
  }, [data, linkedBinId]);

  const wh = data?.find((w) => w.id === activeWh) ?? data?.[0];
  const zones = wh?.zones ?? [];
  const zone = zones.find((z) => z.id === activeZone) ?? null;
  const selectedBin = useMemo(() => {
    if (!selectedBinId) return null;
    for (const warehouse of data ?? []) {
      for (const currentZone of warehouse.zones) {
        const location = currentZone.locations.find((bin) => bin.id === selectedBinId);
        if (location) return { id: location.id, detail: location };
      }
    }
    return null;
  }, [data, selectedBinId]);
  const statusLegend = useMemo(() => {
    const labels = new Map<string, string>();
    for (const warehouse of data ?? []) {
      for (const currentZone of warehouse.zones) {
        for (const location of currentZone.locations) {
          if (!labels.has(location.stock_status)) {
            labels.set(location.stock_status, location.stock_status_label ?? location.stock_status);
          }
        }
      }
    }
    return [...labels.entries()];
  }, [data]);

  // Bin search — filters the current zone's bins by bin code, current item
  // code or name, or block reason, so counting/dispatching targets can be
  // found fast.
  const visibleBins = useMemo(() => {
    if (!zone) return [];
    const q = binQuery.trim().toLowerCase();
    if (!q) return zone.locations;
    return zone.locations.filter(
      (loc) =>
        loc.code.toLowerCase().includes(q) ||
        (loc.current_item?.code ?? '').toLowerCase().includes(q) ||
        (loc.current_item?.name ?? '').toLowerCase().includes(q) ||
        (loc.blocked_reason ?? '').toLowerCase().includes(q),
    );
  }, [zone, binQuery]);

  const binDetail = useQuery({
    queryKey: ['warehouse', 'bin', selectedBinId],
    queryFn: () => warehouseMapApi.binDetail(selectedBinId!),
    enabled: canMap && view === 'map' && !!selectedBin,
  });

  const reloadMap = () => {
    void queryClient.invalidateQueries({ queryKey: ['warehouse', 'map'] });
    void queryClient.invalidateQueries({ queryKey: ['warehouse', 'bin'] });
  };

  const deleteZone = useMutation({
    mutationFn: (id: string) => warehouseApi.deleteZone(id),
    onSuccess: (_result, id) => {
      toast.success('Zone archived.');
      setDeleteTarget(null);
      if (activeZone === id) {
        setActiveZone(null);
        setSelectedBinId(null);
        setSearchParams((params) => {
          params.delete('zone_id');
          params.delete('location_id');
          return params;
        }, { replace: true });
      }
      reloadMap();
    },
    onError: (error: AxiosError<{ message?: string }>) =>
      toast.error(error.response?.data?.message ?? 'Failed to delete zone.'),
  });

  const deleteLocation = useMutation({
    mutationFn: (id: string) => warehouseApi.deleteLocation(id),
    onSuccess: (_result, id) => {
      toast.success('Bin archived.');
      setDeleteTarget(null);
      if (selectedBinId === id) {
        setSelectedBinId(null);
        setSearchParams((params) => {
          params.delete('location_id');
          return params;
        }, { replace: true });
      }
      reloadMap();
    },
    onError: (error: AxiosError<{ message?: string }>) =>
      toast.error(error.response?.data?.message ?? 'Failed to delete bin.'),
  });

  const saveZone = (id: string) => {
    setZoneForm(null);
    setActiveZone(id);
    setSelectedBinId(null);
    setSearchParams((params) => {
      params.set('zone_id', id);
      params.delete('location_id');
      return params;
    }, { replace: true });
    reloadMap();
  };

  const saveLocation = (id: string) => {
    setLocationForm(null);
    setSelectedBinId(id);
    reloadMap();
  };

  return (
    <div>
      <PageHeader
        title={view === 'count' ? 'Stock counts' : 'Warehouse map'}
        subtitle={
          data ? `${data.length} ${data.length === 1 ? 'warehouse' : 'warehouses'}` : undefined
        }
        actions={
          canCount && canMap ? (
            <SegmentedControl
              size="sm"
              label="Warehouse map view"
              value={view}
              onChange={setView}
              options={[
                { value: 'map', label: 'Map' },
                { value: 'count', label: 'Stock Count' },
              ]}
            />
          ) : undefined
        }
      />
      <div className="space-y-6 px-5 py-5">
        {view === 'count' ? (
          <StockCountManager />
        ) : (
          <>
            {/* Legend — explains the bin colour coding. */}
            <div className="space-y-2">
              <div className="flex flex-wrap items-center gap-x-5 gap-y-2 text-xs leading-5 text-muted">
                <span className="font-medium uppercase tracking-wider">Bin status</span>
                {statusLegend.map(([key, label]) => (
                  <span key={key} className="inline-flex items-center gap-1.5">
                    <span
                      aria-hidden
                      className={cn('h-2.5 w-2.5 rounded-[3px]', STATUS_SWATCH[key])}
                    />
                    {label}
                  </span>
                ))}
              </div>
              <p role="note" className="max-w-4xl text-xs leading-5 text-muted">
                Usage counts kg stock and items with a configured gross kg weight per base unit.
                Unweighted items are excluded; see bin cards. Include packaging in item weights.
                Capacity is advisory—verify limits against posted rack and floor ratings.
              </p>
            </div>
            {wh?.is_active === false && (
              <div role="status" className="rounded-md border border-warning/30 bg-warning-bg/20 px-3 py-2 text-sm text-warning-fg">
                This warehouse is inactive. Stock workflows will not accept new movements here.
              </div>
            )}

            {isLoading && !data && (
              <div className="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
                {Array.from({ length: 4 }).map((_, i) => (
                  <SkeletonBlock key={i} className="h-48" />
                ))}
              </div>
            )}
            {isError && (
              <EmptyState
                icon="alert-circle"
                title="Failed to load warehouse map"
                action={<Button onClick={() => refetch()}>Retry</Button>}
              />
            )}
            {data && data.length === 0 && (
              <EmptyState
                icon="inbox"
                title="No warehouses configured"
                description="Create warehouses, zones, and locations to see the map."
              />
            )}
            {data && data.length > 0 && (
              <>
                {/* Warehouse selector */}
                {data.length > 1 && (
                  <SearchableSelect
                    label="Warehouse"
                    value={wh?.id ?? ''}
                    onChange={(id) => {
                      setSearchParams(
                        (params) => {
                          params.set('warehouse_id', id);
                          params.delete('zone_id');
                          params.delete('location_id');
                          return params;
                        },
                        { replace: true },
                      );
                      setActiveWh(id);
                      setActiveZone(null);
                      setSelectedBinId(null);
                      setBinQuery('');
                    }}
                    options={data.map((w) => ({ value: w.id, label: `${w.is_active === false ? 'Inactive · ' : ''}${w.code} — ${w.name}` }))}
                  />
                )}

                <div className="grid grid-cols-1 gap-x-6 gap-y-6 xl:grid-cols-12">
                  {/* Zone sidebar */}
                  <div className="min-w-0 xl:col-span-4 2xl:col-span-3">
                    <div className="mb-3 flex items-center justify-between px-1">
                      <div className="text-xs uppercase tracking-wider text-muted font-medium">Zones</div>
                      {canMap && wh && (
                        <Button
                          type="button"
                          size="xs"
                          variant="secondary"
                          icon={<LuPlus size={12} />}
                          onClick={() => setZoneForm({ mode: 'create', existing: null })}
                        >
                          Add zone
                        </Button>
                      )}
                    </div>
                    {zones.length === 0 && <div className="px-1 py-3 text-xs text-muted">No zones configured.</div>}
                    <div className="flex gap-2 overflow-x-auto pb-2 xl:flex-col xl:overflow-visible xl:pb-0">
                      {zones.map((z) => (
                        <div key={z.id} className={cn(
                          'flex min-w-56 shrink-0 items-start rounded-lg border transition-colors xl:min-w-0 xl:shrink',
                          zone?.id === z.id
                            ? 'border-accent/30 bg-accent/10'
                            : 'border-default bg-surface hover:border-subtle hover:bg-elevated',
                        )}>
                          <button
                            type="button"
                            onClick={() => {
                              setSearchParams(
                                (params) => {
                                  params.set('zone_id', z.id);
                                  params.delete('location_id');
                                  return params;
                                },
                                { replace: true },
                              );
                              setActiveZone(z.id);
                              setSelectedBinId(null);
                              setBinQuery('');
                            }}
                            className={cn('min-w-0 flex-1 rounded-lg px-3 py-3 text-left text-xs leading-5 cursor-pointer', focusRingInset)}
                          >
                            <div className="font-mono font-medium text-primary">{z.code}</div>
                            <div className="mt-1 text-secondary leading-5">{z.name}</div>
                            <div className="text-xs text-muted">{z.type_label} · {z.locations.length} bins</div>
                            <CapacityUsage
                              usedKg={z.capacity_used_kg}
                              capacityKg={z.capacity_kg}
                              capacityStatus={z.capacity_status ?? 'unverified'}
                              untrackedLineCount={z.capacity_untracked_line_count ?? 0}
                            />
                          </button>
                          {canMap && (
                            <div className="flex shrink-0 flex-col gap-1 px-1 pt-2">
                              <Button
                                type="button"
                                size="xs"
                                variant="ghost"
                                iconOnly
                                icon={<LuPencil size={12} />}
                                aria-label={`Edit zone ${z.name}`}
                                onClick={() => setZoneForm({ mode: 'edit', existing: z })}
                              />
                              <Button
                                type="button"
                                size="xs"
                                variant="ghost"
                                iconOnly
                                icon={<LuTrash2 size={12} />}
                                aria-label={`Delete zone ${z.name}`}
                                className="text-muted hover:text-danger-fg"
                                onClick={() => setDeleteTarget({ kind: 'zone', id: z.id, name: z.name })}
                              />
                            </div>
                          )}
                        </div>
                      ))}
                    </div>
                  </div>

                  {/* Bin grid */}
                  <div className="min-w-0 xl:col-span-8 2xl:col-span-6">
                    {zone && (
                      <div className="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center">
                        <Input
                          fieldSize="sm"
                          placeholder="Search bin or item…"
                          value={binQuery}
                          onChange={(e) => setBinQuery(e.target.value)}
                          prefix={<LuSearch size={13} />}
                          className="font-mono"
                          containerClassName="min-w-0 flex-1"
                        />
                        {canMap && (
                          <Button
                            type="button"
                            size="sm"
                            variant="secondary"
                            icon={<LuPlus size={13} />}
                            onClick={() => setLocationForm({ mode: 'create', existing: null })}
                          >
                            Add bin
                          </Button>
                        )}
                      </div>
                    )}
                    <div className="mb-4">
                      {zone ? (
                        <>
                          <h2 className="text-base font-medium leading-6 text-primary">{zone.name}</h2>
                          <p className="mt-1 text-xs leading-5 text-muted">
                            {visibleBins.length} {visibleBins.length === 1 ? 'bin' : 'bins'}
                            {binQuery.trim() ? ' match your search' : ''}
                          </p>
                        </>
                      ) : (
                        <p className="text-sm leading-5 text-muted">Select a zone to view its bins.</p>
                      )}
                    </div>
                    {zone && (
                      <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 2xl:grid-cols-3">
                        {visibleBins.map((loc) => (
                          <button
                            key={loc.id}
                            type="button"
                            onClick={() => setSelectedBinId(loc.id)}
                            className={cn(
                              'min-w-0 rounded-lg border p-3 text-left text-sm leading-5 transition-colors cursor-pointer',
                              focusRing,
                              STATUS_COLORS[loc.stock_status] || STATUS_COLORS['empty'],
                              !loc.is_active && 'opacity-60',
                              selectedBin?.id === loc.id && 'ring-2 ring-accent',
                            )}
                          >
                            <div className="flex items-center justify-between gap-1">
                              <div className="truncate font-mono text-sm font-semibold leading-5">{loc.code}</div>
                              {!loc.is_active && <Chip variant="neutral">Inactive</Chip>}
                            </div>
                            <div className="mt-2 min-h-12 space-y-1 text-xs leading-5">
                              {loc.stock_status === 'empty' ? (
                                <div className="text-muted">No stock</div>
                              ) : loc.current_item ? (
                                <>
                                  <div className="truncate font-mono text-secondary" title={loc.current_item.code}>
                                    {loc.current_item.code}
                                  </div>
                                  <div className="truncate text-secondary" title={loc.current_item.name}>
                                    {loc.current_item.name}
                                  </div>
                                  <div className="font-mono tabular-nums text-primary">
                                    {Number(loc.current_quantity).toLocaleString('en-PH', { maximumFractionDigits: 3 })}{' '}
                                    {loc.current_item.unit_of_measure ?? ''}
                                  </div>
                                </>
                              ) : (
                                <div className="text-secondary">Multiple items</div>
                              )}
                            </div>
                            <CapacityUsage
                              usedKg={loc.capacity_used_kg}
                              capacityKg={loc.capacity_kg}
                              capacityStatus={loc.capacity_status ?? 'unverified'}
                              untrackedLineCount={loc.capacity_untracked_line_count ?? 0}
                              showBasis={false}
                              showUntrackedNote={false}
                            />
                            {!!loc.capacity_untracked_items?.length && (
                              <UnweightedCapacityItems items={loc.capacity_untracked_items} />
                            )}
                            {loc.is_blocked && (
                              <div
                                className="mt-3 border-t border-subtle pt-2 text-xs leading-5 text-danger-fg"
                                title={loc.blocked_reason ?? ''}
                              >
                                {/* Was the ⛔ emoji. Emoji render per-platform,
                                    ignore the semantic colour tokens, and are
                                    read aloud as "no entry sign". */}
                                <LuBan
                                  size={11}
                                  className="inline-block shrink-0 mr-1 align-[-1px]"
                                  aria-hidden="true"
                                />
                                {loc.stock_status_label ?? loc.stock_status}
                                {loc.blocked_reason && <div className="break-words">{loc.blocked_reason}</div>}
                              </div>
                            )}
                          </button>
                        ))}
                      </div>
                    )}
                    {zone && visibleBins.length === 0 && (
                      <div className="text-sm text-muted py-5 text-center">
                        No bins match “{binQuery}”.
                      </div>
                    )}
                  </div>

                  {/* Bin detail panel */}
                  <div className="min-w-0 xl:col-span-12 2xl:col-span-3">
                    {selectedBin && (
                      <Panel
                        title={`Bin: ${selectedBin.detail.full_code}`}
                        actions={canMap ? (
                          <div className="flex gap-1">
                            <Button
                              type="button"
                              size="xs"
                              variant="secondary"
                              icon={<LuPencil size={12} />}
                              onClick={() => setLocationForm({ mode: 'edit', existing: selectedBin.detail })}
                            >
                              Edit
                            </Button>
                            <Button
                              type="button"
                              size="xs"
                              variant="danger"
                              icon={<LuTrash2 size={12} />}
                              onClick={() => setDeleteTarget({
                                kind: 'location',
                                id: selectedBin.detail.id,
                                code: selectedBin.detail.code,
                              })}
                            >
                              Delete
                            </Button>
                          </div>
                        ) : undefined}
                      >
                        <div className="space-y-3 text-xs leading-5">
                          <StatusRow label="Status">
                            <Chip
                              variant={
                                selectedBin.detail.stock_status === 'ok'
                                  ? 'success'
                                  : selectedBin.detail.stock_status === 'low'
                                    ? 'warning'
                                    : selectedBin.detail.stock_status === 'full'
                                      ? 'info'
                                      : selectedBin.detail.stock_status === 'blocked'
                                        ? 'danger'
                                        : 'neutral'
                              }
                            >
                              {selectedBin.detail.stock_status_label ??
                                selectedBin.detail.stock_status}
                            </Chip>
                          </StatusRow>
                          <StatusRow label="Code">{selectedBin.detail.code}</StatusRow>
                          <StatusRow label="Availability">{selectedBin.detail.is_active ? 'Active' : 'Inactive'}</StatusRow>
                          {selectedBin.detail.rack && (
                            <StatusRow label="Rack">{selectedBin.detail.rack}</StatusRow>
                          )}
                          {selectedBin.detail.bin && (
                            <StatusRow label="Bin">{selectedBin.detail.bin}</StatusRow>
                          )}
                          <StatusRow label="Capacity">
                            {selectedBin.detail.capacity_kg
                              ? `${formatKg(selectedBin.detail.capacity_used_kg)} / ${formatKg(selectedBin.detail.capacity_kg)} kg`
                              : `No limit · ${formatKg(selectedBin.detail.capacity_used_kg)} kg used`}
                          </StatusRow>
                          {selectedBin.detail.capacity_kg && (
                            <StatusRow label="Capacity basis">
                              {capacityStatusLabel(selectedBin.detail.capacity_status ?? 'unverified')}
                            </StatusRow>
                          )}
                          <CapacityUsage
                            usedKg={selectedBin.detail.capacity_used_kg}
                            capacityKg={selectedBin.detail.capacity_kg}
                            capacityStatus={selectedBin.detail.capacity_status ?? 'unverified'}
                            untrackedLineCount={selectedBin.detail.capacity_untracked_line_count ?? 0}
                            showLabel={false}
                            showUntrackedNote={false}
                          />
                          {selectedBin.detail.current_item && (
                            <>
                              <StatusRow label="Item">
                                {selectedBin.detail.current_item.code} —{' '}
                                {selectedBin.detail.current_item.name}
                              </StatusRow>
                              <StatusRow label="Quantity">
                                {Number(selectedBin.detail.current_quantity).toFixed(3)}{' '}
                                {selectedBin.detail.current_item.unit_of_measure ?? ''}
                              </StatusRow>
                              {selectedBin.detail.current_lot_number && (
                                <StatusRow label="Lot">
                                  {selectedBin.detail.current_lot_number}
                                </StatusRow>
                              )}
                            </>
                          )}
                          {selectedBin.detail.is_blocked && (
                            <StatusRow label="Blocked">
                              {selectedBin.detail.blocked_reason ?? 'Yes'}
                            </StatusRow>
                          )}

                          {binDetail.isError && (
                            <div role="alert" className="text-sm text-danger-fg">
                              Bin stock details could not be loaded.{' '}
                              <Button
                                type="button"
                                variant="secondary"
                                size="sm"
                                onClick={() => void binDetail.refetch()}
                              >
                                Retry bin details
                              </Button>
                            </div>
                          )}
                          {binDetail.isFetching && !binDetail.data && (
                            <SkeletonBlock className="h-20" />
                          )}
                          {binDetail.data && (
                            <div className="mt-3 space-y-3 border-t border-subtle pt-3">
                              <div className="text-xs uppercase tracking-wider text-muted font-medium">
                                Stock levels
                              </div>
                              {binDetail.data.stock_levels.length === 0 ? (
                                <div className="text-muted">No stock at this bin.</div>
                              ) : (
                                <div className="space-y-2">
                                  {binDetail.data.stock_levels.map((sl, i) => (
                                    <div key={i} className="space-y-0.5 leading-5">
                                      <div className="font-medium">
                                        <span className="font-mono">{sl.item_code || 'Unknown item'}</span>
                                        {sl.item_name && <span> — {sl.item_name}</span>}
                                      </div>
                                      <div className="text-muted">
                                        On hand: {Number(sl.quantity).toLocaleString('en-PH')} {sl.unit_of_measure ?? 'units'}
                                        {' · '}usable: {Number(sl.usable_quantity ?? sl.available).toLocaleString('en-PH')}
                                      </div>
                                      {sl.unit_of_measure?.trim().toLowerCase() !== 'kg' &&
                                        Number(sl.weight_kg_per_unit ?? 0) <= 0 && (
                                          <div className="text-warning-fg">No gross kg weight · excluded from capacity usage</div>
                                        )}
                                    </div>
                                  ))}
                                </div>
                              )}
                              {binDetail.data.last_movement && (
                                <div className="pt-1 text-xs leading-5 text-muted">
                                  Last:{' '}
                                  {binDetail.data.last_movement.movement_type_label ??
                                    binDetail.data.last_movement.movement_type.replace(/_/g, ' ')}
                                  {' · '}
                                  {formatDate(binDetail.data.last_movement.created_at)}
                                </div>
                              )}
                            </div>
                          )}
                        </div>
                      </Panel>
                    )}
                    {!selectedBin && (
                      <p className="py-2 text-sm leading-5 text-muted">Select a bin to see its details.</p>
                    )}
                  </div>
                </div>
              </>
            )}
          </>
        )}
      </div>

      <Modal
        isOpen={!!zoneForm}
        onClose={() => setZoneForm(null)}
        title={zoneForm?.mode === 'edit' ? `Edit zone ${zoneForm.existing?.code}` : 'Add zone'}
        size="sm"
      >
        {zoneForm && wh && (
          <WarehouseZoneForm
            key={`${zoneForm.mode}-${zoneForm.existing?.id ?? 'new'}`}
            mode={zoneForm.mode}
            existing={zoneForm.existing}
            warehouseId={wh.id}
            onClose={() => setZoneForm(null)}
            onSaved={saveZone}
          />
        )}
      </Modal>

      <Modal
        isOpen={!!locationForm}
        onClose={() => setLocationForm(null)}
        title={locationForm?.mode === 'edit' ? `Edit bin ${locationForm.existing?.code}` : 'Add bin'}
        size="sm"
      >
        {locationForm && zone && (
          <WarehouseLocationForm
            key={`${locationForm.mode}-${locationForm.existing?.id ?? 'new'}`}
            mode={locationForm.mode}
            existing={locationForm.existing}
            zoneId={zone.id}
            onClose={() => setLocationForm(null)}
            onSaved={saveLocation}
          />
        )}
      </Modal>

      <ConfirmDialog
        isOpen={!!deleteTarget}
        onClose={() => setDeleteTarget(null)}
        onConfirm={() => {
          if (!deleteTarget) return;
          if (deleteTarget.kind === 'zone') deleteZone.mutate(deleteTarget.id);
          else deleteLocation.mutate(deleteTarget.id);
        }}
        title={deleteTarget?.kind === 'zone' ? 'Delete zone?' : 'Delete bin?'}
        description={deleteTarget ? (
          <>
            <span className="font-medium text-primary">
              {deleteTarget.kind === 'zone' ? deleteTarget.name : deleteTarget.code}
            </span>
            {' will be archived. Deletion is blocked while this zone or bin has stock, reservations, or pending stock adjustments.'}
          </>
        ) : null}
        confirmLabel="Delete"
        variant="danger"
        pending={deleteZone.isPending || deleteLocation.isPending}
      />
    </div>
  );
}

function StatusRow({ label, children }: { label: string; children: React.ReactNode }) {
  return (
    <div className="flex justify-between gap-2">
      <span className="text-muted whitespace-nowrap">{label}</span>
      <span className="text-right font-mono">{children}</span>
    </div>
  );
}

function formatKg(value: string | null | undefined): string {
  return new Intl.NumberFormat('en-PH', { maximumFractionDigits: 1 }).format(Number(value ?? 0));
}

function capacityStatusLabel(status: 'unverified' | 'estimated' | 'derived' | 'rated'): string {
  return {
    unverified: 'Unverified',
    estimated: 'Planning estimate',
    derived: 'Derived from active bins',
    rated: 'Entered from posted rating',
  }[status];
}

function CapacityUsage({
  usedKg,
  capacityKg,
  capacityStatus,
  untrackedLineCount,
  showLabel = true,
  showBasis = true,
  showUntrackedNote = true,
}: {
  usedKg: string;
  capacityKg: string | null;
  capacityStatus: 'unverified' | 'estimated' | 'derived' | 'rated';
  untrackedLineCount: number;
  showLabel?: boolean;
  showBasis?: boolean;
  showUntrackedNote?: boolean;
}) {
  const groupClass = showLabel ? 'mt-3 space-y-2' : 'space-y-2';

  if (capacityKg === null) {
    return (
      <div className={groupClass}>
        {showLabel && <div className="text-xs leading-5 text-muted">No limit · {formatKg(usedKg)} kg tracked</div>}
        {showUntrackedNote && untrackedLineCount > 0 && <UntrackedCapacityNote lineCount={untrackedLineCount} />}
      </div>
    );
  }

  const capacity = Number(capacityKg);
  const used = Number(usedKg);
  const percent = capacity > 0 ? (used / capacity) * 100 : 0;
  const clampedPercent = Math.min(100, Math.max(0, percent));
  const barColor = percent >= 100 ? 'bg-danger' : percent >= 90 ? 'bg-warning' : 'bg-accent';

  return (
    <div className={groupClass}>
      {showLabel && (
        <div className="flex flex-wrap justify-between gap-x-2 text-xs leading-5 text-muted tabular-nums">
          <span>Capacity</span>
          <span>{formatKg(usedKg)} / {formatKg(capacityKg)} kg{percent > 100 ? ` · ${Math.round(percent)}%` : ''}</span>
        </div>
      )}
      {showLabel && showBasis && <div className="text-xs leading-5 text-muted">{capacityStatusLabel(capacityStatus)}</div>}
      <div
        role="progressbar"
        aria-label={`Capacity usage: ${formatKg(usedKg)} of ${formatKg(capacityKg)} kilograms`}
        aria-valuemin={0}
        aria-valuemax={100}
        aria-valuenow={Math.round(clampedPercent)}
        className="h-1.5 overflow-hidden rounded-full bg-subtle"
      >
        <div className={cn('h-full rounded-full transition-[width]', barColor)} style={{ width: `${clampedPercent}%` }} />
      </div>
      {showUntrackedNote && untrackedLineCount > 0 && <UntrackedCapacityNote lineCount={untrackedLineCount} />}
    </div>
  );
}

function UntrackedCapacityNote({ lineCount }: { lineCount: number }) {
  return (
    <div role="note" className="text-xs leading-5 text-warning-fg">
      {lineCount} stock line{lineCount === 1 ? ' has' : 's have'} no gross kg weight configured; see bin cards for item names.
    </div>
  );
}

function UnweightedCapacityItems({
  items,
}: {
  items: NonNullable<WarehouseMapLocation['capacity_untracked_items']>;
}) {
  return (
    <div role="note" className="mt-3 space-y-2 break-words border-t border-subtle pt-3 text-xs leading-5 text-warning-fg">
      <div className="font-medium">Gross weight not set · {items.length}</div>
      <ul className="space-y-2">
        {items.map((item, index) => (
          <li key={item.item_id ?? `${item.item_code ?? 'unknown'}-${index}`}>
            <div>
              <span className="font-mono font-medium">{item.item_code ?? 'Unknown item'}</span>
              <span> — {item.item_name ?? 'Item details unavailable'}</span>
            </div>
            <div className="text-muted">
              On hand: {Number(item.quantity).toLocaleString('en-PH', { maximumFractionDigits: 3 })}{' '}
              {item.unit_of_measure ?? 'units'}
            </div>
          </li>
        ))}
      </ul>
    </div>
  );
}
