import { useEffect, useMemo, useRef, useState } from 'react';
import type { FormEvent } from 'react';
import { Banknote, Check, FileText, HandCoins, Pencil, Search, Trash2, Wallet, X } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { api } from '../lib/api';
import { canManageFinances, extractErrorMessage, useAuth } from '../context/AuthContext';
import type { Building, FundCall, MatrixRow, Payment, PaymentMethod } from '../types/resources';
import { PageHeader } from '../components/PageHeader';
import { Field, Input, Select } from '../components/ui/Input';
import { Button } from '../components/ui/Button';
import { ErrorAlert, SuccessAlert } from '../components/ui/Alert';

const currentYear = new Date().getFullYear();
const yearOptions = [currentYear - 1, currentYear, currentYear + 1];
const apiUrl = import.meta.env.VITE_API_URL ?? 'http://localhost:8081';

function MonthCellIcon({ status }: { status: string }) {
  if (status === 'paid') {
    return (
      <span className="flex size-6 items-center justify-center rounded-full bg-brand-100 text-brand-700">
        <Check className="size-3.5" strokeWidth={3} />
      </span>
    );
  }

  if (status === 'partial') {
    return <span className="flex size-6 items-center justify-center rounded-full bg-amber-100 text-xs font-bold text-amber-700">½</span>;
  }

  if (status === 'unpaid') {
    return (
      <span className="flex size-6 items-center justify-center rounded-full bg-rose-100 text-rose-700">
        <X className="size-3.5" strokeWidth={3} />
      </span>
    );
  }

  return <span className="flex size-6 items-center justify-center rounded-full border border-dashed border-slate-200" />;
}

