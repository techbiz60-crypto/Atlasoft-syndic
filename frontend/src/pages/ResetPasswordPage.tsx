import { useState } from 'react';
import type { FormEvent } from 'react';
import { Link, useNavigate, useSearchParams } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { extractErrorMessage } from '../context/AuthContext';
import { api } from '../lib/api';
import { Logo } from '../components/Logo';
import { LanguageSwitcher } from '../components/LanguageSwitcher';
import { Field, Input } from '../components/ui/Input';
import { Button } from '../components/ui/Button';
import { ErrorAlert } from '../components/ui/Alert';

export function ResetPasswordPage() {
  const { t } = useTranslation();
  const navigate = useNavigate();
  const [searchParams] = useSearchParams();
  const token = searchParams.get('token') ?? '';
  const email = searchParams.get('email') ?? '';

  const [password, setPassword] = useState('');
  const [passwordConfirmation, setPasswordConfirmation] = useState('');
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);

  async function handleSubmit(event: FormEvent) {
    event.preventDefault();
    setError(null);
    setIsSubmitting(true);

    try {
      await api.post('/api/reset-password', {
        token,
        email,
        password,
        password_confirmation: passwordConfirmation,
      });
      navigate('/login?reset=1');
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
          <h1 className="mb-6 text-center text-xl font-bold text-slate-900">{t('auth.resetPassword.title')}</h1>

          {!token || !email ? (
            <ErrorAlert>{t('auth.resetPassword.missingLink')}</ErrorAlert>
          ) : (
            <form onSubmit={handleSubmit} className="flex flex-col gap-4">
              {error && <ErrorAlert>{error}</ErrorAlert>}

              <Field label={t('auth.register.password')} htmlFor="password">
                <Input
                  id="password"
                  type="password"
                  minLength={8}
                  value={password}
                  onChange={(event) => setPassword(event.target.value)}
                  required
                  autoFocus
                />
              </Field>

              <Field label={t('auth.register.passwordConfirmation')} htmlFor="password_confirmation">
                <Input
                  id="password_confirmation"
                  type="password"
                  minLength={8}
                  value={passwordConfirmation}
                  onChange={(event) => setPasswordConfirmation(event.target.value)}
                  required
                />
              </Field>

              <Button type="submit" isLoading={isSubmitting} className="mt-2 w-full">
                {t('auth.resetPassword.submit')}
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
