import { Outlet } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { Building2, LogOut, PanelLeftClose, PanelLeftOpen, Users } from 'lucide-react';
import { useAuth } from '../context/AuthContext';
import { useSidebarCollapsed } from '../hooks/useSidebarCollapsed';
import { LanguageSwitcher } from './LanguageSwitcher';

export function PlatformLayout() {
  const { user, logout } = useAuth();
  const { t } = useTranslation();
  const [collapsed, setCollapsed] = useSidebarCollapsed();

  if (!user) {
    return null;
  }

  return (
    <div className="flex min-h-screen bg-slate-50">
      <aside
        className={`flex shrink-0 flex-col bg-slate-900 px-4 py-6 transition-[width] duration-200 ${collapsed ? 'w-20' : 'w-64'}`}
      >
        <div className={`flex items-center gap-2 px-2 ${collapsed ? 'mb-2 justify-center' : 'mb-6 justify-between'}`}>
          <div className={`flex min-w-0 items-center gap-2 ${collapsed ? 'justify-center' : ''}`}>
            <span className="flex size-9 shrink-0 items-center justify-center rounded-xl bg-brand-600 text-white">
              <Building2 className="size-5" strokeWidth={2.25} />
            </span>
            {!collapsed && (
              <div className="min-w-0">
                <p className="truncate text-sm font-semibold text-white">Atlasoft Syndic</p>
                <p className="text-xs text-slate-400">{t('platform.badge')}</p>
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

        <nav className="flex flex-1 flex-col gap-1">
          <span
            title={collapsed ? t('platform.clients') : undefined}
            className={`flex items-center gap-3 rounded-lg bg-brand-600 px-3 py-2.5 text-sm font-medium text-white shadow-sm shadow-brand-900/20 ${collapsed ? 'justify-center' : ''}`}
          >
            <Users className="size-4.5 shrink-0" />
            {!collapsed && t('platform.clients')}
          </span>
        </nav>

        <div className="mt-6 border-t border-white/10 pt-4">
          {!collapsed && (
            <div className="mb-3 px-2">
              <p className="truncate text-sm font-medium text-white">{user.name}</p>
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

      <main className="flex-1 overflow-y-auto">
        <div className="mx-auto w-full max-w-[1600px] px-8 py-8 lg:px-12">
          <Outlet />
        </div>
      </main>
    </div>
  );
}
