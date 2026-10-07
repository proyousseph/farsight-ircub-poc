import React, { useContext } from "react";
import { Routes, Route, Outlet, Navigate } from "react-router-dom";
import { useSelector } from "react-redux";

import "./index.css";
import "./chart.css";
import "./step.css";

import Nav from "./layouts/nav";
import Footer from "./layouts/Footer";
import ScrollToTop from "./pages/ScrollToTop";
import { ThemeContext } from "../context/ThemeContext";

import ExecutiveDashboard from "./components/Dashboard/ExecutiveDashboard";
import PayersList from "./components/Payers/PayersList";
import PayerCreate from "./components/Payers/PayerCreate";
import PayerProfile from "./components/Payers/PayerProfile";
import AssessmentsPage from "./components/Revenue/AssessmentsPage";
import PaymentsPage from "./components/Revenue/PaymentsPage";
import MeterReadingsPage from "./components/Water/MeterReadingsPage";
import BillingCyclesPage from "./components/Water/BillingCyclesPage";
import WaterBillsPage from "./components/Water/WaterBillsPage";
import ChannelPaymentsPage from "./components/Channels/ChannelPaymentsPage";
import ReconciliationPage from "./components/Channels/ReconciliationPage";
import FmisPage from "./components/Fmis/FmisPage";
import UsersRolesPage from "./components/Admin/UsersRolesPage";
import AuditLogsPage from "./components/Admin/AuditLogsPage";
import SystemConfigPage from "./components/Admin/SystemConfigPage";

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
            <Route key={i} path={data.url} element={data.component} />
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
