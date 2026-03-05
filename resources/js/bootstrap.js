import axios from 'axios';
import Sentry from './sentry';

window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

// Axios interceptor for capturing API errors in Sentry
window.axios.interceptors.response.use(
    (response) => response,
    (error) => {
        if (Sentry && error) {
            const tags = {};
            const cfg = error.config || {};
            if (cfg.url) tags.url = cfg.url;
            if (cfg.method) tags.method = cfg.method;
            const status = error.response?.status;
            if (status) tags.status = String(status);

            Sentry.withScope((scope) => {
                Object.entries(tags).forEach(([k, v]) => scope.setTag(k, v));
                if (error.response?.data) {
                    scope.setExtra('response', error.response.data);
                }
                scope.setLevel('error');
                Sentry.captureException(error);
            });
        }
        return Promise.reject(error);
    }
);
