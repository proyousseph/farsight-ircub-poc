import React, { Suspense, lazy, useContext } from "react";
import { Routes, Route, Outlet, Navigate } from "react-router-dom";
import { useSelector } from "react-redux";

import "./index.css";
import "./chart.css";
import "./step.css";

import Nav from "./layouts/nav";
import Footer from "./layouts/Footer";
import ScrollToTop from "./pages/ScrollToTop";
import { ThemeContext } from "../context/ThemeContext";
import RequirePermission from "./components/Common/RequirePermission";
import { getStoredUser } from "../services/AuthService";

const ExecutiveDashboard = lazy(() => import("./components/Dashboard/ExecutiveDashboard"));
const PayersList = lazy(() => import("./components/Payers/PayersList"));
const PayerCreate = lazy(() => import("./components/Payers/PayerCreate"));
const PayerProfile = lazy(() => import("./components/Payers/PayerProfile"));
const AssessmentsPage = lazy(() => import("./components/Revenue/AssessmentsPage"));
const PaymentsPage = lazy(() => import("./components/Revenue/PaymentsPage"));
const MeterReadingsPage = lazy(() => import("./components/Water/MeterReadingsPage"));
const BillingCyclesPage = lazy(() => import("./components/Water/BillingCyclesPage"));
const WaterBillsPage = lazy(() => import("./components/Water/WaterBillsPage"));
const ChannelPaymentsPage = lazy(() => import("./components/Channels/ChannelPaymentsPage"));
const ReconciliationPage = lazy(() => import("./components/Channels/ReconciliationPage"));
const FmisPage = lazy(() => import("./components/Fmis/FmisPage"));
const UsersRolesPage = lazy(() => import("./components/Admin/UsersRolesPage"));
const AuditLogsPage = lazy(() => import("./components/Admin/AuditLogsPage"));
const SystemConfigPage = lazy(() => import("./components/Admin/SystemConfigPage"));
const ChangePassword = lazy(() => import("./pages/ChangePassword"));

const PageFallback = () => (
  <div className="d-flex justify-content-center align-items-center py-5">
    <div className="spinner-border text-primary" role="status" aria-label="Loading" />
  </div>
);

const Markup = () => {
  const auth = useSelector((state) => state.auth.auth);
  const stored = getStoredUser();
  const mustChange = Boolean(auth?.must_change_password || stored?.must_change_password);

  if (mustChange) {
    return (
      <>
        <Routes>
          <Route
            path="change-password"
            element={
              <Suspense fallback={<PageFallback />}>
                <ChangePassword />
              </Suspense>
            }
          />
          <Route path="*" element={<Navigate to="/change-password" replace />} />
        </Routes>
        <ScrollToTop />
      </>
    );
  }

  const routes = [
    { url: "", component: <ExecutiveDashboard />, anyOf: ['dashboard.view'] },
    { url: "dashboard", component: <ExecutiveDashboard />, anyOf: ['dashboard.view'] },
    { url: "reports", component: <ExecutiveDashboard />, anyOf: ['dashboard.view', 'reports.view'] },

    { url: "payers", component: <PayersList />, anyOf: ['payers.view', 'payers.view_own'] },
    { url: "payers/create", component: <PayerCreate />, anyOf: ['payers.create'] },
    { url: "payers/:id", component: <PayerProfile />, anyOf: ['payers.view', 'payers.view_own'] },

    { url: "assessments", component: <AssessmentsPage />, anyOf: ['assessments.view', 'assessments.view_own'] },
    { url: "my-assessments", component: <AssessmentsPage />, anyOf: ['assessments.view', 'assessments.view_own'] },
    { url: "payments", component: <PaymentsPage />, anyOf: ['payments.view', 'payments.view_own', 'payments.capture'] },

    { url: "meter-readings", component: <MeterReadingsPage />, anyOf: ['meters.capture', 'bills.view'] },
    { url: "billing-cycles", component: <BillingCyclesPage />, anyOf: ['billing.run', 'bills.view'] },
    { url: "water-bills", component: <WaterBillsPage />, anyOf: ['bills.view', 'bills.view_own'] },
    { url: "my-bills", component: <WaterBillsPage />, anyOf: ['bills.view', 'bills.view_own'] },

    { url: "channel-payments", component: <ChannelPaymentsPage />, anyOf: ['payments.view', 'payments.view_own', 'payments.capture', 'payments.pay_own'] },
    { url: "reconciliation", component: <ReconciliationPage />, anyOf: ['channels.reconcile', 'fmis.reconcile'] },

    { url: "fmis", component: <FmisPage />, anyOf: ['fmis.post', 'fmis.reconcile'] },
    { url: "fmis-reconciliation", component: <FmisPage />, anyOf: ['fmis.reconcile', 'fmis.post'] },

    { url: "users", component: <UsersRolesPage />, anyOf: ['users.manage', 'roles.manage'] },
    { url: "audit-logs", component: <AuditLogsPage />, anyOf: ['audit.view'] },
    { url: "system-config", component: <SystemConfigPage />, anyOf: ['config.manage'] },
    { url: "change-password", component: <ChangePassword /> },
  ];

  const defaultPath = auth?.permissions?.includes('dashboard.view')
    || stored?.permissions?.includes('dashboard.view')
    ? '/dashboard'
    : (stored?.permissions?.includes('bills.view_own') ? '/my-bills' : '/payers');

  return (
    <>
      <Routes>
        <Route element={<MainLayout />}>
          {routes.map((data, i) => (
            <Route
              key={i}
              path={data.url}
              element={
                <Suspense fallback={<PageFallback />}>
                  {data.anyOf ? (
                    <RequirePermission anyOf={data.anyOf}>{data.component}</RequirePermission>
                  ) : (
                    data.component
                  )}
                </Suspense>
              }
            />
          ))}
          <Route path="*" element={<Navigate to={defaultPath} replace />} />
        </Route>
      </Routes>
      <ScrollToTop />
    </>
  );
};

function MainLayout() {
  const { sidebariconHover } = useContext(ThemeContext);
  const sideMenu = useSelector((state) => state.sideMenu);

  return (
    <div
      id="main-wrapper"
      className={`show ${sidebariconHover ? "iconhover-toggle" : ""} ${sideMenu ? "menu-toggle" : ""}`}
    >
      <Nav />
      <div className="content-body" style={{ minHeight: "calc(100vh - 60px)" }}>
        <div className="container-fluid">
          <Outlet />
        </div>
      </div>
      <Footer />
    </div>
  );
}

export default Markup;
