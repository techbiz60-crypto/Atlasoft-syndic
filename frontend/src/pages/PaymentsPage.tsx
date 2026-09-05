import { useEffect, useState } from 'react';
import { FileText, Pencil, Receipt, Trash2, X } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { api } from '../lib/api';
import { canManageFinances, extractErrorMessage, useAuth } from '../context/AuthContext';
import type { Building, PaymentMethod, PaymentWithContext } from '../types/resources';
import { PageHeader } from '../components/PageHeader';
import { Field, Input, Select } from '../components/ui/Input';
import { Button } from '../components/ui/Button';
import { ErrorAlert } from '../components/ui/Alert';
import { DataTable } from '../components/ui/DataTable';
import type { DataTableColumn } from '../components/ui/DataTable';

const currentYear = new Date().getFullYear();
const yearOptions = [currentYear - 1, currentYear, currentYear + 1];
const apiUrl = import.meta.env.VITE_API_URL ?? 'http://localhost:8081';

export function PaymentsPage() {
  const { user } = useAuth();
  const { t } = useTranslation();
  const isAdmin = canManageFinances(user);

  const [payments, setPayments] = useState<PaymentWithContext[]>([]);
  const [buildings, setBuildings] = useState<Building[]>([]);
  const [year, setYear] = useState('');
  const [month, setMonth] = useState('');
  const [method, setMethod] = useState('');
  const [buildingId, setBuildingId] = useState('');
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const [editingPayment, setEditingPayment] = useState<PaymentWithContext | null>(null);
  const [editForm, setEditForm] = useState({ amount: '', paid_at: '', method: 'virement' as PaymentMethod, notes: '' });
  const [editMonths, setEditMonths] = useState<number[]>([]);
  const [editYear, setEditYear] = useState<number>(currentYear);
  const [isSaving, setIsSaving] = useState(false);

  const monthLabels = t('common.monthsShort', { returnObjects: true }) as string[];
  const monthFilterLabels = t('common.monthsFull', { returnObjects: true }) as string[];

  async function loadPayments() {
    setIsLoading(true);
    try {
      const { data } = await api.get<{ data: PaymentWithContext[] }>('/api/payments', {
        params: {
          year: year || undefined,
          month: month || undefined,
          method: method || undefined,
          building_id: buildingId || undefined,
        },
      });
      setPayments(data.data);
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
    loadPayments();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [year, month, method, buildingId]);

  function startEdit(payment: PaymentWithContext) {
    setError(null);
    setEditingPayment(payment);
    setEditForm({
      amount: String(payment.amount),
      paid_at: payment.paid_at.slice(0, 10),
      method: payment.method,
      notes: payment.notes ?? '',
    });

    if (payment.batch_id) {
      setEditMonths(payment.periods.map((period) => new Date(period).getMonth() + 1));
      setEditYear(new Date(payment.periods[0]).getFullYear());
    }
  }

  function toggleEditMonth(month: number) {
    setEditMonths((previous) => (previous.includes(month) ? previous.filter((m) => m !== month) : [...previous, month].sort((a, b) => a - b)));
  }

  async function handleSaveEdit() {
    if (!editingPayment) {
      return;
    }

    if (editingPayment.batch_id && editMonths.length === 0) {
      setError(t('cotisations.bulkMonthsNoneSelected'));
      return;
    }

    setError(null);
    setIsSaving(true);

    try {
      if (editingPayment.batch_id) {
        await api.put(`/api/payment-batches/${editingPayment.batch_id}`, {
          months: editMonths,
          year: editYear,
          paid_at: editForm.paid_at,
          method: editForm.method,
          notes: editForm.notes,
        });
      } else {
        await api.put(`/api/fund-calls/${editingPayment.fund_call_id}/payments/${editingPayment.id}`, {
          ...editForm,
          amount: Number(editForm.amount),
        });
      }
      setEditingPayment(null);
      await loadPayments();
    } catch (err) {
      setError(extractErrorMessage(err));
    } finally {
      setIsSaving(false);
    }
  }

  async function handleDelete(payment: PaymentWithContext) {
    if (!confirm(t('payments.confirmDelete'))) {
      return;
    }

    setError(null);

    try {
      if (payment.batch_id) {
        await api.delete(`/api/payment-batches/${payment.batch_id}`);
      } else {
        await api.delete(`/api/fund-calls/${payment.fund_call_id}/payments/${payment.id}`);
      }
      await loadPayments();
    } catch (err) {
      setError(extractErrorMessage(err));
    }
  }

  function receiptUrl(payment: PaymentWithContext): string {
    return payment.batch_id
      ? `${apiUrl}/api/payment-batches/${payment.batch_id}/receipt`
      : `${apiUrl}/api/fund-calls/${payment.fund_call_id}/payments/${payment.id}/receipt`;
  }

  function periodsLabel(payment: PaymentWithContext): string {
    if (payment.is_opening_balance) {
      return t('payments.openingBalanceLabel');
    }

    const labels = payment.periods.map((period) =>
      new Date(period).toLocaleDateString('fr-FR', { month: 'short', year: 'numeric' }),
    );

    if (labels.length === 1) {
      return labels[0];
    }

    return t('payments.periodsRange', { count: labels.length, first: labels[0], last: labels[labels.length - 1] });
  }

  const paymentColumns: DataTableColumn<PaymentWithContext>[] = [
    {
      key: 'id',
      label: t('payments.colId'),
      className: 'whitespace-nowrap font-mono text-xs text-slate-500',
      render: (payment) => `#${payment.id}`,
    },
    {
      key: 'paid_at',
      label: t('payments.colDate'),
      sortable: true,
      className: 'whitespace-nowrap',
      sortValue: (payment) => payment.paid_at,
      render: (payment) => new Date(payment.paid_at).toLocaleDateString('fr-FR'),
    },
    {
      key: 'lot',
      label: t('cotisations.colLot'),
      sortable: true,
      className: 'whitespace-nowrap font-medium text-slate-900',
      sortValue: (payment) => payment.lot.number,
      render: (payment) => payment.lot.number,
    },
    {
      key: 'owner',
      label: t('cotisations.colOwner'),
      sortable: true,
      className: 'whitespace-nowrap',
      sortValue: (payment) => payment.lot.owner_name,
      render: (payment) => payment.lot.owner_name,
    },
    {
      key: 'building',
      label: t('payments.colBuilding'),
      className: 'whitespace-nowrap',
      render: (payment) => payment.lot.building.name,
    },
    {
      key: 'period',
      label: t('payments.colPeriod'),
      className: 'whitespace-nowrap',
      render: (payment) => periodsLabel(payment),
    },
    {
      key: 'amount',
      label: t('payments.colAmount'),
      sortable: true,
      align: 'end',
      className: 'whitespace-nowrap font-medium text-slate-900',
      sortValue: (payment) => payment.amount,
      render: (payment) => `${payment.amount} DH`,
    },
    {
      key: 'method',
      label: t('payments.colMethod'),
      className: 'whitespace-nowrap',
      render: (payment) => t(`common.paymentMethods.${payment.method}`),
    },
    {
      key: 'notes',
      label: t('payments.colNotes'),
      render: (payment) => payment.notes ?? '—',
    },
  ];

  return (
    <div>
      <PageHeader title={t('payments.title')} subtitle={t('payments.subtitle')} />

      <div className="mb-5 flex flex-wrap items-end gap-3">
        <Field label={t('payments.yearLabel')} htmlFor="payment-year-filter">
          <Select id="payment-year-filter" className="w-40" value={year} onChange={(event) => setYear(event.target.value)}>
            <option value="">{t('payments.allYears')}</option>
            {yearOptions.map((y) => (
              <option key={y} value={y}>
                {y}
              </option>
            ))}
          </Select>
        </Field>

        <Field label={t('payments.monthLabel')} htmlFor="payment-month-filter">
          <Select id="payment-month-filter" className="w-40" value={month} onChange={(event) => setMonth(event.target.value)}>
            <option value="">{t('payments.allMonths')}</option>
            {monthFilterLabels.map((label, index) => (
              <option key={label} value={index + 1}>
                {label}
              </option>
            ))}
          </Select>
        </Field>

        <Field label={t('payments.colMethod')} htmlFor="payment-method-filter">
          <Select id="payment-method-filter" className="w-40" value={method} onChange={(event) => setMethod(event.target.value)}>
            <option value="">{t('payments.allMethods')}</option>
            <option value="virement">{t('common.paymentMethods.virement')}</option>
            <option value="especes">{t('common.paymentMethods.especes')}</option>
            <option value="cheque">{t('common.paymentMethods.cheque')}</option>
          </Select>
        </Field>

        {buildings.length > 1 && (
          <Field label={t('payments.buildingLabel')} htmlFor="payment-building-filter">
            <Select id="payment-building-filter" className="w-48" value={buildingId} onChange={(event) => setBuildingId(event.target.value)}>
              <option value="">{t('payments.allBuildings')}</option>
              {buildings.map((building) => (
                <option key={building.id} value={building.id}>
                  {building.name}
                </option>
              ))}
            </Select>
          </Field>
        )}

        {(year || month || method || buildingId) && (
          <Button
            type="button"
            variant="secondary"
            onClick={() => {
              setYear('');
              setMonth('');
              setMethod('');
              setBuildingId('');
            }}
          >
            <X className="size-4" />
            {t('payments.resetFilters')}
          </Button>
        )}
      </div>

      {error && (
        <div className="mb-4">
          <ErrorAlert>{error}</ErrorAlert>
        </div>
      )}

      {editingPayment && (
        <div className="mb-6 flex flex-col gap-4 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
          <div className="flex items-center justify-between">
            <p className="text-sm font-semibold text-slate-900">
              {t('payments.editTitle', { id: editingPayment.id })}
            </p>
            <button type="button" onClick={() => setEditingPayment(null)} className="text-slate-400 hover:text-slate-600">
              <X className="size-4" />
            </button>
          </div>

          {editingPayment.batch_id && (
            <div className="flex flex-col gap-2">
              <span className="text-sm font-medium text-slate-700">{t('payments.editBatchMonthsLabel')}</span>
              <div className="grid grid-cols-2 gap-2 sm:grid-cols-3 md:grid-cols-4">
                {monthLabels.map((label, index) => {
                  const monthNumber = index + 1;
                  const checked = editMonths.includes(monthNumber);

                  return (
                    <label
                      key={monthNumber}
                      className={`flex cursor-pointer items-center gap-2 rounded-lg border px-3 py-2 text-sm ${
                        checked
                          ? 'border-brand-500 bg-brand-50 text-brand-800'
                          : 'border-slate-200 text-slate-700 hover:border-slate-300'
                      }`}
                    >
                      <input
                        type="checkbox"
                        className="size-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500/30"
                        checked={checked}
                        onChange={() => toggleEditMonth(monthNumber)}
                      />
                      <span>{label}</span>
                    </label>
                  );
                })}
              </div>
            </div>
          )}

          <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
            {!editingPayment.batch_id && (
              <Field label={t('payments.colAmount')} htmlFor="edit-payment-amount">
                <Input
                  id="edit-payment-amount"
                  type="number"
                  min={1}
                  value={editForm.amount}
                  onChange={(event) => setEditForm((previous) => ({ ...previous, amount: event.target.value }))}
                />
              </Field>
            )}

            <Field label={t('payments.colDate')} htmlFor="edit-payment-date">
              <Input
                id="edit-payment-date"
                type="date"
                value={editForm.paid_at}
                onChange={(event) => setEditForm((previous) => ({ ...previous, paid_at: event.target.value }))}
              />
            </Field>

            <Field label={t('payments.colMethod')} htmlFor="edit-payment-method">
              <Select
                id="edit-payment-method"
                value={editForm.method}
                onChange={(event) => setEditForm((previous) => ({ ...previous, method: event.target.value as PaymentMethod }))}
              >
                <option value="virement">{t('common.paymentMethods.virement')}</option>
                <option value="especes">{t('common.paymentMethods.especes')}</option>
                <option value="cheque">{t('common.paymentMethods.cheque')}</option>
              </Select>
            </Field>

            <Field label={t('payments.colNotes')} htmlFor="edit-payment-notes">
              <Input
                id="edit-payment-notes"
                value={editForm.notes}
                onChange={(event) => setEditForm((previous) => ({ ...previous, notes: event.target.value }))}
              />
            </Field>
          </div>

          <div className="flex gap-2">
            <Button type="button" isLoading={isSaving} onClick={handleSaveEdit} className="self-start">
              {t('common.save')}
            </Button>
            <Button type="button" variant="secondary" onClick={() => setEditingPayment(null)} className="self-start">
              {t('common.cancel')}
            </Button>
          </div>
        </div>
      )}

      <DataTable
        columns={paymentColumns}
        data={payments}
        rowKey={(payment) => payment.id}
        previousLabel={t('common.previous')}
        nextLabel={t('common.next')}
        rowsPerPageLabel={t('common.rowsPerPage')}
        allRowsLabel={t('common.allRows')}
        isLoading={isLoading}
        loadingText={t('common.loading')}
        emptyState={<EmptyState />}
        searchableText={(payment) =>
          `${payment.lot.number} ${payment.lot.owner_name} ${payment.lot.building.name} ${payment.notes ?? ''}`
        }
        searchPlaceholder={t('common.searchPlaceholder')}
        actions={(payment) => (
          <>
            <a
              href={receiptUrl(payment)}
              target="_blank"
              rel="noopener"
              className="flex size-8 items-center justify-center rounded-lg border border-slate-200 text-slate-500 hover:bg-slate-100"
              title={t('payments.viewReceipt')}
            >
              <FileText className="size-4" />
            </a>
            {isAdmin && (
              <>
                <button
                  onClick={() => startEdit(payment)}
                  className="flex size-8 items-center justify-center rounded-lg border border-slate-200 text-slate-500 hover:bg-slate-100"
                  title={t('common.edit')}
                >
                  <Pencil className="size-4" />
                </button>
                <button
                  onClick={() => handleDelete(payment)}
                  className="flex size-8 items-center justify-center rounded-lg border border-rose-200 text-rose-600 hover:bg-rose-50"
                  title={t('common.delete')}
                >
                  <Trash2 className="size-4" />
                </button>
              </>
            )}
          </>
        )}
      />
    </div>
  );
}

function EmptyState() {
  const { t } = useTranslation();
  return (
    <div className="flex flex-col items-center justify-center rounded-xl border border-dashed border-slate-300 bg-white px-6 py-14 text-center">
      <div className="mb-3 flex size-11 items-center justify-center rounded-full bg-slate-100 text-slate-400">
        <Receipt className="size-5" />
      </div>
      <p className="text-sm font-medium text-slate-900">{t('payments.emptyTitle')}</p>
      <p className="mt-1 text-sm text-slate-500">{t('payments.emptyDesc')}</p>
    </div>
  );
}
