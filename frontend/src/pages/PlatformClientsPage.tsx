import { useEffect, useState } from 'react';
import type { FormEvent } from 'react';
import { CheckCircle2, PauseCircle, X } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { api } from '../lib/api';
import { extractErrorMessage } from '../context/AuthContext';
import type { PlatformResidence, SubscriptionStatus } from '../types/auth';
import { PageHeader } from '../components/PageHeader';
import { Field, Input, Select } from '../components/ui/Input';
import { Button } from '../components/ui/Button';
import { ErrorAlert, SuccessAlert } from '../components/ui/Alert';
import { DataTable } from '../components/ui/DataTable';
import type { DataTableColumn } from '../components/ui/DataTable';

const statusClasses: Record<SubscriptionStatus, string> = {
  free: 'bg-slate-100 text-slate-700',
  trial: 'bg-amber-100 text-amber-800',
  active: 'bg-brand-100 text-brand-800',
  expired: 'bg-rose-100 text-rose-700',
};

export function PlatformClientsPage() {
  const { t } = useTranslation();
  const [residences, setResidences] = useState<PlatformResidence[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState<string | null>(null);

  const [activatingFor, setActivatingFor] = useState<PlatformResidence | null>(null);
  const [activateForm, setActivateForm] = useState({ cycle: 'monthly', plan: '', amount: '' });
  const [isSubmitting, setIsSubmitting] = useState(false);

  async function loadResidences() {
    setIsLoading(true);
    try {
      const { data } = await api.get<{ data: PlatformResidence[] }>('/api/platform/residences');
      setResidences(data.data);
    } catch (err) {
      setError(extractErrorMessage(err));
    } finally {
      setIsLoading(false);
    }
  }

  useEffect(() => {
    loadResidences();
  }, []);

  function openActivate(residence: PlatformResidence) {
    setError(null);
    setSuccess(null);
    setActivatingFor(residence);
    setActivateForm({ cycle: 'monthly', plan: '', amount: '' });
  }

  async function handleActivate(event: FormEvent) {
    event.preventDefault();
    if (!activatingFor) {
      return;
    }

    setError(null);
    setIsSubmitting(true);

    try {
      await api.post(`/api/platform/residences/${activatingFor.residence_id}/activate`, {
        cycle: activateForm.cycle,
        plan: activateForm.plan || undefined,
        amount: activateForm.amount ? Number(activateForm.amount) : undefined,
      });
      setSuccess(t('platform.activateSuccess', { name: activatingFor.residence_name }));
      setActivatingFor(null);
      await loadResidences();
    } catch (err) {
      setError(extractErrorMessage(err));
    } finally {
      setIsSubmitting(false);
    }
  }

  async function handleDeactivate(residence: PlatformResidence) {
    if (!confirm(t('platform.confirmDeactivate', { name: residence.residence_name }))) {
      return;
    }

    setError(null);
    setSuccess(null);

    try {
      await api.post(`/api/platform/residences/${residence.residence_id}/deactivate`);
      setSuccess(t('platform.deactivateSuccess', { name: residence.residence_name }));
      await loadResidences();
    } catch (err) {
      setError(extractErrorMessage(err));
    }
  }

  const residenceColumns: DataTableColumn<PlatformResidence>[] = [
    {
      key: 'residence_name',
      label: t('platform.colResidence'),
      sortable: true,
      className: 'whitespace-nowrap font-medium text-slate-900',
      sortValue: (residence) => residence.residence_name,
      render: (residence) => (
        <>
          {residence.residence_name}
          <span className="ms-1.5 font-normal text-slate-400">
            ({t('platform.lotsCount', { count: residence.lots_count })})
          </span>
        </>
      ),
    },
    {
      key: 'contact',
      label: t('platform.colContact'),
      className: 'whitespace-nowrap',
      render: (residence) => (
        <>
          <div>{residence.admin_name ?? '—'}</div>
          <div className="text-xs text-slate-400">{residence.admin_email}</div>
        </>
      ),
    },
    {
      key: 'plan',
      label: t('platform.colPlan'),
      sortable: true,
      className: 'whitespace-nowrap',
      sortValue: (residence) => residence.subscription?.plan_label ?? null,
      render: (residence) => residence.subscription?.plan_label ?? '—',
    },
    {
      key: 'status',
      label: t('platform.colStatus'),
      sortable: true,
      className: 'whitespace-nowrap',
      sortValue: (residence) => residence.subscription?.status ?? null,
      render: (residence) =>
        residence.subscription ? (
          <span
            className={`inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold ${statusClasses[residence.subscription.status]}`}
          >
            {t(`subscriptionPage.status.${residence.subscription.status}`)}
          </span>
        ) : (
          '—'
        ),
    },
    {
      key: 'days_remaining',
      label: t('platform.colDaysRemaining'),
      sortable: true,
      className: 'whitespace-nowrap',
      sortValue: (residence) => residence.subscription?.days_remaining ?? null,
      render: (residence) => residence.subscription?.days_remaining ?? '—',
    },
  ];

  return (
    <div>
      <PageHeader title={t('platform.title')} subtitle={t('platform.subtitle')} />

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

      {activatingFor && (
        <form onSubmit={handleActivate} className="mb-6 flex flex-col gap-4 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
          <div className="flex items-center justify-between">
            <p className="text-sm font-semibold text-slate-900">
              {t('platform.activateTitle', { name: activatingFor.residence_name })}
            </p>
            <button type="button" onClick={() => setActivatingFor(null)} className="text-slate-400 hover:text-slate-600">
              <X className="size-4" />
            </button>
          </div>

          <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <Field label={t('platform.cycleLabel')} htmlFor="activate-cycle">
              <Select
                id="activate-cycle"
                value={activateForm.cycle}
                onChange={(event) => setActivateForm((previous) => ({ ...previous, cycle: event.target.value }))}
              >
                <option value="monthly">{t('platform.cycleMonthly')}</option>
                <option value="annual">{t('platform.cycleAnnual')}</option>
              </Select>
            </Field>

            <Field label={t('platform.planOverrideLabel')} htmlFor="activate-plan">
              <Select
                id="activate-plan"
                value={activateForm.plan}
                onChange={(event) => setActivateForm((previous) => ({ ...previous, plan: event.target.value }))}
              >
                <option value="">{t('platform.planKeepCurrent')}</option>
                <option value="starter">Starter</option>
                <option value="standard">Standard</option>
                <option value="plus">Plus</option>
                <option value="premium">Premium</option>
                <option value="custom">{t('platform.planCustom')}</option>
              </Select>
            </Field>

            <Field label={t('platform.amountOverrideLabel')} htmlFor="activate-amount">
              <Input
                id="activate-amount"
                type="number"
                min={1}
                placeholder={t('platform.amountPlaceholder')}
                value={activateForm.amount}
                onChange={(event) => setActivateForm((previous) => ({ ...previous, amount: event.target.value }))}
              />
            </Field>
          </div>

          <Button type="submit" isLoading={isSubmitting} className="self-start">
            {t('platform.activateButton')}
          </Button>
        </form>
      )}

      <DataTable
        columns={residenceColumns}
        data={residences}
        rowKey={(residence) => residence.residence_id}
        previousLabel={t('common.previous')}
        nextLabel={t('common.next')}
        rowsPerPageLabel={t('common.rowsPerPage')}
        allRowsLabel={t('common.allRows')}
        isLoading={isLoading}
        loadingText={t('common.loading')}
        emptyState={<p className="text-sm text-slate-500">{t('platform.emptyDesc')}</p>}
        searchableText={(residence) =>
          `${residence.residence_name} ${residence.admin_name ?? ''} ${residence.admin_email}`
        }
        searchPlaceholder={t('common.searchPlaceholder')}
        actions={(residence) => (
          <>
            <Button size="sm" variant="secondary" onClick={() => openActivate(residence)}>
              <CheckCircle2 className="size-3.5" />
              {t('platform.activateButton')}
            </Button>
            {residence.subscription && residence.subscription.plan !== 'free' && (
              <Button size="sm" variant="secondary" onClick={() => handleDeactivate(residence)}>
                <PauseCircle className="size-3.5" />
                {t('platform.deactivateButton')}
              </Button>
            )}
          </>
        )}
      />
    </div>
  );
}
