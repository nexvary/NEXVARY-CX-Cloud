import i18n from 'i18next';
import { initReactI18next } from 'react-i18next';
import en from '../../lang/en/messages.json';
import ar from '../../lang/ar/messages.json';

i18n.use(initReactI18next).init({
    lng: 'en',
    fallbackLng: 'en',
    resources: {
        en: { translation: en },
        ar: { translation: ar },
    },
    interpolation: { escapeValue: false },
});

export default i18n;
