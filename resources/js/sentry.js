import * as Sentry from '@sentry/browser';
import { BrowserTracing } from '@sentry/tracing';

const dsn = import.meta.env.VITE_SENTRY_DSN;

if (dsn) {
    Sentry.init({
        dsn,
        environment: import.meta.env.VITE_SENTRY_ENVIRONMENT || 'production',
        release: import.meta.env.VITE_SENTRY_RELEASE || 'unknown',
        integrations: [new BrowserTracing()],
        tracesSampleRate: 0.1,
        beforeSend(event) {
            // Basic redaction for sensitive fields
            if (event.request && event.request.data) {
                const data = { ...event.request.data };
                ['password', 'token', 'api_key', 'secret', 'credit_card'].forEach((k) => {
                    if (data[k]) data[k] = '[REDACTED]';
                });
                event.request.data = data;
            }
            return event;
        },
    });

    const ctx = window.NS_CONTEXT || {};
    if (ctx.user_id) {
        Sentry.setUser({ id: String(ctx.user_id), email: ctx.email, username: ctx.name });
        Sentry.setTag('user_id', String(ctx.user_id));
    }
    if (ctx.role) Sentry.setTag('role', String(ctx.role));
    if (ctx.harbor_id) Sentry.setTag('harbor_id', String(ctx.harbor_id));
    if (ctx.boat_id) Sentry.setTag('boat_id', String(ctx.boat_id));
    if (ctx.deal_id) Sentry.setTag('deal_id', String(ctx.deal_id));
    if (ctx.language) Sentry.setTag('language', String(ctx.language));
}

export default Sentry;
