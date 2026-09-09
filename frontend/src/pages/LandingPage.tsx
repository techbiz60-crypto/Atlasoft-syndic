import { Link } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { AlertTriangle, Check, FileText, Landmark, MessageCircle, Receipt, Users } from 'lucide-react';
import { Logo } from '../components/Logo';
import { LanguageSwitcher } from '../components/LanguageSwitcher';
import { Button } from '../components/ui/Button';
import { upgradeablePlans } from '../lib/plans';

const contactWhatsapp = import.meta.env.VITE_CONTACT_WHATSAPP_NUMBER as string | undefined;

const featureIcons = [Receipt, Landmark, AlertTriangle, FileText, Users];

const pricingPlans = [
  { plan: 'free', label: 'Gratuit', maxLots: 6, monthlyPrice: 0 },
  ...upgradeablePlans.filter((entry) => entry.plan !== 'custom'),
  { plan: 'custom', label: 'Sur devis', maxLots: null, monthlyPrice: null },
];

export function LandingPage() {
  const { t } = useTranslation();
  const features = t('landing.features', { returnObjects: true }) as { title: string; description: string }[];

  return (
    <div className="min-h-screen bg-white">
      <header className="mx-auto flex max-w-6xl items-center justify-between px-4 py-6 sm:px-6">
        <Logo className="h-10" />
        <div className="flex items-center gap-3">
          <LanguageSwitcher />
          <Link to="/login" className="text-sm font-semibold text-slate-700 hover:text-brand-600">
            {t('landing.nav.login')}
          </Link>
          <Link to="/register">
            <Button size="sm">{t('landing.nav.signup')}</Button>
          </Link>
        </div>
      </header>

      <section className="mx-auto max-w-4xl px-4 py-16 text-center sm:px-6 sm:py-24">
        <h1 className="text-3xl font-bold tracking-tight text-slate-900 sm:text-5xl">{t('landing.hero.title')}</h1>
        <p className="mx-auto mt-5 max-w-2xl text-lg text-slate-600">{t('landing.hero.subtitle')}</p>
        <div className="mt-8 flex flex-wrap items-center justify-center gap-3">
          <Link to="/register">
            <Button size="md" className="px-6">
              {t('landing.hero.ctaPrimary')}
            </Button>
          </Link>
          <Link to="/login">
            <Button size="md" variant="secondary" className="px-6">
              {t('landing.hero.ctaSecondary')}
            </Button>
          </Link>
        </div>
        <p className="mt-4 text-sm text-slate-500">{t('landing.hero.trial')}</p>
      </section>

      <section className="border-y border-slate-100 bg-slate-50 py-16">
        <div className="mx-auto max-w-6xl px-4 sm:px-6">
          <h2 className="text-center text-2xl font-bold text-slate-900">{t('landing.featuresTitle')}</h2>
          <div className="mt-10 grid gap-6 sm:grid-cols-2 lg:grid-cols-5">
            {features.map((feature, index) => {
              const Icon = featureIcons[index] ?? Receipt;
              return (
                <div key={feature.title} className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                  <span className="flex size-10 items-center justify-center rounded-lg bg-brand-50 text-brand-600">
                    <Icon className="size-5" strokeWidth={2.25} />
                  </span>
                  <p className="mt-4 text-sm font-semibold text-slate-900">{feature.title}</p>
                  <p className="mt-1.5 text-sm text-slate-500">{feature.description}</p>
                </div>
              );
            })}
          </div>
        </div>
      </section>

      <section className="mx-auto max-w-6xl px-4 py-16 sm:px-6">
        <h2 className="text-center text-2xl font-bold text-slate-900">{t('landing.pricingTitle')}</h2>
        <p className="mx-auto mt-2 max-w-xl text-center text-sm text-slate-500">{t('landing.pricingSubtitle')}</p>

        <div className="mt-10 grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
          {pricingPlans.map((entry) => (
            <div
              key={entry.plan}
              className="flex flex-col rounded-xl border border-slate-200 bg-white p-5 shadow-sm"
            >
              <p className="text-sm font-semibold text-slate-900">{entry.label}</p>
              <p className="mt-2">
                {entry.monthlyPrice !== null ? (
                  <>
                    <span className="text-2xl font-bold text-slate-900">{entry.monthlyPrice}</span>
                    <span className="text-sm text-slate-500"> DH/{t('landing.perMonth')}</span>
                  </>
                ) : (
                  <span className="text-lg font-bold text-slate-900">{t('landing.custom')}</span>
                )}
              </p>
              <p className="mt-1 text-xs text-slate-500">
                {entry.maxLots !== null
                  ? t('landing.upToLots', { count: entry.maxLots })
                  : t('landing.unlimitedLots')}
              </p>
              <ul className="mt-4 flex flex-1 flex-col gap-2">
                {(t('landing.planIncludes', { returnObjects: true }) as string[]).map((line) => (
                  <li key={line} className="flex items-start gap-2 text-xs text-slate-600">
                    <Check className="mt-0.5 size-3.5 shrink-0 text-brand-600" />
                    {line}
                  </li>
                ))}
              </ul>
              <Link to="/register" className="mt-4">
                <Button size="sm" variant="secondary" className="w-full">
                  {t('landing.nav.signup')}
                </Button>
              </Link>
            </div>
          ))}
        </div>
      </section>

      <footer className="border-t border-slate-100 bg-slate-50 py-10">
        <div className="mx-auto flex max-w-6xl flex-col items-center gap-4 px-4 text-center sm:px-6">
          <Logo className="h-8 opacity-80" />
          {contactWhatsapp && (
            <a
              href={`https://wa.me/${contactWhatsapp.replace(/[^0-9]/g, '')}`}
              target="_blank"
              rel="noreferrer"
              className="flex items-center gap-2 text-sm font-medium text-slate-600 hover:text-brand-600"
            >
              <MessageCircle className="size-4" />
              {contactWhatsapp}
            </a>
          )}
          <p className="text-xs text-slate-400">© {new Date().getFullYear()} Atlasoft Syndic</p>
        </div>
      </footer>
    </div>
  );
}
