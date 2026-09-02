import { useEffect, useState } from 'react';
import type { FormEvent } from 'react';
import { Pencil, Plus, Tag, Trash2, X } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { api } from '../lib/api';
import { canManageFinances, extractErrorMessage, useAuth } from '../context/AuthContext';
import type { ExpenseCategory } from '../types/resources';
import { PageHeader } from '../components/PageHeader';
import { Field, Input } from '../components/ui/Input';
import { Button } from '../components/ui/Button';
import { ErrorAlert } from '../components/ui/Alert';

export function ExpenseCategoriesPage() {
  const { user } = useAuth();
  const { t } = useTranslation();
  const isAdmin = canManageFinances(user);

  const [categories, setCategories] = useState<ExpenseCategory[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const [name, setName] = useState('');
  const [editingId, setEditingId] = useState<number | null>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);

  async function loadCategories() {
    setIsLoading(true);
    try {
      const { data } = await api.get<{ data: ExpenseCategory[] }>('/api/expense-categories');
      setCategories(data.data);
    } catch (err) {
      setError(extractErrorMessage(err));
    } finally {
      setIsLoading(false);
    }
  }

  useEffect(() => {
    loadCategories();
  }, []);

  function startEdit(category: ExpenseCategory) {
    setEditingId(category.id);
    setName(category.name);
  }

  function resetForm() {
    setEditingId(null);
    setName('');
  }

  async function handleSubmit(event: FormEvent) {
    event.preventDefault();
    setError(null);
    setIsSubmitting(true);

    try {
      if (editingId) {
        await api.put(`/api/expense-categories/${editingId}`, { name });
      } else {
        await api.post('/api/expense-categories', { name });
      }

      resetForm();
      await loadCategories();
    } catch (err) {
      setError(extractErrorMessage(err));
    } finally {
      setIsSubmitting(false);
    }
  }

  async function handleDelete(id: number) {
    if (!confirm(t('expenseCategories.confirmDelete'))) {
      return;
    }

    try {
      await api.delete(`/api/expense-categories/${id}`);
      await loadCategories();
    } catch (err) {
      setError(extractErrorMessage(err));
    }
  }

  return (
    <div>
      <PageHeader title={t('expenseCategories.title')} subtitle={t('expenseCategories.subtitle')} />

      <div className="flex flex-col gap-6 lg:flex-row">
        {isAdmin && (
          <form
            onSubmit={handleSubmit}
            className="flex h-fit w-full flex-col gap-4 rounded-xl border border-slate-200 bg-white p-5 shadow-sm lg:w-80 lg:shrink-0"
          >
            <p className="text-sm font-semibold text-slate-900">
              {editingId ? t('expenseCategories.editTitle') : t('expenseCategories.addTitle')}
            </p>

            <Field label={t('expenseCategories.nameLabel')} htmlFor="category-name">
              <Input
                id="category-name"
                placeholder={t('expenseCategories.namePlaceholder')}
                value={name}
                onChange={(event) => setName(event.target.value)}
                required
              />
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

          {isLoading ? (
            <p className="text-sm text-slate-500">{t('common.loading')}</p>
          ) : categories.length === 0 ? (
            <EmptyState />
          ) : (
            <div className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
              <table className="w-full text-start text-sm">
                <thead>
                  <tr className="border-b border-slate-200 bg-slate-50 text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <th className="px-5 py-3">{t('expenseCategories.colName')}</th>
                    <th className="px-5 py-3">{t('expenseCategories.colExpensesCount')}</th>
                    {isAdmin && <th className="px-5 py-3" />}
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-100">
                  {categories.map((category) => (
                    <tr key={category.id} className="hover:bg-slate-50/60">
                      <td className="px-5 py-3.5 font-medium text-slate-900">{category.name}</td>
                      <td className="px-5 py-3.5 text-slate-600">{category.expenses_count ?? 0}</td>
                      {isAdmin && (
                        <td className="px-5 py-3.5">
                          <div className="flex justify-end gap-1.5">
                            <IconButton onClick={() => startEdit(category)} label={t('common.edit')}>
                              <Pencil className="size-4" />
                            </IconButton>
                            <IconButton onClick={() => handleDelete(category.id)} label={t('common.delete')} danger>
                              <Trash2 className="size-4" />
                            </IconButton>
                          </div>
                        </td>
                      )}
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </div>
      </div>
    </div>
  );
}

function IconButton({
  children,
  onClick,
  label,
  danger = false,
}: {
  children: React.ReactNode;
  onClick: () => void;
  label: string;
  danger?: boolean;
}) {
  return (
    <button
      onClick={onClick}
      aria-label={label}
      title={label}
      className={`flex size-8 items-center justify-center rounded-lg border transition-colors ${
        danger
          ? 'border-rose-200 text-rose-600 hover:bg-rose-50'
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
        <Tag className="size-5" />
      </div>
      <p className="text-sm font-medium text-slate-900">{t('expenseCategories.emptyTitle')}</p>
      <p className="mt-1 text-sm text-slate-500">{t('expenseCategories.emptyDesc')}</p>
    </div>
  );
}
