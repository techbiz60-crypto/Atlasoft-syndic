import { useState } from 'react';
import { MailCheck } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { api } from '../lib/api';
import { extractErrorMessage, useAuth } from '../context/AuthContext';
import { Logo } from '../components/Logo';
import { LanguageSwitcher } from '../components/LanguageSwitcher';
import { Button } from '../components/ui/Button';
import { ErrorAlert, SuccessAlert } from '../components/ui/Alert';

export function VerifyEmailPage() {
  const { user, logout } = useAuth();
  const { t } = useTranslation();
  const [isSending, setIsSending] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState<string | null>(null);

  async function handleResend() {
    setError(null);
    setSuccess(null);
    setIsSending(true);

    try {
      const { data } = await api.post<{ message: string }>('/api/email/verification-notification');
      setSuccess(data.message);
    } catch (err) {
      setError(extractErrorMessage(err));
    } finally {
      setIsSending(false);
    }
  }

  return (
    <div className="flex min-h-screen items-center justify-center bg-slate-50 px-4 py-12">
      <div className="w-full max-w-md">
        <div className="mb-4 flex justify-end">
          <LanguageSwitcher />
        </div>
        <div className="mb-8 flex justify-center">
          <Logo />
        </div>

        <div className="rounded-2xl border border-slate-200 bg-white p-8 text-center shadow-xl shadow-slate-900/5">
          <div className="mx-auto mb-4 flex size-12 items-center justify-center rounded-full bg-brand-50 text-brand-700">
            <MailCheck className="size-6" />
          </div>
          <h1 className="text-xl font-bold text-slate-900">{t('auth.verifyEmail.title')}</h1>
          <p className="mt-2 text-sm text-slate-500">
            {t('auth.verifyEmail.description', { email: user?.email })}
          </p>

          <div className="mt-6 flex flex-col gap-3">
            {error && <ErrorAlert>{error}</ErrorAlert>}
            {success && <SuccessAlert>{success}</SuccessAlert>}

            <Button onClick={handleResend} isLoading={isSending} className="w-full">
              {t('auth.verifyEmail.resendButton')}
            </Button>
            <button
              type="button"
              onClick={() => logout()}
              className="text-sm font-medium text-slate-500 hover:text-slate-700"
            >
              {t('auth.verifyEmail.logout')}
            </button>
          </div>
        </div>
      </div>
    </div>
  );
}
