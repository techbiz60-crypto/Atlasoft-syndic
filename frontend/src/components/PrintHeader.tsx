import { useTranslation } from 'react-i18next';
import { Logo } from './Logo';

export interface PrintMetric {
  label: string;
  value: string | number;
  highlight?: boolean;
}

export interface PrintHeaderProps {
  title: string;
  subtitle?: string;
  residenceName?: string | null;
  buildingName?: string | null;
  period?: string | number | null;
  metrics?: PrintMetric[];
}

export function PrintHeader({
  title,
  subtitle,
  residenceName,
  buildingName,
  period,
  metrics,
}: PrintHeaderProps) {
  const { t, i18n } = useTranslation();
  const currentDate = new Date().toLocaleDateString(i18n.language === 'ar' ? 'ar-MA' : 'fr-FR', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });

  return (
    <div className="hidden print:block print:mb-5 print:border-b-2 print:border-slate-800 print:pb-4">
      {/* Top row: Residence info and Logo / Date */}
      <div className="flex items-start justify-between gap-4">
        <div>
          {residenceName && (
            <div className="text-lg font-bold uppercase tracking-wide text-slate-900">
              {residenceName}
            </div>
          )}
          <div className="flex items-center gap-2 text-xs text-slate-600">
            {buildingName && (
              <span className="font-semibold text-slate-800">
                {t('print.building', { defaultValue: 'Immeuble' })} : {buildingName}
              </span>
            )}
            {period && (
              <>
                {buildingName && <span>•</span>}
                <span className="font-medium text-slate-700">
                  {t('print.period', { defaultValue: 'Exercice / Période' })} : {period}
                </span>
              </>
            )}
          </div>
        </div>

        <div className="text-end">
          <div className="flex items-center justify-end gap-2">
            <Logo className="h-7 w-auto" />
          </div>
          <div className="mt-1 text-[10px] text-slate-500">
            {t('print.date', { defaultValue: 'Édité le' })} : {currentDate}
          </div>
        </div>
      </div>

      {/* Main Document Title */}
      <div className="mt-3 flex items-center justify-between rounded-lg bg-slate-100 px-4 py-2">
        <div>
          <h1 className="text-base font-bold uppercase tracking-wider text-slate-900">
            {title}
          </h1>
          {subtitle && (
            <p className="text-xs text-slate-600">{subtitle}</p>
          )}
        </div>

        {/* Optional KPI pills on header */}
        {metrics && metrics.length > 0 && (
          <div className="flex items-center gap-4">
            {metrics.map((metric) => (
              <div key={metric.label} className="text-end">
                <div className="text-[10px] font-medium uppercase text-slate-500">
                  {metric.label}
                </div>
                <div
                  className={`text-sm font-bold ${
                    metric.highlight ? 'text-rose-700' : 'text-slate-900'
                  }`}
                >
                  {metric.value}
                </div>
              </div>
            ))}
          </div>
        )}
      </div>
    </div>
  );
}
