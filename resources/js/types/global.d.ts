import type { AxiosInstance } from 'axios';

declare global {
    interface Window {
        axios: AxiosInstance;
    }

    // Ziggy route() helper — available via ziggy-js or server-side injection
    function route(name: string, params?: Record<string, unknown>, absolute?: boolean): string;
}

export {};
