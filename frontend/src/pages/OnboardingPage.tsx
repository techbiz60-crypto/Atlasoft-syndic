import { useEffect, useState } from 'react';
import type { FormEvent } from 'react';
import { useNavigate } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { Building2, CheckCircle2, Copy, Layers, Plus, Users, Wallet } from 'lucide-react';
import { api } from '../lib/api';
import { extractErrorMessage } from '../context/AuthContext';
import { LogoMark } from '../components/Logo';
import { Field, Input, Select } from '../components/ui/Input';
import { Button } from '../components/ui/Button';
import { ErrorAlert, SuccessAlert } from '../components/ui/Alert';
import type { Building, LotType } from '../types/resources';
import type { Role } from '../types/auth';

type StepKey = 'lotTypes' | 'buildings' | 'lots' | 'cotisations' | 'team' | 'done';

const STEP_ORDER: StepKey[] = ['lotTypes', 'buildings', 'lots', 'cotisations', 'team', 'done'];

export function OnboardingPage() {
  const { t } = useTranslation();
  const navigate = useNavigate();
  const [stepIndex, setStepIndex] = useState(0);
  const step = STEP_ORDER[stepIndex];

  function goNext() {
    setStepIndex((previous) => Math.min(previous + 1, STEP_ORDER.length - 1));
  }

  return (
    <div className="min-h-screen bg-slate-50 px-4 py-10">
      <div className="mx-auto flex w-full max-w-2xl flex-col gap-6">
        <div className="flex items-center justify-between">
          <div className="flex items-center gap-2">
            <LogoMark className="size-8" />
            <span className="text-sm font-semibold text-slate-900">Atlasoft Syndic</span>
          </div>
          <button
            type="button"
            onClick={() => navigate('/dashboard')}
            className="text-sm font-medium text-slate-500 hover:text-slate-700"
          >
            {t('onboarding.exit')}
          </button>
        </div>

        <div className="flex items-center gap-1.5">
          {STEP_ORDER.slice(0, -1).map((key, index) => (
            <div
              key={key}
              className={`h-1.5 flex-1 rounded-full transition-colors ${index <= stepIndex ? 'bg-brand-600' : 'bg-slate-200'}`}
            />
          ))}
        </div>

        <div className="rounded-2xl border border-slate-200 bg-white p-8 shadow-sm">
          {step === 'lotTypes' && <LotTypesStep onNext={goNext} />}
          {step === 'buildings' && <BuildingsStep onNext={goNext} />}
          {step === 'lots' && <LotsStep onNext={goNext} />}
          {step === 'cotisations' && <CotisationsStep onNext={goNext} />}
          {step === 'team' && <TeamStep onNext={goNext} />}
          {step === 'done' && <DoneStep />}
        </div>
      </div>
    </div>
  );
}

function StepHeader({ icon: Icon, title, subtitle }: { icon: typeof Building2; title: string; subtitle: string }) {
  return (
    <div className="mb-6 flex items-start gap-3">
      <div className="flex size-10 shrink-0 items-center justify-center rounded-lg bg-brand-50 text-brand-600">
        <Icon className="size-5" />
      </div>
      <div>
        <h1 className="text-lg font-bold text-slate-900">{title}</h1>
        <p className="mt-0.5 text-sm text-slate-500">{subtitle}</p>
      </div>
    </div>
  );
}

function StepActions({
  onNext,
  nextLabel,
  nextDisabled,
  isLoading,
  onSkip,
}: {
  onNext: () => void;
  nextLabel: string;
  nextDisabled?: boolean;
  isLoading?: boolean;
  onSkip: () => void;
}) {
  const { t } = useTranslation();

  return (
    <div className="mt-6 flex items-center gap-3">
      <Button type="button" onClick={onNext} disabled={nextDisabled} isLoading={isLoading}>
        {nextLabel}
      </Button>
      <button type="button" onClick={onSkip} className="text-sm font-medium text-slate-500 hover:text-slate-700">
        {t('onboarding.skipStep')}
      </button>
    </div>
  );
}

