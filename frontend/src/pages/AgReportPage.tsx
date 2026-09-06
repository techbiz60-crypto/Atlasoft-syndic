import { useEffect, useState } from 'react';
import { Gavel, Printer } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { api } from '../lib/api';
import { extractErrorMessage } from '../context/AuthContext';
import type { AgReport } from '../types/resources';
import { PageHeader } from '../components/PageHeader';
import { Field, Select } from '../components/ui/Input';
import { Button } from '../components/ui/Button';
import { ErrorAlert } from '../components/ui/Alert';

const currentYear = new Date().getFullYear();
const yearOptions = [currentYear - 2, currentYear - 1, currentYear, currentYear + 1];

function sum(amounts: number[]): number {
  return amounts.reduce((total, amount) => total + amount, 0);
}

function formatAmount(amount: number): string {
  return amount.toLocaleString('fr-FR', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
}

export function AgReportPage() {
  const { t } = useTranslation();
  const monthLabels = t('common.monthsShort', { returnObjects: true }) as string[];

  const [year, setYear] = useState(currentYear);
  const [report, setReport] = useState<AgReport | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    setIsLoading(true);
    api
      .get<AgReport>('/api/reports/ag', { params: { year } })
      .then(({ data }) => setReport(data))
      .catch((err) => setError(extractErrorMessage(err)))
      .finally(() => setIsLoading(false));
  }, [year]);

  return (
    <div>
      <PageHeader
        title={t('agReport.title')}
        subtitle={t('agReport.subtitle')}
        action={
          report && (
            <Button type="button" variant="secondary" onClick={() => window.print()} className="no-print">
              <Printer className="size-4" />
              {t('agReport.printButton')}
            </Button>
          )
        }
      />

      <div className="no-print mb-5 max-w-[10rem]">
        <Field label={t('agReport.yearLabel')} htmlFor="ag-year">
          <Select id="ag-year" value={year} onChange={(event) => setYear(Number(event.target.value))}>
            {yearOptions.map((y) => (
              <option key={y} value={y}>
                {y}
              </option>
            ))}
          </Select>
        </Field>
      </div>

      {error && (
        <div className="no-print mb-4">
          <ErrorAlert>{error}</ErrorAlert>
        </div>
      )}

      {isLoading ? (
        <p className="text-sm text-slate-500">{t('common.loading')}</p>
      ) : !report ? null : (
        <div className="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm print:overflow-visible print:rounded-none print:border-0 print:shadow-none">
          <div className="hidden border-b border-slate-200 px-5 py-3 text-sm font-semibold text-slate-900 print:block">
            {report.residence_name} — {t('agReport.printTitle', { year: report.year })}
          </div>

          <table className="w-full border-collapse text-start text-sm">
            <thead>
              <tr className="bg-slate-50 text-[11px] font-semibold uppercase tracking-wide text-slate-500">
                <th className="sticky start-0 z-10 min-w-[220px] border-b-2 border-e-2 border-slate-300 bg-slate-50 px-4 py-3 text-start">
                  {t('agReport.rubrique')}
                </th>
                <th className="min-w-[100px] border-b-2 border-e-2 border-slate-300 bg-slate-100 px-3 py-3 text-end">
                  {t('agReport.total')}
                </th>
                {monthLabels.map((label) => (
                  <th key={label} className="min-w-[76px] border-b-2 border-s border-slate-200 px-3 py-3 text-end">
                    {label}
                  </th>
                ))}
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100">
              <BalanceRow label={t('agReport.openingRow', { year: report.year })} value={report.opening_balance} />

              <SectionRow label={t('agReport.incomeSection')} accent="brand" />

              <Row label={t('agReport.cotisationsRow')} amounts={report.cotisations} />

              {sum(report.opening_balance_recovered) > 0 && (
                <Row label={t('agReport.openingBalanceRow')} amounts={report.opening_balance_recovered} />
              )}

              {report.revenue_categories.map((category) => (
                <Row key={category.name} label={category.name} amounts={category.amounts} />
              ))}

              <TotalRow label={t('agReport.totalIncome')} amounts={report.income_by_month} />

              <SectionRow label={t('agReport.expensesSection')} accent="rose" />

              {report.expense_categories.map((category) => (
                <Row key={category.name} label={category.name} amounts={category.amounts} />
              ))}

              <TotalRow label={t('agReport.totalExpenses')} amounts={report.expenses_by_month} />

              <tr className="bg-slate-900 text-white">
                <td className="sticky start-0 border-e-2 border-slate-700 bg-slate-900 px-4 py-3 font-bold">
                  {t('agReport.resultRow')}
                </td>
                <td className="border-e-2 border-slate-700 px-3 py-3 text-end font-bold">{formatAmount(report.result)}</td>
                {report.net_by_month.map((amount, index) => (
                  <td key={index} className="border-s border-slate-700 px-3 py-3 text-end font-bold">
                    {formatAmount(amount)}
                  </td>
                ))}
              </tr>

              <BalanceRow
                label={t('agReport.closingRow', { year: report.year })}
                value={report.closing_balance}
                emphasize
              />
            </tbody>
          </table>

          <p className="border-t border-slate-200 px-5 py-3 text-xs text-slate-500">{t('agReport.basisNote')}</p>
        </div>
      )}

      {!isLoading && report && report.total_income === 0 && report.total_expenses === 0 && <EmptyState />}
    </div>
  );
}

