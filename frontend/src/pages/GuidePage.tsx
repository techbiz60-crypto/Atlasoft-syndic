import { BookOpen, ChevronDown } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { PageHeader } from '../components/PageHeader';
import { useAuth } from '../context/AuthContext';

type GuideItem = { title: string; steps: string[] };
type GuideSection = { title: string; adminOnly?: boolean; items: GuideItem[] };

export function GuidePage() {
  const { t } = useTranslation();
  const { user } = useAuth();

  const sections = t('guide.sections', { returnObjects: true }) as GuideSection[];
  const visibleSections = sections.filter((section) => !section.adminOnly || user?.role === 'admin');

  return (
    <div>
      <PageHeader title={t('guide.title')} subtitle={t('guide.subtitle')} />

      <div className="flex flex-col gap-6">
        {visibleSections.map((section) => (
          <div key={section.title} className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <div className="mb-4 flex items-center gap-2">
              <BookOpen className="size-4.5 shrink-0 text-brand-600" />
              <h2 className="text-base font-semibold text-slate-900">{section.title}</h2>
              {section.adminOnly && (
                <span className="rounded-full bg-brand-50 px-2 py-0.5 text-xs font-medium text-brand-700">
                  {t('guide.adminOnlyBadge')}
                </span>
              )}
            </div>

            <div className="flex flex-col divide-y divide-slate-100">
              {section.items.map((item) => (
                <details key={item.title} className="group py-3 first:pt-0 last:pb-0" open>
                  <summary className="flex cursor-pointer list-none items-center justify-between gap-2 text-sm font-medium text-slate-800">
                    {item.title}
                    <ChevronDown className="size-4 shrink-0 text-slate-400 transition-transform group-open:rotate-180" />
                  </summary>
                  <ul className="mt-3 flex list-disc flex-col gap-1.5 ps-5 text-sm text-slate-600">
                    {item.steps.map((step) => (
                      <li key={step}>{step}</li>
                    ))}
                  </ul>
                </details>
              ))}
            </div>
          </div>
        ))}
      </div>
    </div>
  );
}
