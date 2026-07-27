import { lazy, Suspense } from 'react';
import { Navigate, Route, Routes } from 'react-router-dom';

import { ProtectedRoute } from './features/auth/ProtectedRoute';
import { AdminRoute } from './features/auth/AdminRoute';
import AdminLayout from './layouts/AdminLayout';
import PageLoadingFallback from './components/common/PageLoadingFallback';
import ErrorBoundary from './components/common/ErrorBoundary';
import { LANDING_SECTIONS } from './config/publicNav';
import LoginPage from './pages/LoginPage';
import FindIdPage from './pages/FindIdPage';
import ForgotPasswordPage from './pages/ForgotPasswordPage';
import ResetPasswordPage from './pages/ResetPasswordPage';
import ChatScreen from './pages/ChatScreen';
import AdminDashboardPage from './pages/AdminDashboardPage';
import ConsultManagerPage from './components/consults/manager/ConsultManagerPage';
import AdminMorePage from './pages/AdminMorePage';
import NotFoundPage from './pages/NotFoundPage';
import AdminCustomersPage from './pages/AdminCustomersPage';
import AdminContractsPage from './pages/AdminContractsPage';
import ContractDetailPage from './pages/ContractDetailPage';
import ContractCreatePage from './pages/ContractCreatePage';
import AdminSitesPage from './pages/AdminSitesPage';
import AdminProductsPage from './pages/AdminProductsPage';
import AdminStatsPage from './pages/AdminStatsPage';
import AdminUsersPage from './pages/AdminUsersPage';
import AdminSettingsPage from './pages/AdminSettingsPage';

const LandingPage = lazy(() => import('./pages/LandingPage'));
const LandingSectionPage = lazy(() => import('./pages/LandingSectionPage'));

export default function App() {
  return (
    <Suspense fallback={<PageLoadingFallback />}>
      <Routes>
        <Route path="/index.html" element={<Navigate to="/landing" replace />} />
        <Route path="/" element={<LandingPage />} />
        <Route path="/landing" element={<LandingPage />} />
        {LANDING_SECTIONS.map(({ path, sectionId }) => (
          <Route key={path} path={path} element={<LandingSectionPage sectionId={sectionId} />} />
        ))}
        <Route path="/login" element={<LoginPage />} />
        <Route path="/find-id" element={<FindIdPage />} />
        <Route path="/forgot-password" element={<ForgotPasswordPage />} />
        <Route path="/reset-password" element={<ResetPasswordPage />} />
        <Route
          path="/chat"
          element={
            <ProtectedRoute>
              <ChatScreen />
            </ProtectedRoute>
          }
        />
        <Route
          path="/admin"
          element={
            <AdminRoute>
              <ErrorBoundary>
                <AdminLayout />
              </ErrorBoundary>
            </AdminRoute>
          }
        >
          <Route index element={<Navigate to="dashboard" replace />} />
          <Route path="dashboard" element={<AdminDashboardPage />} />
          <Route path="consults" element={<ConsultManagerPage />} />
          <Route path="consults/:id" element={<ConsultManagerPage />} />
          <Route path="customers" element={<AdminCustomersPage />} />
          <Route path="contracts" element={<AdminContractsPage />} />
          <Route path="contracts/new" element={<ContractCreatePage />} />
          <Route path="contracts/:id" element={<ContractDetailPage />} />
          <Route path="sites" element={<AdminSitesPage />} />
          <Route path="products" element={<AdminProductsPage />} />
          <Route path="stats" element={<AdminStatsPage />} />
          <Route path="users" element={<AdminUsersPage />} />
          <Route path="settings" element={<AdminSettingsPage />} />
          <Route path="more" element={<AdminMorePage />} />
        </Route>
        <Route path="*" element={<NotFoundPage />} />
      </Routes>
    </Suspense>
  );
}