export function CotisationsPage() {
  const { user } = useAuth();
  const { t } = useTranslation();
  const isAdmin = canManageFinances(user);
  const monthLabels = t('common.monthsShort', { returnObjects: true }) as string[];

  const [rows, setRows] = useState<MatrixRow[]>([]);
  const [buildings, setBuildings] = useState<Building[]>([]);
  const [year, setYear] = useState(currentYear);
  const [buildingId, setBuildingId] = useState('');
  const [query, setQuery] = useState('');
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState<string | null>(null);

  const [payingCell, setPayingCell] = useState<{
    lot: MatrixRow;
    monthLabel: string;
    month: number | null;
    fundCallId: number | null;
    due: number;
    status: string;
  } | null>(null);
  const [cellPayments, setCellPayments] = useState<Payment[]>([]);
  const [isLoadingCellPayments, setIsLoadingCellPayments] = useState(false);
  const [paymentForm, setPaymentForm] = useState({
    amount: '',
    paid_at: new Date().toISOString().slice(0, 10),
    method: 'virement' as PaymentMethod,
    notes: '',
  });
  const [isSubmittingPayment, setIsSubmittingPayment] = useState(false);

  const [editingPaymentId, setEditingPaymentId] = useState<number | null>(null);
  const [editForm, setEditForm] = useState({
    amount: '',
    paid_at: '',
    method: 'virement' as PaymentMethod,
    notes: '',
  });
  const [isSavingEdit, setIsSavingEdit] = useState(false);

  const [bulkForLot, setBulkForLot] = useState<MatrixRow | null>(null);
  const [selectedMonths, setSelectedMonths] = useState<number[]>([]);
  const [isBulkPanelOpen, setIsBulkPanelOpen] = useState(false);
  const [bulkForm, setBulkForm] = useState({
    year: String(currentYear),
    paid_at: new Date().toISOString().slice(0, 10),
    method: 'especes' as PaymentMethod,
    notes: '',
  });
  const [isSubmittingBulk, setIsSubmittingBulk] = useState(false);

  async function loadMatrix() {
    setIsLoading(true);
    try {
      const { data } = await api.get<{ data: MatrixRow[] }>('/api/fund-calls/matrix', {
        params: { year, building_id: buildingId || undefined },
      });
      setRows(data.data);
    } catch (err) {
      setError(extractErrorMessage(err));
    } finally {
      setIsLoading(false);
    }
  }

  useEffect(() => {
    api.get<{ data: Building[] }>('/api/buildings').then(({ data }) => setBuildings(data.data));
  }, []);

  useEffect(() => {
    loadMatrix();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [year, buildingId]);

  async function fetchCellPayments(fundCallId: number | null) {
    if (!fundCallId) {
      setCellPayments([]);
      return;
    }

    setIsLoadingCellPayments(true);
    try {
      const { data } = await api.get<{ data: FundCall }>(`/api/fund-calls/${fundCallId}`);
      setCellPayments(data.data.payments);
    } catch (err) {
      setError(extractErrorMessage(err));
    } finally {
      setIsLoadingCellPayments(false);
    }
  }

  function openPaymentForm(row: MatrixRow, monthIndex: number) {
    const cell = row.months[monthIndex];
    if (!isAdmin) {
      return;
    }

    setError(null);
    setEditingPaymentId(null);
    setPayingCell({
      lot: row,
      monthLabel: `${monthLabels[monthIndex]} ${year}`,
      month: cell.month,
      fundCallId: cell.fund_call_id,
      due: cell.amount ?? 0,
      status: cell.status,
    });
    setPaymentForm({
      amount: String(cell.amount ?? ''),
      paid_at: new Date().toISOString().slice(0, 10),
      method: 'virement',
      notes: '',
    });

    void fetchCellPayments(cell.fund_call_id);
  }

  function openDebtPaymentForm(row: MatrixRow) {
    if (!isAdmin || !row.opening_balance) {
      return;
    }

    const remainingDue = row.opening_balance.amount - row.opening_balance.paid_amount;

    setError(null);
    setEditingPaymentId(null);
    setPayingCell({
      lot: row,
      monthLabel: t('cotisations.openingBalanceLabel'),
      month: null,
      fundCallId: row.opening_balance.fund_call_id,
      due: remainingDue,
      status: row.opening_balance.status,
    });
    setPaymentForm({
      amount: String(remainingDue),
      paid_at: new Date().toISOString().slice(0, 10),
      method: 'virement',
      notes: '',
    });

    void fetchCellPayments(row.opening_balance.fund_call_id);
  }

  function closeCellPanel() {
    setPayingCell(null);
    setCellPayments([]);
    setEditingPaymentId(null);
  }

  async function handleSubmitPayment(event: FormEvent) {
    event.preventDefault();
    if (!payingCell) {
      return;
    }

    setError(null);
    setIsSubmittingPayment(true);

    try {
      let fundCallId = payingCell.fundCallId;

      if (!fundCallId) {
        // No fund call exists yet for this month (the admin never had to
        // click "Générer" for it) — create it on the fly, at the amount
        // shown, before attaching the payment.
        const period = `${year}-${String(payingCell.month).padStart(2, '0')}-01`;
        const { data } = await api.post<{ data: FundCall }>('/api/fund-calls', {
          lot_id: payingCell.lot.lot_id,
          amount: payingCell.due,
          period,
        });
        fundCallId = data.data.id;
      }

      await api.post(`/api/fund-calls/${fundCallId}/payments`, {
        ...paymentForm,
        amount: Number(paymentForm.amount),
      });
      closeCellPanel();
      await loadMatrix();
    } catch (err) {
      setError(extractErrorMessage(err));
    } finally {
      setIsSubmittingPayment(false);
    }
  }

  function startEditingPayment(payment: Payment) {
    setEditingPaymentId(payment.id);
    setEditForm({
      amount: String(payment.amount),
      paid_at: payment.paid_at.slice(0, 10),
      method: payment.method,
      notes: payment.notes ?? '',
    });
  }

  async function handleSaveEdit(paymentId: number) {
    if (!payingCell) {
      return;
    }

    setError(null);
    setIsSavingEdit(true);

    try {
      await api.put(`/api/fund-calls/${payingCell.fundCallId}/payments/${paymentId}`, {
        ...editForm,
        amount: Number(editForm.amount),
      });
      setEditingPaymentId(null);
      await Promise.all([loadMatrix(), fetchCellPayments(payingCell.fundCallId)]);
    } catch (err) {
      setError(extractErrorMessage(err));
    } finally {
      setIsSavingEdit(false);
    }
  }

  async function handleDeletePayment(paymentId: number) {
    if (!payingCell) {
      return;
    }

    setError(null);

    try {
      await api.delete(`/api/fund-calls/${payingCell.fundCallId}/payments/${paymentId}`);
      setCellPayments((previous) => previous.filter((payment) => payment.id !== paymentId));
      await loadMatrix();
    } catch (err) {
      setError(extractErrorMessage(err));
    }
  }

  function openBulkForm(row: MatrixRow) {
    setBulkForLot(row);
    setSelectedMonths([]);
    setIsBulkPanelOpen(true);
    setBulkForm({
      year: String(year),
      paid_at: new Date().toISOString().slice(0, 10),
      method: 'especes',
      notes: '',
    });
  }

  const isDraggingRef = useRef(false);
  const dragOriginRef = useRef<{ lotId: number; month: number } | null>(null);
  const didDragRef = useRef(false);

  /**
   * Points a fresh or ongoing selection at this lot's months, adding
   * (never removing) the given months — shared by the click-to-toggle path
   * and the click-and-drag path below.
   */
  function addMonthsToSelection(row: MatrixRow, months: number[]) {
    if (bulkForLot?.lot_id !== row.lot_id) {
      setBulkForLot(row);
      setSelectedMonths(Array.from(new Set(months)).sort((a, b) => a - b));
      setIsBulkPanelOpen(false);
      setBulkForm({
        year: String(year),
        paid_at: new Date().toISOString().slice(0, 10),
        method: 'especes',
        notes: '',
      });
      return;
    }

    setSelectedMonths((previous) => {
      const set = new Set(previous);
      months.forEach((month) => set.add(month));
      return Array.from(set).sort((a, b) => a - b);
    });
  }

  /**
   * Clicking an unpaid month just toggles it on/off (colored highlight),
   * building up a selection across the row — no panel pops up yet, so
   * selecting several months stays a quick, uninterrupted click-click-click.
   * Clicking a month from a different lot than the current selection starts
   * a fresh selection for that lot. Paid months open the read-only history
   * panel instead, since there's nothing to select there.
   */
  function toggleMonthSelection(row: MatrixRow, cell: { month: number; status: string }) {
    if (!isAdmin) {
      return;
    }

    if (cell.status === 'paid') {
      openPaymentForm(row, cell.month - 1);
      return;
    }

    if (bulkForLot?.lot_id !== row.lot_id) {
      addMonthsToSelection(row, [cell.month]);
      return;
    }

    setSelectedMonths((previous) =>
      previous.includes(cell.month) ? previous.filter((m) => m !== cell.month) : [...previous, cell.month].sort((a, b) => a - b),
    );
  }

  /** Click-and-drag month selection, like a Windows-style rubber-band select. */
  function handleMonthMouseDown(row: MatrixRow, cell: { month: number; status: string }) {
    if (!isAdmin || cell.status === 'paid') {
      return;
    }
    isDraggingRef.current = true;
    dragOriginRef.current = { lotId: row.lot_id, month: cell.month };
    didDragRef.current = false;
  }

  function handleMonthMouseEnter(row: MatrixRow, cell: { month: number; status: string }) {
    if (!isDraggingRef.current || cell.status === 'paid') {
      return;
    }
    const origin = dragOriginRef.current;
    if (!origin || origin.lotId !== row.lot_id || origin.month === cell.month) {
      return;
    }

    if (!didDragRef.current) {
      didDragRef.current = true;
      addMonthsToSelection(row, [origin.month, cell.month]);
    } else {
      addMonthsToSelection(row, [cell.month]);
    }
  }

  function handleMonthClick(row: MatrixRow, cell: { month: number; status: string }) {
    if (didDragRef.current) {
      // Tail end of a drag (mousedown and mouseup landed on the same cell
      // after crossing others) — already applied via mouse-enter, so this
      // click shouldn't also toggle it.
      didDragRef.current = false;
      return;
    }
    toggleMonthSelection(row, cell);
  }

  useEffect(() => {
    function handleWindowMouseUp() {
      isDraggingRef.current = false;
      dragOriginRef.current = null;
      // Deferred so a genuine click firing right after mouseup (same-cell
      // case) still sees didDragRef as this interaction left it.
      setTimeout(() => {
        didDragRef.current = false;
      }, 0);
    }

    window.addEventListener('mouseup', handleWindowMouseUp);
    return () => window.removeEventListener('mouseup', handleWindowMouseUp);
  }, []);

  function cancelSelection() {
    setBulkForLot(null);
    setSelectedMonths([]);
    setIsBulkPanelOpen(false);
  }

  function toggleMonth(monthNumber: number) {
    setSelectedMonths((previous) =>
      previous.includes(monthNumber) ? previous.filter((m) => m !== monthNumber) : [...previous, monthNumber].sort((a, b) => a - b),
    );
  }

  const selectedMonthsTotal =
    bulkForLot?.months.reduce((total, cell, index) => (selectedMonths.includes(index + 1) ? total + (cell.amount ?? 0) : total), 0) ?? 0;

  async function handleSubmitBulk(event: FormEvent) {
    event.preventDefault();
    if (!bulkForLot) {
      return;
    }

    if (selectedMonths.length === 0) {
      setError(t('cotisations.bulkMonthsNoneSelected'));
      return;
    }

    setError(null);
    setSuccess(null);
    setIsSubmittingBulk(true);

    try {
      const payload = {
        months: selectedMonths,
        year: Number(bulkForm.year),
        paid_at: bulkForm.paid_at,
        method: bulkForm.method,
        notes: bulkForm.notes,
      };

      const { data } = await api.post<{ message: string }>(`/api/lots/${bulkForLot.lot_id}/payments/bulk`, payload);
      setSuccess(data.message);
      cancelSelection();
      await loadMatrix();
    } catch (err) {
      setError(extractErrorMessage(err));
    } finally {
      setIsSubmittingBulk(false);
    }
  }

  const filteredRows = useMemo(() => {
    if (!query.trim()) {
      return rows;
    }
    const needle = query.trim().toLowerCase();
    return rows.filter((row) => `${row.lot_number} ${row.owner_name}`.toLowerCase().includes(needle));
  }, [rows, query]);

  const realNow = new Date();
  const realCurrentYear = realNow.getFullYear();
  const realCurrentMonth = realNow.getMonth() + 1;

  function lotHasUnpaidOpeningBalance(row: MatrixRow): boolean {
    return !!row.opening_balance && row.opening_balance.status !== 'paid';
  }

  /**
   * A month with no fund call generated yet ('none') still reads as unpaid
   * once its date has passed — the admin may simply not have visited that
   * month yet, that shouldn't make it look settled or irrelevant.
   *
   * For a past year (browsing history, not the real current year), we have
   * no reliable per-month record of what was actually billed back then —
   * the opening balance already represents all of that as one lump sum. So
   * a past year's unbilled months only turn red as a flag that this lot
   * carries old debt, not as a literal per-month obligation; a lot with no
   * opening balance (or fully paid) shows its past history as neutral.
   */
  function effectiveCellStatus(row: MatrixRow, cell: { month: number; status: string }): string {
    if (cell.status !== 'none') {
      return cell.status;
    }

    if (year > realCurrentYear) {
      return 'none';
    }

    if (year === realCurrentYear) {
      return cell.month <= realCurrentMonth ? 'unpaid' : 'none';
    }

    return lotHasUnpaidOpeningBalance(row) ? 'unpaid' : 'none';
  }

  function rowTotals(row: MatrixRow) {
    const cotise = row.months.reduce((total, cell) => total + cell.paid_amount, 0);

    // "Dette" always reflects what's owed as of today, regardless of which
    // year's grid is being browsed — so the current-year monthly shortfall
    // only applies when that's actually the year loaded in `row.months`.
    const monthsDebt =
      year === realCurrentYear
        ? row.months.reduce((total, cell) => {
            if (cell.month > realCurrentMonth || cell.status === 'paid') {
              return total;
            }
            return total + Math.max(0, (cell.amount ?? 0) - cell.paid_amount);
          }, 0)
        : 0;

    const openingDebt = row.opening_balance ? Math.max(0, row.opening_balance.amount - row.opening_balance.paid_amount) : 0;

    return { cotise, dette: monthsDebt + openingDebt };
  }

  return (
    <div>
      <PageHeader title={t('cotisations.title')} subtitle={t('cotisations.subtitle')} />

      <div className="mb-5 flex flex-wrap items-end gap-3">
        <Field label={t('cotisations.yearLabel')} htmlFor="year-filter">
          <Select id="year-filter" className="w-32" value={year} onChange={(event) => setYear(Number(event.target.value))}>
            {yearOptions.map((y) => (
              <option key={y} value={y}>
                {y}
              </option>
            ))}
          </Select>
        </Field>

        {buildings.length > 1 && (
          <Field label={t('cotisations.buildingLabel')} htmlFor="building-filter">
            <Select id="building-filter" className="w-48" value={buildingId} onChange={(event) => setBuildingId(event.target.value)}>
              <option value="">{t('cotisations.allBuildings')}</option>
              {buildings.map((building) => (
                <option key={building.id} value={building.id}>
                  {building.name}
                </option>
              ))}
            </Select>
          </Field>
        )}

        <Field label={t('common.search')} htmlFor="cotisations-search">
          <div className="relative">
            <Search className="pointer-events-none absolute start-3 top-1/2 size-4 -translate-y-1/2 text-slate-400" />
            <Input
              id="cotisations-search"
              type="text"
              className="w-56 ps-9"
              value={query}
              onChange={(event) => setQuery(event.target.value)}
              placeholder={t('common.searchPlaceholder')}
            />
          </div>
        </Field>
      </div>

      {error && (
        <div className="mb-4">
          <ErrorAlert>{error}</ErrorAlert>
        </div>
      )}
      {success && (
        <div className="mb-4">
          <SuccessAlert>{success}</SuccessAlert>
        </div>
      )}

      {payingCell && (
        <div className="mb-6 flex flex-col gap-4 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
          <div className="flex items-center justify-between">
            <p className="text-sm font-semibold text-slate-900">
              {t('cotisations.registerPaymentTitle', {
                lot: payingCell.lot.lot_number,
                building: payingCell.lot.building_name,
                month: payingCell.monthLabel,
              })}
            </p>
            <button type="button" onClick={closeCellPanel} className="text-slate-400 hover:text-slate-600">
              <X className="size-4" />
            </button>
          </div>

          {isLoadingCellPayments ? (
            <p className="text-sm text-slate-500">{t('common.loading')}</p>
          ) : (
            cellPayments.length > 0 && (
              <div className="flex flex-col gap-2">
                <span className="text-sm font-medium text-slate-700">{t('cotisations.existingPaymentsLabel')}</span>
                {cellPayments.map((payment) =>
                  editingPaymentId === payment.id ? (
                    <div key={payment.id} className="grid grid-cols-1 gap-3 rounded-lg border border-brand-200 bg-brand-50/40 p-3 sm:grid-cols-2">
                      <Field label={t('cotisations.amountReceivedLabel')} htmlFor={`edit-amount-${payment.id}`}>
                        <Input
                          id={`edit-amount-${payment.id}`}
                          type="number"
                          min={1}
                          value={editForm.amount}
                          onChange={(event) => setEditForm((previous) => ({ ...previous, amount: event.target.value }))}
                        />
                      </Field>
                      <Field label={t('cotisations.paymentDateLabel')} htmlFor={`edit-date-${payment.id}`}>
                        <Input
                          id={`edit-date-${payment.id}`}
                          type="date"
                          value={editForm.paid_at}
                          onChange={(event) => setEditForm((previous) => ({ ...previous, paid_at: event.target.value }))}
                        />
                      </Field>
                      <Field label={t('cotisations.methodLabel')} htmlFor={`edit-method-${payment.id}`}>
                        <Select
                          id={`edit-method-${payment.id}`}
                          value={editForm.method}
                          onChange={(event) => setEditForm((previous) => ({ ...previous, method: event.target.value as PaymentMethod }))}
                        >
                          <option value="virement">{t('common.paymentMethods.virement')}</option>
                          <option value="especes">{t('common.paymentMethods.especes')}</option>
                          <option value="cheque">{t('common.paymentMethods.cheque')}</option>
                        </Select>
                      </Field>
                      <Field label={t('cotisations.noteLabel')} htmlFor={`edit-notes-${payment.id}`}>
                        <Input
                          id={`edit-notes-${payment.id}`}
                          value={editForm.notes}
                          onChange={(event) => setEditForm((previous) => ({ ...previous, notes: event.target.value }))}
                        />
                      </Field>
                      <div className="flex gap-2 sm:col-span-2">
                        <Button type="button" size="sm" isLoading={isSavingEdit} onClick={() => handleSaveEdit(payment.id)}>
                          {t('common.save')}
                        </Button>
                        <Button type="button" size="sm" variant="secondary" onClick={() => setEditingPaymentId(null)}>
                          {t('common.cancel')}
                        </Button>
                      </div>
                    </div>
                  ) : (
                    <div key={payment.id} className="flex items-center justify-between gap-3 rounded-lg border border-slate-200 px-3 py-2 text-sm">
                      <div className="flex flex-wrap items-center gap-x-3 gap-y-0.5 text-slate-700">
                        <span className="font-semibold text-slate-900">{payment.amount} DH</span>
                        <span>{new Date(payment.paid_at).toLocaleDateString('fr-FR')}</span>
                        <span className="text-slate-400">·</span>
                        <span>{t(`common.paymentMethods.${payment.method}`)}</span>
                        {payment.notes && <span className="text-slate-400">({payment.notes})</span>}
                      </div>
                      <div className="flex shrink-0 gap-1">
                        <a
                          href={`${apiUrl}/api/fund-calls/${payingCell.fundCallId}/payments/${payment.id}/receipt`}
                          target="_blank"
                          rel="noopener"
                          className="rounded-md p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700"
                          title={t('cotisations.viewReceipt')}
                        >
                          <FileText className="size-3.5" />
                        </a>
                        <button
                          type="button"
                          onClick={() => startEditingPayment(payment)}
                          className="rounded-md p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700"
                          title={t('common.edit')}
                        >
                          <Pencil className="size-3.5" />
                        </button>
                        <button
                          type="button"
                          onClick={() => handleDeletePayment(payment.id)}
                          className="rounded-md p-1.5 text-slate-400 hover:bg-rose-50 hover:text-rose-600"
                          title={t('common.delete')}
                        >
                          <Trash2 className="size-3.5" />
                        </button>
                      </div>
                    </div>
                  ),
                )}
              </div>
            )
          )}

          {payingCell.status !== 'paid' && (
            <form onSubmit={handleSubmitPayment} className="flex flex-col gap-4 border-t border-slate-100 pt-4">
              <span className="text-sm font-medium text-slate-700">{t('cotisations.addPaymentLabel')}</span>
              <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <Field label={t('cotisations.amountReceivedLabel')} htmlFor="payment-amount">
                  <Input
                    id="payment-amount"
                    type="number"
                    min={1}
                    value={paymentForm.amount}
                    onChange={(event) => setPaymentForm((previous) => ({ ...previous, amount: event.target.value }))}
                    required
                  />
                </Field>

                <Field label={t('cotisations.paymentDateLabel')} htmlFor="payment-date">
                  <Input
                    id="payment-date"
                    type="date"
                    value={paymentForm.paid_at}
                    onChange={(event) => setPaymentForm((previous) => ({ ...previous, paid_at: event.target.value }))}
                    required
                  />
                </Field>

                <Field label={t('cotisations.methodLabel')} htmlFor="payment-method">
                  <Select
                    id="payment-method"
                    value={paymentForm.method}
                    onChange={(event) =>
                      setPaymentForm((previous) => ({ ...previous, method: event.target.value as PaymentMethod }))
                    }
                  >
                    <option value="virement">{t('common.paymentMethods.virement')}</option>
                    <option value="especes">{t('common.paymentMethods.especes')}</option>
                    <option value="cheque">{t('common.paymentMethods.cheque')}</option>
                  </Select>
                </Field>

                <Field label={t('cotisations.noteLabel')} htmlFor="payment-notes">
                  <Input
                    id="payment-notes"
                    value={paymentForm.notes}
                    onChange={(event) => setPaymentForm((previous) => ({ ...previous, notes: event.target.value }))}
                  />
                </Field>
              </div>

              <Button type="submit" isLoading={isSubmittingPayment} className="self-start">
                {t('cotisations.registerPaymentButton')}
              </Button>
            </form>
          )}
        </div>
      )}

      {bulkForLot && isBulkPanelOpen && (
        <form
          onSubmit={handleSubmitBulk}
          className="mb-6 flex flex-col gap-4 rounded-xl border border-slate-200 bg-white p-5 shadow-sm"
        >
          <div className="flex items-center justify-between">
            <p className="text-sm font-semibold text-slate-900">
              {t('cotisations.bulkTitle', {
                lot: bulkForLot.lot_number,
                building: bulkForLot.building_name,
                owner: bulkForLot.owner_name,
              })}
            </p>
            <button type="button" onClick={cancelSelection} className="text-slate-400 hover:text-slate-600">
              <X className="size-4" />
            </button>
          </div>
          <p className="text-sm text-slate-500">{t('cotisations.bulkMonthsDesc')}</p>

          <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <Field label={t('cotisations.bulkYearLabel')} htmlFor="bulk-year-fixed">
              <Input id="bulk-year-fixed" value={bulkForm.year} disabled />
            </Field>

            <Field label={t('cotisations.paymentDateLabel')} htmlFor="bulk-date">
              <Input
                id="bulk-date"
                type="date"
                value={bulkForm.paid_at}
                onChange={(event) => setBulkForm((previous) => ({ ...previous, paid_at: event.target.value }))}
                required
              />
            </Field>

            <Field label={t('cotisations.methodLabel')} htmlFor="bulk-method">
              <Select
                id="bulk-method"
                value={bulkForm.method}
                onChange={(event) =>
                  setBulkForm((previous) => ({ ...previous, method: event.target.value as PaymentMethod }))
                }
              >
                <option value="virement">{t('common.paymentMethods.virement')}</option>
                <option value="especes">{t('common.paymentMethods.especes')}</option>
                <option value="cheque">{t('common.paymentMethods.cheque')}</option>
              </Select>
            </Field>

            <Field label={t('cotisations.noteLabel')} htmlFor="bulk-notes">
              <Input
                id="bulk-notes"
                value={bulkForm.notes}
                onChange={(event) => setBulkForm((previous) => ({ ...previous, notes: event.target.value }))}
              />
            </Field>
          </div>

          {bulkForLot && (
            <div className="flex flex-col gap-2">
              <span className="text-sm font-medium text-slate-700">{t('cotisations.bulkMonthsLabel')}</span>
              <div className="grid grid-cols-2 gap-2 sm:grid-cols-3 md:grid-cols-4">
                {bulkForLot.months.map((cell, index) => {
                  const monthNumber = index + 1;
                  const alreadyPaid = cell.status === 'paid';
                  const checked = alreadyPaid || selectedMonths.includes(monthNumber);

                  return (
                    <label
                      key={monthNumber}
                      className={`flex items-center gap-2 rounded-lg border px-3 py-2 text-sm ${
                        alreadyPaid
                          ? 'cursor-default border-slate-100 bg-slate-50 text-slate-400'
                          : checked
                            ? 'cursor-pointer border-brand-500 bg-brand-50 text-brand-800'
                            : 'cursor-pointer border-slate-200 text-slate-700 hover:border-slate-300'
                      }`}
                    >
                      <input
                        type="checkbox"
                        className="size-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500/30"
                        checked={checked}
                        disabled={alreadyPaid}
                        onChange={() => toggleMonth(monthNumber)}
                      />
                      <span>{monthLabels[index]}</span>
                      {alreadyPaid && <span className="text-xs">({t('cotisations.bulkMonthAlreadyPaid')})</span>}
                    </label>
                  );
                })}
              </div>
              {selectedMonths.length > 0 && (
                <p className="text-sm text-slate-600">
                  {t('cotisations.bulkMonthsTotal', { amount: selectedMonthsTotal, count: selectedMonths.length })}
                </p>
              )}
            </div>
          )}

          <Button type="submit" isLoading={isSubmittingBulk} className="self-start">
            <Banknote className="size-4" />
            {t('cotisations.registerPaymentButton')}
          </Button>
        </form>
      )}

      {isLoading ? (
        <p className="text-sm text-slate-500">{t('common.loading')}</p>
      ) : rows.length === 0 ? (
        <EmptyState />
      ) : filteredRows.length === 0 ? (
        <p className="text-sm text-slate-500">{t('common.noResults')}</p>
      ) : (
        <>
          {isAdmin && <p className="mb-2 text-xs text-slate-400">{t('cotisations.selectionHint')}</p>}
          <div className="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
          <table className="w-full text-start text-sm">
            <thead>
              <tr className="border-b border-slate-200 bg-slate-50 text-xs font-semibold uppercase tracking-wide text-slate-500">
                <th className="sticky start-0 bg-slate-50 px-4 py-3">{t('cotisations.colLot')}</th>
                <th className="px-4 py-3">{t('cotisations.colOwner')}</th>
                {monthLabels.map((label) => (
                  <th key={label} className="px-2 py-3 text-center">
                    {label}
                  </th>
                ))}
                <th className="px-4 py-3 text-end">{t('cotisations.colCotise')}</th>
                <th className="px-4 py-3 text-end">{t('cotisations.colDette')}</th>
                {isAdmin && <th className="px-4 py-3" />}
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100">
              {filteredRows.map((row) => {
                const totals = rowTotals(row);

                return (
                <tr key={row.lot_id} className="hover:bg-slate-50/60">
                  <td className="sticky start-0 whitespace-nowrap bg-white px-4 py-2.5 font-medium text-slate-900">
                    {row.lot_number}
                    <span className="ms-1.5 font-normal text-slate-400">({row.building_name})</span>
                  </td>
                  <td className="whitespace-nowrap px-4 py-2.5 text-slate-600">{row.owner_name}</td>
                  {row.months.map((cell) => {
                    const isSelected = bulkForLot?.lot_id === row.lot_id && selectedMonths.includes(cell.month);

                    return (
                      <td key={cell.month} className="px-2 py-2.5 text-center">
                        <button
                          type="button"
                          onMouseDown={(event) => {
                            event.preventDefault();
                            handleMonthMouseDown(row, cell);
                          }}
                          onMouseEnter={() => handleMonthMouseEnter(row, cell)}
                          onClick={() => handleMonthClick(row, cell)}
                          disabled={!isAdmin}
                          className={`inline-flex rounded-full transition-shadow disabled:cursor-default ${
                            isSelected ? 'ring-2 ring-brand-500 ring-offset-1' : ''
                          }`}
                          title={cell.amount ? `${cell.amount} DH` : undefined}
                        >
                          <MonthCellIcon status={effectiveCellStatus(row, cell)} />
                        </button>
                      </td>
                    );
                  })}
                  <td className="whitespace-nowrap px-4 py-2.5 text-end font-medium text-slate-900">{totals.cotise} DH</td>
                  <td
                    className={`whitespace-nowrap px-4 py-2.5 text-end font-medium ${totals.dette > 0 ? 'text-rose-600' : 'text-slate-400'}`}
                  >
                    {totals.dette} DH
                  </td>
                  {isAdmin && (
                    <td className="whitespace-nowrap px-4 py-2.5">
                      {bulkForLot?.lot_id === row.lot_id && selectedMonths.length > 0 ? (
                        <div className="flex items-center gap-1.5">
                          <span className="whitespace-nowrap rounded-full bg-brand-50 px-2.5 py-1 text-xs font-medium text-brand-700">
                            {t('cotisations.selectionCount', { count: selectedMonths.length })}
                          </span>
                          <Button size="sm" onClick={() => setIsBulkPanelOpen(true)}>
                            {t('cotisations.paySelectionButton')}
                          </Button>
                          <button
                            type="button"
                            onClick={cancelSelection}
                            className="flex size-7 shrink-0 items-center justify-center rounded-lg text-slate-400 hover:bg-slate-100 hover:text-slate-600"
                            title={t('common.cancel')}
                          >
                            <X className="size-3.5" />
                          </button>
                        </div>
                      ) : (
                        <div className="flex gap-1.5">
                          <Button size="sm" variant="secondary" onClick={() => openBulkForm(row)}>
                            <Banknote className="size-3.5" /> {t('cotisations.bulkButton')}
                          </Button>
                          {row.opening_balance && row.opening_balance.status !== 'paid' && (
                            <Button size="sm" variant="secondary" onClick={() => openDebtPaymentForm(row)}>
                              <HandCoins className="size-3.5" /> {t('cotisations.debtPaymentButton')}
                            </Button>
                          )}
                        </div>
                      )}
                    </td>
                  )}
                </tr>
                );
              })}
            </tbody>
          </table>
          </div>
        </>
      )}
    </div>
  );
}

function EmptyState() {
  const { t } = useTranslation();
  return (
    <div className="flex flex-col items-center justify-center rounded-xl border border-dashed border-slate-300 bg-white px-6 py-14 text-center">
      <div className="mb-3 flex size-11 items-center justify-center rounded-full bg-slate-100 text-slate-400">
        <Wallet className="size-5" />
      </div>
      <p className="text-sm font-medium text-slate-900">{t('cotisations.emptyTitle')}</p>
      <p className="mt-1 text-sm text-slate-500">{t('cotisations.emptyDesc')}</p>
    </div>
  );
}
