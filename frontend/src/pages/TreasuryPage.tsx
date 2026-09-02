import { useEffect, useState } from 'react';
import { Landmark } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { api } from '../lib/api';
import { extractErrorMessage } from '../context/AuthContext';
import type { TreasuryReport } from '../types/resources';
import { PageHeader } from '../components/PageHeader';
import { Field, Select } from '../components/ui/Input';
import { ErrorAlert } from '../components/ui/Alert';

const currentYear = new Date().getFullYear();
const yearOptions = [currentYear - 1, currentYear, currentYear + 1];

function sum(amounts: number[]): number {
  return amounts.reduce((total, amount) => total + amount, 0);
}

function formatAmount(amount: number): string {
  return amount.toLocaleString('fr-FR', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
}

export function TreasuryPage() {
  const { t } = useTranslation();
  const monthLabels = t('common.monthsShort', { returnObjects: true }) as string[];
  const [year, setYear] = useState(currentYear);
  const [report, setReport] = useState<TreasuryReport | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    setIsLoading(true);
    api
      .get<TreasuryReport>('/api/treasury-report', { params: { year } })
      .then(({ data }) => setReport(data))
      .catch((err) => setError(extractErrorMessage(err)))
      .finally(() => setIsLoading(false));
  }, [year]);

  return (
    <div>
      <PageHeader title={t('treasury.title')} subtitle={t('treasury.subtitle')} />

      <div className="mb-5 max-w-[10rem]">
        <Field label={t('treasury.yearLabel')} htmlFor="treasury-year">
          <Select id="treasury-year" value={year} onChange={(event) => setYear(Number(event.target.value))}>
            {yearOptions.map((y) => (
              <option key={y} value={y}>
                {y}
              </option>
            ))}
          </Select>
        </Field>
      </div>

      {error && (
        <div className="mb-4">
          <ErrorAlert>{error}</ErrorAlert>
        </div>
      )}

      {isLoading ? (
        <p className="text-sm text-slate-500">{t('common.loading')}</p>
      ) : !report ? null : (
        <div className="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
          <table className="w-full text-start text-sm">
            <thead>
              <tr className="border-b border-slate-200 bg-slate-50 text-xs font-semibold uppercase tracking-wide text-slate-500">
                <th className="sticky start-0 z-10 min-w-[220px] bg-slate-50 px-4 py-3">{t('treasury.rubrique')}</th>
                <th className="min-w-[90px] px-3 py-3 text-end">{t('treasury.total')}</th>
                {monthLabels.map((label) => (
                  <th key={label} className="min-w-[80px] px-3 py-3 text-end">
                    {label}
                  </th>
                ))}
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100">
              <tr className="bg-brand-50/50">
                <td colSpan={14} className="px-4 py-2 text-xs font-bold uppercase tracking-wide text-brand-700">
                  {t('treasury.revenuesSection')}
                </td>
              </tr>

              <Row label={t('treasury.cotisationsRow')} amounts={report.cotisations} />

              {report.revenue_categories.map((category) => (
                <Row key={category.name} label={category.name} amounts={category.amounts} />
              ))}

              <TotalRow label={t('treasury.totalRevenues')} amounts={report.income_by_month} />

              <tr className="bg-rose-50/50">
                <td colSpan={14} className="px-4 py-2 text-xs font-bold uppercase tracking-wide text-rose-700">
                  {t('treasury.expensesSection')}
                </td>
              </tr>

              {report.expense_categories.map((category) => (
                <Row key={category.name} label={category.name} amounts={category.amounts} />
              ))}

              <TotalRow label={t('treasury.totalExpenses')} amounts={report.expenses_by_month} />

              <TotalRow label={t('treasury.monthBalance')} amounts={report.net_by_month} emphasize />

              <tr>
                <td className="sticky start-0 bg-white px-4 py-3 font-semibold text-slate-900">
                  {t('treasury.openingBalance', { year })}
                </td>
                <td colSpan={13} className="px-3 py-3 text-end font-semibold text-slate-900">
                  {formatAmount(report.opening_balance)} DH
                </td>
              </tr>

              <tr className="bg-slate-900 text-white">
                <td className="sticky start-0 bg-slate-900 px-4 py-3 font-bold">{t('treasury.closingBalance')}</td>
                <td className="px-3 py-3 text-end font-bold">{formatAmount(report.closing_balance)}</td>
                {report.balance_by_month.map((amount, index) => (
                  <td key={index} className="px-3 py-3 text-end font-bold">
                    {formatAmount(amount)}
                  </td>
                ))}
              </tr>
            </tbody>
          </table>
        </div>
      )}

      {!isLoading && report && report.revenue_categories.length === 0 && report.expense_categories.length === 0 && sum(report.cotisations) === 0 && (
        <EmptyState />
      )}
    </div>
  );
}

function Row({ label, amounts }: { label: string; amounts: number[] }) {
  return (
    <tr className="hover:bg-slate-50/60">
      <td className="sticky start-0 bg-white px-4 py-2.5 text-slate-700">{label}</td>
      <td className="px-3 py-2.5 text-end font-medium text-slate-900">{formatAmount(sum(amounts))}</td>
      {amounts.map((amount, index) => (
        <td key={index} className="px-3 py-2.5 text-end text-slate-600">
          {amount === 0 ? '—' : formatAmount(amount)}
        </td>
      ))}
    </tr>
  );
}

function TotalRow({ label, amounts, emphasize = false }: { label: string; amounts: number[]; emphasize?: boolean }) {
  return (
    <tr className={emphasize ? 'bg-slate-50' : undefined}>
      <td className="sticky start-0 bg-inherit px-4 py-2.5 font-semibold text-slate-900">{label}</td>
      <td className="px-3 py-2.5 text-end font-bold text-slate-900">{formatAmount(sum(amounts))}</td>
      {amounts.map((amount, index) => (
        <td key={index} className="px-3 py-2.5 text-end font-semibold text-slate-800">
          {formatAmount(amount)}
        </td>
      ))}
    </tr>
  );
}

function EmptyState() {
  const { t } = useTranslation();
  return (
    <div className="mt-6 flex flex-col items-center justify-center rounded-xl border border-dashed border-slate-300 bg-white px-6 py-14 text-center">
      <div className="mb-3 flex size-11 items-center justify-center rounded-full bg-slate-100 text-slate-400">
        <Landmark className="size-5" />
      </div>
      <p className="text-sm font-medium text-slate-900">{t('treasury.emptyTitle')}</p>
      <p className="mt-1 text-sm text-slate-500">{t('treasury.emptyDesc')}</p>
    </div>
  );
}
