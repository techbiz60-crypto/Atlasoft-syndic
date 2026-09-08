import { useState } from 'react';
import type { FormEvent } from 'react';
import { KeyRound } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { api } from '../lib/api';
import { extractErrorMessage, useAuth } from '../context/AuthContext';
import { PageHeader } from '../components/PageHeader';
import { Field, Input } from '../components/ui/Input';
import { Button } from '../components/ui/Button';
import { ErrorAlert, SuccessAlert } from '../components/ui/Alert';

const emptyForm = { current_password: '', password: '', password_confirmation: '' };

export function MyAccountPage() {
  const { t } = useTranslation();
  const { user } = useAuth();
  const [form, setForm] = useState(emptyForm);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState(false);

  function updateField(field: keyof typeof form) {
    return (event: React.ChangeEvent<HTMLInputElement>) => {
      setForm((previous) => ({ ...previous, [field]: event.target.value }));
    };
  }

  async function handleSubmit(event: FormEvent) {
    event.preventDefault();
    setError(null);
    setSuccess(false);
    setIsSubmitting(true);

    try {
      await api.put('/api/password', form);
      setForm(emptyForm);
      setSuccess(true);
    } catch (err) {
      setError(extractErrorMessage(err));
    } finally {
      setIsSubmitting(false);
    }
  }

  if (!user) {
    return null;
  }

  return (
    <div>
      <PageHeader title={t('myAccount.title')} subtitle={t('myAccount.subtitle', { name: user.name, email: user.email })} />

      <form
        onSubmit={handleSubmit}
        className="flex max-w-md flex-col gap-4 rounded-xl border border-slate-200 bg-white p-6 shadow-sm"
      >
        {error && <ErrorAlert>{error}</ErrorAlert>}
        {success && <SuccessAlert>{t('myAccount.savedMessage')}</SuccessAlert>}

        <Field label={t('myAccount.currentPasswordLabel')} htmlFor="current_password">
          <Input
            id="current_password"
            type="password"
            autoComplete="current-password"
            value={form.current_password}
            onChange={updateField('current_password')}
            required
          />
        </Field>

        <Field label={t('myAccount.newPasswordLabel')} htmlFor="password">
          <Input
            id="password"
            type="password"
            autoComplete="new-password"
            minLength={8}
            value={form.password}
            onChange={updateField('password')}
            required
          />
        </Field>

        <Field label={t('myAccount.confirmPasswordLabel')} htmlFor="password_confirmation">
          <Input
            id="password_confirmation"
            type="password"
            autoComplete="new-password"
            minLength={8}
            value={form.password_confirmation}
            onChange={updateField('password_confirmation')}
            required
          />
        </Field>

        <Button type="submit" isLoading={isSubmitting} className="mt-2 self-start">
          <KeyRound className="size-4" />
          {t('myAccount.saveButton')}
        </Button>
      </form>
    </div>
  );
}
