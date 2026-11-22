/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './resources/js/**/*.{js,ts,jsx,tsx}',
        './resources/views/**/*.blade.php',
    ],
    theme: {
        extend: {
            colors: {
                'nx-bg':     'var(--nx-bg-base)',
                'nx-panel':  'var(--nx-bg-panel)',
                'nx-border': 'var(--nx-border)',
                'nx-silver': 'var(--nx-silver)',
                'nx-crimson':'var(--nx-crimson)',
                'nx-muted':  'var(--nx-text-muted)',
            },
            fontFamily: {
                sans: ['Inter', 'Cairo', 'Noto Sans Arabic', 'ui-sans-serif', 'system-ui'],
            },
        },
    },
    plugins: [],
};
