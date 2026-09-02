import { Navigate, Outlet } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { useAuth } from '../context/AuthContext';

export function PlatformProtectedRoute() {
  const { user, isLoading } = useAuth();
  const { t } = useTranslation();

  if (isLoading) {
    return <div className="loading-screen">{t('common.loading')}</div>;
  }

  if (!user) {
    return <Navigate to="/login" replace />;
  }

  if (!user.is_platform_admin) {
    return <Navigate to="/dashboard" replace />;
  }

  return <Outlet />;
}
