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

const PageFallback = () => (
  <div className="d-flex justify-content-center align-items-center py-5">
    <div className="spinner-border text-primary" role="status" aria-label="Loading" />
  </div>
);

const Markup = () => {
  const routes = [
    { url: "", component: <ExecutiveDashboard /> },
    { url: "dashboard", component: <ExecutiveDashboard /> },
    { url: "reports", component: <ExecutiveDashboard /> },

    { url: "payers", component: <PayersList /> },
    { url: "payers/create", component: <PayerCreate /> },
    { url: "payers/:id", component: <PayerProfile /> },

    { url: "assessments", component: <AssessmentsPage /> },
    { url: "my-assessments", component: <AssessmentsPage /> },
    { url: "payments", component: <PaymentsPage /> },

    { url: "meter-readings", component: <MeterReadingsPage /> },
    { url: "billing-cycles", component: <BillingCyclesPage /> },
    { url: "water-bills", component: <WaterBillsPage /> },
    { url: "my-bills", component: <WaterBillsPage /> },

    { url: "channel-payments", component: <ChannelPaymentsPage /> },
    { url: "reconciliation", component: <ReconciliationPage /> },

    { url: "fmis", component: <FmisPage /> },
    { url: "fmis-reconciliation", component: <FmisPage /> },

    { url: "users", component: <UsersRolesPage /> },
    { url: "audit-logs", component: <AuditLogsPage /> },
    { url: "system-config", component: <SystemConfigPage /> },
  ];

  return (
    <>
      <Routes>
        <Route element={<MainLayout />}>
          {routes.map((data, i) => (
            <Route
              key={i}
              path={data.url}
              element={<Suspense fallback={<PageFallback />}>{data.component}</Suspense>}
            />
          ))}
          <Route path="*" element={<Navigate to="/dashboard" replace />} />
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
