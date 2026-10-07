import React, { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { listPayers } from '../../../services/PayerService';
import { createPayment, listAssessments, listPayments, listRevenueTypes, uploadPaymentsCsv } from '../../../services/RevenueService';
import { hasPermission } from '../../../services/AuthService';

const PaymentsPage = () => {
  const [items, setItems] = useState([]);
  const [meta, setMeta] = useState(null);
  const [payers, setPayers] = useState([]);
  const [assessments, setAssessments] = useState([]);
  const [types, setTypes] = useState([]);
  const [q, setQ] = useState('');
  const [channel, setChannel] = useState('');
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [uploadResult, setUploadResult] = useState(null);
  const [saving, setSaving] = useState(false);
  const canCapture = hasPermission('payments.capture');
  const [form, setForm] = useState({
    payer_id: '',
    assessment_id: '',
    revenue_code: 'BIZLIC',
    amount: '',
    channel: 'MOBILE_MONEY',
    external_ref: '',
    notes: '',
  });

  const load = async (page = 1) => {
    setLoading(true);
    setError('');
    try {
      const { data } = await listPayments({
        q: q || undefined,
        channel: channel || undefined,
        page,
        per_page: 15,
      });
      setItems(data.data || []);
      setMeta(data);
    } catch (err) {
      setError(err.response?.data?.message || 'Failed to load payments.');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    load();
    listPayers({ per_page: 100 }).then((res) => setPayers(res.data.data || [])).catch(() => {});
    listRevenueTypes().then((res) => setTypes(res.data.data || [])).catch(() => {});
    listAssessments({ per_page: 100, status: 'OPEN' }).then((res) => setAssessments(res.data.data || [])).catch(() => {});
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  const onSubmit = async (e) => {
    e.preventDefault();
    setSaving(true);
    setError('');
    try {
      await createPayment({
        payer_id: Number(form.payer_id),
        assessment_id: form.assessment_id ? Number(form.assessment_id) : null,
        revenue_code: form.revenue_code,
        amount: Number(form.amount),
        channel: form.channel,
        external_ref: form.external_ref,
        notes: form.notes || null,
      });
      setForm({
        payer_id: '',
        assessment_id: '',
        revenue_code: 'BIZLIC',
        amount: '',
        channel: 'MOBILE_MONEY',
        external_ref: '',
        notes: '',
      });
      await load();
    } catch (err) {
      const errors = err.response?.data?.errors;
      setError(errors ? Object.values(errors).flat().join(' ') : (err.response?.data?.message || 'Capture failed.'));
    } finally {
      setSaving(false);
    }
  };

  const onUpload = async (e) => {
    const file = e.target.files?.[0];
    if (!file) return;
    setUploadResult(null);
    setError('');
    try {
      const { data } = await uploadPaymentsCsv(file);
      setUploadResult(data);
      await load();
    } catch (err) {
      setError(err.response?.data?.message || 'CSV upload failed.');
    } finally {
      e.target.value = '';
    }
  };

  return (
    <>
      <div className="page-titles">
        <ol className="breadcrumb">
          <li className="breadcrumb-item"><Link to="/dashboard">Dashboard</Link></li>
          <li className="breadcrumb-item active"><Link to="/payments">Payments</Link></li>
        </ol>
      </div>

      <div className="row">
        {canCapture && (
          <div className="col-xl-4">
            <div className="card">
              <div className="card-header"><h4 className="card-title">Capture payment</h4></div>
              <div className="card-body">
                <form onSubmit={onSubmit}>
                  <div className="mb-3">
                    <label className="form-label">Payer</label>
                    <select className="form-control" value={form.payer_id} onChange={(e) => setForm({ ...form, payer_id: e.target.value })} required>
                      <option value="">Select payer</option>
                      {payers.map((p) => <option key={p.id} value={p.id}>{p.tin} — {p.full_name}</option>)}
                    </select>
                  </div>
                  <div className="mb-3">
                    <label className="form-label">Assessment (optional)</label>
                    <select className="form-control" value={form.assessment_id} onChange={(e) => setForm({ ...form, assessment_id: e.target.value })}>
                      <option value="">None</option>
                      {assessments.filter((a) => !form.payer_id || String(a.payer_id) === String(form.payer_id)).map((a) => (
                        <option key={a.id} value={a.id}>{a.control_number} ({a.revenue_code})</option>
                      ))}
                    </select>
                  </div>
                  <div className="mb-3">
                    <label className="form-label">Revenue code</label>
                    <select className="form-control" value={form.revenue_code} onChange={(e) => setForm({ ...form, revenue_code: e.target.value })} required>
                      {types.map((t) => <option key={t.id} value={t.revenue_code}>{t.revenue_code}</option>)}
                    </select>
                  </div>
                  <div className="mb-3">
                    <label className="form-label">Amount</label>
                    <input type="number" min="0.01" step="0.01" className="form-control" value={form.amount} onChange={(e) => setForm({ ...form, amount: e.target.value })} required />
                  </div>
                  <div className="mb-3">
                    <label className="form-label">Channel</label>
                    <select className="form-control" value={form.channel} onChange={(e) => setForm({ ...form, channel: e.target.value })}>
                      <option value="MOBILE_MONEY">MOBILE_MONEY</option>
                      <option value="BANK">BANK</option>
                      <option value="CASH">CASH</option>
                    </select>
                  </div>
                  <div className="mb-3">
                    <label className="form-label">External reference</label>
                    <input className="form-control" value={form.external_ref} onChange={(e) => setForm({ ...form, external_ref: e.target.value })} required />
                  </div>
                  <button className="btn btn-primary w-100" disabled={saving}>{saving ? 'Saving...' : 'Capture payment'}</button>
                </form>

                <hr />
                <h6>Bulk CSV upload</h6>
                <p className="small text-muted mb-2">
                  Columns: payer_tin,revenue_code,amount,channel,external_ref,control_number(optional)
                </p>
                <input type="file" accept=".csv,text/csv" className="form-control" onChange={onUpload} />
              </div>
            </div>
          </div>
        )}

        <div className={canCapture ? 'col-xl-8' : 'col-xl-12'}>
          <div className="card">
            <div className="card-header"><h4 className="card-title">Payments</h4></div>
            <div className="card-body">
              <div className="row g-2 mb-3">
                <div className="col-md-6">
                  <input className="form-control" placeholder="Search ref, TIN, name..." value={q} onChange={(e) => setQ(e.target.value)} onKeyDown={(e) => e.key === 'Enter' && load()} />
                </div>
                <div className="col-md-3">
                  <select className="form-control" value={channel} onChange={(e) => setChannel(e.target.value)}>
                    <option value="">All channels</option>
                    <option value="MOBILE_MONEY">MOBILE_MONEY</option>
                    <option value="BANK">BANK</option>
                    <option value="CASH">CASH</option>
                  </select>
                </div>
                <div className="col-md-3">
                  <button className="btn btn-outline-primary w-100" onClick={() => load()}>Search</button>
                </div>
              </div>

              {error && <div className="alert alert-danger">{error}</div>}
              {uploadResult && (
                <div className="alert alert-info">
                  Upload summary: total {uploadResult.summary.total_rows}, accepted {uploadResult.summary.accepted}, rejected {uploadResult.summary.rejected}
                  {uploadResult.rejected?.length > 0 && (
                    <ul className="mb-0 mt-2">
                      {uploadResult.rejected.slice(0, 5).map((r) => (
                        <li key={`${r.row}-${r.reason}`}>Row {r.row}: {r.reason}</li>
                      ))}
                    </ul>
                  )}
                </div>
              )}

              {loading ? <p>Loading...</p> : (
                <div className="table-responsive">
                  <table className="table table-hover">
                    <thead>
                      <tr>
                        <th>External ref</th>
                        <th>Payer</th>
                        <th>Amount</th>
                        <th>Channel</th>
                        <th>Assessment</th>
                        <th>FMIS</th>
                        <th>Paid at</th>
                      </tr>
                    </thead>
                    <tbody>
                      {items.map((p) => (
                        <tr key={p.id}>
                          <td>{p.external_ref}</td>
                          <td>{p.payer?.tin}<br /><small>{p.payer?.full_name}</small></td>
                          <td>{Number(p.amount).toFixed(2)} {p.currency}</td>
                          <td>{p.channel}</td>
                          <td>{p.assessment?.control_number || '—'}</td>
                          <td>{p.fmis_status}</td>
                          <td>{p.paid_at ? new Date(p.paid_at).toLocaleString() : '—'}</td>
                        </tr>
                      ))}
                      {!items.length && <tr><td colSpan="7" className="text-center text-muted">No payments found.</td></tr>}
                    </tbody>
                  </table>
                </div>
              )}
            </div>
          </div>
        </div>
      </div>
    </>
  );
};

export default PaymentsPage;
