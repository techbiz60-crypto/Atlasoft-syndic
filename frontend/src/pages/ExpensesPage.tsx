import { useEffect, useMemo, useRef, useState } from 'react';
import type { FormEvent } from 'react';
import { FileText, Paperclip, Pencil, Receipt, Settings, Trash2 } from 'lucide-react';
import { Link } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { api } from '../lib/api';
import { canManageFinances, extractErrorMessage, useAuth } from '../context/AuthContext';
import type { Expense, ExpenseCategory, PaymentMethod } from '../types/resources';
import { PageHeader } from '../components/PageHeader';
import { Field, Input, Select } from '../components/ui/Input';
import { Button } from '../components/ui/Button';
import { ErrorAlert } from '../components/ui/Alert';
import { DataTable } from '../components/ui/DataTable';
import type { DataTableColumn } from '../components/ui/DataTable';

const now = new Date();
const yearOptions = [now.getFullYear() - 1, now.getFullYear(), now.getFullYear() + 1];

const apiUrl = import.meta.env.VITE_API_URL ?? 'http://localhost:8081';

export function ExpensesPage() {
  const { user } = useAuth();
  const { t } = useTranslation();
  const isAdmin = canManageFinances(user);
  const monthLabels = t('common.monthsFull', { returnObjects: true }) as string[];

  const [expenses, setExpenses] = useState<Expense[]>([]);
  const [categories, setCategories] = useState<ExpenseCategory[]>([]);
  const [year, setYear] = useState(now.getFullYear());
  const [month, setMonth] = useState<number | 'all'>(now.getMonth() + 1);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);

  const emptyForm = {
    expense_category_id: '',
    method: 'especes' as PaymentMethod,
    paid_at: new Date().toISOString().slice(0, 10),
    label: '',
    amount: '',
  };
  const [form, setForm] = useState(emptyForm);
  const [receiptFile, setReceiptFile] = useState<File | null>(null);
  const [editingId, setEditingId] = useState<number | null>(null);
  const formRef = useRef<HTMLFormElement>(null);
  const firstFieldRef = useRef<HTMLSelectElement>(null);

  useEffect(() => {
    api.get<{ data: ExpenseCategory[] }>('/api/expense-categories').then(({ data }) => {
      setCategories(data.data);
      setForm((previous) => ({ ...previous, expense_category_id: String(data.data[0]?.id ?? '') }));
    });
  }, []);

  async function loadExpenses() {
    setIsLoading(true);
    try {
      const { data } = await api.get<{ data: Expense[] }>('/api/expenses', {
        params: { year, ...(month !== 'all' ? { month } : {}) },
      });
      setExpenses(data.data);
    } catch (err) {
      setError(extractErrorMessage(err));
    } finally {
      setIsLoading(false);
    }
  }

  useEffect(() => {
    loadExpenses();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [year, month]);

  const total = useMemo(() => expenses.reduce((sum, expense) => sum + expense.amount, 0), [expenses]);

  async function handleSubmit(event: FormEvent) {
    event.preventDefault();
    setError(null);
    setIsSubmitting(true);

    try {
      const payload = new FormData();
      payload.append('expense_category_id', form.expense_category_id);
      payload.append('method', form.method);
      payload.append('paid_at', form.paid_at);
      payload.append('label', form.label);
      payload.append('amount', form.amount);
      if (receiptFile) {
        payload.append('receipt', receiptFile);
      }

      if (editingId) {
        payload.append('_method', 'PUT');
        await api.post(`/api/expenses/${editingId}`, payload);
      } else {
        await api.post('/api/expenses', payload);
      }

      cancelEdit();
      await loadExpenses();
    } catch (err) {
      setError(extractErrorMessage(err));
    } finally {
      setIsSubmitting(false);
    }
  }

  function startEdit(expense: Expense) {
    setEditingId(expense.id);
    setForm({
      expense_category_id: String(expense.expense_category_id),
      method: expense.method,
      paid_at: expense.paid_at.slice(0, 10),
      label: expense.label ?? '',
      amount: String(expense.amount),
    });
    setReceiptFile(null);
    formRef.current?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    firstFieldRef.current?.focus();
  }

  function cancelEdit() {
    setEditingId(null);
    setForm({ ...emptyForm, expense_category_id: form.expense_category_id });
    setReceiptFile(null);
  }

  async function handleDelete(id: number) {
    if (!confirm(t('expenses.confirmDelete'))) {
      return;
    }

    try {
      await api.delete(`/api/expenses/${id}`);
      await loadExpenses();
    } catch (err) {
      setError(extractErrorMessage(err));
    }
  }

  const expenseColumns: DataTableColumn<Expense>[] = [
    {
      key: 'method',
      label: t('expenses.colMethod'),
      className: 'uppercase',
      render: (expense) => expense.method,
    },
    {
      key: 'category',
      label: t('expenses.colCategory'),
      sortable: true,
      sortValue: (expense) => expense.category.name,
      render: (expense) => (
        <span className="rounded-full bg-brand-50 px-2.5 py-1 text-xs font-medium text-brand-700">
          {expense.category.name}
        </span>
      ),
    },
    {
      key: 'paid_at',
      label: t('expenses.colDate'),
      sortable: true,
      sortValue: (expense) => expense.paid_at,
      render: (expense) => new Date(expense.paid_at).toLocaleDateString('fr-FR'),
    },
    {
      key: 'label',
      label: t('expenses.colLabel'),
      render: (expense) => expense.label ?? '—',
    },
    {
      key: 'amount',
      label: t('expenses.colAmount'),
      sortable: true,
      align: 'end',
      className: 'font-medium text-slate-900',
      sortValue: (expense) => expense.amount,
      render: (expense) => `${expense.amount} DH`,
    },
  ];

  return (
    <div>
      <PageHeader
        title={t('expenses.title')}
        subtitle={
          month === 'all'
            ? t('expenses.subtitleTotalAllMonths', { year, total })
            : t('expenses.subtitleTotal', { month, year, total })
        }
        action={
          <Link
            to="/depenses/categories"
            className="inline-flex items-center gap-2 rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50"
          >
            <Settings className="size-4" />
            {t('expenses.manageCategories')}
          </Link>
        }
      />

      {error && (
        <div className="mb-4">
          <ErrorAlert>{error}</ErrorAlert>
        </div>
      )}

      {isAdmin && (
        <form ref={formRef} onSubmit={handleSubmit} className="mb-6 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
          <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <Field label={t('expenses.methodLabel')} htmlFor="expense-method">
              <Select
                id="expense-method"
                ref={firstFieldRef}
                value={form.method}
                onChange={(event) => setForm((previous) => ({ ...previous, method: event.target.value as PaymentMethod }))}
              >
                <option value="especes">{t('common.paymentMethods.especes')}</option>
                <option value="virement">{t('common.paymentMethods.virement')}</option>
                <option value="cheque">{t('common.paymentMethods.cheque')}</option>
              </Select>
            </Field>

            <Field label={t('expenses.categoryLabel')} htmlFor="expense-category">
              <Select
                id="expense-category"
                value={form.expense_category_id}
                onChange={(event) => setForm((previous) => ({ ...previous, expense_category_id: event.target.value }))}
                required
              >
                <option value="" disabled>
                  {t('expenses.chooseCategory')}
                </option>
                {categories.map((category) => (
                  <option key={category.id} value={category.id}>
                    {category.name}
                  </option>
                ))}
              </Select>
            </Field>

            <Field label={t('expenses.dateLabel')} htmlFor="expense-date">
              <Input
                id="expense-date"
                type="date"
                value={form.paid_at}
                onChange={(event) => setForm((previous) => ({ ...previous, paid_at: event.target.value }))}
                required
              />
            </Field>

            <Field label={t('expenses.labelLabel')} htmlFor="expense-label">
              <Input
                id="expense-label"
                value={form.label}
                onChange={(event) => setForm((previous) => ({ ...previous, label: event.target.value }))}
              />
            </Field>

            <Field label={t('expenses.amountLabel')} htmlFor="expense-amount">
              <Input
                id="expense-amount"
                type="number"
                min={0.01}
                step={0.01}
                value={form.amount}
                onChange={(event) => setForm((previous) => ({ ...previous, amount: event.target.value }))}
                required
              />
            </Field>

            <Field label={t('expenses.receiptLabel')} htmlFor="expense-receipt">
              <label
                htmlFor="expense-receipt"
                className="flex cursor-pointer items-center gap-2 rounded-lg border border-slate-300 bg-white px-3.5 py-2.5 text-sm text-slate-600 hover:bg-slate-50"
              >
                <Paperclip className="size-4" />
                {receiptFile ? receiptFile.name : t('expenses.receiptChoose')}
              </label>
              <input
                id="expense-receipt"
                type="file"
                accept=".pdf,.jpg,.jpeg,.png"
                className="hidden"
                onChange={(event) => setReceiptFile(event.target.files?.[0] ?? null)}
              />
            </Field>
          </div>

          <div className="mt-4 flex items-center gap-3">
            <Button type="submit" isLoading={isSubmitting}>
              {editingId ? t('expenses.updateButton') : t('expenses.submitButton')}
            </Button>
            {editingId && (
              <Button type="button" variant="secondary" onClick={cancelEdit}>
                {t('common.cancel')}
              </Button>
            )}
          </div>
        </form>
      )}

      <div className="mb-5 flex flex-wrap items-end gap-3">
        <Field label={t('expenses.yearLabel')} htmlFor="expense-year-filter">
          <Select id="expense-year-filter" className="w-32" value={year} onChange={(event) => setYear(Number(event.target.value))}>
            {yearOptions.map((y) => (
              <option key={y} value={y}>
                {y}
              </option>
            ))}
          </Select>
        </Field>

        <Field label={t('expenses.monthLabel')} htmlFor="expense-month-filter">
          <Select
            id="expense-month-filter"
            className="w-40"
            value={month}
            onChange={(event) => setMonth(event.target.value === 'all' ? 'all' : Number(event.target.value))}
          >
            <option value="all">{t('expenses.allMonths')}</option>
            {monthLabels.map((label, index) => (
              <option key={label} value={index + 1}>
                {label}
              </option>
            ))}
          </Select>
        </Field>
      </div>

      <DataTable
        columns={expenseColumns}
        data={expenses}
        rowKey={(expense) => expense.id}
        previousLabel={t('common.previous')}
        nextLabel={t('common.next')}
        rowsPerPageLabel={t('common.rowsPerPage')}
        allRowsLabel={t('common.allRows')}
        isLoading={isLoading}
        loadingText={t('common.loading')}
        emptyState={<EmptyState />}
        searchableText={(expense) => `${expense.category.name} ${expense.label ?? ''}`}
        searchPlaceholder={t('common.searchPlaceholder')}
        actions={(expense) => (
          <>
            {expense.has_receipt && (
              <a
                href={`${apiUrl}/api/expenses/${expense.id}/receipt`}
                target="_blank"
                rel="noopener"
                className="flex size-8 items-center justify-center rounded-lg border border-slate-200 text-slate-500 hover:bg-slate-100"
                title={t('expenses.viewReceipt')}
              >
                <FileText className="size-4" />
              </a>
            )}
            {isAdmin && (
              <button
                onClick={() => startEdit(expense)}
                className="flex size-8 items-center justify-center rounded-lg border border-slate-200 text-slate-500 hover:bg-slate-100"
                title={t('common.edit')}
              >
                <Pencil className="size-4" />
              </button>
            )}
            {isAdmin && (
              <button
                onClick={() => handleDelete(expense.id)}
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
        <Receipt className="size-5" />
      </div>
      <p className="text-sm font-medium text-slate-900">{t('expenses.emptyTitle')}</p>
      <p className="mt-1 text-sm text-slate-500">{t('expenses.emptyDesc')}</p>
    </div>
  );
}
