import { useEffect, useRef, useState } from 'react';
import { ChevronDown, LogOut, UserCircle } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { NavLink } from 'react-router-dom';
import type { User } from '../types/auth';

export function UserMenu({ user, roleLabel, onLogout }: { user: User; roleLabel: string; onLogout: () => void }) {
  const { t } = useTranslation();
  const [open, setOpen] = useState(false);
  const rootRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    if (!open) {
      return;
    }

    function handleClickOutside(event: MouseEvent) {
      if (rootRef.current && !rootRef.current.contains(event.target as Node)) {
        setOpen(false);
      }
    }

    document.addEventListener('mousedown', handleClickOutside);
    return () => document.removeEventListener('mousedown', handleClickOutside);
  }, [open]);

  const initials = user.name
    .split(' ')
    .map((part) => part[0])
    .slice(0, 2)
    .join('')
    .toUpperCase();

  return (
    <div ref={rootRef} className="relative">
      <button
        type="button"
        onClick={() => setOpen((previous) => !previous)}
        className="flex items-center gap-2.5 rounded-lg py-1.5 pe-2 ps-1.5 transition-colors hover:bg-slate-100"
      >
        <span className="flex size-8 shrink-0 items-center justify-center rounded-full bg-brand-600 text-xs font-semibold text-white">
          {initials}
        </span>
        <span className="hidden min-w-0 text-start sm:block">
          <span className="block truncate text-sm font-medium text-slate-900">{user.name}</span>
          <span className="block text-xs text-slate-500">{roleLabel}</span>
        </span>
        <ChevronDown className={`size-4 shrink-0 text-slate-400 transition-transform ${open ? 'rotate-180' : ''}`} />
      </button>

      {open && (
        <div className="absolute end-0 z-20 mt-2 w-56 overflow-hidden rounded-xl border border-slate-200 bg-white py-1.5 shadow-lg">
          <NavLink
            to="/mon-compte"
            onClick={() => setOpen(false)}
            className="flex items-center gap-2.5 px-3.5 py-2 text-sm text-slate-700 hover:bg-slate-50"
          >
            <UserCircle className="size-4 shrink-0 text-slate-400" />
            {t('nav.myAccount')}
          </NavLink>
          <button
            type="button"
            onClick={onLogout}
            className="flex w-full items-center gap-2.5 px-3.5 py-2 text-start text-sm text-slate-700 hover:bg-slate-50"
          >
            <LogOut className="size-4 shrink-0 text-slate-400" />
            {t('nav.logout')}
          </button>
        </div>
      )}
    </div>
  );
}
