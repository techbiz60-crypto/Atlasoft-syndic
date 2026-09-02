import { useTranslation } from 'react-i18next';

type Variant = 'dark' | 'light';

const wrapperClasses: Record<Variant, string> = {
  dark: 'bg-white/5',
  light: 'border border-slate-200 bg-slate-100',
};

const inactiveClasses: Record<Variant, string> = {
  dark: 'text-slate-400 hover:text-white',
  light: 'text-slate-500 hover:text-slate-700',
};

export function LanguageSwitcher({
  className = '',
  variant = 'light',
}: {
  className?: string;
  variant?: Variant;
}) {
  const { i18n, t } = useTranslation();

  function changeLanguage(lang: 'fr' | 'ar') {
    i18n.changeLanguage(lang);
    window.localStorage.setItem('atlasoft-lang', lang);
  }

  const optionClass = (lang: 'fr' | 'ar') =>
    `flex-1 rounded-md px-2.5 py-1.5 text-xs font-semibold transition-colors ${
      i18n.language === lang ? 'bg-brand-600 text-white shadow-sm' : inactiveClasses[variant]
    }`;

  return (
    <div className={`inline-flex items-center gap-1 rounded-lg p-1 ${wrapperClasses[variant]} ${className}`}>
      <button type="button" onClick={() => changeLanguage('fr')} className={optionClass('fr')}>
        {t('common.languageFr')}
      </button>
      <button type="button" onClick={() => changeLanguage('ar')} className={optionClass('ar')}>
        {t('common.languageAr')}
      </button>
    </div>
  );
}
