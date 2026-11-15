import { useMutation, useQuery } from '@tanstack/react-query';
import { zodResolver } from '@hookform/resolvers/zod';
import { AxiosError } from 'axios';
import { useForm } from 'react-hook-form';
import { z } from 'zod';
import toast from 'react-hot-toast';
import { warehouseApi } from '@/api/inventory/warehouse';
import { Button } from '@/components/ui/Button';
import { Input } from '@/components/ui/Input';
import { ModalFooter } from '@/components/ui/Modal';
import { Select } from '@/components/ui/Select';
import { Switch } from '@/components/ui/Switch';
import { onFormInvalid } from '@/lib/formErrors';
import type { ApiValidationError } from '@/types';

type ExistingZone = {
  id: string;
  name: string;
  code: string;
  zone_type: string;
  capacity_kg?: string | null;
  capacity_status?: 'unverified' | 'estimated' | 'derived' | 'rated';
};

type ExistingLocation = {
  id: string;
  code: string;
  rack: string | null;
  bin: string | null;
  capacity_kg?: string | null;
  capacity_status?: 'unverified' | 'estimated' | 'rated';
  is_active?: boolean;
  is_blocked: boolean;
};

const codeRegex = /^[A-Z0-9-]+$/;
const capacityKgSchema = z.string()
  .trim()
  .optional()
  .transform((value) => value || null)
  .refine((value) => value === null || /^\d+(?:\.\d{1,2})?$/.test(value), 'Use a positive kg amount with up to 2 decimal places.')
  .refine((value) => value === null || Number(value) > 0, 'Capacity must be greater than 0.')
  .refine((value) => value === null || Number(value) <= 99999999.99, 'Capacity cannot exceed 99,999,999.99 kg.')
  .transform((value) => value === null ? null : Number(value));

const zoneSchema = z.object({
  name: z.string().trim().min(2, 'Name must be at least 2 characters.').max(50),
  code: z.string().trim().min(1).max(10).regex(codeRegex, 'Use uppercase letters, digits, hyphens.'),
  zone_type: z.string().min(1, 'Zone type is required.'),
  capacity_kg: capacityKgSchema,
  capacity_status: z.enum(['unverified', 'estimated', 'derived', 'rated']),
});
type ZoneFormInput = z.input<typeof zoneSchema>;
type ZoneFormValues = z.output<typeof zoneSchema>;

const locationSchema = z.object({
  code: z.string().trim().min(1).max(20).regex(codeRegex, 'Use uppercase letters, digits, hyphens.'),
  rack: z.string().max(10).optional().or(z.literal('')),
  bin: z.string().max(10).optional().or(z.literal('')),
  capacity_kg: capacityKgSchema,
  capacity_status: z.enum(['unverified', 'estimated', 'rated']),
  is_active: z.boolean().default(true),
  is_blocked: z.boolean().default(false),
});
type LocationFormInput = z.input<typeof locationSchema>;
type LocationFormValues = z.output<typeof locationSchema>;

function applyServerErrors<T extends Record<string, unknown>>(
  error: AxiosError<ApiValidationError>,
  setError: (field: keyof T, options: { type: string; message: string }) => void,
  fallback: string,
) {
  if (error.response?.status === 422 && error.response.data?.errors) {
    Object.entries(error.response.data.errors).forEach(([field, messages]) => {
      setError(field as keyof T, {
        type: 'server',
        message: Array.isArray(messages) ? (messages as string[])[0] : String(messages),
      });
    });
    toast.error('Please fix the highlighted fields.');
    return;
  }

  toast.error(error.response?.data?.message ?? fallback);
}

export function WarehouseZoneForm({
  mode,
  existing,
  warehouseId,
  onClose,
  onSaved,
}: {
  mode: 'create' | 'edit';
  existing: ExistingZone | null;
  warehouseId: string;
  onClose: () => void;
  onSaved: (id: string) => void;
}) {
  const { data: warehouseOptions } = useQuery({
    queryKey: ['inventory', 'warehouse-options'],
    queryFn: warehouseApi.options,
    staleTime: 5 * 60 * 1000,
  });
  const {
    register,
    handleSubmit,
    setError,
    watch,
    formState: { errors, isSubmitting },
  } = useForm<ZoneFormInput, unknown, ZoneFormValues>({
    resolver: zodResolver(zoneSchema),
    defaultValues: {
      name: existing?.name ?? '',
      code: existing?.code ?? '',
      zone_type: existing?.zone_type ?? warehouseOptions?.zone_types?.[0]?.value ?? '',
      capacity_kg: existing?.capacity_kg ?? '',
      capacity_status: existing?.capacity_status ?? 'estimated',
    },
  });
  const capacityStatus = watch('capacity_status');

  const mutation = useMutation({
    mutationFn: (values: ZoneFormValues) => mode === 'create'
      ? warehouseApi.createZone({ warehouse_id: warehouseId, ...values })
      : warehouseApi.updateZone(existing!.id, values),
    onSuccess: (zone) => {
      toast.success(mode === 'create' ? 'Zone created.' : 'Zone updated.');
      onSaved(zone.id);
    },
    onError: (error: AxiosError<ApiValidationError>) =>
      applyServerErrors<ZoneFormInput>(error, setError, 'Failed to save zone.'),
  });

  const submit = (values: ZoneFormValues) => {
    if (mode === 'create' && values.capacity_kg === null) {
      setError('capacity_kg', { type: 'required', message: 'Set a maximum capacity for the new zone.' });
      return;
    }
    mutation.mutate(values);
  };

  return (
    <form onSubmit={handleSubmit(submit, onFormInvalid<ZoneFormInput>())} className="py-3">
      <div className="space-y-3">
        <Input label="Name" required maxLength={50} autoFocus {...register('name')} error={errors.name?.message} />
        <Input
          label="Code"
          required
          maxLength={10}
          {...register('code')}
          error={errors.code?.message}
          className="font-mono uppercase"
          placeholder="Zone code"
        />
        <Select label="Type" required {...register('zone_type')} error={errors.zone_type?.message}>
          {(warehouseOptions?.zone_types ?? []).map((type) => (
            <option key={type.value} value={type.value}>{type.label}</option>
          ))}
        </Select>
        <Input
          label="Maximum capacity (kg)"
          type="number"
          required={mode === 'create'}
          disabled={capacityStatus === 'derived'}
          min="0.01"
          max="99999999.99"
          step="0.01"
          {...register('capacity_kg')}
          error={errors.capacity_kg?.message}
          helper={capacityStatus === 'derived'
            ? 'Calculated from the limits of active bins in this zone.'
            : 'Enter a planning estimate or use the rating posted for the zone floor/racks.'}
        />
        <Select label="Capacity basis" {...register('capacity_status')} error={errors.capacity_status?.message}>
          <option value="unverified">Unverified existing value</option>
          <option value="estimated">Planning estimate</option>
          {mode === 'edit' && <option value="derived">Derived from active bins</option>}
          <option value="rated">Entered from posted rating</option>
        </Select>
        <p className="text-xs text-muted">
          Derived zone limits update when active bins are added, removed, activated, or their capacity changes. Manual limits stay fixed until edited.
        </p>
      </div>
      <ModalFooter>
        <Button type="button" variant="secondary" onClick={onClose} disabled={mutation.isPending}>Cancel</Button>
        <Button type="submit" variant="primary" loading={mutation.isPending} disabled={mutation.isPending || isSubmitting}>
          {mode === 'create' ? 'Create zone' : 'Save changes'}
        </Button>
      </ModalFooter>
    </form>
  );
}

