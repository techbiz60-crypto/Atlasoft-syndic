import { useEffect, useState } from 'react';
import type { FormEvent } from 'react';
import { Plus, Save, Trash2 } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { api } from '../lib/api';
import { extractErrorMessage } from '../context/AuthContext';
import type { Residence } from '../types/auth';
import type { GeneralAssembly } from '../types/resources';
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

      <GeneralAssembliesSection />
    </div>
  );
}

function GeneralAssembliesSection() {
  const { t } = useTranslation();
  const [assemblies, setAssemblies] = useState<GeneralAssembly[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [feedback, setFeedback] = useState<'saved' | 'cleared' | null>(null);
  const [drafts, setDrafts] = useState<Record<number, string>>({});
  const [newYear, setNewYear] = useState('');
  const [newDate, setNewDate] = useState('');

  async function loadAssemblies() {
    setIsLoading(true);
    try {
      const { data } = await api.get<{ data: GeneralAssembly[] }>('/api/general-assemblies');
      setAssemblies(data.data);
      setDrafts(Object.fromEntries(data.data.map((assembly) => [assembly.exercise_year, assembly.held_on])));
    } catch (err) {
      setError(extractErrorMessage(err));
    } finally {
      setIsLoading(false);
    }
  }

  useEffect(() => {
    loadAssemblies();
  }, []);

  async function saveYear(year: number, heldOn: string) {
    if (!heldOn) {
      return;
    }
    setError(null);
    try {
      await api.put(`/api/general-assemblies/${year}`, { held_on: heldOn });
      setFeedback('saved');
      await loadAssemblies();
    } catch (err) {
      setError(extractErrorMessage(err));
    }
  }

  async function clearYear(year: number) {
    setError(null);
    try {
      await api.delete(`/api/general-assemblies/${year}`);
      setFeedback('cleared');
      await loadAssemblies();
    } catch (err) {
      setError(extractErrorMessage(err));
    }
  }

  function handleAddYear(event: FormEvent) {
    event.preventDefault();
    const year = Number(newYear);
    if (!year || !newDate) {
      return;
    }
    saveYear(year, newDate).then(() => {
      setNewYear('');
      setNewDate('');
    });
  }

  return (
    <div className="mt-8 max-w-lg rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
      <h2 className="text-sm font-semibold text-slate-900">{t('generalAssemblies.title')}</h2>
      <p className="mt-1 text-sm text-slate-500">{t('generalAssemblies.subtitle')}</p>

      {error && (
        <div className="mt-4">
          <ErrorAlert>{error}</ErrorAlert>
        </div>
      )}
      {feedback && (
        <div className="mt-4">
          <SuccessAlert>{t(`generalAssemblies.${feedback === 'saved' ? 'savedMessage' : 'clearedMessage'}`)}</SuccessAlert>
        </div>
      )}

      {isLoading ? (
        <p className="mt-4 text-sm text-slate-500">{t('common.loading')}</p>
      ) : (
        <div className="mt-4 flex flex-col gap-3">
          {assemblies.map((assembly) => (
            <div key={assembly.exercise_year} className="flex items-end gap-2">
              <div className="w-24">
                <Field label={t('generalAssemblies.yearLabel')} htmlFor={`ag-year-${assembly.exercise_year}`}>
                  <Input id={`ag-year-${assembly.exercise_year}`} value={assembly.exercise_year} disabled />
                </Field>
              </div>
              <div className="flex-1">
                <Field label={t('generalAssemblies.dateLabel')} htmlFor={`ag-date-${assembly.exercise_year}`}>
                  <Input
                    id={`ag-date-${assembly.exercise_year}`}
                    type="date"
                    value={drafts[assembly.exercise_year]?.slice(0, 10) ?? ''}
                    onChange={(event) =>
                      setDrafts((previous) => ({ ...previous, [assembly.exercise_year]: event.target.value }))
                    }
                  />
                </Field>
              </div>
              <Button
                type="button"
                title={t('generalAssemblies.saveButton')}
                onClick={() => saveYear(assembly.exercise_year, drafts[assembly.exercise_year] ?? '')}
              >
                <Save className="size-4" />
              </Button>
              <Button
                type="button"
                variant="danger"
                title={t('generalAssemblies.clearButton')}
                onClick={() => clearYear(assembly.exercise_year)}
              >
                <Trash2 className="size-4" />
              </Button>
            </div>
          ))}

          <form onSubmit={handleAddYear} className="flex items-end gap-2 border-t border-slate-100 pt-3">
            <div className="w-24">
              <Field label={t('generalAssemblies.yearLabel')} htmlFor="ag-new-year">
                <Input
                  id="ag-new-year"
                  type="number"
                  placeholder="2026"
                  value={newYear}
                  onChange={(event) => setNewYear(event.target.value)}
                />
              </Field>
            </div>
            <div className="flex-1">
              <Field label={t('generalAssemblies.dateLabel')} htmlFor="ag-new-date">
                <Input id="ag-new-date" type="date" value={newDate} onChange={(event) => setNewDate(event.target.value)} />
              </Field>
            </div>
            <Button type="submit">
              <Plus className="size-4" />
              {t('generalAssemblies.addButton')}
            </Button>
          </form>
        </div>
      )}
    </div>
  );
}
