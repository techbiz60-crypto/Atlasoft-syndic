import { Lock } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { useAuth } from '../context/AuthContext';

export function DeactivatedAccountBanner() {
  const { t } = useTranslation();
  const { user } = useAuth();

  if (!user?.deactivated_at) {
    return null;
  }

  return (
    <div className="flex items-center gap-2.5 bg-slate-700 px-6 py-2.5 text-sm font-medium text-white">
      <Lock className="size-4 shrink-0" />
      {t('syndicTransition.deactivatedBanner')}
    </div>
  );
}
