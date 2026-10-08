import React, { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { listPayers } from '../../../services/PayerService';
import { listAssessments, listRevenueTypes } from '../../../services/RevenueService';
import { hasPermission } from '../../../services/AuthService';
import {
  checkChannelPayment,
  fetchChannelRate,
  initiateChannelPayment,
  listChannelPayments,
  listSupervisorNotifications,
  runChannelRetries,
} from '../../../services/ChannelService';

const ChannelPaymentsPage = () => {
  const canCapture = hasPermission('payments.capture') || hasPermission('payments.pay_own');
  const payOwnOnly = hasPermission('payments.pay_own') && !hasPermission('payments.capture');
  const canRetry = hasPermission('payments.approve_reversal') || hasPermission('payments.capture');
  const canSeeAlerts = hasPermission('payments.approve_reversal') || hasPermission('audit.view');
  const [items, setItems] = useState([]);
  const [meta, setMeta] = useState(null);
  const [payers, setPayers] = useState([]);
  const [types, setTypes] = useState([]);
  const [assessments, setAssessments] = useState([]);
  const [rate, setRate] = useState(null);
  const [notifications, setNotifications] = useState([]);
  const [q, setQ] = useState('');
  const [status, setStatus] = useState('');
  const [channel, setChannel] = useState('');
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState('');
  const [info, setInfo] = useState('');
  const [form, setForm] = useState({
    payer_id: '',
    assessment_id: '',
    revenue_code: 'BIZLIC',
    channel: 'MOBILE_MONEY',
    amount: '',
    currency: 'USD',
    simulate: 'PENDING',
  });

  const load = async (page = 1) => {
    setLoading(true);
    setError('');
    try {
      const { data } = await listChannelPayments({
        q: q || undefined,
        status: status || undefined,
        channel: channel || undefined,
        page,
        per_page: 15,
      });
      setItems(data.data || []);
      setMeta(data);
    } catch (err) {
      setError(err.response?.data?.message || 'Failed to load channel payments.');
    } finally {
      setLoading(false);
    }
  };

  const loadRate = async (quote = 'SOS') => {
    try {
      const { data } = await fetchChannelRate({ quote });
      setRate(data.rate);
    } catch {
      setRate(null);
    }
  };

  useEffect(() => {
    load();
    loadRate('SOS');
    listPayers({ per_page: 100 }).then((res) => setPayers(res.data.data || [])).catch(() => {});
    listRevenueTypes().then((res) => setTypes(res.data.data || [])).catch(() => {});
    listAssessments({ per_page: 50, status: 'OPEN' }).then((res) => setAssessments(res.data.data || [])).catch(() => {});
    if (canSeeAlerts) {
      listSupervisorNotifications({ per_page: 10 }).then((res) => setNotifications(res.data.data || [])).catch(() => {});
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  const onSubmit = async (e) => {
    e.preventDefault();
    setSaving(true);
    setError('');
    setInfo('');
    try {
      const { data } = await initiateChannelPayment({
        ...form,
        payer_id: Number(form.payer_id),
        assessment_id: form.assessment_id ? Number(form.assessment_id) : null,
        amount: Number(form.amount),
      });
      setInfo(`Initiated ${data.channel_payment.external_ref} → ${data.channel_payment.status}`);
      setForm({ ...form, amount: '', assessment_id: '' });
      await loadRate(form.currency === 'USD' ? 'SOS' : form.currency);
      await load();
    } catch (err) {
      setError(err.response?.data?.message || 'Initiate failed.');
    } finally {
      setSaving(false);
    }
  };

  const onCheck = async (id) => {
    setError('');
    try {
      const { data } = await checkChannelPayment(id);
      setInfo(`Status check: ${data.channel_payment.external_ref} → ${data.channel_payment.status}`);
      await load();
      if (canSeeAlerts) {
        const n = await listSupervisorNotifications({ per_page: 10 });
        setNotifications(n.data.data || []);
      }
    } catch (err) {
      setError(err.response?.data?.message || 'Status check failed.');
    }
  };

  const onRetryDue = async () => {
    setError('');
    try {
      const { data } = await runChannelRetries();
      setInfo(`Processed ${data.processed} due retry/status check(s).`);
      await load();
      if (canSeeAlerts) {
        const n = await listSupervisorNotifications({ per_page: 10 });
        setNotifications(n.data.data || []);
      }
    } catch (err) {
      setError(err.response?.data?.message || 'Retry run failed.');
    }
  };

  return (
    <>
      <div className="page-titles">
        <ol className="breadcrumb">
          <li className="breadcrumb-item"><Link to="/dashboard">Dashboard</Link></li>
          <li className="breadcrumb-item active"><Link to="/channel-payments">Channel Payments</Link></li>
        </ol>
      </div>

      <div className="row">
        {canCapture && (
          <div className="col-xl-4">
            <div className="card">
              <div className="card-header"><h4 className="card-title">Initiate channel payment</h4></div>
              <div className="card-body">
                {rate && (
                  <div className="alert alert-secondary py-2">
                    Live mock FX: 1 {rate.base_currency} = {Number(rate.rate).toLocaleString()} {rate.quote_currency}
                    <div><small>Retrieved {rate.retrieved_at}</small></div>
                  </div>
                )}
                <form onSubmit={onSubmit}>
                  <div className="mb-3">
                    <label className="form-label">Payer</label>
                    <select className="form-control" value={form.payer_id} onChange={(e) => setForm({ ...form, payer_id: e.target.value })} required>
                      <option value="">Select payer</option>
                      {payers.map((p) => <option key={p.id} value={p.id}>{p.tin} — {p.full_name}</option>)}
                    </select>
                  </div>
                  <div className="mb-3">
                    <label className="form-label">{payOwnOnly ? 'Assessment (required)' : 'Assessment (optional)'}</label>
                    <select
                      className="form-control"
                      value={form.assessment_id}
                      onChange={(e) => setForm({ ...form, assessment_id: e.target.value })}
                      required={payOwnOnly}
                    >
                      <option value="">{payOwnOnly ? 'Select your assessment' : 'None'}</option>
                      {assessments.filter((a) => !form.payer_id || String(a.payer_id) === String(form.payer_id)).map((a) => (
                        <option key={a.id} value={a.id}>{a.control_number} ({a.revenue_code})</option>
                      ))}
                    </select>
                  </div>
                  <div className="mb-3">
                    <label className="form-label">Revenue code</label>
                    <select className="form-control" value={form.revenue_code} onChange={(e) => setForm({ ...form, revenue_code: e.target.value })}>
                      {types.map((t) => <option key={t.id} value={t.revenue_code}>{t.revenue_code}</option>)}
                    </select>
                  </div>
                  <div className="mb-3">
                    <label className="form-label">Channel</label>
                    <select className="form-control" value={form.channel} onChange={(e) => setForm({ ...form, channel: e.target.value })}>
                      <option value="MOBILE_MONEY">MOBILE_MONEY</option>
                      <option value="BANK">BANK</option>
                    </select>
                  </div>
                  <div className="row">
                    <div className="col-6 mb-3">
                      <label className="form-label">Amount</label>
                      <input type="number" step="0.01" className="form-control" value={form.amount} onChange={(e) => setForm({ ...form, amount: e.target.value })} required />
                    </div>
                    <div className="col-6 mb-3">
                      <label className="form-label">Currency</label>
                      <select className="form-control" value={form.currency} onChange={(e) => { setForm({ ...form, currency: e.target.value }); loadRate(e.target.value === 'USD' ? 'SOS' : e.target.value); }}>
                        <option value="USD">USD</option>
                        <option value="SOS">SOS</option>
                      </select>
                    </div>
                  </div>
                  {!payOwnOnly && (
                    <div className="mb-3">
                      <label className="form-label">Simulate provider result (local only)</label>
                      <select className="form-control" value={form.simulate} onChange={(e) => setForm({ ...form, simulate: e.target.value })}>
                        <option value="PENDING">PENDING (needs status/callback)</option>
                        <option value="SUCCESS">SUCCESS immediately</option>
                        <option value="FAILED">FAILED</option>
                      </select>
                    </div>
                  )}
                  <button className="btn btn-primary w-100" disabled={saving}>{saving ? 'Initiating...' : 'Step 2: Initiate payment'}</button>
                </form>
                <p className="small text-muted mt-3 mb-0">
                  Flow: 1) fetch FX rates → 2) initiate mock bank/MM payment → 3) callback or status check updates assessment/bill.
                </p>
              </div>
            </div>
          </div>
        )}

        <div className={canCapture ? 'col-xl-8' : 'col-xl-12'}>
          <div className="card">
            <div className="card-header d-flex justify-content-between align-items-center">
              <h4 className="card-title mb-0">Channel payments</h4>
              {canRetry && <button className="btn btn-sm btn-outline-primary" onClick={onRetryDue}>Run due retries</button>}
            </div>
            <div className="card-body">
              <div className="row g-2 mb-3">
                <div className="col-md-4">
                  <input className="form-control" placeholder="Search ref / TIN..." value={q} onChange={(e) => setQ(e.target.value)} onKeyDown={(e) => e.key === 'Enter' && load()} />
                </div>
                <div className="col-md-3">
                  <select className="form-control" value={channel} onChange={(e) => setChannel(e.target.value)}>
                    <option value="">All channels</option>
                    <option value="BANK">BANK</option>
                    <option value="MOBILE_MONEY">MOBILE_MONEY</option>
                  </select>
                </div>
                <div className="col-md-3">
                  <select className="form-control" value={status} onChange={(e) => setStatus(e.target.value)}>
                    <option value="">All statuses</option>
                    <option value="PENDING">PENDING</option>
                    <option value="SUCCESS">SUCCESS</option>
                    <option value="FAILED">FAILED</option>
                    <option value="PERMANENTLY_FAILED">PERMANENTLY_FAILED</option>
                  </select>
                </div>
                <div className="col-md-2">
                  <button className="btn btn-outline-primary w-100" onClick={() => load()}>Filter</button>
                </div>
              </div>

              {error && <div className="alert alert-danger">{error}</div>}
              {info && <div className="alert alert-info">{info}</div>}

              {loading ? <p>Loading...</p> : (
                <div className="table-responsive">
                  <table className="table table-hover">
                    <thead>
                      <tr>
                        <th>Ref</th>
                        <th>Payer</th>
                        <th>Channel</th>
                        <th>USD / Local</th>
                        <th>Status</th>
                        <th>Retries</th>
                        <th></th>
                      </tr>
                    </thead>
                    <tbody>
                      {items.map((p) => (
                        <tr key={p.id}>
                          <td>
                            {p.external_ref}
                            <div><small>{p.provider_txn_id}</small></div>
                          </td>
                          <td>{p.payer?.tin}<br /><small>{p.payer?.full_name}</small></td>
                          <td>{p.channel}</td>
                          <td>
                            ${Number(p.amount_usd).toFixed(2)}
                            <div><small>{Number(p.amount_local || 0).toLocaleString()} {p.local_currency}</small></div>
                          </td>
                          <td><span className="badge badge-primary">{p.status}</span></td>
                          <td>{p.retry_count}/{p.max_retries}</td>
                          <td>
                            {canRetry && !['SUCCESS', 'PERMANENTLY_FAILED'].includes(p.status) && (
                              <button className="btn btn-sm btn-outline-secondary" onClick={() => onCheck(p.id)}>Check</button>
                            )}
                          </td>
                        </tr>
                      ))}
                      {!items.length && <tr><td colSpan="7" className="text-muted text-center">No channel payments yet.</td></tr>}
                    </tbody>
                  </table>
                </div>
              )}
              {meta?.last_page > 1 && (
                <div className="d-flex justify-content-between mt-2">
                  <small>Page {meta.current_page} / {meta.last_page}</small>
                  <div className="btn-group">
                    <button className="btn btn-sm btn-outline-secondary" disabled={meta.current_page <= 1} onClick={() => load(meta.current_page - 1)}>Prev</button>
                    <button className="btn btn-sm btn-outline-secondary" disabled={meta.current_page >= meta.last_page} onClick={() => load(meta.current_page + 1)}>Next</button>
                  </div>
                </div>
              )}
            </div>
          </div>

          {canSeeAlerts && (
            <div className="card">
              <div className="card-header"><h4 className="card-title">Supervisor notifications</h4></div>
              <div className="card-body">
                {notifications.length === 0 ? <p className="text-muted mb-0">No permanent-failure alerts yet.</p> : (
                  <ul className="list-group">
                    {notifications.map((n) => (
                      <li key={n.id} className="list-group-item">
                        <strong>{n.title}</strong>
                        <div className="small">{n.message}</div>
                        <small className="text-muted">{n.created_at}</small>
                      </li>
                    ))}
                  </ul>
                )}
              </div>
            </div>
          )}
        </div>
      </div>
    </>
  );
};

export default ChannelPaymentsPage;
