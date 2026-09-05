import { useEffect, useMemo, useState } from 'react';
import { CheckCircle2, MessageCircle } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { api } from '../lib/api';
import { canManageFinances, extractErrorMessage, useAuth } from '../context/AuthContext';
import type { UnpaidLot } from '../types/resources';
import { PageHeader } from '../components/PageHeader';
import { ErrorAlert } from '../components/ui/Alert';
import { DataTable } from '../components/ui/DataTable';
import type { DataTableColumn } from '../components/ui/DataTable';
import { Field, Select } from '../components/ui/Input';

type DelayFilter = 'all' | '3months' | '1year';

const delayThresholds: Record<DelayFilter, number> = { all: 0, '3months': 3, '1year': 12 };

function buildReminderLink(lot: UnpaidLot): string | null {
  if (!lot.owner_phone) {
    return null;
  }

  const phone = lot.owner_phone.replace(/[^\d+]/g, '');
  const message = `Bonjour ${lot.owner_name}, un rappel amical concernant votre cotisation de syndic pour l'appartement ${lot.lot_number} (${lot.building_name}) : montant dû ${lot.total_due} DH. Merci de régulariser dès que possible.`;

  return `https://wa.me/${phone.replace('+', '')}?text=${encodeURIComponent(message)}`;
}

export function ImpayesPage() {
  const { user } = useAuth();
  const { t } = useTranslation();
  const isAdmin = canManageFinances(user);

  const [unpaidLots, setUnpaidLots] = useState<UnpaidLot[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [delayFilter, setDelayFilter] = useState<DelayFilter>('all');

  useEffect(() => {
    api
      .get<{ data: UnpaidLot[] }>('/api/fund-calls/unpaid')
      .then(({ data }) => setUnpaidLots(data.data))
      .catch((err) => setError(extractErrorMessage(err)))
      .finally(() => setIsLoading(false));
  }, []);

  const filteredLots = useMemo(
    () => unpaidLots.filter((lot) => lot.months_late >= delayThresholds[delayFilter]),
    [unpaidLots, delayFilter],
  );

  const columns: DataTableColumn<UnpaidLot>[] = [
    {
      key: 'lot_number',
      label: t('impayes.colLot'),
      sortable: true,
      className: 'font-medium text-slate-900',
      sortValue: (lot) => lot.lot_number,
      render: (lot) => (
        <>
          {lot.lot_number}
          <span className="ms-1.5 font-normal text-slate-400">({lot.building_name})</span>
        </>
      ),
    },
    {
      key: 'owner_name',
      label: t('impayes.colOwner'),
      sortable: true,
      sortValue: (lot) => lot.owner_name,
    },
    {
      key: 'months_late',
      label: t('impayes.colDelay'),
      sortable: true,
      sortValue: (lot) => lot.months_late,
      render: (lot) => (
        <span className="rounded-full bg-rose-50 px-2.5 py-1 text-xs font-medium text-rose-700">
          {t('impayes.monthsLate', { count: lot.months_late })}
        </span>
      ),
    },
    {
      key: 'total_due',
      label: t('impayes.colAmountDue'),
      sortable: true,
      align: 'end',
      className: 'font-medium text-slate-900',
      sortValue: (lot) => lot.total_due,
      render: (lot) => (
        <>
          {lot.total_due} DH
          {lot.opening_balance_due > 0 && (
            <span className="ms-1.5 block font-normal text-slate-400">
              {t('impayes.includingOpeningBalance', { amount: lot.opening_balance_due })}
            </span>
          )}
        </>
      ),
    },
    {
      key: 'last_payment_date',
      label: t('impayes.colLastPayment'),
      sortable: true,
      sortValue: (lot) => lot.last_payment_date,
      render: (lot) => lot.last_payment_date ?? t('common.never'),
    },
  ];

  return (
    <div>
      <PageHeader title={t('impayes.title')} subtitle={t('impayes.subtitle')} />

      {error && (
        <div className="mb-4">
          <ErrorAlert>{error}</ErrorAlert>
        </div>
      )}

      <div className="mb-5 max-w-[14rem]">
        <Field label={t('impayes.delayFilterLabel')} htmlFor="impayes-delay-filter">
          <Select
            id="impayes-delay-filter"
            value={delayFilter}
            onChange={(event) => setDelayFilter(event.target.value as DelayFilter)}
          >
            <option value="all">{t('impayes.delayFilterAll')}</option>
            <option value="3months">{t('impayes.delayFilter3Months')}</option>
            <option value="1year">{t('impayes.delayFilter1Year')}</option>
          </Select>
        </Field>
      </div>

      <DataTable
        columns={columns}
        data={filteredLots}
        rowKey={(lot) => lot.lot_id}
        previousLabel={t('common.previous')}
        nextLabel={t('common.next')}
        rowsPerPageLabel={t('common.rowsPerPage')}
        allRowsLabel={t('common.allRows')}
        isLoading={isLoading}
        loadingText={t('common.loading')}
        emptyState={<EmptyState />}
        searchableText={(lot) => `${lot.lot_number} ${lot.owner_name} ${lot.building_name}`}
        searchPlaceholder={t('common.searchPlaceholder')}
        actions={
          isAdmin
            ? (lot) => {
                const reminderLink = buildReminderLink(lot);
                return reminderLink ? (
                  <a
                    href={reminderLink}
                    target="_blank"
                    rel="noreferrer"
                    className="inline-flex items-center gap-1.5 rounded-lg border border-brand-200 bg-brand-50 px-2.5 py-1.5 text-xs font-medium text-brand-700 hover:bg-brand-100"
                  >
                    <MessageCircle className="size-3.5" />
                    {t('impayes.reminderButton')}
                  </a>
                ) : null;
              }
            : undefined
        }
      />
    </div>
  );
}

function EmptyState() {
  const { t } = useTranslation();
  return (
    <div className="flex flex-col items-center justify-center rounded-xl border border-dashed border-slate-300 bg-white px-6 py-14 text-center">
      <div className="mb-3 flex size-11 items-center justify-center rounded-full bg-brand-50 text-brand-600">
        <CheckCircle2 className="size-5" />
      </div>
      <p className="text-sm font-medium text-slate-900">{t('impayes.emptyTitle')}</p>
      <p className="mt-1 text-sm text-slate-500">{t('impayes.emptyDesc')}</p>
    </div>
  );
}
