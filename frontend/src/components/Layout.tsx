import { NavLink, Outlet } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { LogoMark } from './Logo';
import {
  AlertTriangle,
  BookOpen,
  Building2,
  CreditCard,
  FileText,
  Gavel,
  Landmark,
  Layers,
  LayoutGrid,
  ListOrdered,
  Menu,
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
import { DeactivatedAccountBanner } from './DeactivatedAccountBanner';
import { UserMenu } from './UserMenu';

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

  type NavItem = { to: string; end?: boolean; icon: typeof LayoutGrid; label: string };

  const dashboardItem: NavItem = { to: '/dashboard', end: true, icon: LayoutGrid, label: t('nav.dashboard') };
  const DashboardIcon = dashboardItem.icon;

  const navGroups: { title: string; items: NavItem[] }[] = [
    {
      title: t('nav.sectionCotisations'),
      items: [
        { to: '/cotisations', icon: Wallet, label: t('nav.cotisations') },
        { to: '/paiements', icon: ListOrdered, label: t('nav.payments') },
        { to: '/impayes', icon: AlertTriangle, label: t('nav.impayes') },
        { to: '/rapports/paiements', icon: FileText, label: t('nav.paymentsReport') },
      ],
    },
    {
      title: t('nav.sectionAccounting'),
      items: [
        { to: '/tresorerie', icon: Landmark, label: t('nav.treasury') },
        { to: '/depenses', icon: Receipt, label: t('nav.expenses') },
        { to: '/recettes', icon: TrendingUp, label: t('nav.revenues') },
        { to: '/rapports/ag', icon: Gavel, label: t('nav.agReport') },
      ],
    },
    {
      title: t('nav.sectionProperty'),
      items: [
        { to: '/buildings', icon: Layers, label: t('nav.buildings') },
        { to: '/lot-types', icon: Building2, label: t('nav.lotTypes') },
        { to: '/lots', icon: Users, label: t('nav.lots') },
      ],
    },
  ];

  return (
    <div className="flex min-h-screen flex-col bg-slate-50">
      <header className="no-print flex h-16 shrink-0 items-center justify-between border-b border-slate-200 bg-white px-4 lg:px-6">
        <div className="flex min-w-0 items-center gap-3">
          <button
            type="button"
            onClick={() => setCollapsed(!collapsed)}
            title={collapsed ? t('nav.expandSidebar') : t('nav.collapseSidebar')}
            className="flex size-9 shrink-0 items-center justify-center rounded-lg text-slate-500 transition-colors hover:bg-slate-100"
          >
            <Menu className="size-5" />
          </button>

          <div className="flex min-w-0 items-center gap-2.5">
            <LogoMark className="size-8 shrink-0" />
            <div className="hidden min-w-0 sm:block">
              <p className="truncate text-sm font-semibold text-slate-900">{user.residence.name}</p>
              <p className="text-xs text-slate-500">{t('nav.residenceApartments', { count: user.residence.lots_count })}</p>
            </div>
          </div>
        </div>

        <div className="flex shrink-0 items-center gap-3">
          {user.role !== 'coproprietaire' && (
            <NavLink
              to="/guide"
              title={t('nav.guide')}
              className={({ isActive }) =>
                `hidden items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-sm font-medium transition-colors sm:flex ${
                  isActive ? 'bg-brand-50 text-brand-700' : 'text-slate-500 hover:bg-slate-100 hover:text-slate-700'
                }`
              }
            >
              <BookOpen className="size-4.5 shrink-0" />
              {t('nav.guide')}
            </NavLink>
          )}
          <LanguageSwitcher variant="light" className="hidden sm:inline-flex" />
          <UserMenu user={user} roleLabel={roleLabels[user.role] ?? user.role} onLogout={() => logout()} />
        </div>
      </header>

      <div className="flex flex-1">
        <aside
          className={`no-print flex shrink-0 flex-col bg-slate-900 px-3 py-5 transition-[width] duration-200 ${collapsed ? 'w-20' : 'w-64'}`}
        >
          <nav className="flex flex-1 flex-col gap-4 overflow-y-auto">
            <div className="flex flex-col gap-1">
              <NavLink to={dashboardItem.to} end={dashboardItem.end} className={navItemClass} title={collapsed ? dashboardItem.label : undefined}>
                <DashboardIcon className="size-4.5 shrink-0" />
                {!collapsed && dashboardItem.label}
              </NavLink>
            </div>

            {navGroups.map((group) => (
              <div key={group.title} className="flex flex-col gap-1">
                {!collapsed && <p className="px-3 pb-1 text-xs font-semibold tracking-wide text-slate-500 uppercase">{group.title}</p>}
                {collapsed && <div className="mx-2 my-1 border-t border-white/10" />}
                {group.items.map(({ to, end, icon: Icon, label }) => (
                  <NavLink key={to} to={to} end={end} className={navItemClass} title={collapsed ? label : undefined}>
                    <Icon className="size-4.5 shrink-0" />
                    {!collapsed && label}
                  </NavLink>
                ))}
              </div>
            ))}

            {user.role === 'admin' && (
              <div className="flex flex-col gap-1">
                {!collapsed && (
                  <p className="px-3 pb-1 text-xs font-semibold tracking-wide text-slate-500 uppercase">{t('nav.sectionAdministration')}</p>
                )}
                {collapsed && <div className="mx-2 my-1 border-t border-white/10" />}
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
              </div>
            )}
          </nav>
        </aside>

        <main className="flex flex-1 flex-col overflow-y-auto print:overflow-visible">
          <div className="no-print">
            <DeactivatedAccountBanner />
            <SubscriptionBanner />
          </div>
          <div className="mx-auto w-full max-w-[1600px] px-8 py-8 lg:px-12 print:max-w-none print:p-0">
            <Outlet />
          </div>
        </main>
      </div>
    </div>
  );
}