/**
 * A single figure spanning the month columns — a balance is a position at
 * one point in time, so spreading it across months would be meaningless.
 */
function BalanceRow({
  label,
  value,
  emphasize = false,
  muted = false,
}: {
  label: string;
  value: number;
  emphasize?: boolean;
  muted?: boolean;
}) {
  if (emphasize) {
    return (
      <tr className="bg-slate-900 text-white">
        <td className="sticky start-0 border-e-2 border-slate-700 bg-slate-900 px-4 py-3 font-bold">{label}</td>
        <td colSpan={13} className="px-3 py-3 text-end font-bold">
          {formatAmount(value)} DH
        </td>
      </tr>
    );
  }

  return (
    <tr className={`border-y border-slate-200 ${muted ? 'bg-slate-50/60 italic text-slate-500' : 'bg-slate-50'}`}>
      <td
        className={`sticky start-0 border-e-2 border-slate-200 bg-inherit px-4 py-3 ${
          muted ? 'text-xs' : 'font-semibold text-slate-900'
        }`}
      >
        {label}
      </td>
      <td colSpan={13} className={`px-3 py-3 text-end ${muted ? 'text-xs' : 'font-semibold text-slate-900'}`}>
        {formatAmount(value)} DH
      </td>
    </tr>
  );
}

function SectionRow({ label, accent }: { label: string; accent: 'brand' | 'rose' }) {
  const colors =
    accent === 'brand'
      ? 'border-s-4 border-brand-600 bg-brand-50/60 text-brand-800'
      : 'border-s-4 border-rose-600 bg-rose-50/60 text-rose-800';
  return (
    <tr className={colors}>
      <td colSpan={14} className="px-4 py-2 text-xs font-bold uppercase tracking-wide">
        {label}
      </td>
    </tr>
  );
}

function Row({ label, amounts }: { label: string; amounts: number[] }) {
  return (
    <tr className="hover:bg-slate-50/60">
      <td className="sticky start-0 border-e-2 border-slate-200 bg-white px-4 py-2.5 text-slate-700">{label}</td>
      <td className="border-e-2 border-slate-200 bg-slate-50/40 px-3 py-2.5 text-end font-medium text-slate-900">
        {formatAmount(sum(amounts))}
      </td>
      {amounts.map((amount, index) => (
        <td key={index} className="border-s border-slate-100 px-3 py-2.5 text-end text-slate-600">
          {amount === 0 ? '—' : formatAmount(amount)}
        </td>
      ))}
    </tr>
  );
}

function TotalRow({ label, amounts }: { label: string; amounts: number[] }) {
  return (
    <tr className="border-y border-slate-200 bg-slate-50">
      <td className="sticky start-0 border-e-2 border-slate-200 bg-inherit px-4 py-2.5 font-semibold text-slate-900">{label}</td>
      <td className="border-e-2 border-slate-200 bg-inherit px-3 py-2.5 text-end font-bold text-slate-900">
        {formatAmount(sum(amounts))}
      </td>
      {amounts.map((amount, index) => (
        <td key={index} className="border-s border-slate-100 px-3 py-2.5 text-end font-semibold text-slate-800">
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
        <Gavel className="size-5" />
      </div>
      <p className="text-sm font-medium text-slate-900">{t('agReport.emptyTitle')}</p>
      <p className="mt-1 text-sm text-slate-500">{t('agReport.emptyDesc')}</p>
    </div>
  );
}
