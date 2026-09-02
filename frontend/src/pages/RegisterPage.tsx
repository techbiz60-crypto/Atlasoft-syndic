import { useState } from 'react';
import type { FormEvent } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { extractErrorMessage, useAuth } from '../context/AuthContext';
import { Logo } from '../components/Logo';
import { LanguageSwitcher } from '../components/LanguageSwitcher';
import { Field, Input } from '../components/ui/Input';
import { Button } from '../components/ui/Button';
import { ErrorAlert } from '../components/ui/Alert';

export function RegisterPage() {
  const { register } = useAuth();
  const navigate = useNavigate();
  const { t } = useTranslation();
  const [error, setError] = useState<string | null>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);

  const [form, setForm] = useState({
    residence_name: '',
    lots_count: '',
    name: '',
    email: '',
    whatsapp_number: '',
    password: '',
    password_confirmation: '',
  });

  function updateField(field: keyof typeof form) {
    return (event: React.ChangeEvent<HTMLInputElement>) => {
      setForm((previous) => ({ ...previous, [field]: event.target.value }));
    };
  }

  async function handleSubmit(event: FormEvent) {
    event.preventDefault();
    setError(null);
    setIsSubmitting(true);

    try {
      await register({
        ...form,
        lots_count: Number(form.lots_count),
      });
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
          <div className="mb-6 text-center">
            <h1 className="text-xl font-bold text-slate-900">{t('auth.register.title')}</h1>
            <p className="mt-1 text-sm text-slate-500">{t('auth.register.trial')}</p>
          </div>

          <form onSubmit={handleSubmit} className="flex flex-col gap-4">
            {error && <ErrorAlert>{error}</ErrorAlert>}

            <Field label={t('auth.register.residenceName')} htmlFor="residence_name">
              <Input
                id="residence_name"
                value={form.residence_name}
                onChange={updateField('residence_name')}
                required
              />
            </Field>

            <Field label={t('auth.register.lotsCount')} htmlFor="lots_count">
              <Input
                id="lots_count"
                type="number"
                min={1}
                value={form.lots_count}
                onChange={updateField('lots_count')}
                required
              />
            </Field>

            <Field label={t('auth.register.yourName')} htmlFor="name">
              <Input id="name" value={form.name} onChange={updateField('name')} required />
            </Field>

            <Field label={t('auth.register.email')} htmlFor="email">
              <Input id="email" type="email" value={form.email} onChange={updateField('email')} required />
            </Field>

            <Field label={t('auth.register.whatsapp')} htmlFor="whatsapp_number">
              <Input
                id="whatsapp_number"
                type="tel"
                placeholder="+212600000000"
                value={form.whatsapp_number}
                onChange={updateField('whatsapp_number')}
                required
              />
            </Field>

            <Field label={t('auth.register.password')} htmlFor="password">
              <Input
                id="password"
                type="password"
                minLength={8}
                value={form.password}
                onChange={updateField('password')}
                required
              />
            </Field>

            <Field label={t('auth.register.passwordConfirmation')} htmlFor="password_confirmation">
              <Input
                id="password_confirmation"
                type="password"
                minLength={8}
                value={form.password_confirmation}
                onChange={updateField('password_confirmation')}
                required
              />
            </Field>

            <Button type="submit" isLoading={isSubmitting} className="mt-2 w-full">
              {t('auth.register.submit')}
            </Button>

            <p className="text-center text-sm text-slate-500">
              {t('auth.register.alreadyRegistered')}{' '}
              <Link to="/login" className="font-semibold text-brand-600 hover:text-brand-700">
                {t('auth.register.login')}
              </Link>
            </p>
          </form>
        </div>
      </div>
    </div>
  );
}
