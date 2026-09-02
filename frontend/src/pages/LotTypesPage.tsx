import { useEffect, useState } from 'react';
import type { FormEvent } from 'react';
import { Building2, History, Plus, Trash2, X } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { api } from '../lib/api';
import { extractErrorMessage, useAuth } from '../context/AuthContext';
import type { LotType } from '../types/resources';
import { PageHeader } from '../components/PageHeader';
import { Field, Input } from '../components/ui/Input';
import { Button } from '../components/ui/Button';
import { ErrorAlert } from '../components/ui/Alert';
import { DataTable } from '../components/ui/DataTable';
import type { DataTableColumn } from '../components/ui/DataTable';

function formatDate(date: string): string {
  return new Date(date).toLocaleDateString('fr-FR', { day: '2-digit', month: 'short', year: 'numeric' });
}

export function LotTypesPage() {
  const { user } = useAuth();
  const { t } = useTranslation();
  const isAdmin = user?.role === 'admin';

  const [lotTypes, setLotTypes] = useState<LotType[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const [name, setName] = useState('');
  const [amount, setAmount] = useState('');
  const [isSubmitting, setIsSubmitting] = useState(false);

  const [historyFor, setHistoryFor] = useState<LotType | null>(null);
  const [newRate, setNewRate] = useState({ amount: '', effective_date: '' });
  const [isSubmittingRate, setIsSubmittingRate] = useState(false);

  async function loadLotTypes() {
    setIsLoading(true);
    try {
      const { data } = await api.get<{ data: LotType[] }>('/api/lot-types');
      setLotTypes(data.data);
      if (historyFor) {
        setHistoryFor(data.data.find((lotType) => lotType.id === historyFor.id) ?? null);
      }
    } catch (err) {
      setError(extractErrorMessage(err));
    } finally {
      setIsLoading(false);
    }
  }

  useEffect(() => {
    loadLotTypes();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  async function handleSubmit(event: FormEvent) {
    event.preventDefault();
    setError(null);
    setIsSubmitting(true);

    try {
      await api.post('/api/lot-types', { name, amount: Number(amount) });
      setName('');
      setAmount('');
      await loadLotTypes();
    } catch (err) {
      setError(extractErrorMessage(err));
    } finally {
      setIsSubmitting(false);
    }
  }

  async function handleDelete(id: number) {
    if (!confirm(t('lotTypes.confirmDelete'))) {
      return;
    }

    try {
      await api.delete(`/api/lot-types/${id}`);
      await loadLotTypes();
    } catch (err) {
      setError(extractErrorMessage(err));
    }
  }

  function openHistory(lotType: LotType) {
    setHistoryFor(lotType);
    setNewRate({ amount: '', effective_date: '' });
  }

  async function handleAddRate(event: FormEvent) {
    event.preventDefault();
    if (!historyFor) {
      return;
    }

    setError(null);
    setIsSubmittingRate(true);

    try {
      await api.post(`/api/lot-types/${historyFor.id}/rates`, {
        amount: Number(newRate.amount),
        effective_date: newRate.effective_date,
      });
      setNewRate({ amount: '', effective_date: '' });
      await loadLotTypes();
    } catch (err) {
      setError(extractErrorMessage(err));
    } finally {
      setIsSubmittingRate(false);
    }
  }

  async function handleDeleteRate(rateId: number) {
    if (!historyFor || !confirm(t('lotTypes.confirmDeleteRate'))) {
      return;
    }

    try {
      await api.delete(`/api/lot-types/${historyFor.id}/rates/${rateId}`);
      await loadLotTypes();
    } catch (err) {
      setError(extractErrorMessage(err));
    }
  }

  const lotTypeColumns: DataTableColumn<LotType>[] = [
    {
      key: 'name',
      label: t('lotTypes.nameLabel'),
      sortable: true,
      className: 'font-medium text-slate-900',
      sortValue: (lotType) => lotType.name,
    },
    {
      key: 'current_amount',
      label: t('lotTypes.colCurrentAmount'),
      sortable: true,
      sortValue: (lotType) => lotType.current_amount,
      render: (lotType) => (lotType.current_amount != null ? `${lotType.current_amount} DH` : '—'),
    },
  ];

  return (
    <div>
      <PageHeader title={t('lotTypes.title')} subtitle={t('lotTypes.subtitle')} />

      <div className="flex flex-col gap-6 lg:flex-row">
        {isAdmin && (
          <form
            onSubmit={handleSubmit}
            className="flex h-fit w-full flex-col gap-4 rounded-xl border border-slate-200 bg-white p-5 shadow-sm lg:w-80 lg:shrink-0"
          >
            <p className="text-sm font-semibold text-slate-900">{t('lotTypes.addTitle')}</p>

            <Field label={t('lotTypes.nameLabel')} htmlFor="lot-type-name">
              <Input
                id="lot-type-name"
                placeholder={t('lotTypes.namePlaceholder')}
                value={name}
                onChange={(event) => setName(event.target.value)}
                required
              />
            </Field>

            <Field label={t('lotTypes.amountLabel')} htmlFor="lot-type-amount">
              <Input
                id="lot-type-amount"
                type="number"
                min={0}
                value={amount}
                onChange={(event) => setAmount(event.target.value)}
                required
              />
            </Field>

            <Button type="submit" isLoading={isSubmitting}>
              <Plus className="size-4" /> {t('common.add')}
            </Button>
          </form>
        )}

        <div className="flex-1">
          {error && (
            <div className="mb-4">
              <ErrorAlert>{error}</ErrorAlert>
            </div>
          )}

          {historyFor && (
            <div className="mb-6 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
              <div className="mb-4 flex items-center justify-between">
                <p className="text-sm font-semibold text-slate-900">
                  {t('lotTypes.historyTitle', { name: historyFor.name })}
                </p>
                <button onClick={() => setHistoryFor(null)} className="text-slate-400 hover:text-slate-600">
                  <X className="size-4" />
                </button>
              </div>

              <table className="mb-4 w-full text-start text-sm">
                <thead>
                  <tr className="border-b border-slate-200 text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <th className="py-2">{t('lotTypes.colAmount')}</th>
                    <th className="py-2">{t('lotTypes.colEffectiveDate')}</th>
                    {isAdmin && <th className="py-2" />}
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-100">
                  {historyFor.rates.map((rate) => (
                    <tr key={rate.id}>
                      <td className="py-2.5 font-medium text-slate-900">{rate.amount} DH</td>
                      <td className="py-2.5 text-slate-600">{formatDate(rate.effective_date)}</td>
                      {isAdmin && (
                        <td className="py-2.5 text-end">
                          <button
                            onClick={() => handleDeleteRate(rate.id)}
                            className="text-slate-400 hover:text-rose-600"
                            title={t('common.delete')}
                          >
                            <Trash2 className="size-4" />
                          </button>
                        </td>
                      )}
                    </tr>
                  ))}
                </tbody>
              </table>

              {isAdmin && (
                <form onSubmit={handleAddRate} className="flex flex-wrap items-end gap-3">
                  <Field label={t('lotTypes.newAmountLabel')} htmlFor="new-rate-amount">
                    <Input
                      id="new-rate-amount"
                      type="number"
                      min={0}
                      className="w-40"
                      value={newRate.amount}
                      onChange={(event) => setNewRate((previous) => ({ ...previous, amount: event.target.value }))}
                      required
                    />
                  </Field>
                  <Field label={t('lotTypes.newDateLabel')} htmlFor="new-rate-date">
                    <Input
                      id="new-rate-date"
                      type="date"
                      className="w-44"
                      value={newRate.effective_date}
                      onChange={(event) =>
                        setNewRate((previous) => ({ ...previous, effective_date: event.target.value }))
                      }
                      required
                    />
                  </Field>
                  <Button type="submit" size="sm" isLoading={isSubmittingRate}>
                    {t('common.add')}
                  </Button>
                </form>
              )}
              <p className="mt-3 text-xs text-slate-500">{t('lotTypes.historyNote')}</p>
            </div>
          )}

          <DataTable
            columns={lotTypeColumns}
            data={lotTypes}
            rowKey={(lotType) => lotType.id}
            previousLabel={t('common.previous')}
            nextLabel={t('common.next')}
            rowsPerPageLabel={t('common.rowsPerPage')}
            allRowsLabel={t('common.allRows')}
            isLoading={isLoading}
            loadingText={t('common.loading')}
            emptyState={<EmptyState />}
            searchableText={(lotType) => lotType.name}
            searchPlaceholder={t('common.searchPlaceholder')}
            actions={(lotType) => (
              <>
                <Button size="sm" variant="secondary" onClick={() => openHistory(lotType)}>
                  <History className="size-3.5" /> {t('lotTypes.historyButton')}
                </Button>
                {isAdmin && (
                  <Button size="sm" variant="danger" onClick={() => handleDelete(lotType.id)}>
                    <Trash2 className="size-3.5" />
                  </Button>
                )}
              </>
            )}
          />
        </div>
      </div>
    </div>
  );
}

function EmptyState() {
  const { t } = useTranslation();
  return (
    <div className="flex flex-col items-center justify-center rounded-xl border border-dashed border-slate-300 bg-white px-6 py-14 text-center">
      <div className="mb-3 flex size-11 items-center justify-center rounded-full bg-slate-100 text-slate-400">
        <Building2 className="size-5" />
      </div>
      <p className="text-sm font-medium text-slate-900">{t('lotTypes.emptyTitle')}</p>
      <p className="mt-1 text-sm text-slate-500">{t('lotTypes.emptyDesc')}</p>
    </div>
  );
}
