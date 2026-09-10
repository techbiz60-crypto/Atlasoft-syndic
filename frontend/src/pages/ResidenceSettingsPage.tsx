import { useEffect, useState } from 'react';
import type { FormEvent } from 'react';
import { Copy, Plus, Save, ShieldAlert, Trash2 } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { api } from '../lib/api';
import { extractErrorMessage } from '../context/AuthContext';
import type { Residence, SyndicMandateClosure } from '../types/auth';
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
      <SyndicTransitionSection residenceName={form.name} />
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

function SyndicTransitionSection({ residenceName }: { residenceName: string }) {
  const { t } = useTranslation();
  const [history, setHistory] = useState<SyndicMandateClosure[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [showForm, setShowForm] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [result, setResult] = useState<{ email: string; password: string } | null>(null);

  const [form, setForm] = useState({ confirmation_text: '', password: '', new_admin_name: '', new_admin_email: '' });

  async function loadHistory() {
    setIsLoading(true);
    try {
      const { data } = await api.get<{ data: { current_lock: SyndicMandateClosure | null; history: SyndicMandateClosure[] } }>(
        '/api/syndic-transition',
      );
      setHistory(data.data.history);
    } catch (err) {
      setError(extractErrorMessage(err));
    } finally {
      setIsLoading(false);
    }
  }

  useEffect(() => {
    loadHistory();
  }, []);

  async function handleSubmit(event: FormEvent) {
    event.preventDefault();
    setError(null);
    setIsSubmitting(true);

    try {
      const { data } = await api.post<{ data: { email: string }; generated_password: string }>('/api/syndic-transition', form);
      setResult({ email: data.data.email, password: data.generated_password });
      setForm({ confirmation_text: '', password: '', new_admin_name: '', new_admin_email: '' });
      setShowForm(false);
      await loadHistory();
    } catch (err) {
      setError(extractErrorMessage(err));
    } finally {
      setIsSubmitting(false);
    }
  }

  function copyPassword() {
    if (result) {
      void navigator.clipboard.writeText(result.password);
    }
  }

  return (
    <div className="mt-8 max-w-lg rounded-xl border border-rose-200 bg-rose-50/40 p-6 shadow-sm">
      <div className="flex items-center gap-2">
        <ShieldAlert className="size-4.5 shrink-0 text-rose-600" />
        <h2 className="text-sm font-semibold text-slate-900">{t('syndicTransition.title')}</h2>
      </div>
      <p className="mt-1 text-sm text-slate-500">{t('syndicTransition.subtitle')}</p>

      {error && (
        <div className="mt-4">
          <ErrorAlert>{error}</ErrorAlert>
        </div>
      )}

      {result && (
        <div className="mt-4">
          <SuccessAlert>
            <div className="flex flex-wrap items-center gap-2">
              <span>
                {t('syndicTransition.resultLabel', { email: result.email })}{' '}
                <span className="font-mono font-semibold">{result.password}</span>
              </span>
              <button
                type="button"
                onClick={copyPassword}
                className="inline-flex items-center gap-1 rounded-lg border border-current px-2 py-1 text-xs font-medium"
              >
                <Copy className="size-3.5" />
                {t('users.copyButton')}
              </button>
            </div>
          </SuccessAlert>
        </div>
      )}

      {!isLoading && history.length > 0 && (
        <div className="mt-4 flex flex-col gap-2 border-t border-rose-100 pt-4">
          <p className="text-xs font-semibold tracking-wide text-slate-500 uppercase">{t('syndicTransition.historyTitle')}</p>
          {history.map((closure) => (
            <div key={closure.id} className="text-sm text-slate-600">
              {t('syndicTransition.historyLine', {
                date: new Date(closure.closed_at).toLocaleDateString(),
                admin: closure.closed_by?.name ?? '—',
                newAdmin: closure.new_admin?.name ?? '—',
              })}
              {closure.reopened_at && (
                <span className="ms-1 text-xs font-medium text-amber-600">{t('syndicTransition.reopenedTag')}</span>
              )}
            </div>
          ))}
        </div>
      )}

      {!showForm ? (
        <Button type="button" variant="danger" className="mt-4" onClick={() => setShowForm(true)}>
          <ShieldAlert className="size-4" />
          {t('syndicTransition.startButton')}
        </Button>
      ) : (
        <form onSubmit={handleSubmit} className="mt-4 flex flex-col gap-4 border-t border-rose-100 pt-4">
          <p className="text-sm text-slate-600">{t('syndicTransition.warning')}</p>

          <Field label={t('syndicTransition.newAdminNameLabel')} htmlFor="new-admin-name">
            <Input
              id="new-admin-name"
              value={form.new_admin_name}
              onChange={(event) => setForm((previous) => ({ ...previous, new_admin_name: event.target.value }))}
              required
            />
          </Field>

          <Field label={t('syndicTransition.newAdminEmailLabel')} htmlFor="new-admin-email">
            <Input
              id="new-admin-email"
              type="email"
              value={form.new_admin_email}
              onChange={(event) => setForm((previous) => ({ ...previous, new_admin_email: event.target.value }))}
              required
            />
          </Field>

          <Field
            label={t('syndicTransition.confirmationTextLabel', { name: residenceName })}
            htmlFor="confirmation-text"
          >
            <Input
              id="confirmation-text"
              value={form.confirmation_text}
              onChange={(event) => setForm((previous) => ({ ...previous, confirmation_text: event.target.value }))}
              required
            />
          </Field>

          <Field label={t('syndicTransition.passwordLabel')} htmlFor="confirm-password">
            <Input
              id="confirm-password"
              type="password"
              value={form.password}
              onChange={(event) => setForm((previous) => ({ ...previous, password: event.target.value }))}
              required
            />
          </Field>

          <div className="flex gap-2">
            <Button type="submit" variant="danger" isLoading={isSubmitting}>
              {t('syndicTransition.confirmButton')}
            </Button>
            <Button type="button" variant="secondary" onClick={() => setShowForm(false)}>
              {t('common.cancel')}
            </Button>
          </div>
        </form>
      )}
    </div>
  );
}
