import { StrictMode } from 'react'
import { createRoot } from 'react-dom/client'
import * as Sentry from '@sentry/react'
import './index.css'
import i18n from './lib/i18n'
import App from './App.tsx'

// Left unset in dev (no VITE_SENTRY_DSN there) — the SDK simply never
// initializes, so local errors don't count against the free error quota
// or mix in with real production ones. Errors only, no performance
// tracing (tracesSampleRate 0) — quota is precious, and this is about
// finding out about bugs, not profiling.
const sentryDsn = import.meta.env.VITE_SENTRY_DSN
if (sentryDsn) {
  Sentry.init({ dsn: sentryDsn, tracesSampleRate: 0 })
}

document.documentElement.lang = i18n.language
document.documentElement.dir = i18n.language === 'ar' ? 'rtl' : 'ltr'

createRoot(document.getElementById('root')!).render(
  <StrictMode>
    <App />
  </StrictMode>,
)
