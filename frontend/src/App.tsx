import { useEffect } from 'react';
import { useTranslation } from 'react-i18next';
import { Navigate, Route, BrowserRouter, Routes } from 'react-router-dom';
import { AuthProvider } from './context/AuthContext';
import { ProtectedRoute } from './components/ProtectedRoute';
import { PlatformProtectedRoute } from './components/PlatformProtectedRoute';
import { Layout } from './components/Layout';
import { PlatformLayout } from './components/PlatformLayout';
import { PlatformClientsPage } from './pages/PlatformClientsPage';
import { RegisterPage } from './pages/RegisterPage';
import { LoginPage } from './pages/LoginPage';
import { DashboardPage } from './pages/DashboardPage';
import { BuildingsPage } from './pages/BuildingsPage';
import { LotTypesPage } from './pages/LotTypesPage';
import { LotsPage } from './pages/LotsPage';
import { CotisationsPage } from './pages/CotisationsPage';
import { PaymentsPage } from './pages/PaymentsPage';
import { ImpayesPage } from './pages/ImpayesPage';
import { ExpensesPage } from './pages/ExpensesPage';
import { ExpenseCategoriesPage } from './pages/ExpenseCategoriesPage';
import { RevenuesPage } from './pages/RevenuesPage';
import { RevenueCategoriesPage } from './pages/RevenueCategoriesPage';
import { ResidenceSettingsPage } from './pages/ResidenceSettingsPage';
import { TreasuryPage } from './pages/TreasuryPage';
import { SubscriptionPage } from './pages/SubscriptionPage';
import { UsersPage } from './pages/UsersPage';
import { RolePermissionsPage } from './pages/RolePermissionsPage';
import { PaymentsReportPage } from './pages/PaymentsReportPage';

function App() {
  const { i18n } = useTranslation();

  useEffect(() => {
    document.documentElement.lang = i18n.language;
    document.documentElement.dir = i18n.language === 'ar' ? 'rtl' : 'ltr';
  }, [i18n.language]);

  return (
    <BrowserRouter>
      <AuthProvider>
        <Routes>
          <Route path="/" element={<Navigate to="/dashboard" replace />} />
          <Route path="/register" element={<RegisterPage />} />
          <Route path="/login" element={<LoginPage />} />
          <Route element={<ProtectedRoute />}>
            <Route element={<Layout />}>
              <Route path="/dashboard" element={<DashboardPage />} />
              <Route path="/tresorerie" element={<TreasuryPage />} />
              <Route path="/buildings" element={<BuildingsPage />} />
              <Route path="/lot-types" element={<LotTypesPage />} />
              <Route path="/lots" element={<LotsPage />} />
              <Route path="/cotisations" element={<CotisationsPage />} />
              <Route path="/paiements" element={<PaymentsPage />} />
              <Route path="/impayes" element={<ImpayesPage />} />
              <Route path="/rapports/paiements" element={<PaymentsReportPage />} />
              <Route path="/depenses" element={<ExpensesPage />} />
              <Route path="/depenses/categories" element={<ExpenseCategoriesPage />} />
              <Route path="/recettes" element={<RevenuesPage />} />
              <Route path="/recettes/categories" element={<RevenueCategoriesPage />} />
              <Route path="/residence" element={<ResidenceSettingsPage />} />
              <Route path="/utilisateurs" element={<UsersPage />} />
              <Route path="/permissions" element={<RolePermissionsPage />} />
              <Route path="/abonnement" element={<SubscriptionPage />} />
            </Route>
          </Route>
          <Route element={<PlatformProtectedRoute />}>
            <Route element={<PlatformLayout />}>
              <Route path="/platform" element={<PlatformClientsPage />} />
            </Route>
          </Route>
        </Routes>
      </AuthProvider>
    </BrowserRouter>
  );
}

export default App;
