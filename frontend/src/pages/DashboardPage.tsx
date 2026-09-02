import { useEffect, useState } from 'react';
import { AlertTriangle, Banknote, Receipt, Sparkles, Users } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { useAuth } from '../context/AuthContext';
import { PageHeader } from '../components/PageHeader';
import { api } from '../lib/api';
import type { Expense, FundCall, Revenue, UnpaidLot } from '../types/resources';

function StatCard({ icon: Icon, label, value }: { icon: typeof Banknote; label: string; value: string }) {
  return (
    <div className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
      <div className="mb-3 flex size-9 items-center justify-center rounded-lg bg-brand-50 text-brand-600">
        <Icon className="size-4.5" />
      </div>
      <p className="text-sm text-slate-500">{label}</p>
      <p className="mt-0.5 text-xl font-bold text-slate-900">{value}</p>
    </div>
  );
}

export function DashboardPage() {
  const { user } = useAuth();
  const { t } = useTranslation();
  const [isLoading, setIsLoading] = useState(true);
  const [treasury, setTreasury] = useState<number | null>(null);
  const [unpaidCount, setUnpaidCount] = useState<number | null>(null);
  const [monthlyExpenses, setMonthlyExpenses] = useState<number | null>(null);

  const roleLabels: Record<string, string> = {
    admin: t('dashboard.roles.admin'),
    conseil: t('dashboard.roles.conseil'),
    coproprietaire: t('dashboard.roles.coproprietaire'),
  };

  useEffect(() => {
    const now = new Date();

    Promise.all([
      api.get<{ data: FundCall[] }>('/api/fund-calls'),
      api.get<{ data: Expense[] }>('/api/expenses'),
      api.get<{ data: Revenue[] }>('/api/revenues'),
      api.get<{ data: UnpaidLot[] }>('/api/fund-calls/unpaid'),
      api.get<{ data: Expense[] }>('/api/expenses', {
        params: { year: now.getFullYear(), month: now.getMonth() + 1 },
      }),
    ])
      .then(([fundCalls, expenses, revenues, unpaid, expensesThisMonth]) => {
        const totalCollected = fundCalls.data.data.reduce((sum, call) => sum + call.paid_amount, 0);
        const totalExpenses = expenses.data.data.reduce((sum, expense) => sum + expense.amount, 0);
        const totalRevenues = revenues.data.data.reduce((sum, revenue) => sum + revenue.amount, 0);
        setTreasury(totalCollected + totalRevenues - totalExpenses);
        setUnpaidCount(unpaid.data.data.length);
        setMonthlyExpenses(expensesThisMonth.data.data.reduce((sum, expense) => sum + expense.amount, 0));
      })
      .finally(() => setIsLoading(false));
  }, []);

  if (!user || !user.residence) {
    return null;
  }

  return (
    <div>
      <PageHeader
        title={t('dashboard.welcome', { name: user.name.split(' ')[0] })}
        subtitle={`${user.residence.name} — ${roleLabels[user.role] ?? user.role}`}
      />

      <div className="grid grid-cols-1 gap-4 sm:grid-cols-4">
        <StatCard
          icon={Banknote}
          label={t('dashboard.treasuryBalance')}
          value={isLoading || treasury === null ? '—' : `${treasury} DH`}
        />
        <StatCard icon={Users} label={t('dashboard.apartments')} value={String(user.residence.lots_count)} />
        <StatCard
          icon={AlertTriangle}
          label={t('dashboard.unpaidApartments')}
          value={isLoading || unpaidCount === null ? '—' : String(unpaidCount)}
        />
        <StatCard
          icon={Receipt}
          label={t('dashboard.monthlyExpenses')}
          value={isLoading || monthlyExpenses === null ? '—' : `${monthlyExpenses} DH`}
        />
      </div>

      <div className="mt-6 flex items-start gap-3 rounded-xl border border-dashed border-slate-300 bg-white p-6">
        <div className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-amber-50 text-amber-600">
          <Sparkles className="size-4.5" />
        </div>
        <div>
          <p className="text-sm font-semibold text-slate-900">{t('dashboard.comingSoon')}</p>
          <p className="mt-0.5 text-sm text-slate-500">{t('dashboard.comingSoonDesc')}</p>
        </div>
      </div>
    </div>
  );
}
