import { StrictMode } from 'react'
import { createRoot } from 'react-dom/client'
import './index.css'
import i18n from './lib/i18n'
import App from './App.tsx'

document.documentElement.lang = i18n.language
document.documentElement.dir = i18n.language === 'ar' ? 'rtl' : 'ltr'

createRoot(document.getElementById('root')!).render(
  <StrictMode>
    <App />
  </StrictMode>,
)
