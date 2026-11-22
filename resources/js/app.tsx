import './bootstrap';
import '../css/nexvary.css';

import { createInertiaApp } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createRoot } from 'react-dom/client';
import i18n from './i18n';
import { I18nextProvider } from 'react-i18next';

createInertiaApp({
    title: (title) => `${title} — NEXVARY CX Cloud`,
    resolve: (name) =>
        resolvePageComponent(
            `./pages/${name}.tsx`,
            import.meta.glob('./pages/**/*.tsx'),
        ),
    setup({ el, App, props }) {
        const root = createRoot(el);

        // Apply RTL for Arabic/Persian/Urdu
        const locale: string =
            (props.initialPage.props as { locale?: string }).locale ?? 'en';
        const rtlLocales = ['ar', 'fa', 'ur', 'he'];
        document.documentElement.dir = rtlLocales.includes(locale) ? 'rtl' : 'ltr';
        document.documentElement.lang = locale;

        i18n.changeLanguage(locale);

        root.render(
            <I18nextProvider i18n={i18n}>
                <App {...props} />
            </I18nextProvider>,
        );
    },
    progress: {
        color: '#e02020',
    },
});
