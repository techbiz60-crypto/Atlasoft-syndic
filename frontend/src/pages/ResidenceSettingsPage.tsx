import { useEffect, useState } from 'react';
import type { FormEvent } from 'react';
import { Save } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { api } from '../lib/api';
import { extractErrorMessage } from '../context/AuthContext';
import type { Residence } from '../types/auth';
import { PageHeader } from '../components/PageHeader';
import { Field, Input } from '../components/ui/Input';
import { Button } from '../components/ui/Button';
import { ErrorAlert, SuccessAlert } from '../components/ui/Alert';

export function ResidenceSettingsPage() {
  const { t } = useTranslation();
  const [isLoading, setIsLoading] = useState(true);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState(false);

  const [form, setForm] = useState({ name: '', address: '', lots_count: '', bank_rib: '', opening_balance: '' });

  useEffect(() => {
    api
      .get<{ data: Residence }>('/api/residence')
      .then(({ data }) => {
        setForm({
          name: data.data.name,
          address: data.data.address ?? '',
          lots_count: String(data.data.lots_count),
          bank_rib: data.data.bank_rib ?? '',
          opening_balance: String(data.data.opening_balance ?? 0),
        });
      })
      .catch((err) => setError(extractErrorMessage(err)))
      .finally(() => setIsLoading(false));
  }, []);

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
      await api.put<{ data: Residence }>('/api/residence', {
        ...form,
        lots_count: Number(form.lots_count),
        opening_balance: Math.round(Number(form.opening_balance)),
      });
      setSuccess(true);
    } catch (err) {
      setError(extractErrorMessage(err));
    } finally {
      setIsSubmitting(false);
    }
  }

  if (isLoading) {
    return <p className="text-sm text-slate-500">{t('common.loading')}</p>;
  }

  return (
    <div>
      <PageHeader title={t('residenceSettings.title')} subtitle={t('residenceSettings.subtitle')} />

      <form
        onSubmit={handleSubmit}
        className="flex max-w-lg flex-col gap-4 rounded-xl border border-slate-200 bg-white p-6 shadow-sm"
      >
        {error && <ErrorAlert>{error}</ErrorAlert>}
        {success && <SuccessAlert>{t('residenceSettings.savedMessage')}</SuccessAlert>}

        <Field label={t('residenceSettings.nameLabel')} htmlFor="name">
          <Input id="name" value={form.name} onChange={updateField('name')} required />
        </Field>

        <Field label={t('residenceSettings.addressLabel')} htmlFor="address">
          <Input id="address" value={form.address} onChange={updateField('address')} />
        </Field>

        <Field label={t('residenceSettings.lotsCountLabel')} htmlFor="lots_count">
          <Input
            id="lots_count"
            type="number"
            min={1}
            value={form.lots_count}
            onChange={updateField('lots_count')}
            required
          />
        </Field>

        <Field label={t('residenceSettings.bankRibLabel')} htmlFor="bank_rib">
          <Input
            id="bank_rib"
            value={form.bank_rib}
            onChange={updateField('bank_rib')}
            placeholder={t('residenceSettings.bankRibPlaceholder')}
          />
        </Field>

        <Field label={t('residenceSettings.openingBalanceLabel')} htmlFor="opening_balance">
          <Input
            id="opening_balance"
            type="number"
            step={1}
            value={form.opening_balance}
            onChange={updateField('opening_balance')}
          />
        </Field>

        <Button type="submit" isLoading={isSubmitting} className="mt-2 self-start">
          <Save className="size-4" />
          {t('residenceSettings.saveButton')}
        </Button>
      </form>
    </div>
  );
}
