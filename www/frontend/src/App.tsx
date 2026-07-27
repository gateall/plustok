import { Navigate, Route, Routes } from 'react-router-dom';

import { ProtectedRoute } from './features/auth/ProtectedRoute';
import { AdminRoute } from './features/auth/AdminRoute';
import AdminLayout from './layouts/AdminLayout';
import LoginPage from './pages/LoginPage';
import FindIdPage from './pages/FindIdPage';
import ForgotPasswordPage from './pages/ForgotPasswordPage';
import ResetPasswordPage from './pages/ResetPasswordPage';
import ChatScreen from './pages/ChatScreen';
import AdminDashboardPage from './pages/AdminDashboardPage';
import AdminPlaceholderPage from './pages/AdminPlaceholderPage';
import ConsultManagerPage from './components/consults/manager/ConsultManagerPage';
import AdminMorePage from './pages/AdminMorePage';
import NotFoundPage from './pages/NotFoundPage';
import AdminCustomersPage from './pages/AdminCustomersPage';
import AdminContractsPage from './pages/AdminContractsPage';
import ContractDetailPage from './pages/ContractDetailPage';
import ContractCreatePage from './pages/ContractCreatePage';
import AdminSitesPage from './pages/AdminSitesPage';

export default function App() {
  return (
    <Routes>
      <Route path="/index.html" element={<Navigate to="/chat" replace />} />
      <Route path="/" element={<Navigate to="/chat" replace />} />
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
            <AdminLayout />
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
        <Route
          path="stats"
          element={
            <AdminPlaceholderPage
              title="통계"
              description="상세 통계 차트는 Phase 3 이후 제공됩니다."
              phase="Phase 3"
            />
          }
        />
        <Route path="more" element={<AdminMorePage />} />
      </Route>
      <Route path="*" element={<NotFoundPage />} />
    </Routes>
  );
}
