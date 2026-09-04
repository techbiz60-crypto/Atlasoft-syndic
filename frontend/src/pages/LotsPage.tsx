import { useEffect, useMemo, useRef, useState } from 'react';
import type { FormEvent } from 'react';
import { AxiosError } from 'axios';
import { Copy, History, Home, IdCard, KeyRound, ListPlus, Pencil, Plus, Trash2, UserRoundCog, X } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { api } from '../lib/api';
import { extractErrorMessage, useAuth } from '../context/AuthContext';
import type { Building, Lot, LotOwnerHistoryEntry, LotReferenceEntry, LotType } from '../types/resources';
import { PageHeader } from '../components/PageHeader';
import { Field, Input, Select } from '../components/ui/Input';
import { Button } from '../components/ui/Button';
import { ErrorAlert, SuccessAlert } from '../components/ui/Alert';
import { DataTable } from '../components/ui/DataTable';
import type { DataTableColumn } from '../components/ui/DataTable';

interface BulkRow {
  number: string;
  floor: string;
  owner_name: string;
  owner_phone: string;
  owner_email: string;
  lot_type_id: string;
}

/**
 * Splits a pasted line the way spreadsheet copy/paste actually behaves: tab
 * first (Excel/Sheets default), falling back to comma or 2+ spaces so a
 * quick CSV paste still works.
 */
function splitPastedLine(line: string): string[] {
  if (line.includes('\t')) {
    return line.split('\t');
  }
  if (line.includes(',')) {
    return line.split(',');
  }
  return line.split(/\s{2,}/);
}