export function WarehouseLocationForm({
  mode,
  existing,
  zoneId,
  onClose,
  onSaved,
}: {
  mode: 'create' | 'edit';
  existing: ExistingLocation | null;
  zoneId: string;
  onClose: () => void;
  onSaved: (id: string) => void;
}) {
  const {
    register,
    handleSubmit,
    setError,
    formState: { errors, isSubmitting },
  } = useForm<LocationFormInput, unknown, LocationFormValues>({
    resolver: zodResolver(locationSchema),
    defaultValues: {
      code: existing?.code ?? '',
      rack: existing?.rack ?? '',
      bin: existing?.bin ?? '',
      capacity_kg: existing?.capacity_kg ?? (mode === 'create' ? '250' : ''),
      capacity_status: existing?.capacity_status ?? (mode === 'create' ? 'estimated' : 'unverified'),
      is_active: existing?.is_active ?? true,
      is_blocked: existing?.is_blocked ?? false,
    },
  });

  const mutation = useMutation({
    mutationFn: (values: LocationFormValues) => {
      const payload = { ...values, rack: values.rack?.trim() || null, bin: values.bin?.trim() || null };
      return mode === 'create'
        ? warehouseApi.createLocation({ zone_id: zoneId, ...payload })
        : warehouseApi.updateLocation(existing!.id, payload);
    },
    onSuccess: (location) => {
      toast.success(mode === 'create' ? 'Bin created.' : 'Bin updated.');
      onSaved(location.id);
    },
    onError: (error: AxiosError<ApiValidationError>) =>
      applyServerErrors<LocationFormInput>(error, setError, 'Failed to save bin.'),
  });

  const submit = (values: LocationFormValues) => {
    if (mode === 'create' && values.capacity_kg === null) {
      setError('capacity_kg', { type: 'required', message: 'Set a maximum capacity for the new bin.' });
      return;
    }
    mutation.mutate(values);
  };

  return (
    <form onSubmit={handleSubmit(submit, onFormInvalid<LocationFormInput>())} className="py-3">
      <div className="space-y-3">
        <Input
          label="Code"
          required
          maxLength={20}
          autoFocus
          {...register('code')}
          error={errors.code?.message}
          className="font-mono uppercase"
          placeholder="Bin code"
        />
        <div className="grid grid-cols-2 gap-3">
          <Input label="Rack" maxLength={10} {...register('rack')} error={errors.rack?.message} />
          <Input label="Bin" maxLength={10} {...register('bin')} error={errors.bin?.message} />
        </div>
        <Input
          label="Maximum capacity (kg)"
          type="number"
          required={mode === 'create'}
          min="0.01"
          max="99999999.99"
          step="0.01"
          {...register('capacity_kg')}
          error={errors.capacity_kg?.message}
          helper="New bins start at an editable 250 kg estimate. Confirm the actual rack rating before relying on this limit."
        />
        <Select label="Capacity basis" {...register('capacity_status')} error={errors.capacity_status?.message}>
          <option value="unverified">Unverified existing value</option>
          <option value="estimated">Planning estimate</option>
          <option value="rated">Entered from posted rating</option>
        </Select>
        <Switch label="Active" {...register('is_active')} />
        <Switch label="Blocked for receiving" {...register('is_blocked')} />
      </div>
      <ModalFooter>
        <Button type="button" variant="secondary" onClick={onClose} disabled={mutation.isPending}>Cancel</Button>
        <Button type="submit" variant="primary" loading={mutation.isPending} disabled={mutation.isPending || isSubmitting}>
          {mode === 'create' ? 'Create bin' : 'Save changes'}
        </Button>
      </ModalFooter>
    </form>
  );
}
