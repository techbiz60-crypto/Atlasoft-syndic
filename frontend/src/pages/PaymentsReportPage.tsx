import { Fragment, useEffect, useMemo, useState } from 'react';
import { Printer } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { api } from '../lib/api';
import { extractErrorMessage } from '../context/AuthContext';
import type { Building, PaymentsReport, PaymentsReportRow } from '../types/resources';
import { PageHeader } from '../components/PageHeader';
import { Field, Select } from '../components/ui/Input';
import { Button } from '../components/ui/Button';
import { ErrorAlert } from '../components/ui/Alert';

const currentYear = new Date().getFullYear();
const yearOptions = [currentYear - 1, currentYear, currentYear + 1];

interface FloorGroup {
  floor: string;
  label: string;
  rows: PaymentsReportRow[];
}

/**
 * Most lots just have a bare "0", "1", "2"… in their floor field (matches
 * the field's own placeholder) — the report reads better with that spelled
 * out ("RDC", "Étage 1"...) instead of a lone digit. A floor already typed
 * as free text (e.g. "RDC HAUT") is shown as-is.
 */
function formatFloorLabel(floor: string, t: (key: string, options?: Record<string, unknown>) => string): string {
  if (floor === '0') {
    return t('paymentsReport.groundFloor');
  }
  if (/^\d+$/.test(floor)) {
    return t('paymentsReport.floorLabel', { number: floor });
  }
  return floor;
}

function groupByFloor(
  rows: PaymentsReportRow[],
  noFloorLabel: string,
  t: (key: string, options?: Record<string, unknown>) => string,
): FloorGroup[] {
  const groups: FloorGroup[] = [];

  for (const row of rows) {
    const floor = row.floor?.trim() || '';
    const label = floor ? formatFloorLabel(floor, t) : noFloorLabel;
    const lastGroup = groups[groups.length - 1];

    if (lastGroup && lastGroup.floor === floor) {
      lastGroup.rows.push(row);
    } else {
      groups.push({ floor, label, rows: [row] });
    }
  }

  return groups;
}

export function PaymentsReportPage() {
  const { t } = useTranslation();
  const monthLabels = t('common.monthsFull', { returnObjects: true }) as string[];

  const [buildings, setBuildings] = useState<Building[]>([]);
  const [buildingId, setBuildingId] = useState('');
  const [year, setYear] = useState(currentYear);
  const [report, setReport] = useState<PaymentsReport | null>(null);
  const [isLoading, setIsLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    api.get<{ data: Building[] }>('/api/buildings').then(({ data }) => {
      setBuildings(data.data);
      if (data.data.length > 0) {
        setBuildingId((previous) => previous || String(data.data[0].id));
      }
    });
  }, []);

  useEffect(() => {
    if (!buildingId) {
      return;
    }

    setIsLoading(true);
    api
      .get<PaymentsReport>('/api/reports/payments', { params: { year, building_id: buildingId } })
      .then(({ data }) => setReport(data))
      .catch((err) => setError(extractErrorMessage(err)))
      .finally(() => setIsLoading(false));
  }, [year, buildingId]);

  const groups = useMemo(
    () => (report ? groupByFloor(report.rows, t('paymentsReport.noFloor'), t) : []),
    [report, t],
  );

  return (
    <div>
      <PageHeader
        title={t('paymentsReport.title')}
        action={
          report && (
            <Button type="button" variant="secondary" onClick={() => window.print()} className="no-print">
              <Printer className="size-4" />
              {t('paymentsReport.printButton')}
            </Button>
          )
        }
      />

      <div className="no-print mb-5 flex flex-wrap items-end gap-3">
        <Field label={t('paymentsReport.buildingLabel')} htmlFor="report-building">
          <Select id="report-building" className="w-56" value={buildingId} onChange={(event) => setBuildingId(event.target.value)}>
            {buildings.map((building) => (
              <option key={building.id} value={building.id}>
                {building.name}
              </option>
            ))}
          </Select>
        </Field>

        <Field label={t('paymentsReport.yearLabel')} htmlFor="report-year">
          <Select id="report-year" className="w-32" value={year} onChange={(event) => setYear(Number(event.target.value))}>
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
      ) : report && report.rows.length > 0 ? (
        <div className="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm print:overflow-visible print:rounded-none print:border-0 print:shadow-none">
          <div className="hidden border-b border-slate-200 px-5 py-3 text-sm font-semibold text-slate-900 print:block">
            {report.building_name} — {report.year}
          </div>
          <table className="w-full border-collapse text-start text-xs">
            <thead>
              <tr className="bg-slate-50 text-[10px] font-semibold uppercase tracking-wide text-slate-500">
                <th className="border border-slate-300 px-3 py-2.5 text-start">{t('paymentsReport.colLot')}</th>
                <th className="border border-slate-300 px-3 py-2.5 text-start">{t('paymentsReport.colOwner')}</th>
                <th className="w-[68px] border border-slate-300 px-2 py-2.5 text-end">{t('paymentsReport.colRemaining')}</th>
                <th className="w-[68px] border border-slate-300 px-2 py-2.5 text-end">{t('paymentsReport.colPaidOld')}</th>
                {monthLabels.map((label) => (
                  <th key={label} className="w-[68px] border border-slate-300 px-2 py-2.5 text-end">
                    {label}
                  </th>
                ))}
                <th className="border border-slate-300 px-3 py-2.5 text-end">{t('paymentsReport.colTotal')}</th>
              </tr>
            </thead>
            <tbody>
              {groups.map((group) => (
                <Fragment key={`${group.floor}-${group.rows[0].lot_id}`}>
                  <tr className="bg-slate-900">
                    <td colSpan={17} className="border border-slate-900 px-3 py-2 text-center text-sm font-bold text-white">
                      {group.label}
                    </td>
                  </tr>
                  {group.rows.map((row) => (
                    <tr key={row.lot_id} className="hover:bg-slate-50/60">
                      <td className="border border-slate-300 px-3 py-2 font-medium text-slate-900">{row.lot_number}</td>
                      <td className="border border-slate-300 px-3 py-2 whitespace-nowrap text-slate-700">{row.owner_name}</td>
                      <td className="border border-slate-300 bg-rose-50 px-2 py-2 text-end font-medium text-rose-700">{row.opening_balance_remaining ?? ''}</td>
                      <td className="border border-slate-300 bg-emerald-50 px-2 py-2 text-end font-medium text-emerald-700">{row.opening_balance_paid_this_year ?? ''}</td>
                      {row.months.map((amount, index) => (
                        <td key={index} className="border border-slate-300 px-2 py-2 text-end text-slate-700">
                          {amount ?? ''}
                        </td>
                      ))}
                      <td className="border border-slate-300 px-3 py-2 text-end font-semibold text-slate-900">{row.total}</td>
                    </tr>
                  ))}
                </Fragment>
              ))}
            </tbody>
          </table>
        </div>
      ) : (
        <p className="text-sm text-slate-500">{t('paymentsReport.emptyDesc')}</p>
      )}
    </div>
  );
}
