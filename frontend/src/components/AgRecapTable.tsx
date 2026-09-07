import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { api } from '../lib/api';
import { extractErrorMessage } from '../context/AuthContext';
import type { AgRecap, AgRecapLine } from '../types/resources';
import { ErrorAlert } from './ui/Alert';

function formatAmount(amount: number): string {
  return amount.toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function formatPercent(rate: number): string {
  return `${(rate * 100).toLocaleString('fr-FR', { minimumFractionDigits: 0, maximumFractionDigits: 2 })} %`;
}

/**
 * The per-building recap an AG reads, laid out like the spreadsheet it
 * replaces: one column per block, one line per indicator, totals on the
 * right.
 */
export function AgRecapTable({ year }: { year: number }) {
  const { t } = useTranslation();

  const [recap, setRecap] = useState<AgRecap | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    setIsLoading(true);
    api
      .get<AgRecap>('/api/reports/ag-recap', { params: { year } })
      .then(({ data }) => setRecap(data))
      .catch((err) => setError(extractErrorMessage(err)))
      .finally(() => setIsLoading(false));
  }, [year]);

  if (error) {
    return <ErrorAlert>{error}</ErrorAlert>;
  }

  if (isLoading) {
    return <p className="text-sm text-slate-500">{t('common.loading')}</p>;
  }

  if (!recap || recap.buildings.length === 0) {
    return <p className="text-sm text-slate-500">{t('agRecap.empty')}</p>;
  }

  const lines: { label: string; render: (line: AgRecapLine) => string; strong?: boolean }[] = [
    { label: t('agRecap.lotsCount'), render: (l) => String(l.lots_count) },
    { label: t('agRecap.payingLotsCount', { year: recap.year }), render: (l) => String(l.paying_lots_count) },
    { label: t('agRecap.payingLotsRate'), render: (l) => formatPercent(l.paying_lots_rate) },
    { label: t('agRecap.duesTotal'), render: (l) => formatAmount(l.dues_total) },
    { label: t('agRecap.collectedForYear', { year: recap.year }), render: (l) => formatAmount(l.collected_for_year) },
    { label: t('agRecap.collectionRate', { year: recap.year }), render: (l) => formatPercent(l.collection_rate), strong: true },
    { label: t('agRecap.collectedForArrears', { year: recap.year }), render: (l) => formatAmount(l.collected_for_arrears) },
    { label: t('agRecap.collectedTotal', { year: recap.year }), render: (l) => formatAmount(l.collected_total), strong: true },
  ];

  return (
    <div>
      <div className="print-bw overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm print:overflow-visible print:rounded-none print:border-0 print:shadow-none">
        <div className="hidden border-b border-slate-200 px-5 py-3 text-sm font-semibold text-slate-900 print:block">
          {t('agRecap.printTitle', { residence: recap.residence_name, year: recap.year })}
        </div>

        <table className="w-full border-collapse text-start text-xs">
          <thead>
            <tr className="bg-slate-50 text-[10px] font-semibold uppercase tracking-wide text-slate-500">
              <th className="min-w-[280px] border border-slate-300 px-3 py-2.5 text-start">{t('agRecap.indicator')}</th>
              {recap.buildings.map((building) => (
                <th key={building.id} className="min-w-[92px] border border-slate-300 px-3 py-2.5 text-end">
                  {building.name}
                </th>
              ))}
              <th className="min-w-[104px] border border-slate-300 bg-slate-100 px-3 py-2.5 text-end">{t('agRecap.total')}</th>
            </tr>
          </thead>
          <tbody>
            {lines.map((line) => (
              <tr key={line.label} className={line.strong ? 'bg-slate-50' : 'hover:bg-slate-50/60'}>
                <td
                  className={`border border-slate-300 px-3 py-2 text-slate-700 ${line.strong ? 'font-semibold text-slate-900' : ''}`}
                >
                  {line.label}
                </td>
                {recap.buildings.map((building) => (
                  <td
                    key={building.id}
                    className={`border border-slate-300 px-3 py-2 text-end text-slate-700 ${line.strong ? 'font-semibold text-slate-900' : ''}`}
                  >
                    {line.render(building)}
                  </td>
                ))}
                <td className="border border-slate-300 bg-slate-100 px-3 py-2 text-end font-bold text-slate-900">
                  {line.render(recap.total)}
                </td>
              </tr>
            ))}
          </tbody>
        </table>

        <p className="border-t border-slate-200 px-5 py-3 text-xs text-slate-500">{t('agRecap.basisNote')}</p>
      </div>
    </div>
  );
}
