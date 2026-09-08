import { useEffect, useMemo, useState } from 'react';
import { Link } from 'react-router-dom';
import {
  AlertTriangle,
  ArrowDownLeft,
  ArrowUpRight,
  Banknote,
  CheckCircle2,
  Receipt,
  TrendingUp,
  Users,
} from 'lucide-react';
import { useTranslation } from 'react-i18next';
import {
  Bar,
  CartesianGrid,
  ComposedChart,
  Legend,
  Line,
  ResponsiveContainer,
  Tooltip,
  XAxis,
  YAxis,
} from 'recharts';
import { useAuth } from '../context/AuthContext';
import { PageHeader } from '../components/PageHeader';
import { Field, Input, Select } from '../components/ui/Input';
import { api } from '../lib/api';
import { extractErrorMessage } from '../context/AuthContext';
import { ErrorAlert } from '../components/ui/Alert';
import type { Building, Dashboard, DashboardMovement, DashboardUnpaidLot } from '../types/resources';

function formatAmount(amount: number): string {
  return amount.toLocaleString('fr-FR', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
}

function formatPercent(rate: number): string {
  return `${(rate * 100).toLocaleString('fr-FR', { minimumFractionDigits: 0, maximumFractionDigits: 1 })} %`;
}

function startOfYear(): string {
  return `${new Date().getFullYear()}-01-01`;
}

function today(): string {
  return new Date().toISOString().slice(0, 10);
}

function StatCard({
  icon: Icon,
  label,
  value,
  sub,
  to,
  tone = 'brand',
}: {
  icon: typeof Banknote;
  label: string;
  value: string;
  sub?: string;
  to?: string;
  tone?: 'brand' | 'rose' | 'emerald';
}) {
  const toneClasses: Record<string, string> = {
    brand: 'bg-brand-50 text-brand-600',
    rose: 'bg-rose-50 text-rose-600',
    emerald: 'bg-emerald-50 text-emerald-600',
  };

  const content = (
    <div className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm transition-shadow hover:shadow-md">
      <div className={`mb-3 flex size-9 items-center justify-center rounded-lg ${toneClasses[tone]}`}>
        <Icon className="size-4.5" />
      </div>
      <p className="text-sm text-slate-500">{label}</p>
      <p className="mt-0.5 text-xl font-bold text-slate-900">{value}</p>
      {sub && <p className="mt-0.5 text-xs text-slate-400">{sub}</p>}
    </div>
  );

  return to ? <Link to={to}>{content}</Link> : content;
}

export function DashboardPage() {
  const { user } = useAuth();
  const { t } = useTranslation();

  const [buildings, setBuildings] = useState<Building[]>([]);
  const [buildingId, setBuildingId] = useState('');
  const [from, setFrom] = useState(startOfYear());
  const [to, setTo] = useState(today());
  const [dashboard, setDashboard] = useState<Dashboard | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const roleLabels: Record<string, string> = {
    admin: t('dashboard.roles.admin'),
    tresorier: t('dashboard.roles.tresorier'),
    conseil: t('dashboard.roles.conseil'),
    coproprietaire: t('dashboard.roles.coproprietaire'),
  };

  useEffect(() => {
    api.get<{ data: Building[] }>('/api/buildings').then(({ data }) => setBuildings(data.data));
  }, []);

  useEffect(() => {
    setIsLoading(true);
    setError(null);
    api
      .get<Dashboard>('/api/dashboard', { params: { from, to, building_id: buildingId || undefined } })
      .then(({ data }) => setDashboard(data))
      .catch((err) => setError(extractErrorMessage(err)))
      .finally(() => setIsLoading(false));
  }, [from, to, buildingId]);

  const chartData = useMemo(
    () =>
      (dashboard?.monthly_series ?? []).map((point) => ({
        ...point,
        label: new Date(`${point.month}-01T00:00:00`).toLocaleDateString('fr-FR', { month: 'short', year: '2-digit' }),
      })),
    [dashboard],
  );

  if (!user || !user.residence) {
    return null;
  }

  return (
    <div>
      <PageHeader
        title={t('dashboard.welcome', { name: user.name.split(' ')[0] })}
        subtitle={`${user.residence.name} — ${roleLabels[user.role] ?? user.role}`}
      />

      <div className="mb-5 flex flex-wrap items-end gap-3">
        <Field label={t('dashboard.fromLabel')} htmlFor="dashboard-from">
          <Input id="dashboard-from" type="date" className="w-40" value={from} onChange={(event) => setFrom(event.target.value)} />
        </Field>
        <Field label={t('dashboard.toLabel')} htmlFor="dashboard-to">
          <Input id="dashboard-to" type="date" className="w-40" value={to} onChange={(event) => setTo(event.target.value)} />
        </Field>
        <Field label={t('dashboard.buildingLabel')} htmlFor="dashboard-building">
          <Select id="dashboard-building" className="w-52" value={buildingId} onChange={(event) => setBuildingId(event.target.value)}>
            <option value="">{t('dashboard.allBuildings')}</option>
            {buildings.map((building) => (
              <option key={building.id} value={building.id}>
                {building.name}
              </option>
            ))}
          </Select>
        </Field>
      </div>

      {error && (
        <div className="mb-4">
          <ErrorAlert>{error}</ErrorAlert>
        </div>
      )}

      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <StatCard
          icon={Banknote}
          label={t('dashboard.cashBalance')}
          value={isLoading || !dashboard ? '—' : `${formatAmount(dashboard.cash_balance)} DH`}
          to="/tresorerie"
        />
        <StatCard
          icon={AlertTriangle}
          tone="rose"
          label={t('dashboard.unpaidTotal')}
          value={isLoading || !dashboard ? '—' : `${formatAmount(dashboard.unpaid_total)} DH`}
          sub={isLoading || !dashboard ? undefined : t('dashboard.unpaidCount', { count: dashboard.unpaid_count })}
          to="/impayes"
        />
        <StatCard
          icon={CheckCircle2}
          tone="emerald"
          label={t('dashboard.collectionRate')}
          value={isLoading || !dashboard ? '—' : formatPercent(dashboard.collection_rate)}
          sub={isLoading || !dashboard ? undefined : t('dashboard.collectionRateSub', { amount: formatAmount(dashboard.collected_for_range) })}
          to="/cotisations"
        />
        <StatCard
          icon={Receipt}
          label={t('dashboard.expensesTotal')}
          value={isLoading || !dashboard ? '—' : `${formatAmount(dashboard.expenses_total)} DH`}
          to="/depenses"
        />
      </div>

      <div className="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <StatCard icon={Users} label={t('dashboard.apartments')} value={String(user.residence.lots_count)} to="/lots" />
        <StatCard
          icon={TrendingUp}
          label={t('dashboard.revenuesTotal')}
          value={isLoading || !dashboard ? '—' : `${formatAmount(dashboard.revenues_total)} DH`}
          to="/recettes"
        />
      </div>

      <div className="mt-6 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <p className="mb-4 text-sm font-semibold text-slate-900">{t('dashboard.chartTitle')}</p>
        {isLoading ? (
          <p className="text-sm text-slate-500">{t('common.loading')}</p>
        ) : chartData.length === 0 ? (
          <p className="text-sm text-slate-500">{t('dashboard.chartEmpty')}</p>
        ) : (
          <ResponsiveContainer width="100%" height={320}>
            <ComposedChart data={chartData}>
              <CartesianGrid strokeDasharray="3 3" stroke="#e2e8f0" />
              <XAxis dataKey="label" tick={{ fontSize: 12, fill: '#64748b' }} />
              <YAxis tick={{ fontSize: 12, fill: '#64748b' }} tickFormatter={(value) => formatAmount(Number(value))} />
              <Tooltip
                formatter={(value) => `${formatAmount(Number(value))} DH`}
                contentStyle={{ borderRadius: 8, border: '1px solid #e2e8f0', fontSize: 13 }}
              />
              <Legend wrapperStyle={{ fontSize: 13 }} />
              <Bar dataKey="income" name={t('dashboard.chartIncome')} fill="#059669" radius={[4, 4, 0, 0]} />
              <Bar dataKey="expenses" name={t('dashboard.chartExpenses')} fill="#e11d48" radius={[4, 4, 0, 0]} />
              <Line
                type="monotone"
                dataKey="balance"
                name={t('dashboard.chartBalance')}
                stroke="#0f172a"
                strokeWidth={2}
                dot={false}
              />
            </ComposedChart>
          </ResponsiveContainer>
        )}
      </div>

      <div className="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-2">
        <div className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
          <div className="mb-3 flex items-center justify-between">
            <p className="text-sm font-semibold text-slate-900">{t('dashboard.topUnpaidTitle')}</p>
            <Link to="/impayes" className="text-xs font-medium text-brand-600 hover:underline">
              {t('dashboard.seeAll')}
            </Link>
          </div>
          {isLoading ? (
            <p className="text-sm text-slate-500">{t('common.loading')}</p>
          ) : !dashboard || dashboard.top_unpaid.length === 0 ? (
            <p className="text-sm text-slate-500">{t('dashboard.topUnpaidEmpty')}</p>
          ) : (
            <ul className="divide-y divide-slate-100">
              {dashboard.top_unpaid.map((lot) => (
                <TopUnpaidRow key={lot.lot_id} lot={lot} t={t} />
              ))}
            </ul>
          )}
        </div>

        <div className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
          <div className="mb-3 flex items-center justify-between">
            <p className="text-sm font-semibold text-slate-900">{t('dashboard.recentMovementsTitle')}</p>
            <Link to="/tresorerie" className="text-xs font-medium text-brand-600 hover:underline">
              {t('dashboard.seeAll')}
            </Link>
          </div>
          {isLoading ? (
            <p className="text-sm text-slate-500">{t('common.loading')}</p>
          ) : !dashboard || dashboard.recent_movements.length === 0 ? (
            <p className="text-sm text-slate-500">{t('dashboard.recentMovementsEmpty')}</p>
          ) : (
            <ul className="divide-y divide-slate-100">
              {dashboard.recent_movements.map((movement, index) => (
                <MovementRow key={index} movement={movement} />
              ))}
            </ul>
          )}
        </div>
      </div>
    </div>
  );
}

function TopUnpaidRow({ lot, t }: { lot: DashboardUnpaidLot; t: (key: string, options?: Record<string, unknown>) => string }) {
  return (
    <li className="flex items-center justify-between gap-3 py-2.5">
      <div className="min-w-0">
        <p className="truncate text-sm font-medium text-slate-900">{lot.owner_name}</p>
        <p className="text-xs text-slate-500">
          {t('dashboard.lotRef', { number: lot.lot_number, building: lot.building_name })}
        </p>
      </div>
      <div className="shrink-0 text-end">
        <p className="text-sm font-semibold text-rose-600">{formatAmount(lot.total_due)} DH</p>
        <p className="text-xs text-slate-400">{t('impayes.monthsLate', { count: lot.months_late })}</p>
      </div>
    </li>
  );
}

function MovementRow({ movement }: { movement: DashboardMovement }) {
  return (
    <li className="flex items-center justify-between gap-3 py-2.5">
      <div className="flex min-w-0 items-center gap-2.5">
        <span
          className={`flex size-7 shrink-0 items-center justify-center rounded-full ${
            movement.direction === 'in' ? 'bg-brand-50 text-brand-600' : 'bg-rose-50 text-rose-600'
          }`}
        >
          {movement.direction === 'in' ? <ArrowDownLeft className="size-3.5" /> : <ArrowUpRight className="size-3.5" />}
        </span>
        <div className="min-w-0">
          <p className="truncate text-sm font-medium text-slate-900">{movement.label}</p>
          <p className="text-xs text-slate-500">
            {movement.reference} · {new Date(movement.date).toLocaleDateString('fr-FR')}
          </p>
        </div>
      </div>
      <p className={`shrink-0 text-sm font-semibold ${movement.direction === 'in' ? 'text-brand-700' : 'text-rose-700'}`}>
        {movement.direction === 'in' ? '+' : '−'}
        {formatAmount(movement.amount)}
      </p>
    </li>
  );
}
