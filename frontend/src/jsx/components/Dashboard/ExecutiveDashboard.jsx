import React, { Suspense, lazy, useCallback, useEffect, useMemo, useState } from 'react';
import { Link } from 'react-router-dom';
import { getDashboard, getDashboardAlerts, refreshDashboard } from '../../../services/DashboardService';
import { hasPermission } from '../../../services/AuthService';

const ReactApexChart = lazy(() => import('react-apexcharts'));

const ChartFallback = () => (
  <div className="text-muted py-5 text-center">Loading chart…</div>
);

const money = (n) =>
  Number(n || 0).toLocaleString(undefined, { style: 'currency', currency: 'USD', maximumFractionDigits: 0 });

const severityClass = (severity) => {
  if (severity === 'critical') return 'alert-danger';
  if (severity === 'warning') return 'alert-warning';
  return 'alert-info';
};

const ExecutiveDashboard = () => {
  const canRefresh = hasPermission('dashboard.view') || hasPermission('reports.view');
  const [data, setData] = useState(null);
  const [error, setError] = useState('');
  const [info, setInfo] = useState('');
  const [loading, setLoading] = useState(true);
  const [busy, setBusy] = useState(false);
  const [lastPoll, setLastPoll] = useState('');

  const load = useCallback(async () => {
    setError('');
    try {
      const { data: payload } = await getDashboard({ cache_ttl: 30 });
      setData(payload);
      setLastPoll(new Date().toLocaleTimeString());
    } catch (err) {
      setError(err.response?.data?.message || 'Failed to load dashboard.');
    } finally {
      setLoading(false);
    }
  }, []);

  const pollAlerts = useCallback(async () => {
    try {
      const { data: payload } = await getDashboardAlerts({ cache_ttl: 15 });
      setData((prev) => (prev ? { ...prev, alerts: payload.alerts, kpis: payload.kpis || prev.kpis, generated_at: payload.generated_at } : prev));
      setLastPoll(new Date().toLocaleTimeString());
    } catch {
      // keep last good snapshot on poll failure
    }
  }, []);

  useEffect(() => {
    load();
    const id = setInterval(pollAlerts, 30000);
    return () => clearInterval(id);
  }, [load, pollAlerts]);

  const onRefresh = async () => {
    setBusy(true);
    setInfo('');
    setError('');
    try {
      const { data: payload } = await refreshDashboard();
      setData(payload.dashboard);
      setInfo(payload.message || 'Aggregates rebuilt.');
      setLastPoll(new Date().toLocaleTimeString());
    } catch (err) {
      setError(err.response?.data?.message || 'Refresh failed.');
    } finally {
      setBusy(false);
    }
  };

  const trendOptions = useMemo(() => {
    if (!data?.trends) return null;
    return {
      chart: { type: 'line', toolbar: { show: false }, zoom: { enabled: false } },
      stroke: { curve: 'smooth', width: 3 },
      dataLabels: { enabled: false },
      xaxis: { categories: data.trends.labels },
      yaxis: { labels: { formatter: (v) => `$${Math.round(v)}` } },
      legend: { position: 'top' },
      colors: ['#0d6efd', '#198754', '#fd7e14', '#6f42c1', '#20c997', '#dc3545'],
      tooltip: { y: { formatter: (v) => money(v) } },
    };
  }, [data]);

  const typeSeries = useMemo(() => {
    if (!data?.trends?.by_revenue_type) return [];
    return data.trends.by_revenue_type.map((s) => ({ name: s.label, data: s.data }));
  }, [data]);

  const channelSeries = useMemo(() => {
    if (!data?.trends?.by_channel) return [];
    return data.trends.by_channel.map((s) => ({ name: s.label, data: s.data }));
  }, [data]);

  const forecastOptions = useMemo(() => {
    if (!data?.forecast?.history) return null;
    const hist = data.forecast.history;
    const futureLabels = data.forecast.next_quarter.months.map((m) => m.period);
    const futureVals = data.forecast.next_quarter.months.map((m) => m.predicted);
    return {
      options: {
        chart: { type: 'line', toolbar: { show: false } },
        stroke: { width: [3, 2, 3], dashArray: [0, 0, 6], curve: 'smooth' },
        xaxis: { categories: [...hist.labels, ...futureLabels] },
        yaxis: { labels: { formatter: (v) => `$${Math.round(v)}` } },
        legend: { position: 'top' },
        colors: ['#0d6efd', '#6c757d', '#198754'],
        tooltip: { y: { formatter: (v) => money(v) } },
      },
      series: [
        { name: 'Actual', data: [...hist.actuals, null, null, null] },
        { name: 'Fitted', data: [...hist.fitted, null, null, null] },
        { name: 'Forecast', data: [...Array(hist.actuals.length).fill(null), ...futureVals] },
      ],
    };
  }, [data]);

  if (loading && !data) {
    return <div className="alert alert-light border">Loading executive dashboard…</div>;
  }

  const kpis = data?.kpis || {};
  const targets = data?.targets || {};
  const water = data?.water || {};
  const forecast = data?.forecast || {};

  return (
    <>
      <div className="page-titles">
        <ol className="breadcrumb">
          <li className="breadcrumb-item active"><Link to="/dashboard">Dashboard</Link></li>
        </ol>
      </div>

      <div className="d-flex flex-wrap align-items-center justify-content-between mb-3 gap-2">
        <div>
          <h3 className="mb-1">Executive Dashboard</h3>
          <p className="mb-0 text-muted">
            Collections, water efficiency, OLS next-quarter forecast, and anomaly alerts.
            {lastPoll ? ` · Last update ${lastPoll}` : ''}
            {data?.meta?.cache
              ? ` · Cache ${data.meta.cache.hit ? 'HIT' : 'MISS'} (${data.meta.cache.driver}, TTL ${data.meta.cache.ttl_seconds}s)`
              : ''}
          </p>
        </div>
        <div className="d-flex gap-2">
          <button type="button" className="btn btn-outline-secondary btn-sm" onClick={load} disabled={busy}>
            Reload
          </button>
          {canRefresh && (
            <button type="button" className="btn btn-primary btn-sm" onClick={onRefresh} disabled={busy}>
              {busy ? 'Refreshing…' : 'Rebuild aggregates'}
            </button>
          )}
        </div>
      </div>

      {error && <div className="alert alert-danger">{error}</div>}
      {info && <div className="alert alert-success">{info}</div>}

      <div className="row">
        {[
          { label: 'Collected today', value: money(kpis.collected_today) },
          { label: 'Collected MTD', value: money(kpis.collected_mtd) },
          { label: 'Payments MTD', value: kpis.payments_mtd ?? 0 },
          { label: '12-month collections', value: money(kpis.collected_12m) },
        ].map((card) => (
          <div className="col-xl-3 col-sm-6" key={card.label}>
            <div className="card">
              <div className="card-body py-3">
                <p className="mb-1 text-muted">{card.label}</p>
                <h3 className="mb-0">{card.value}</h3>
              </div>
            </div>
          </div>
        ))}
      </div>

      <div className="row">
        <div className="col-xl-8">
          <div className="card">
            <div className="card-header border-0 pb-0">
              <h4 className="card-title">Revenue trends by type</h4>
            </div>
            <div className="card-body">
              {trendOptions && typeSeries.length > 0 ? (
                <Suspense fallback={<ChartFallback />}>
                  <ReactApexChart options={trendOptions} series={typeSeries} type="line" height={320} />
                </Suspense>
              ) : (
                <p className="text-muted mb-0">No trend data yet.</p>
              )}
            </div>
          </div>
        </div>
        <div className="col-xl-4">
          <div className="card">
            <div className="card-header border-0 pb-0">
              <h4 className="card-title">Alerts</h4>
              <span className="fs-12 text-muted">Polling every 30s</span>
            </div>
            <div className="card-body">
              {(data?.alerts || []).map((alert) => (
                <div key={alert.code} className={`alert ${severityClass(alert.severity)} py-2`}>
                  <strong>{alert.title}</strong>
                  <div className="fs-13">{alert.message}</div>
                </div>
              ))}
            </div>
          </div>
        </div>
      </div>

      <div className="row">
        <div className="col-xl-6">
          <div className="card">
            <div className="card-header border-0 pb-0">
              <h4 className="card-title">Revenue trends by channel</h4>
            </div>
            <div className="card-body">
              {trendOptions && channelSeries.length > 0 ? (
                <Suspense fallback={<ChartFallback />}>
                  <ReactApexChart options={trendOptions} series={channelSeries} type="line" height={300} />
                </Suspense>
              ) : (
                <p className="text-muted mb-0">No channel trend data yet.</p>
              )}
            </div>
          </div>
        </div>
        <div className="col-xl-3">
          <div className="card h-100">
            <div className="card-header border-0 pb-0">
              <h4 className="card-title">Collections vs targets</h4>
            </div>
            <div className="card-body">
              <div className="mb-4">
                <div className="d-flex justify-content-between">
                  <span>Month {targets.month?.period}</span>
                  <strong>{targets.month?.achievement_pct ?? 0}%</strong>
                </div>
                <div className="progress mt-2" style={{ height: 10 }}>
                  <div className="progress-bar bg-primary" style={{ width: `${Math.min(100, targets.month?.achievement_pct || 0)}%` }} />
                </div>
                <small className="text-muted">{money(targets.month?.collected)} / {money(targets.month?.target)}</small>
              </div>
              <div>
                <div className="d-flex justify-content-between">
                  <span>Quarter {targets.quarter?.period}</span>
                  <strong>{targets.quarter?.achievement_pct ?? 0}%</strong>
                </div>
                <div className="progress mt-2" style={{ height: 10 }}>
                  <div className="progress-bar bg-success" style={{ width: `${Math.min(100, targets.quarter?.achievement_pct || 0)}%` }} />
                </div>
                <small className="text-muted">{money(targets.quarter?.collected)} / {money(targets.quarter?.target)}</small>
              </div>
            </div>
          </div>
        </div>
        <div className="col-xl-3">
          <div className="card h-100">
            <div className="card-header border-0 pb-0">
              <h4 className="card-title">Water billed vs collected</h4>
            </div>
            <div className="card-body">
              <h2 className="mb-1">{water.collection_efficiency_pct ?? 0}%</h2>
              <p className="text-muted">Collection efficiency</p>
              <ul className="list-unstyled mb-0">
                <li className="d-flex justify-content-between py-1"><span>Billed</span><strong>{money(water.billed)}</strong></li>
                <li className="d-flex justify-content-between py-1"><span>Collected</span><strong>{money(water.collected)}</strong></li>
                <li className="d-flex justify-content-between py-1"><span>Outstanding</span><strong>{money(water.outstanding)}</strong></li>
              </ul>
            </div>
          </div>
        </div>
      </div>

      <div className="row">
        <div className="col-xl-8">
          <div className="card">
            <div className="card-header border-0 pb-0">
              <h4 className="card-title">Next-quarter revenue forecast (OLS)</h4>
            </div>
            <div className="card-body">
              {forecastOptions ? (
                <Suspense fallback={<ChartFallback />}>
                  <ReactApexChart options={forecastOptions.options} series={forecastOptions.series} type="line" height={320} />
                </Suspense>
              ) : (
                <p className="text-muted mb-0">Forecast unavailable.</p>
              )}
            </div>
          </div>
        </div>
        <div className="col-xl-4">
          <div className="card h-100">
            <div className="card-header border-0 pb-0">
              <h4 className="card-title">Forecast summary</h4>
            </div>
            <div className="card-body">
              <p className="mb-1 text-muted">{forecast.next_quarter?.label}</p>
              <h2 className="mb-3">{money(forecast.next_quarter?.predicted_total)}</h2>
              <p className="fs-13 text-muted">{forecast.justification}</p>
              <ul className="list-unstyled mb-0">
                <li className="d-flex justify-content-between py-1"><span>Slope</span><strong>{forecast.slope}</strong></li>
                <li className="d-flex justify-content-between py-1"><span>Intercept</span><strong>{forecast.intercept}</strong></li>
                <li className="d-flex justify-content-between py-1"><span>R²</span><strong>{forecast.r_squared}</strong></li>
              </ul>
              <hr />
              {(forecast.next_quarter?.months || []).map((m) => (
                <div key={m.period} className="d-flex justify-content-between py-1">
                  <span>{m.period}</span>
                  <strong>{money(m.predicted)}</strong>
                </div>
              ))}
            </div>
          </div>
        </div>
      </div>
    </>
  );
};

export default ExecutiveDashboard;