export function LotsPage() {
  const { user } = useAuth();
  const { t } = useTranslation();
  const isAdmin = user?.role === 'admin';

  const [lots, setLots] = useState<Lot[]>([]);
  const [lotTypes, setLotTypes] = useState<LotType[]>([]);
  const [buildings, setBuildings] = useState<Building[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [buildingFilter, setBuildingFilter] = useState('');

  const emptyForm = {
    building_id: '',
    lot_type_id: '',
    number: '',
    floor: '',
    owner_name: '',
    owner_phone: '',
    owner_email: '',
  };
  const [form, setForm] = useState(emptyForm);
  const [editingId, setEditingId] = useState<number | null>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const formRef = useRef<HTMLFormElement>(null);
  const firstFieldRef = useRef<HTMLSelectElement>(null);

  const [openingBalanceLot, setOpeningBalanceLot] = useState<Lot | null>(null);
  const [openingBalanceForm, setOpeningBalanceForm] = useState({ amount: '', period: '' });
  const [isSavingOpeningBalance, setIsSavingOpeningBalance] = useState(false);

  const [ownerHistoryLot, setOwnerHistoryLot] = useState<Lot | null>(null);
  const [ownerHistory, setOwnerHistory] = useState<LotOwnerHistoryEntry[]>([]);
  const [isLoadingOwnerHistory, setIsLoadingOwnerHistory] = useState(false);
  const [newOwnerForm, setNewOwnerForm] = useState({
    owner_name: '',
    owner_phone: '',
    owner_email: '',
    started_at: new Date().toISOString().slice(0, 10),
  });
  const [isSavingNewOwner, setIsSavingNewOwner] = useState(false);

  const [accessLot, setAccessLot] = useState<Lot | null>(null);
  const [accessForm, setAccessForm] = useState({ name: '', email: '', whatsapp_number: '' });
  const [isSavingAccess, setIsSavingAccess] = useState(false);
  const [generatedPassword, setGeneratedPassword] = useState<string | null>(null);

  const [referencesLot, setReferencesLot] = useState<Lot | null>(null);
  const [references, setReferences] = useState<LotReferenceEntry[]>([]);
  const [isLoadingReferences, setIsLoadingReferences] = useState(false);
  const [newChipValue, setNewChipValue] = useState('');
  const [newGarageValue, setNewGarageValue] = useState('');
  const [isSavingReference, setIsSavingReference] = useState(false);

  const [isBulkOpen, setIsBulkOpen] = useState(false);
  const [bulkBuildingId, setBulkBuildingId] = useState('');
  const [bulkRaw, setBulkRaw] = useState('');
  const [bulkRows, setBulkRows] = useState<BulkRow[]>([]);
  const [bulkRowErrors, setBulkRowErrors] = useState<Record<number, string>>({});
  const [isSubmittingBulk, setIsSubmittingBulk] = useState(false);

  async function loadData() {
    setIsLoading(true);
    try {
      const [lotsResponse, lotTypesResponse, buildingsResponse] = await Promise.all([
        api.get<{ data: Lot[] }>('/api/lots'),
        api.get<{ data: LotType[] }>('/api/lot-types'),
        api.get<{ data: Building[] }>('/api/buildings'),
      ]);
      setLots(lotsResponse.data.data);
      setLotTypes(lotTypesResponse.data.data);
      setBuildings(buildingsResponse.data.data);
    } catch (err) {
      setError(extractErrorMessage(err));
    } finally {
      setIsLoading(false);
    }
  }

  useEffect(() => {
    loadData();
  }, []);

  const visibleLots = useMemo(
    () => (buildingFilter ? lots.filter((lot) => lot.building_id === Number(buildingFilter)) : lots),
    [lots, buildingFilter],
  );

  function updateField(field: keyof typeof form) {
    return (event: React.ChangeEvent<HTMLInputElement | HTMLSelectElement>) => {
      setForm((previous) => ({ ...previous, [field]: event.target.value }));
    };
  }

  function startEdit(lot: Lot) {
    setEditingId(lot.id);
    setForm({
      building_id: String(lot.building_id),
      lot_type_id: String(lot.lot_type_id),
      number: lot.number,
      floor: lot.floor ?? '',
      owner_name: lot.owner_name,
      owner_phone: lot.owner_phone ?? '',
      owner_email: lot.owner_email ?? '',
    });
    formRef.current?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    firstFieldRef.current?.focus();
  }

  function resetForm() {
    setEditingId(null);
    setForm(emptyForm);
  }

  async function handleSubmit(event: FormEvent) {
    event.preventDefault();
    setError(null);
    setIsSubmitting(true);

    try {
      const payload = { ...form, building_id: Number(form.building_id), lot_type_id: Number(form.lot_type_id) };

      if (editingId) {
        await api.put(`/api/lots/${editingId}`, payload);
      } else {
        await api.post('/api/lots', payload);
      }

      resetForm();
      await loadData();
    } catch (err) {
      setError(extractErrorMessage(err));
    } finally {
      setIsSubmitting(false);
    }
  }

  async function handleDelete(id: number) {
    if (!confirm(t('lots.confirmDelete'))) {
      return;
    }

    try {
      await api.delete(`/api/lots/${id}`);
      await loadData();
    } catch (err) {
      setError(extractErrorMessage(err));
    }
  }

  function startOpeningBalance(lot: Lot) {
    setError(null);
    setOpeningBalanceLot(lot);
    setOpeningBalanceForm({
      amount: lot.opening_balance ? String(lot.opening_balance.amount) : '',
      period: lot.opening_balance ? lot.opening_balance.period.slice(0, 10) : '',
    });
  }

  async function handleSaveOpeningBalance() {
    if (!openingBalanceLot) {
      return;
    }

    setError(null);
    setIsSavingOpeningBalance(true);

    try {
      const payload = { amount: Number(openingBalanceForm.amount), period: openingBalanceForm.period };

      if (openingBalanceLot.opening_balance) {
        await api.put(`/api/fund-calls/${openingBalanceLot.opening_balance.id}/opening-balance`, payload);
      } else {
        await api.post('/api/fund-calls', { ...payload, lot_id: openingBalanceLot.id, is_opening_balance: true });
      }

      setOpeningBalanceLot(null);
      await loadData();
    } catch (err) {
      setError(extractErrorMessage(err));
    } finally {
      setIsSavingOpeningBalance(false);
    }
  }

  async function handleDeleteOpeningBalance() {
    if (!openingBalanceLot?.opening_balance || !confirm(t('lots.confirmDeleteOpeningBalance'))) {
      return;
    }

    setError(null);
    setIsSavingOpeningBalance(true);

    try {
      await api.delete(`/api/fund-calls/${openingBalanceLot.opening_balance.id}`);
      setOpeningBalanceLot(null);
      await loadData();
    } catch (err) {
      setError(extractErrorMessage(err));
    } finally {
      setIsSavingOpeningBalance(false);
    }
  }

  async function startOwnerHistory(lot: Lot) {
    setError(null);
    setOwnerHistoryLot(lot);
    setNewOwnerForm({ owner_name: '', owner_phone: '', owner_email: '', started_at: new Date().toISOString().slice(0, 10) });
    setIsLoadingOwnerHistory(true);

    try {
      const { data } = await api.get<{ data: LotOwnerHistoryEntry[] }>(`/api/lots/${lot.id}/owners`);
      setOwnerHistory(data.data);
    } catch (err) {
      setError(extractErrorMessage(err));
    } finally {
      setIsLoadingOwnerHistory(false);
    }
  }

  async function handleSaveNewOwner() {
    if (!ownerHistoryLot) {
      return;
    }

    setError(null);
    setIsSavingNewOwner(true);

    try {
      await api.post(`/api/lots/${ownerHistoryLot.id}/owners`, newOwnerForm);
      setOwnerHistoryLot(null);
      await loadData();
    } catch (err) {
      setError(extractErrorMessage(err));
    } finally {
      setIsSavingNewOwner(false);
    }
  }

  function startAccess(lot: Lot) {
    setError(null);
    setGeneratedPassword(null);
    setAccessLot(lot);
    setAccessForm({
      name: lot.owner_name,
      email: lot.owner_email ?? '',
      whatsapp_number: lot.owner_phone ?? '',
    });
  }

  async function handleSaveAccess() {
    if (!accessLot) {
      return;
    }

    setError(null);
    setIsSavingAccess(true);

    try {
      const { data } = await api.post<{ generated_password: string }>(`/api/lots/${accessLot.id}/access`, accessForm);
      setGeneratedPassword(data.generated_password);
      await loadData();
    } catch (err) {
      setError(extractErrorMessage(err));
    } finally {
      setIsSavingAccess(false);
    }
  }

  async function handleRevokeAccess() {
    if (!accessLot?.resident_user || !confirm(t('lots.confirmRevokeAccess'))) {
      return;
    }

    setError(null);
    setIsSavingAccess(true);

    try {
      await api.delete(`/api/users/${accessLot.resident_user.id}`);
      setAccessLot(null);
      await loadData();
    } catch (err) {
      setError(extractErrorMessage(err));
    } finally {
      setIsSavingAccess(false);
    }
  }

  async function startReferences(lot: Lot) {
    setError(null);
    setReferencesLot(lot);
    setNewChipValue('');
    setNewGarageValue('');
    setIsLoadingReferences(true);

    try {
      const { data } = await api.get<{ data: LotReferenceEntry[] }>(`/api/lots/${lot.id}/references`);
      setReferences(data.data);
    } catch (err) {
      setError(extractErrorMessage(err));
    } finally {
      setIsLoadingReferences(false);
    }
  }

  async function handleAddReference(type: 'elevator_chip' | 'garage_number', value: string) {
    if (!referencesLot || !value.trim()) {
      return;
    }

    setError(null);
    setIsSavingReference(true);

    try {
      const { data } = await api.post<{ data: LotReferenceEntry }>(`/api/lots/${referencesLot.id}/references`, {
        type,
        value: value.trim(),
      });
      setReferences((previous) => [...previous, data.data]);
      if (type === 'elevator_chip') {
        setNewChipValue('');
      } else {
        setNewGarageValue('');
      }
    } catch (err) {
      setError(extractErrorMessage(err));
    } finally {
      setIsSavingReference(false);
    }
  }

  async function handleDeleteReference(reference: LotReferenceEntry) {
    setError(null);

    try {
      await api.delete(`/api/lot-references/${reference.id}`);
      setReferences((previous) => previous.filter((r) => r.id !== reference.id));
    } catch (err) {
      setError(extractErrorMessage(err));
    }
  }

  function parseBulkRows() {
    const defaultTypeId = lotTypes.length === 1 ? String(lotTypes[0].id) : '';

    const rows: BulkRow[] = bulkRaw
      .split('\n')
      .map((line) => line.trim())
      .filter((line) => line.length > 0)
      .map((line) => {
        const [number = '', owner_name = '', owner_phone = '', owner_email = ''] = splitPastedLine(line).map((cell) =>
          cell.trim(),
        );
        return { number, floor: '', owner_name, owner_phone, owner_email, lot_type_id: defaultTypeId };
      });

    setBulkRows(rows);
    setBulkRowErrors({});
  }

  function updateBulkRow(index: number, field: keyof BulkRow, value: string) {
    setBulkRows((previous) => previous.map((row, i) => (i === index ? { ...row, [field]: value } : row)));
  }

  function removeBulkRow(index: number) {
    setBulkRows((previous) => previous.filter((_, i) => i !== index));
  }

  function resetBulk() {
    setIsBulkOpen(false);
    setBulkBuildingId('');
    setBulkRaw('');
    setBulkRows([]);
    setBulkRowErrors({});
  }

  async function handleSubmitBulk() {
    if (!bulkBuildingId || bulkRows.length === 0) {
      return;
    }

    if (bulkRows.some((row) => !row.lot_type_id)) {
      setError(t('lots.bulkTypeRequired'));
      return;
    }

    setError(null);
    setBulkRowErrors({});
    setIsSubmittingBulk(true);

    try {
      await api.post('/api/lots/bulk', {
        building_id: Number(bulkBuildingId),
        lots: bulkRows.map((row) => ({
          number: row.number,
          floor: row.floor || undefined,
          lot_type_id: Number(row.lot_type_id),
          owner_name: row.owner_name,
          owner_phone: row.owner_phone || undefined,
          owner_email: row.owner_email || undefined,
        })),
      });

      resetBulk();
      await loadData();
    } catch (err) {
      setError(extractErrorMessage(err));

      if (err instanceof AxiosError) {
        const errors = (err.response?.data as { errors?: Record<string, string[]> } | undefined)?.errors ?? {};
        const rowErrors: Record<number, string> = {};

        for (const [key, messages] of Object.entries(errors)) {
          const match = key.match(/^lots\.(\d+)\./);
          if (match) {
            rowErrors[Number(match[1])] = messages.join(' ');
          }
        }

        setBulkRowErrors(rowErrors);
      }
    } finally {
      setIsSubmittingBulk(false);
    }
  }

  const lotColumns: DataTableColumn<Lot>[] = [
    {
      key: 'number',
      label: t('lots.colLot'),
      sortable: true,
      className: 'font-medium text-slate-900',
      sortValue: (lot) => lot.number,
    },
    {
      key: 'floor',
      label: t('lots.colFloor'),
      render: (lot) => lot.floor ?? '—',
    },
    {
      key: 'building',
      label: t('lots.colBuilding'),
      sortable: true,
      sortValue: (lot) => lot.building.name,
      render: (lot) => lot.building.name,
    },
    {
      key: 'type',
      label: t('lots.colType'),
      sortable: true,
      sortValue: (lot) => lot.lot_type.name,
      render: (lot) => (
        <span className="rounded-full bg-brand-50 px-2.5 py-1 text-xs font-medium text-brand-700">
          {lot.lot_type.name}
        </span>
      ),
    },
    {
      key: 'owner',
      label: t('lots.colOwner'),
      sortable: true,
      sortValue: (lot) => lot.owner_name,
      render: (lot) => lot.owner_name,
    },
    {
      key: 'phone',
      label: t('lots.colPhone'),
      render: (lot) => lot.owner_phone ?? '—',
    },
  ];

  return (
    <div>
      <PageHeader
        title={t('lots.title')}
        subtitle={t('lots.subtitle')}
        action={
          isAdmin && (
            <Button type="button" variant="secondary" onClick={() => setIsBulkOpen((previous) => !previous)}>
              <ListPlus className="size-4" />
              {t('lots.bulkAddButton')}
            </Button>
          )
        }
      />

      {isAdmin && isBulkOpen && (
        <div className="mb-6 flex flex-col gap-4 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
          <div className="flex items-center justify-between">
            <div>
              <p className="text-sm font-semibold text-slate-900">{t('lots.bulkTitle')}</p>
              <p className="mt-0.5 text-sm text-slate-500">{t('lots.bulkHint')}</p>
            </div>
            <button type="button" onClick={resetBulk} className="text-slate-400 hover:text-slate-600">
              <X className="size-4" />
            </button>
          </div>

          <Field label={t('lots.buildingLabel')} htmlFor="bulk-building-select">
            <Select
              id="bulk-building-select"
              className="max-w-xs"
              value={bulkBuildingId}
              onChange={(event) => setBulkBuildingId(event.target.value)}
              required
            >
              <option value="" disabled>
                {t('lots.chooseBuilding')}
              </option>
              {buildings.map((building) => (
                <option key={building.id} value={building.id}>
                  {building.name}
                </option>
              ))}
            </Select>
          </Field>

          {bulkRows.length === 0 ? (
            <>
              <Field label={t('lots.bulkPasteLabel')} htmlFor="bulk-paste">
                <textarea
                  id="bulk-paste"
                  rows={8}
                  value={bulkRaw}
                  onChange={(event) => setBulkRaw(event.target.value)}
                  placeholder={t('lots.bulkPastePlaceholder')}
                  className="w-full rounded-lg border border-slate-300 px-3 py-2 font-mono text-sm text-slate-700 focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20"
                />
              </Field>
              <Button type="button" onClick={parseBulkRows} disabled={!bulkRaw.trim()} className="self-start">
                {t('lots.bulkAnalyzeButton')}
              </Button>
            </>
          ) : (
            <>
              <div className="overflow-x-auto rounded-xl border border-slate-200">
                <table className="w-full text-start text-sm">
                  <thead>
                    <tr className="border-b border-slate-200 bg-slate-50 text-xs font-semibold uppercase tracking-wide text-slate-500">
                      <th className="px-3 py-2.5 text-start">{t('lots.numberLabel')}</th>
                      <th className="px-3 py-2.5 text-start">{t('lots.colFloor')}</th>
                      <th className="px-3 py-2.5 text-start">{t('lots.lotTypeLabel')}</th>
                      <th className="px-3 py-2.5 text-start">{t('lots.ownerNameLabel')}</th>
                      <th className="px-3 py-2.5 text-start">{t('lots.ownerPhoneLabel')}</th>
                      <th className="px-3 py-2.5 text-start">{t('lots.ownerEmailLabel')}</th>
                      <th className="px-3 py-2.5" />
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-slate-100">
                    {bulkRows.map((row, index) => (
                      <tr key={index} className={bulkRowErrors[index] ? 'bg-rose-50/60' : undefined}>
                        <td className="px-3 py-2">
                          <Input
                            value={row.number}
                            onChange={(event) => updateBulkRow(index, 'number', event.target.value)}
                            className="min-w-24"
                          />
                          {bulkRowErrors[index] && <p className="mt-1 text-xs text-rose-600">{bulkRowErrors[index]}</p>}
                        </td>
                        <td className="px-3 py-2">
                          <Input
                            value={row.floor}
                            onChange={(event) => updateBulkRow(index, 'floor', event.target.value)}
                            placeholder={t('lots.floorPlaceholder')}
                            className="min-w-20"
                          />
                        </td>
                        <td className="px-3 py-2">
                          <Select
                            value={row.lot_type_id}
                            onChange={(event) => updateBulkRow(index, 'lot_type_id', event.target.value)}
                            className="min-w-32"
                          >
                            <option value="" disabled>
                              {t('lots.chooseLotType')}
                            </option>
                            {lotTypes.map((lotType) => (
                              <option key={lotType.id} value={lotType.id}>
                                {lotType.name}
                              </option>
                            ))}
                          </Select>
                        </td>
                        <td className="px-3 py-2">
                          <Input
                            value={row.owner_name}
                            onChange={(event) => updateBulkRow(index, 'owner_name', event.target.value)}
                            className="min-w-32"
                          />
                        </td>
                        <td className="px-3 py-2">
                          <Input
                            value={row.owner_phone}
                            onChange={(event) => updateBulkRow(index, 'owner_phone', event.target.value)}
                            className="min-w-28"
                          />
                        </td>
                        <td className="px-3 py-2">
                          <Input
                            value={row.owner_email}
                            onChange={(event) => updateBulkRow(index, 'owner_email', event.target.value)}
                            className="min-w-40"
                          />
                        </td>
                        <td className="px-3 py-2">
                          <button
                            type="button"
                            onClick={() => removeBulkRow(index)}
                            className="flex size-8 items-center justify-center rounded-lg text-slate-400 hover:bg-rose-50 hover:text-rose-600"
                          >
                            <Trash2 className="size-4" />
                          </button>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>

              <div className="flex gap-2">
                <Button
                  type="button"
                  isLoading={isSubmittingBulk}
                  disabled={!bulkBuildingId}
                  onClick={handleSubmitBulk}
                  className="self-start"
                >
                  {t('lots.bulkConfirmButton', { count: bulkRows.length })}
                </Button>
                <Button type="button" variant="secondary" onClick={() => setBulkRows([])} className="self-start">
                  {t('lots.bulkBackButton')}
                </Button>
              </div>
            </>
          )}
        </div>
      )}

      <div className="flex flex-col gap-6 lg:flex-row">
        {isAdmin && (
          <form
            ref={formRef}
            onSubmit={handleSubmit}
            className="flex h-fit w-full flex-col gap-4 rounded-xl border border-slate-200 bg-white p-5 shadow-sm lg:w-80 lg:shrink-0"
          >
            <p className="text-sm font-semibold text-slate-900">
              {editingId ? t('lots.editTitle') : t('lots.addTitle')}
            </p>

            <Field label={t('lots.buildingLabel')} htmlFor="building-select">
              <Select id="building-select" ref={firstFieldRef} value={form.building_id} onChange={updateField('building_id')} required>
                <option value="" disabled>
                  {t('lots.chooseBuilding')}
                </option>
                {buildings.map((building) => (
                  <option key={building.id} value={building.id}>
                    {building.name}
                  </option>
                ))}
              </Select>
            </Field>

            <Field label={t('lots.lotTypeLabel')} htmlFor="lot-type-select">
              <Select id="lot-type-select" value={form.lot_type_id} onChange={updateField('lot_type_id')} required>
                <option value="" disabled>
                  {t('lots.chooseLotType')}
                </option>
                {lotTypes.map((lotType) => (
                  <option key={lotType.id} value={lotType.id}>
                    {lotType.name}
                  </option>
                ))}
              </Select>
            </Field>

            <Field label={t('lots.numberLabel')} htmlFor="lot-number">
              <Input
                id="lot-number"
                placeholder={t('lots.numberPlaceholder')}
                value={form.number}
                onChange={updateField('number')}
                required
              />
            </Field>

            <Field label={t('lots.floorLabel')} htmlFor="lot-floor">
              <Input
                id="lot-floor"
                placeholder={t('lots.floorPlaceholder')}
                value={form.floor}
                onChange={updateField('floor')}
              />
            </Field>

            <Field label={t('lots.ownerNameLabel')} htmlFor="owner-name">
              <Input id="owner-name" value={form.owner_name} onChange={updateField('owner_name')} required />
            </Field>

            <Field label={t('lots.ownerPhoneLabel')} htmlFor="owner-phone">
              <Input id="owner-phone" value={form.owner_phone} onChange={updateField('owner_phone')} />
            </Field>

            <Field label={t('lots.ownerEmailLabel')} htmlFor="owner-email">
              <Input id="owner-email" type="email" value={form.owner_email} onChange={updateField('owner_email')} />
            </Field>

            <div className="flex gap-2">
              <Button type="submit" isLoading={isSubmitting} className="flex-1">
                {editingId ? (
                  <>
                    <Pencil className="size-4" /> {t('common.update')}
                  </>
                ) : (
                  <>
                    <Plus className="size-4" /> {t('common.add')}
                  </>
                )}
              </Button>
              {editingId && (
                <Button type="button" variant="secondary" onClick={resetForm}>
                  <X className="size-4" />
                </Button>
              )}
            </div>
          </form>
        )}

        <div className="flex-1">
          {error && (
            <div className="mb-4">
              <ErrorAlert>{error}</ErrorAlert>
            </div>
          )}

          {buildings.length > 1 && (
            <div className="mb-4 max-w-xs">
              <Select value={buildingFilter} onChange={(event) => setBuildingFilter(event.target.value)}>
                <option value="">{t('lots.allBuildings')}</option>
                {buildings.map((building) => (
                  <option key={building.id} value={building.id}>
                    {building.name}
                  </option>
                ))}
              </Select>
            </div>
          )}

          {openingBalanceLot && (
            <div className="mb-4 flex flex-col gap-4 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
              <div className="flex items-center justify-between">
                <div>
                  <p className="text-sm font-semibold text-slate-900">
                    {t('lots.openingBalanceTitle', { number: openingBalanceLot.number })}
                  </p>
                  <p className="mt-0.5 text-sm text-slate-500">{t('lots.openingBalanceHint')}</p>
                </div>
                <button
                  type="button"
                  onClick={() => setOpeningBalanceLot(null)}
                  className="text-slate-400 hover:text-slate-600"
                >
                  <X className="size-4" />
                </button>
              </div>

              <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <Field label={t('lots.openingBalanceAmountLabel')} htmlFor="opening-balance-amount">
                  <Input
                    id="opening-balance-amount"
                    type="number"
                    min={0}
                    value={openingBalanceForm.amount}
                    onChange={(event) => setOpeningBalanceForm((previous) => ({ ...previous, amount: event.target.value }))}
                  />
                </Field>
                <Field label={t('lots.openingBalanceDateLabel')} htmlFor="opening-balance-date">
                  <Input
                    id="opening-balance-date"
                    type="date"
                    value={openingBalanceForm.period}
                    onChange={(event) => setOpeningBalanceForm((previous) => ({ ...previous, period: event.target.value }))}
                  />
                </Field>
              </div>

              <div className="flex gap-2">
                <Button type="button" isLoading={isSavingOpeningBalance} onClick={handleSaveOpeningBalance} className="self-start">
                  {t('common.save')}
                </Button>
                {openingBalanceLot.opening_balance && (
                  <Button type="button" variant="secondary" onClick={handleDeleteOpeningBalance} className="self-start text-rose-600">
                    {t('common.delete')}
                  </Button>
                )}
              </div>
            </div>
          )}

          {ownerHistoryLot && (
            <div className="mb-4 flex flex-col gap-4 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
              <div className="flex items-center justify-between">
                <div>
                  <p className="text-sm font-semibold text-slate-900">
                    {t('lots.ownerHistoryTitle', { number: ownerHistoryLot.number })}
                  </p>
                  <p className="mt-0.5 text-sm text-slate-500">{t('lots.ownerHistoryHint')}</p>
                </div>
                <button
                  type="button"
                  onClick={() => setOwnerHistoryLot(null)}
                  className="text-slate-400 hover:text-slate-600"
                >
                  <X className="size-4" />
                </button>
              </div>

              {isLoadingOwnerHistory ? (
                <p className="text-sm text-slate-500">{t('common.loading')}</p>
              ) : (
                <ul className="flex flex-col gap-2">
                  {ownerHistory.map((entry, index) => (
                    <li
                      key={entry.id}
                      className="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-slate-200 px-3 py-2 text-sm"
                    >
                      <div>
                        <span className="font-medium text-slate-900">{entry.owner_name}</span>
                        {(entry.owner_phone || entry.owner_email) && (
                          <span className="ms-2 text-slate-500">{[entry.owner_phone, entry.owner_email].filter(Boolean).join(' · ')}</span>
                        )}
                      </div>
                      <span className="text-xs text-slate-400">
                        {index === 0
                          ? t('lots.ownerSince', { date: new Date(entry.started_at).toLocaleDateString('fr-FR') })
                          : t('lots.ownerFromTo', {
                              from: new Date(entry.started_at).toLocaleDateString('fr-FR'),
                              to: new Date(ownerHistory[index - 1].started_at).toLocaleDateString('fr-FR'),
                            })}
                      </span>
                    </li>
                  ))}
                </ul>
              )}

              <div className="grid grid-cols-1 gap-4 border-t border-slate-100 pt-4 sm:grid-cols-2">
                <p className="text-sm font-medium text-slate-700 sm:col-span-2">{t('lots.newOwnerTitle')}</p>
                <Field label={t('lots.ownerNameLabel')} htmlFor="new-owner-name">
                  <Input
                    id="new-owner-name"
                    value={newOwnerForm.owner_name}
                    onChange={(event) => setNewOwnerForm((previous) => ({ ...previous, owner_name: event.target.value }))}
                  />
                </Field>
                <Field label={t('lots.ownerHistoryDateLabel')} htmlFor="new-owner-date">
                  <Input
                    id="new-owner-date"
                    type="date"
                    value={newOwnerForm.started_at}
                    onChange={(event) => setNewOwnerForm((previous) => ({ ...previous, started_at: event.target.value }))}
                  />
                </Field>
                <Field label={t('lots.ownerPhoneLabel')} htmlFor="new-owner-phone">
                  <Input
                    id="new-owner-phone"
                    value={newOwnerForm.owner_phone}
                    onChange={(event) => setNewOwnerForm((previous) => ({ ...previous, owner_phone: event.target.value }))}
                  />
                </Field>
                <Field label={t('lots.ownerEmailLabel')} htmlFor="new-owner-email">
                  <Input
                    id="new-owner-email"
                    type="email"
                    value={newOwnerForm.owner_email}
                    onChange={(event) => setNewOwnerForm((previous) => ({ ...previous, owner_email: event.target.value }))}
                  />
                </Field>
              </div>

              <Button
                type="button"
                isLoading={isSavingNewOwner}
                disabled={!newOwnerForm.owner_name.trim()}
                onClick={handleSaveNewOwner}
                className="self-start"
              >
                {t('lots.saveNewOwnerButton')}
              </Button>
            </div>
          )}

          {accessLot && (
            <div className="mb-4 flex flex-col gap-4 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
              <div className="flex items-center justify-between">
                <div>
                  <p className="text-sm font-semibold text-slate-900">
                    {t('lots.accessTitle', { number: accessLot.number })}
                  </p>
                  <p className="mt-0.5 text-sm text-slate-500">{t('lots.accessHint')}</p>
                </div>
                <button type="button" onClick={() => setAccessLot(null)} className="text-slate-400 hover:text-slate-600">
                  <X className="size-4" />
                </button>
              </div>

              {generatedPassword ? (
                <SuccessAlert>
                  <div className="flex flex-wrap items-center gap-2">
                    <span>
                      {t('lots.generatedPasswordLabel')} <span className="font-mono font-semibold">{generatedPassword}</span>
                    </span>
                    <button
                      type="button"
                      onClick={() => void navigator.clipboard.writeText(generatedPassword)}
                      className="inline-flex items-center gap-1 rounded-lg border border-current px-2 py-1 text-xs font-medium"
                    >
                      <Copy className="size-3.5" />
                      {t('users.copyButton')}
                    </button>
                  </div>
                </SuccessAlert>
              ) : accessLot.resident_user ? (
                <div className="flex flex-col gap-3">
                  <p className="text-sm text-slate-600">
                    {t('lots.accessActiveHint', { email: accessLot.resident_user.email })}
                  </p>
                  <Button
                    type="button"
                    variant="secondary"
                    isLoading={isSavingAccess}
                    onClick={handleRevokeAccess}
                    className="self-start text-rose-600"
                  >
                    {t('lots.revokeAccessButton')}
                  </Button>
                </div>
              ) : (
                <>
                  <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <Field label={t('lots.ownerNameLabel')} htmlFor="access-name">
                      <Input
                        id="access-name"
                        value={accessForm.name}
                        onChange={(event) => setAccessForm((previous) => ({ ...previous, name: event.target.value }))}
                        required
                      />
                    </Field>
                    <Field label={t('users.emailLabel')} htmlFor="access-email">
                      <Input
                        id="access-email"
                        type="email"
                        value={accessForm.email}
                        onChange={(event) => setAccessForm((previous) => ({ ...previous, email: event.target.value }))}
                        required
                      />
                    </Field>
                    <Field label={t('lots.ownerPhoneLabel')} htmlFor="access-phone">
                      <Input
                        id="access-phone"
                        value={accessForm.whatsapp_number}
                        onChange={(event) => setAccessForm((previous) => ({ ...previous, whatsapp_number: event.target.value }))}
                      />
                    </Field>
                  </div>
                  <Button
                    type="button"
                    isLoading={isSavingAccess}
                    disabled={!accessForm.name.trim() || !accessForm.email.trim()}
                    onClick={handleSaveAccess}
                    className="self-start"
                  >
                    {t('lots.grantAccessButton')}
                  </Button>
                </>
              )}
            </div>
          )}

          {referencesLot && (
            <div className="mb-4 flex flex-col gap-4 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
              <div className="flex items-center justify-between">
                <p className="text-sm font-semibold text-slate-900">
                  {t('lots.referencesTitle', { number: referencesLot.number })}
                </p>
                <button type="button" onClick={() => setReferencesLot(null)} className="text-slate-400 hover:text-slate-600">
                  <X className="size-4" />
                </button>
              </div>

              {isLoadingReferences ? (
                <p className="text-sm text-slate-500">{t('common.loading')}</p>
              ) : (
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                  <ReferenceList
                    title={t('lots.elevatorChipsLabel')}
                    items={references.filter((reference) => reference.type === 'elevator_chip')}
                    inputValue={newChipValue}
                    onInputChange={setNewChipValue}
                    onAdd={() => handleAddReference('elevator_chip', newChipValue)}
                    onDelete={handleDeleteReference}
                    isSaving={isSavingReference}
                    placeholder={t('lots.chipNumberPlaceholder')}
                  />
                  <ReferenceList
                    title={t('lots.garageNumbersLabel')}
                    items={references.filter((reference) => reference.type === 'garage_number')}
                    inputValue={newGarageValue}
                    onInputChange={setNewGarageValue}
                    onAdd={() => handleAddReference('garage_number', newGarageValue)}
                    onDelete={handleDeleteReference}
                    isSaving={isSavingReference}
                    placeholder={t('lots.garageNumberPlaceholder')}
                  />
                </div>
              )}
            </div>
          )}

          <DataTable
            columns={lotColumns}
            data={visibleLots}
            rowKey={(lot) => lot.id}
            previousLabel={t('common.previous')}
            nextLabel={t('common.next')}
            rowsPerPageLabel={t('common.rowsPerPage')}
            allRowsLabel={t('common.allRows')}
            isLoading={isLoading}
            loadingText={t('common.loading')}
            emptyState={<EmptyState />}
            searchableText={(lot) => `${lot.number} ${lot.owner_name} ${lot.building.name} ${lot.owner_phone ?? ''}`}
            searchPlaceholder={t('common.searchPlaceholder')}
            actions={
              isAdmin
                ? (lot) => (
                    <>
                      <IconButton
                        onClick={() => startOpeningBalance(lot)}
                        label={
                          lot.opening_balance
                            ? t('lots.editOpeningBalance', { amount: lot.opening_balance.amount })
                            : t('lots.addOpeningBalance')
                        }
                        active={!!lot.opening_balance}
                      >
                        <History className="size-4" />
                      </IconButton>
                      <IconButton onClick={() => startOwnerHistory(lot)} label={t('lots.ownerHistoryButton')}>
                        <UserRoundCog className="size-4" />
                      </IconButton>
                      <IconButton
                        onClick={() => startAccess(lot)}
                        label={lot.resident_user ? t('lots.accessActiveButton') : t('lots.grantAccessButton')}
                        active={!!lot.resident_user}
                      >
                        <KeyRound className="size-4" />
                      </IconButton>
                      <IconButton onClick={() => startReferences(lot)} label={t('lots.referencesButton')}>
                        <IdCard className="size-4" />
                      </IconButton>
                      <IconButton onClick={() => startEdit(lot)} label={t('common.edit')}>
                        <Pencil className="size-4" />
                      </IconButton>
                      <IconButton onClick={() => handleDelete(lot.id)} label={t('common.delete')} danger>
                        <Trash2 className="size-4" />
                      </IconButton>
                    </>
                  )
                : undefined
            }
          />
        </div>
      </div>
    </div>
  );
}

function ReferenceList({
  title,
  items,
  inputValue,
  onInputChange,
  onAdd,
  onDelete,
  isSaving,
  placeholder,
}: {
  title: string;
  items: LotReferenceEntry[];
  inputValue: string;
  onInputChange: (value: string) => void;
  onAdd: () => void;
  onDelete: (reference: LotReferenceEntry) => void;
  isSaving: boolean;
  placeholder: string;
}) {
  const { t } = useTranslation();

  return (
    <div className="flex flex-col gap-2 rounded-lg border border-slate-200 p-3">
      <p className="text-sm font-medium text-slate-700">{title}</p>

      {items.length > 0 && (
        <ul className="flex flex-wrap gap-1.5">
          {items.map((item) => (
            <li
              key={item.id}
              className="flex items-center gap-1 rounded-full bg-slate-100 py-1 pe-1.5 ps-2.5 text-sm text-slate-700"
            >
              {item.value}
              <button
                type="button"
                onClick={() => onDelete(item)}
                aria-label={t('common.delete')}
                className="flex size-4 items-center justify-center rounded-full text-slate-400 hover:bg-slate-200 hover:text-slate-600"
              >
                <X className="size-3" />
              </button>
            </li>
          ))}
        </ul>
      )}

      <div className="flex gap-1.5">
        <Input
          value={inputValue}
          onChange={(event) => onInputChange(event.target.value)}
          placeholder={placeholder}
          onKeyDown={(event) => {
            if (event.key === 'Enter') {
              event.preventDefault();
              onAdd();
            }
          }}
        />
        <Button type="button" variant="secondary" isLoading={isSaving} onClick={onAdd} disabled={!inputValue.trim()}>
          <Plus className="size-4" />
        </Button>
      </div>
    </div>
  );
}

function IconButton({
  children,
  onClick,
  label,
  danger = false,
  active = false,
}: {
  children: React.ReactNode;
  onClick: () => void;
  label: string;
  danger?: boolean;
  active?: boolean;
}) {
  return (
    <button
      onClick={onClick}
      aria-label={label}
      title={label}
      className={`flex size-8 items-center justify-center rounded-lg border transition-colors ${
        danger
          ? 'border-rose-200 text-rose-600 hover:bg-rose-50'
          : active
            ? 'border-brand-200 bg-brand-50 text-brand-700 hover:bg-brand-100'
            : 'border-slate-200 text-slate-500 hover:bg-slate-100 hover:text-slate-700'
      }`}
    >
      {children}
    </button>
  );
}

function EmptyState() {
  const { t } = useTranslation();
  return (
    <div className="flex flex-col items-center justify-center rounded-xl border border-dashed border-slate-300 bg-white px-6 py-14 text-center">
      <div className="mb-3 flex size-11 items-center justify-center rounded-full bg-slate-100 text-slate-400">
        <Home className="size-5" />
      </div>
      <p className="text-sm font-medium text-slate-900">{t('lots.emptyTitle')}</p>
      <p className="mt-1 text-sm text-slate-500">{t('lots.emptyDesc')}</p>
    </div>
  );
}
