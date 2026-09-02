import { NavLink, Outlet } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import {
  AlertTriangle,
  Building2,
  CreditCard,
  FileText,
  Landmark,
  Layers,
  LayoutGrid,
  ListOrdered,
  LogOut,
  PanelLeftClose,
  PanelLeftOpen,
  Receipt,
  Settings,
  ShieldCheck,
  TrendingUp,
  UserPlus,
  Users,
  Wallet,
} from 'lucide-react';
import { useAuth } from '../context/AuthContext';
import { useSidebarCollapsed } from '../hooks/useSidebarCollapsed';
import { LanguageSwitcher } from './LanguageSwitcher';
import { SubscriptionBanner } from './SubscriptionBanner';

export function Layout() {
  const { user, logout } = useAuth();
  const { t } = useTranslation();
  const [collapsed, setCollapsed] = useSidebarCollapsed();

  if (!user || !user.residence) {
    return null;
  }

  const roleLabels: Record<string, string> = {
    admin: t('nav.roles.admin'),
    tresorier: t('nav.roles.tresorier'),
    conseil: t('nav.roles.conseil'),
    coproprietaire: t('nav.roles.coproprietaire'),
  };

  const navItemClass = ({ isActive }: { isActive: boolean }) =>
    `flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition-colors ${collapsed ? 'justify-center' : ''} ${
      isActive ? 'bg-brand-600 text-white shadow-sm shadow-brand-900/20' : 'text-slate-300 hover:bg-white/5 hover:text-white'
    }`;

  const operationsItems: { to: string; end?: boolean; icon: typeof LayoutGrid; label: string }[] = [
    { to: '/dashboard', end: true, icon: LayoutGrid, label: t('nav.dashboard') },
    { to: '/tresorerie', icon: Landmark, label: t('nav.treasury') },
    { to: '/cotisations', icon: Wallet, label: t('nav.cotisations') },
    { to: '/paiements', icon: ListOrdered, label: t('nav.payments') },
    { to: '/impayes', icon: AlertTriangle, label: t('nav.impayes') },
    { to: '/rapports/paiements', icon: FileText, label: t('nav.paymentsReport') },
    { to: '/depenses', icon: Receipt, label: t('nav.expenses') },
    { to: '/recettes', icon: TrendingUp, label: t('nav.revenues') },
  ];

  const settingsItems: { to: string; end?: boolean; icon: typeof LayoutGrid; label: string }[] = [
    { to: '/buildings', icon: Layers, label: t('nav.buildings') },
    { to: '/lot-types', icon: Building2, label: t('nav.lotTypes') },
    { to: '/lots', icon: Users, label: t('nav.lots') },
  ];

  return (
    <div className="flex min-h-screen bg-slate-50">
      <aside
        className={`no-print flex shrink-0 flex-col bg-slate-900 px-4 py-6 transition-[width] duration-200 ${collapsed ? 'w-20' : 'w-64'}`}
      >
        <div className={`flex items-center gap-2 px-2 ${collapsed ? 'mb-2 justify-center' : 'mb-6 justify-between'}`}>
          <div className={`flex min-w-0 items-center gap-2 ${collapsed ? 'justify-center' : ''}`}>
            <span className="flex size-9 shrink-0 items-center justify-center rounded-xl bg-brand-600 text-white">
              <Building2 className="size-5" strokeWidth={2.25} />
            </span>
            {!collapsed && (
              <div className="min-w-0">
                <p className="truncate text-sm font-semibold text-white">{user.residence.name}</p>
                <p className="text-xs text-slate-400">{t('nav.residenceApartments', { count: user.residence.lots_count })}</p>
              </div>
            )}
          </div>
          {!collapsed && (
            <button
              onClick={() => setCollapsed(!collapsed)}
              title={t('nav.collapseSidebar')}
              className="flex size-8 shrink-0 items-center justify-center rounded-lg text-slate-400 transition-colors hover:bg-white/5 hover:text-white"
            >
              <PanelLeftClose className="size-4.5 rtl:-scale-x-100" />
            </button>
          )}
        </div>

        {collapsed && (
          <button
            onClick={() => setCollapsed(!collapsed)}
            title={t('nav.expandSidebar')}
            className="mb-6 flex w-full items-center justify-center rounded-lg py-2 text-slate-400 transition-colors hover:bg-white/5 hover:text-white"
          >
            <PanelLeftOpen className="size-4.5 rtl:-scale-x-100" />
          </button>
        )}

        <nav className="flex flex-1 flex-col gap-4 overflow-y-auto">
          <div className="flex flex-col gap-1">
            {!collapsed && (
              <p className="px-3 pb-1 text-xs font-semibold tracking-wide text-slate-500 uppercase">{t('nav.sectionOperations')}</p>
            )}
            {operationsItems.map(({ to, end, icon: Icon, label }) => (
              <NavLink key={to} to={to} end={end} className={navItemClass} title={collapsed ? label : undefined}>
                <Icon className="size-4.5 shrink-0" />
                {!collapsed && label}
              </NavLink>
            ))}
          </div>

          <div className="flex flex-col gap-1">
            {!collapsed && (
              <p className="px-3 pb-1 text-xs font-semibold tracking-wide text-slate-500 uppercase">{t('nav.sectionSettings')}</p>
            )}
            {collapsed && <div className="mx-2 my-1 border-t border-white/10" />}
            {settingsItems.map(({ to, end, icon: Icon, label }) => (
              <NavLink key={to} to={to} end={end} className={navItemClass} title={collapsed ? label : undefined}>
                <Icon className="size-4.5 shrink-0" />
                {!collapsed && label}
              </NavLink>
            ))}
            {user.role === 'admin' && (
              <>
                <NavLink to="/abonnement" className={navItemClass} title={collapsed ? t('nav.subscription') : undefined}>
                  <CreditCard className="size-4.5 shrink-0" />
                  {!collapsed && t('nav.subscription')}
                </NavLink>
                <NavLink to="/residence" className={navItemClass} title={collapsed ? t('nav.residenceSettings') : undefined}>
                  <Settings className="size-4.5 shrink-0" />
                  {!collapsed && t('nav.residenceSettings')}
                </NavLink>
                <NavLink to="/utilisateurs" className={navItemClass} title={collapsed ? t('nav.users') : undefined}>
                  <UserPlus className="size-4.5 shrink-0" />
                  {!collapsed && t('nav.users')}
                </NavLink>
                <NavLink to="/permissions" className={navItemClass} title={collapsed ? t('nav.rolePermissions') : undefined}>
                  <ShieldCheck className="size-4.5 shrink-0" />
                  {!collapsed && t('nav.rolePermissions')}
                </NavLink>
              </>
            )}
          </div>
        </nav>

        <div className="mt-6 border-t border-white/10 pt-4">
          {!collapsed && (
            <div className="mb-3 px-2">
              <p className="truncate text-sm font-medium text-white">{user.name}</p>
              <p className="text-xs text-slate-400">{roleLabels[user.role] ?? user.role}</p>
            </div>
          )}
          {!collapsed && (
            <div className="mb-3 px-2">
              <LanguageSwitcher variant="dark" />
            </div>
          )}
          <button
            onClick={() => logout()}
            title={collapsed ? t('nav.logout') : undefined}
            className={`flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium text-slate-300 transition-colors hover:bg-white/5 hover:text-white ${collapsed ? 'justify-center' : ''}`}
          >
            <LogOut className="size-4.5 shrink-0" />
            {!collapsed && t('nav.logout')}
          </button>
        </div>
      </aside>

      <main className="flex flex-1 flex-col overflow-y-auto print:overflow-visible">
        <div className="no-print">
          <SubscriptionBanner />
        </div>
        <div className="mx-auto w-full max-w-[1600px] px-8 py-8 lg:px-12 print:max-w-none print:p-0">
          <Outlet />
        </div>
      </main>
    </div>
  );
}