function LotTypesStep({ onNext }: { onNext: () => void }) {
  const { t } = useTranslation();
  const [lotTypes, setLotTypes] = useState<LotType[]>([]);
  const [name, setName] = useState('');
  const [amount, setAmount] = useState('');
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    api.get<{ data: LotType[] }>('/api/lot-types').then(({ data }) => setLotTypes(data.data));
  }, []);

  async function handleAdd(event: FormEvent) {
    event.preventDefault();
    setError(null);
    setIsSubmitting(true);

    try {
      const { data } = await api.post<{ data: LotType }>('/api/lot-types', { name, amount: Number(amount) });
      setLotTypes((previous) => [...previous, data.data]);
      setName('');
      setAmount('');
    } catch (err) {
      setError(extractErrorMessage(err));
    } finally {
      setIsSubmitting(false);
    }
  }

  return (
    <div>
      <StepHeader icon={Layers} title={t('onboarding.lotTypes.title')} subtitle={t('onboarding.lotTypes.subtitle')} />

      {error && (
        <div className="mb-4">
          <ErrorAlert>{error}</ErrorAlert>
        </div>
      )}

      {lotTypes.length > 0 && (
        <ul className="mb-4 flex flex-col gap-2">
          {lotTypes.map((lotType) => (
            <li
              key={lotType.id}
              className="flex items-center justify-between rounded-lg border border-slate-200 bg-slate-50 px-3.5 py-2.5 text-sm"
            >
              <span className="font-medium text-slate-800">{lotType.name}</span>
              <span className="text-slate-500">{lotType.current_amount} DH / mois</span>
            </li>
          ))}
        </ul>
      )}

      <form onSubmit={handleAdd} className="flex flex-wrap items-end gap-3">
        <div className="min-w-[10rem] flex-1">
          <Field label={t('onboarding.lotTypes.nameLabel')} htmlFor="ob-lot-type-name">
            <Input id="ob-lot-type-name" value={name} onChange={(event) => setName(event.target.value)} required />
          </Field>
        </div>
        <div className="w-40">
          <Field label={t('onboarding.lotTypes.amountLabel')} htmlFor="ob-lot-type-amount">
            <Input
              id="ob-lot-type-amount"
              type="number"
              min={0}
              value={amount}
              onChange={(event) => setAmount(event.target.value)}
              required
            />
          </Field>
        </div>
        <Button type="submit" variant="secondary" isLoading={isSubmitting}>
          <Plus className="size-4" />
          {t('common.add')}
        </Button>
      </form>

      <StepActions
        onNext={onNext}
        onSkip={onNext}
        nextLabel={t('onboarding.continue')}
        nextDisabled={lotTypes.length === 0}
      />
    </div>
  );
}

function BuildingsStep({ onNext }: { onNext: () => void }) {
  const { t } = useTranslation();
  const [buildings, setBuildings] = useState<Building[]>([]);
  const [name, setName] = useState('');
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    api.get<{ data: Building[] }>('/api/buildings').then(({ data }) => setBuildings(data.data));
  }, []);

  async function handleAdd(event: FormEvent) {
    event.preventDefault();
    setError(null);
    setIsSubmitting(true);

    try {
      const { data } = await api.post<{ data: Building }>('/api/buildings', { name });
      setBuildings((previous) => [...previous, data.data]);
      setName('');
    } catch (err) {
      setError(extractErrorMessage(err));
    } finally {
      setIsSubmitting(false);
    }
  }

  return (
    <div>
      <StepHeader icon={Building2} title={t('onboarding.buildings.title')} subtitle={t('onboarding.buildings.subtitle')} />

      {error && (
        <div className="mb-4">
          <ErrorAlert>{error}</ErrorAlert>
        </div>
      )}

      {buildings.length > 0 && (
        <ul className="mb-4 flex flex-col gap-2">
          {buildings.map((building) => (
            <li key={building.id} className="rounded-lg border border-slate-200 bg-slate-50 px-3.5 py-2.5 text-sm font-medium text-slate-800">
              {building.name}
            </li>
          ))}
        </ul>
      )}

      <form onSubmit={handleAdd} className="flex flex-wrap items-end gap-3">
        <div className="min-w-[10rem] flex-1">
          <Field label={t('onboarding.buildings.nameLabel')} htmlFor="ob-building-name">
            <Input id="ob-building-name" value={name} onChange={(event) => setName(event.target.value)} required />
          </Field>
        </div>
        <Button type="submit" variant="secondary" isLoading={isSubmitting}>
          <Plus className="size-4" />
          {t('common.add')}
        </Button>
      </form>

      <StepActions onNext={onNext} onSkip={onNext} nextLabel={t('onboarding.continue')} />
    </div>
  );
}

function LotsStep({ onNext }: { onNext: () => void }) {
  const { t } = useTranslation();
  const [lotsCount, setLotsCount] = useState<number | null>(null);

  useEffect(() => {
    api.get<{ data: unknown[] }>('/api/lots').then(({ data }) => setLotsCount(data.data.length));
  }, []);

  return (
    <div>
      <StepHeader icon={Users} title={t('onboarding.lots.title')} subtitle={t('onboarding.lots.subtitle')} />

      <div className="mb-6 rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600">
        {lotsCount === null
          ? t('common.loading')
          : lotsCount === 0
            ? t('onboarding.lots.noneYet')
            : t('onboarding.lots.count', { count: lotsCount })}
      </div>

      <a
        href="/lots"
        target="_blank"
        rel="noreferrer"
        className="inline-flex items-center gap-2 rounded-lg bg-brand-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm shadow-brand-900/10 hover:bg-brand-700"
      >
        {t('onboarding.lots.openButton')}
      </a>

      <StepActions onNext={onNext} onSkip={onNext} nextLabel={t('onboarding.continue')} />
    </div>
  );
}

