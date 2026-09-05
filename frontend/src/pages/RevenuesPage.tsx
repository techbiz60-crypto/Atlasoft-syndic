import { useEffect, useMemo, useState } from 'react';
import type { FormEvent } from 'react';
import { FileText, Paperclip, Settings, Trash2, TrendingUp } from 'lucide-react';
import { Link } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { api } from '../lib/api';
import { canManageFinances, extractErrorMessage, useAuth } from '../context/AuthContext';
import type { PaymentMethod, Revenue, RevenueCategory } from '../types/resources';
import { PageHeader } from '../components/PageHeader';
import { Field, Input, Select } from '../components/ui/Input';
import { Button } from '../components/ui/Button';
import { ErrorAlert } from '../components/ui/Alert';
import { DataTable } from '../components/ui/DataTable';
import type { DataTableColumn } from '../components/ui/DataTable';

const now = new Date();
const yearOptions = [now.getFullYear() - 1, now.getFullYear(), now.getFullYear() + 1];

const apiUrl = import.meta.env.VITE_API_URL ?? 'http://localhost:8081';

export function RevenuesPage() {
  const { user } = useAuth();
  const { t } = useTranslation();
  const isAdmin = canManageFinances(user);
  const monthLabels = t('common.monthsFull', { returnObjects: true }) as string[];

  const [revenues, setRevenues] = useState<Revenue[]>([]);
  const [categories, setCategories] = useState<RevenueCategory[]>([]);
  const [year, setYear] = useState(now.getFullYear());
  const [month, setMonth] = useState<number | 'all'>(now.getMonth() + 1);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);

  const emptyForm = {
    revenue_category_id: '',
    method: 'especes' as PaymentMethod,
    received_at: new Date().toISOString().slice(0, 10),
    label: '',
    amount: '',
  };
  const [form, setForm] = useState(emptyForm);
  const [receiptFile, setReceiptFile] = useState<File | null>(null);

  useEffect(() => {
    api.get<{ data: RevenueCategory[] }>('/api/revenue-categories').then(({ data }) => {
      setCategories(data.data);
      setForm((previous) => ({ ...previous, revenue_category_id: String(data.data[0]?.id ?? '') }));
    });
  }, []);

  async function loadRevenues() {
    setIsLoading(true);
    try {
      const { data } = await api.get<{ data: Revenue[] }>('/api/revenues', {
        params: { year, ...(month !== 'all' ? { month } : {}) },
      });
      setRevenues(data.data);
    } catch (err) {
      setError(extractErrorMessage(err));
    } finally {
      setIsLoading(false);
    }
  }

  useEffect(() => {
    loadRevenues();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [year, month]);

  const total = useMemo(() => revenues.reduce((sum, revenue) => sum + revenue.amount, 0), [revenues]);

  async function handleSubmit(event: FormEvent) {
    event.preventDefault();
    setError(null);
    setIsSubmitting(true);

    try {
      const payload = new FormData();
      payload.append('revenue_category_id', form.revenue_category_id);
      payload.append('method', form.method);
      payload.append('received_at', form.received_at);
      payload.append('label', form.label);
      payload.append('amount', form.amount);
      if (receiptFile) {
        payload.append('receipt', receiptFile);
      }

      await api.post('/api/revenues', payload);
      setForm({ ...emptyForm, revenue_category_id: form.revenue_category_id });
      setReceiptFile(null);
      await loadRevenues();
    } catch (err) {
      setError(extractErrorMessage(err));
    } finally {
      setIsSubmitting(false);
    }
  }

  async function handleDelete(id: number) {
    if (!confirm(t('revenues.confirmDelete'))) {
      return;
    }

    try {
      await api.delete(`/api/revenues/${id}`);
      await loadRevenues();
    } catch (err) {
      setError(extractErrorMessage(err));
    }
  }

  const revenueColumns: DataTableColumn<Revenue>[] = [
    {
      key: 'method',
      label: t('revenues.colMethod'),
      className: 'uppercase',
      render: (revenue) => revenue.method,
    },
    {
      key: 'category',
      label: t('revenues.colCategory'),
      sortable: true,
      sortValue: (revenue) => revenue.category.name,
      render: (revenue) => (
        <span className="rounded-full bg-brand-50 px-2.5 py-1 text-xs font-medium text-brand-700">
          {revenue.category.name}
        </span>
      ),
    },
    {
      key: 'received_at',
      label: t('revenues.colDate'),
      sortable: true,
      sortValue: (revenue) => revenue.received_at,
      render: (revenue) => new Date(revenue.received_at).toLocaleDateString('fr-FR'),
    },
    {
      key: 'label',
      label: t('revenues.colLabel'),
      render: (revenue) => revenue.label ?? '—',
    },
    {
      key: 'amount',
      label: t('revenues.colAmount'),
      sortable: true,
      align: 'end',
      className: 'font-medium text-slate-900',
      sortValue: (revenue) => revenue.amount,
      render: (revenue) => `${revenue.amount} DH`,
    },
  ];

  return (
    <div>
      <PageHeader
        title={t('revenues.title')}
        subtitle={
          month === 'all'
            ? t('revenues.subtitleTotalAllMonths', { year, total })
            : t('revenues.subtitleTotal', { month, year, total })
        }
        action={
          <Link
            to="/recettes/categories"
            className="inline-flex items-center gap-2 rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50"
          >
            <Settings className="size-4" />
            {t('revenues.manageCategories')}
          </Link>
        }
      />

      {error && (
        <div className="mb-4">
          <ErrorAlert>{error}</ErrorAlert>
        </div>
      )}

      {isAdmin && (
        <form onSubmit={handleSubmit} className="mb-6 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
          <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <Field label={t('revenues.methodLabel')} htmlFor="revenue-method">
              <Select
                id="revenue-method"
                value={form.method}
                onChange={(event) => setForm((previous) => ({ ...previous, method: event.target.value as PaymentMethod }))}
              >
                <option value="especes">{t('common.paymentMethods.especes')}</option>
                <option value="virement">{t('common.paymentMethods.virement')}</option>
                <option value="cheque">{t('common.paymentMethods.cheque')}</option>
              </Select>
            </Field>

            <Field label={t('revenues.categoryLabel')} htmlFor="revenue-category">
              <Select
                id="revenue-category"
                value={form.revenue_category_id}
                onChange={(event) => setForm((previous) => ({ ...previous, revenue_category_id: event.target.value }))}
                required
              >
                <option value="" disabled>
                  {t('revenues.chooseCategory')}
                </option>
                {categories.map((category) => (
                  <option key={category.id} value={category.id}>
                    {category.name}
                  </option>
                ))}
              </Select>
            </Field>

            <Field label={t('revenues.dateLabel')} htmlFor="revenue-date">
              <Input
                id="revenue-date"
                type="date"
                value={form.received_at}
                onChange={(event) => setForm((previous) => ({ ...previous, received_at: event.target.value }))}
                required
              />
            </Field>

            <Field label={t('revenues.labelLabel')} htmlFor="revenue-label">
              <Input
                id="revenue-label"
                placeholder={t('revenues.labelPlaceholder')}
                value={form.label}
                onChange={(event) => setForm((previous) => ({ ...previous, label: event.target.value }))}
              />
            </Field>

            <Field label={t('revenues.amountLabel')} htmlFor="revenue-amount">
              <Input
                id="revenue-amount"
                type="number"
                min={1}
                value={form.amount}
                onChange={(event) => setForm((previous) => ({ ...previous, amount: event.target.value }))}
                required
              />
            </Field>

            <Field label={t('revenues.receiptLabel')} htmlFor="revenue-receipt">
              <label
                htmlFor="revenue-receipt"
                className="flex cursor-pointer items-center gap-2 rounded-lg border border-slate-300 bg-white px-3.5 py-2.5 text-sm text-slate-600 hover:bg-slate-50"
              >
                <Paperclip className="size-4" />
                {receiptFile ? receiptFile.name : t('revenues.receiptChoose')}
              </label>
              <input
                id="revenue-receipt"
                type="file"
                accept=".pdf,.jpg,.jpeg,.png"
                className="hidden"
                onChange={(event) => setReceiptFile(event.target.files?.[0] ?? null)}
              />
            </Field>
          </div>

          <Button type="submit" isLoading={isSubmitting} className="mt-4">
            {t('revenues.submitButton')}
          </Button>
        </form>
      )}

      <div className="mb-5 flex flex-wrap items-end gap-3">
        <Field label={t('revenues.yearLabel')} htmlFor="revenue-year-filter">
          <Select id="revenue-year-filter" className="w-32" value={year} onChange={(event) => setYear(Number(event.target.value))}>
            {yearOptions.map((y) => (
              <option key={y} value={y}>
                {y}
              </option>
            ))}
          </Select>
        </Field>

        <Field label={t('revenues.monthLabel')} htmlFor="revenue-month-filter">
          <Select
            id="revenue-month-filter"
            className="w-40"
            value={month}
            onChange={(event) => setMonth(event.target.value === 'all' ? 'all' : Number(event.target.value))}
          >
            <option value="all">{t('revenues.allMonths')}</option>
            {monthLabels.map((label, index) => (
              <option key={label} value={index + 1}>
                {label}
              </option>
            ))}
          </Select>
        </Field>
      </div>

      <DataTable
        columns={revenueColumns}
        data={revenues}
        rowKey={(revenue) => revenue.id}
        previousLabel={t('common.previous')}
        nextLabel={t('common.next')}
        rowsPerPageLabel={t('common.rowsPerPage')}
        allRowsLabel={t('common.allRows')}
        isLoading={isLoading}
        loadingText={t('common.loading')}
        emptyState={<EmptyState />}
        searchableText={(revenue) => `${revenue.category.name} ${revenue.label ?? ''}`}
        searchPlaceholder={t('common.searchPlaceholder')}
        actions={(revenue) => (
          <>
            {revenue.has_receipt && (
              <a
                href={`${apiUrl}/api/revenues/${revenue.id}/receipt`}
                target="_blank"
                rel="noopener"
                className="flex size-8 items-center justify-center rounded-lg border border-slate-200 text-slate-500 hover:bg-slate-100"
                title={t('revenues.viewReceipt')}
              >
                <FileText className="size-4" />
              </a>
            )}
            {isAdmin && (
              <button
                onClick={() => handleDelete(revenue.id)}
                className="flex size-8 items-center justify-center rounded-lg border border-rose-200 text-rose-600 hover:bg-rose-50"
                title={t('common.delete')}
              >
                <Trash2 className="size-4" />
              </button>
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
        <TrendingUp className="size-5" />
      </div>
      <p className="text-sm font-medium text-slate-900">{t('revenues.emptyTitle')}</p>
      <p className="mt-1 text-sm text-slate-500">{t('revenues.emptyDesc')}</p>
    </div>
  );
}
