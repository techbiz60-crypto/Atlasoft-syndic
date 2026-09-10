import { useState } from 'react';
import type { FormEvent } from 'react';
import { Link } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { extractErrorMessage } from '../context/AuthContext';
import { api } from '../lib/api';
import { Logo } from '../components/Logo';
import { LanguageSwitcher } from '../components/LanguageSwitcher';
import { Field, Input } from '../components/ui/Input';
import { Button } from '../components/ui/Button';
import { ErrorAlert, SuccessAlert } from '../components/ui/Alert';

export function ForgotPasswordPage() {
  const { t } = useTranslation();
  const [email, setEmail] = useState('');
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [message, setMessage] = useState<string | null>(null);

  async function handleSubmit(event: FormEvent) {
    event.preventDefault();
    setError(null);
    setIsSubmitting(true);

    try {
      const { data } = await api.post<{ message: string }>('/api/forgot-password', { email });
      setMessage(data.message);
    } catch (err) {
      setError(extractErrorMessage(err));
    } finally {
      setIsSubmitting(false);
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

        <div className="rounded-2xl border border-slate-200 bg-white p-8 shadow-xl shadow-slate-900/5">
          <h1 className="mb-2 text-center text-xl font-bold text-slate-900">{t('auth.forgotPassword.title')}</h1>
          <p className="mb-6 text-center text-sm text-slate-500">{t('auth.forgotPassword.subtitle')}</p>

          {message ? (
            <SuccessAlert>{message}</SuccessAlert>
          ) : (
            <form onSubmit={handleSubmit} className="flex flex-col gap-4">
              {error && <ErrorAlert>{error}</ErrorAlert>}

              <Field label={t('auth.login.email')} htmlFor="email">
                <Input
                  id="email"
                  type="email"
                  value={email}
                  onChange={(event) => setEmail(event.target.value)}
                  required
                  autoFocus
                />
              </Field>

              <Button type="submit" isLoading={isSubmitting} className="mt-2 w-full">
                {t('auth.forgotPassword.submit')}
              </Button>
            </form>
          )}

          <p className="mt-6 text-center text-sm text-slate-500">
            <Link to="/login" className="font-semibold text-brand-600 hover:text-brand-700">
              {t('auth.forgotPassword.backToLogin')}
            </Link>
          </p>
        </div>
      </div>
    </div>
  );
}