function CotisationsStep({ onNext }: { onNext: () => void }) {
  const { t } = useTranslation();
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [message, setMessage] = useState<string | null>(null);

  async function handleGenerate() {
    setError(null);
    setIsSubmitting(true);

    try {
      const { data } = await api.post<{ message: string }>('/api/fund-calls/generate', {});
      setMessage(data.message);
    } catch (err) {
      setError(extractErrorMessage(err));
    } finally {
      setIsSubmitting(false);
    }
  }

  return (
    <div>
      <StepHeader icon={Wallet} title={t('onboarding.cotisations.title')} subtitle={t('onboarding.cotisations.subtitle')} />

      {error && (
        <div className="mb-4">
          <ErrorAlert>{error}</ErrorAlert>
        </div>
      )}
      {message && (
        <div className="mb-4">
          <SuccessAlert>{message}</SuccessAlert>
        </div>
      )}

      <Button type="button" variant="secondary" onClick={handleGenerate} isLoading={isSubmitting}>
        {t('onboarding.cotisations.generateButton')}
      </Button>

      <StepActions onNext={onNext} onSkip={onNext} nextLabel={t('onboarding.continue')} />
    </div>
  );
}

function TeamStep({ onNext }: { onNext: () => void }) {
  const { t } = useTranslation();
  const [form, setForm] = useState({ name: '', email: '', role: 'tresorier' as Role });
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [generatedPassword, setGeneratedPassword] = useState<{ email: string; password: string } | null>(null);

  async function handleAdd(event: FormEvent) {
    event.preventDefault();
    setError(null);
    setIsSubmitting(true);

    try {
      const { data } = await api.post<{ data: { email: string }; generated_password: string }>('/api/users', form);
      setGeneratedPassword({ email: data.data.email, password: data.generated_password });
      setForm({ name: '', email: '', role: 'tresorier' });
    } catch (err) {
      setError(extractErrorMessage(err));
    } finally {
      setIsSubmitting(false);
    }
  }

  function copyPassword() {
    if (generatedPassword) {
      void navigator.clipboard.writeText(generatedPassword.password);
    }
  }

  return (
    <div>
      <StepHeader icon={Users} title={t('onboarding.team.title')} subtitle={t('onboarding.team.subtitle')} />

      {error && (
        <div className="mb-4">
          <ErrorAlert>{error}</ErrorAlert>
        </div>
      )}

      {generatedPassword && (
        <div className="mb-4">
          <SuccessAlert>
            <div className="flex flex-wrap items-center gap-2">
              <span>
                {t('users.generatedPasswordLabel', { email: generatedPassword.email })}{' '}
                <span className="font-mono font-semibold">{generatedPassword.password}</span>
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

      <form onSubmit={handleAdd} className="flex flex-col gap-4">
        <Field label={t('users.nameLabel')} htmlFor="ob-team-name">
          <Input id="ob-team-name" value={form.name} onChange={(event) => setForm((p) => ({ ...p, name: event.target.value }))} required />
        </Field>
        <Field label={t('users.emailLabel')} htmlFor="ob-team-email">
          <Input
            id="ob-team-email"
            type="email"
            value={form.email}
            onChange={(event) => setForm((p) => ({ ...p, email: event.target.value }))}
            required
          />
        </Field>
        <Field label={t('users.roleLabel')} htmlFor="ob-team-role">
          <Select
            id="ob-team-role"
            value={form.role}
            onChange={(event) => setForm((p) => ({ ...p, role: event.target.value as Role }))}
          >
            <option value="tresorier">{t('nav.roles.tresorier')}</option>
            <option value="conseil">{t('nav.roles.conseil')}</option>
          </Select>
        </Field>
        <Button type="submit" variant="secondary" isLoading={isSubmitting} className="self-start">
          <Plus className="size-4" />
          {t('common.add')}
        </Button>
      </form>

      <StepActions onNext={onNext} onSkip={onNext} nextLabel={t('onboarding.continue')} />
    </div>
  );
}

function DoneStep() {
  const { t } = useTranslation();
  const navigate = useNavigate();

  return (
    <div className="flex flex-col items-center py-6 text-center">
      <div className="mb-4 flex size-14 items-center justify-center rounded-full bg-emerald-50 text-emerald-600">
        <CheckCircle2 className="size-7" />
      </div>
      <h1 className="text-lg font-bold text-slate-900">{t('onboarding.done.title')}</h1>
      <p className="mt-1.5 max-w-sm text-sm text-slate-500">{t('onboarding.done.subtitle')}</p>
      <Button type="button" onClick={() => navigate('/dashboard')} className="mt-6">
        {t('onboarding.done.button')}
      </Button>
    </div>
  );
}
