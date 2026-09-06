import { useEffect, useState } from 'react';
import { ArrowDownLeft, ArrowUpRight, BookOpen } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { api } from '../lib/api';
import { extractErrorMessage } from '../context/AuthContext';
import type { Ledger, LedgerMovement } from '../types/resources';
import { ErrorAlert } from './ui/Alert';
import { DataTable } from './ui/DataTable';
import type { DataTableColumn } from './ui/DataTable';

function formatAmount(amount: number): string {
  return amount.toLocaleString('fr-FR', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
}

/**
 * The detail behind the treasury summary. Columns are deliberately not
 * sortable: the running balance only reads correctly in date order.
 */
export function TreasuryLedger({ year }: { year: number }) {
  const { t } = useTranslation();

  const [ledger, setLedger] = useState<Ledger | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    setIsLoading(true);
    api
      .get<Ledger>('/api/ledger', { params: { year } })
      .then(({ data }) => setLedger(data))
      .catch((err) => setError(extractErrorMessage(err)))
      .finally(() => setIsLoading(false));
  }, [year]);

  const columns: DataTableColumn<LedgerMovement>[] = [
    {
      key: 'date',
      label: t('ledger.colDate'),
      className: 'whitespace-nowrap',
      render: (movement) => new Date(movement.date).toLocaleDateString('fr-FR'),
    },
    {
      key: 'kind',
      label: t('ledger.colKind'),
      className: 'whitespace-nowrap',
      render: (movement) => (
        <span
          className={`inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium ${
            movement.direction === 'in' ? 'bg-brand-50 text-brand-700' : 'bg-rose-50 text-rose-700'
          }`}
        >
          {movement.direction === 'in' ? <ArrowDownLeft className="size-3.5" /> : <ArrowUpRight className="size-3.5" />}
          {t(`ledger.kinds.${movement.kind}`)}
        </span>
      ),
    },
    {
      key: 'label',
      label: t('ledger.colLabel'),
      className: 'text-slate-700',
      render: (movement) => (
        <>
          {movement.label}
          {(movement.months_covered ?? 1) > 1 && (
            <span className="ms-1.5 text-xs text-slate-400">
              {t('ledger.monthsCovered', { count: movement.months_covered })}
            </span>
          )}
        </>
      ),
    },
    {
      key: 'reference',
      label: t('ledger.colReference'),
      className: 'whitespace-nowrap text-slate-500',
      render: (movement) => movement.reference,
    },
    {
      key: 'method',
      label: t('ledger.colMethod'),
      className: 'whitespace-nowrap text-slate-500',
      render: (movement) => t(`common.paymentMethods.${movement.method}`),
    },
    {
      key: 'amount',
      label: t('ledger.colAmount'),
      align: 'end',
      className: 'whitespace-nowrap font-medium',
      render: (movement) => (
        <span className={movement.direction === 'in' ? 'text-brand-700' : 'text-rose-700'}>
          {movement.direction === 'in' ? '+' : '−'}
          {formatAmount(movement.amount)}
        </span>
      ),
    },
    {
      key: 'balance',
      label: t('ledger.colBalance'),
      align: 'end',
      className: 'whitespace-nowrap font-semibold text-slate-900',
      render: (movement) => formatAmount(movement.balance),
    },
  ];

  if (error) {
    return <ErrorAlert>{error}</ErrorAlert>;
  }

  return (
    <div>
      {ledger && (
        <div className="mb-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
          <SummaryTile label={t('ledger.openingBalance')} value={ledger.opening_balance} />
          <SummaryTile label={t('ledger.totalIn')} value={ledger.total_in} tone="in" />
          <SummaryTile label={t('ledger.totalOut')} value={ledger.total_out} tone="out" />
          <SummaryTile label={t('ledger.closingBalance')} value={ledger.closing_balance} emphasize />
        </div>
      )}

      <DataTable
        columns={columns}
        data={ledger?.data ?? []}
        rowKey={(movement) => movement.id}
        previousLabel={t('common.previous')}
        nextLabel={t('common.next')}
        rowsPerPageLabel={t('common.rowsPerPage')}
        allRowsLabel={t('common.allRows')}
        isLoading={isLoading}
        loadingText={t('common.loading')}
        emptyState={<EmptyState />}
        searchableText={(movement) => `${movement.label} ${movement.reference}`}
        searchPlaceholder={t('common.searchPlaceholder')}
      />
    </div>
  );
}

function SummaryTile({
  label,
  value,
  tone,
  emphasize = false,
}: {
  label: string;
  value: number;
  tone?: 'in' | 'out';
  emphasize?: boolean;
}) {
  const valueColor = tone === 'in' ? 'text-brand-700' : tone === 'out' ? 'text-rose-700' : 'text-slate-900';

  return (
    <div className={`rounded-xl border p-4 ${emphasize ? 'border-slate-900 bg-slate-900' : 'border-slate-200 bg-white'}`}>
      <p className={`text-xs font-medium ${emphasize ? 'text-slate-300' : 'text-slate-500'}`}>{label}</p>
      <p className={`mt-1 text-lg font-bold ${emphasize ? 'text-white' : valueColor}`}>{formatAmount(value)} DH</p>
    </div>
  );
}

function EmptyState() {
  const { t } = useTranslation();
  return (
    <div className="flex flex-col items-center justify-center rounded-xl border border-dashed border-slate-300 bg-white px-6 py-14 text-center">
      <div className="mb-3 flex size-11 items-center justify-center rounded-full bg-slate-100 text-slate-400">
        <BookOpen className="size-5" />
      </div>
      <p className="text-sm font-medium text-slate-900">{t('ledger.emptyTitle')}</p>
      <p className="mt-1 text-sm text-slate-500">{t('ledger.emptyDesc')}</p>
    </div>
  );
}
