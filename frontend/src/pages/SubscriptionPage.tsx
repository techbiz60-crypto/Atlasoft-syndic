import { useEffect, useState } from 'react';
import { CalendarClock, MessageCircle, Receipt } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { api } from '../lib/api';
import { extractErrorMessage, useAuth } from '../context/AuthContext';
import type { Subscription, SubscriptionInvoice, SubscriptionStatus } from '../types/auth';
import { PageHeader } from '../components/PageHeader';
import { ErrorAlert } from '../components/ui/Alert';
import { upgradeablePlans } from '../lib/plans';
import { DataTable } from '../components/ui/DataTable';
import type { DataTableColumn } from '../components/ui/DataTable';

const statusClasses: Record<SubscriptionStatus, string> = {
  free: 'bg-slate-100 text-slate-700',
  trial: 'bg-amber-100 text-amber-800',
  active: 'bg-brand-100 text-brand-800',
  expired: 'bg-rose-100 text-rose-700',
};

const contactWhatsapp = import.meta.env.VITE_CONTACT_WHATSAPP_NUMBER;

export function SubscriptionPage() {
  const { t } = useTranslation();
  const { user } = useAuth();
  const [subscription, setSubscription] = useState<Subscription | null>(null);
  const [invoices, setInvoices] = useState<SubscriptionInvoice[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    Promise.all([
      api.get<{ data: Subscription | null }>('/api/subscription'),
      api.get<{ data: SubscriptionInvoice[] }>('/api/subscription/invoices'),
    ])
      .then(([subscriptionRes, invoicesRes]) => {
        setSubscription(subscriptionRes.data.data);
        setInvoices(invoicesRes.data.data);
      })
      .catch((err) => setError(extractErrorMessage(err)))
      .finally(() => setIsLoading(false));
  }, []);

  if (isLoading) {
    return <p className="text-sm text-slate-500">{t('common.loading')}</p>;
  }

  const invoiceColumns: DataTableColumn<SubscriptionInvoice>[] = [
    {
      key: 'period',
      label: t('subscriptionPage.colPeriod'),
      sortable: true,
      className: 'whitespace-nowrap',
      sortValue: (invoice) => invoice.period_start,
      render: (invoice) =>
        `${new Date(invoice.period_start).toLocaleDateString('fr-FR')} — ${new Date(invoice.period_end).toLocaleDateString('fr-FR')}`,
    },
    {
      key: 'plan_label',
      label: t('subscriptionPage.colPlan'),
      className: 'whitespace-nowrap',
    },
    {
      key: 'amount',
      label: t('subscriptionPage.colAmount'),
      sortable: true,
      align: 'end',
      className: 'whitespace-nowrap font-medium text-slate-900',
      sortValue: (invoice) => invoice.amount,
      render: (invoice) => `${invoice.amount} DH`,
    },
    {
      key: 'validated_at',
      label: t('subscriptionPage.colValidatedAt'),
      sortable: true,
      className: 'whitespace-nowrap',
      sortValue: (invoice) => invoice.validated_at,
      render: (invoice) => new Date(invoice.validated_at).toLocaleDateString('fr-FR'),
    },
  ];

  return (
    <div>
      <PageHeader title={t('subscriptionPage.title')} subtitle={t('subscriptionPage.subtitle')} />

      {error && (
        <div className="mb-4">
          <ErrorAlert>{error}</ErrorAlert>
        </div>
      )}

      {!subscription ? (
        <EmptyState />
      ) : (
        <>
          <div className="mb-8 grid grid-cols-1 gap-4 sm:grid-cols-3">
            <div className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
              <p className="text-xs font-medium uppercase tracking-wide text-slate-500">{t('subscriptionPage.planLabel')}</p>
              <p className="mt-1.5 text-xl font-bold text-slate-900">{subscription.plan_label}</p>
              <span className={`mt-3 inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold ${statusClasses[subscription.status]}`}>
                {t(`subscriptionPage.status.${subscription.status}`)}
              </span>
            </div>

            <div className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
              <p className="text-xs font-medium uppercase tracking-wide text-slate-500">{t('subscriptionPage.priceLabel')}</p>
              <p className="mt-1.5 text-xl font-bold text-slate-900">
                {subscription.plan === 'free'
                  ? t('subscriptionPage.free')
                  : subscription.billing_cycle === 'annual'
                    ? t('subscriptionPage.annualPrice', { amount: subscription.annual_price })
                    : t('subscriptionPage.monthlyPrice', { amount: subscription.monthly_price })}
              </p>
            </div>

            <div className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
              <p className="text-xs font-medium uppercase tracking-wide text-slate-500">{t('subscriptionPage.daysRemainingLabel')}</p>
              <p className="mt-1.5 flex items-center gap-2 text-xl font-bold text-slate-900">
                <CalendarClock className="size-5 text-brand-600" />
                {subscription.plan === 'free' || subscription.days_remaining === null
                  ? t('subscriptionPage.unlimited')
                  : t('subscriptionPage.daysCount', { count: Math.max(subscription.days_remaining, 0) })}
              </p>
            </div>
          </div>

          <h2 className="mb-1 text-sm font-semibold text-slate-700">{t('subscriptionPage.upgradeTitle')}</h2>
          <p className="mb-4 text-sm text-slate-500">{t('subscriptionPage.upgradeDesc')}</p>

          <div className="mb-8 grid grid-cols-1 gap-4 sm:grid-cols-3 lg:grid-cols-5">
            {upgradeablePlans.map((entry) => {
              const isCurrent = subscription.plan === entry.plan;
              const message = t('subscriptionPage.upgradeMessage', {
                plan: entry.label,
                residence: user?.residence?.name,
                id: user?.residence_id,
              });
              const waLink = contactWhatsapp
                ? `https://wa.me/${contactWhatsapp.replace('+', '')}?text=${encodeURIComponent(message)}`
                : null;

              return (
                <div
                  key={entry.plan}
                  className={`flex flex-col rounded-xl border p-4 ${isCurrent ? 'border-brand-500 bg-brand-50/40' : 'border-slate-200 bg-white'}`}
                >
                  <p className="text-sm font-bold text-slate-900">{entry.label}</p>
                  <p className="mt-0.5 text-xs text-slate-500">
                    {entry.maxLots ? t('subscriptionPage.upToLots', { count: entry.maxLots }) : t('subscriptionPage.moreThan100')}
                  </p>
                  <p className="mt-2 text-base font-bold text-slate-900">
                    {entry.monthlyPrice !== null ? t('subscriptionPage.monthlyPrice', { amount: entry.monthlyPrice }) : t('platform.planCustom')}
                  </p>

                  {isCurrent ? (
                    <span className="mt-3 inline-flex w-fit items-center rounded-full bg-brand-100 px-2.5 py-1 text-xs font-semibold text-brand-800">
                      {t('subscriptionPage.currentPlanBadge')}
                    </span>
                  ) : waLink ? (
                    <a
                      href={waLink}
                      target="_blank"
                      rel="noopener"
                      className="mt-3 inline-flex items-center justify-center gap-1.5 rounded-lg bg-brand-600 px-3 py-2 text-xs font-semibold text-white hover:bg-brand-700"
                    >
                      <MessageCircle className="size-3.5" />
                      {t('subscriptionPage.requestPlan')}
                    </a>
                  ) : (
                    <p className="mt-3 text-xs text-slate-400">{t('subscriptionPage.noContactConfigured')}</p>
                  )}
                </div>
              );
            })}
          </div>

          <h2 className="mb-3 text-sm font-semibold text-slate-700">{t('subscriptionPage.invoicesTitle')}</h2>

          {invoices.length === 0 ? (
            <p className="text-sm text-slate-500">{t('subscriptionPage.noInvoices')}</p>
          ) : (
            <DataTable
              columns={invoiceColumns}
              data={invoices}
              rowKey={(invoice) => invoice.id}
              previousLabel={t('common.previous')}
              nextLabel={t('common.next')}
              rowsPerPageLabel={t('common.rowsPerPage')}
              allRowsLabel={t('common.allRows')}
            />
          )}
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
        <Receipt className="size-5" />
      </div>
      <p className="text-sm font-medium text-slate-900">{t('subscriptionPage.emptyTitle')}</p>
      <p className="mt-1 text-sm text-slate-500">{t('subscriptionPage.emptyDesc')}</p>
    </div>
  );
}

