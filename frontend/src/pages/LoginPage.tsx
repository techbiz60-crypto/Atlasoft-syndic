import { useState } from 'react';
import type { FormEvent } from 'react';
import { Link, useNavigate, useSearchParams } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { extractErrorMessage, useAuth } from '../context/AuthContext';
import { Logo } from '../components/Logo';
import { LanguageSwitcher } from '../components/LanguageSwitcher';
import { Field, Input } from '../components/ui/Input';
import { Button } from '../components/ui/Button';
import { ErrorAlert, SuccessAlert } from '../components/ui/Alert';

export function LoginPage() {
  const { login } = useAuth();
  const navigate = useNavigate();
  const { t } = useTranslation();
  const [searchParams] = useSearchParams();
  const [error, setError] = useState<string | null>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const justVerified = searchParams.get('verified') === '1';

  async function handleSubmit(event: FormEvent) {
    event.preventDefault();
    setError(null);
    setIsSubmitting(true);

    try {
      await login({ email, password });
      navigate('/dashboard');
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
          <h1 className="mb-6 text-center text-xl font-bold text-slate-900">{t('auth.login.title')}</h1>

          <form onSubmit={handleSubmit} className="flex flex-col gap-4">
            {justVerified && <SuccessAlert>{t('auth.login.emailVerified')}</SuccessAlert>}
            {error && <ErrorAlert>{error}</ErrorAlert>}

            <Field label={t('auth.login.email')} htmlFor="email">
              <Input
                id="email"
                type="email"
                value={email}
                onChange={(event) => setEmail(event.target.value)}
                required
              />
            </Field>

            <Field label={t('auth.login.password')} htmlFor="password">
              <Input
                id="password"
                type="password"
                value={password}
                onChange={(event) => setPassword(event.target.value)}
                required
              />
            </Field>

            <Button type="submit" isLoading={isSubmitting} className="mt-2 w-full">
              {t('auth.login.submit')}
            </Button>

            <p className="text-center text-sm text-slate-500">
              {t('auth.login.noAccount')}{' '}
              <Link to="/register" className="font-semibold text-brand-600 hover:text-brand-700">
                {t('auth.login.createResidence')}
              </Link>
            </p>
          </form>
        </div>
      </div>
    </div>
  );
}
