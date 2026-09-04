import { useEffect, useRef, useState } from 'react';
import type { FormEvent } from 'react';
import { Layers, Pencil, Plus, Trash2, X } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { api } from '../lib/api';
import { extractErrorMessage, useAuth } from '../context/AuthContext';
import type { Building } from '../types/resources';
import { PageHeader } from '../components/PageHeader';
import { Field, Input } from '../components/ui/Input';
import { Button } from '../components/ui/Button';
import { ErrorAlert } from '../components/ui/Alert';
import { DataTable } from '../components/ui/DataTable';
import type { DataTableColumn } from '../components/ui/DataTable';

export function BuildingsPage() {
  const { user } = useAuth();
  const { t } = useTranslation();
  const isAdmin = user?.role === 'admin';

  const [buildings, setBuildings] = useState<Building[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const [name, setName] = useState('');
  const [editingId, setEditingId] = useState<number | null>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const formRef = useRef<HTMLFormElement>(null);
  const nameFieldRef = useRef<HTMLInputElement>(null);

  async function loadBuildings() {
    setIsLoading(true);
    try {
      const { data } = await api.get<{ data: Building[] }>('/api/buildings');
      setBuildings(data.data);
    } catch (err) {
      setError(extractErrorMessage(err));
    } finally {
      setIsLoading(false);
    }
  }

  useEffect(() => {
    loadBuildings();
  }, []);

  function startEdit(building: Building) {
    setEditingId(building.id);
    setName(building.name);
    formRef.current?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    nameFieldRef.current?.focus();
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
        await api.put(`/api/buildings/${editingId}`, { name });
      } else {
        await api.post('/api/buildings', { name });
      }

      resetForm();
      await loadBuildings();
    } catch (err) {
      setError(extractErrorMessage(err));
    } finally {
      setIsSubmitting(false);
    }
  }

  async function handleDelete(id: number) {
    if (!confirm(t('buildings.confirmDelete'))) {
      return;
    }

    try {
      await api.delete(`/api/buildings/${id}`);
      await loadBuildings();
    } catch (err) {
      setError(extractErrorMessage(err));
    }
  }

  const buildingColumns: DataTableColumn<Building>[] = [
    {
      key: 'name',
      label: t('buildings.colName'),
      sortable: true,
      className: 'font-medium text-slate-900',
      sortValue: (building) => building.name,
    },
    {
      key: 'lots_count',
      label: t('buildings.colApartments'),
      sortable: true,
      sortValue: (building) => building.lots_count ?? 0,
      render: (building) => building.lots_count ?? 0,
    },
  ];

  return (
    <div>
      <PageHeader title={t('buildings.title')} subtitle={t('buildings.subtitle')} />

      <div className="flex flex-col gap-6 lg:flex-row">
        {isAdmin && (
          <form
            ref={formRef}
            onSubmit={handleSubmit}
            className="flex h-fit w-full flex-col gap-4 rounded-xl border border-slate-200 bg-white p-5 shadow-sm lg:w-80 lg:shrink-0"
          >
            <p className="text-sm font-semibold text-slate-900">
              {editingId ? t('buildings.editTitle') : t('buildings.addTitle')}
            </p>

            <Field label={t('buildings.nameLabel')} htmlFor="building-name">
              <Input
                id="building-name"
                ref={nameFieldRef}
                placeholder={t('buildings.namePlaceholder')}
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

          <DataTable
            columns={buildingColumns}
            data={buildings}
            rowKey={(building) => building.id}
            previousLabel={t('common.previous')}
            nextLabel={t('common.next')}
            rowsPerPageLabel={t('common.rowsPerPage')}
            allRowsLabel={t('common.allRows')}
            isLoading={isLoading}
            loadingText={t('common.loading')}
            emptyState={<EmptyState />}
            searchableText={(building) => building.name}
            searchPlaceholder={t('common.searchPlaceholder')}
            actions={
              isAdmin
                ? (building) => (
                    <>
                      <IconButton onClick={() => startEdit(building)} label={t('common.edit')}>
                        <Pencil className="size-4" />
                      </IconButton>
                      <IconButton onClick={() => handleDelete(building.id)} label={t('common.delete')} danger>
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
        <Layers className="size-5" />
      </div>
      <p className="text-sm font-medium text-slate-900">{t('buildings.emptyTitle')}</p>
      <p className="mt-1 text-sm text-slate-500">{t('buildings.emptyDesc')}</p>
    </div>
  );
}
