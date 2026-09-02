import i18n from 'i18next';
import { initReactI18next } from 'react-i18next';
import { fr } from '../locales/fr';
import { ar } from '../locales/ar';

const storedLang = typeof window !== 'undefined' ? window.localStorage.getItem('atlasoft-lang') : null;

i18n.use(initReactI18next).init({
  resources: {
    fr: { translation: fr },
    ar: { translation: ar },
  },
  lng: storedLang ?? 'fr',
  fallbackLng: 'fr',
  interpolation: {
    escapeValue: false,
  },
});

export default i18n;
