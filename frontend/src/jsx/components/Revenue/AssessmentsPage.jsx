import React, { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { listPayers } from '../../../services/PayerService';
import { createAssessment, listAssessments, listRevenueTypes } from '../../../services/RevenueService';
import { hasPermission } from '../../../services/AuthService';

const AssessmentsPage = () => {
  const [items, setItems] = useState([]);
  const [meta, setMeta] = useState(null);
  const [payers, setPayers] = useState([]);
  const [types, setTypes] = useState([]);
  const [q, setQ] = useState('');
  const [status, setStatus] = useState('');
  const [revenueCode, setRevenueCode] = useState('');
  const [amountMin, setAmountMin] = useState('');
  const [amountMax, setAmountMax] = useState('');
  const [dueFrom, setDueFrom] = useState('');
  const [dueTo, setDueTo] = useState('');
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [saving, setSaving] = useState(false);
  const canCreate = hasPermission('assessments.create');
  const [form, setForm] = useState({
    payer_id: '',
    revenue_code: 'BIZLIC',
    amount_due: '',
    due_date: '',
    period: '',
    notes: '',
  });

  const load = async (page = 1, filters = null) => {
    const f = filters || {
      q,
      status,
      revenue_code: revenueCode,
      amount_min: amountMin,
      amount_max: amountMax,
      due_from: dueFrom,
      due_to: dueTo,
    };
    setLoading(true);
    setError('');
    try {
      const { data } = await listAssessments({
        q: f.q || undefined,
        status: f.status || undefined,
        revenue_code: f.revenue_code || undefined,
        amount_min: f.amount_min || undefined,
        amount_max: f.amount_max || undefined,
        due_from: f.due_from || undefined,
        due_to: f.due_to || undefined,
        page,
        per_page: 15,
      });
      setItems(data.data || []);
      setMeta(data);
    } catch (err) {
      setError(err.response?.data?.message || 'Failed to load assessments.');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    load();
    listPayers({ per_page: 100 }).then((res) => setPayers(res.data.data || [])).catch(() => {});
    listRevenueTypes({ category: 'TAX' }).then((res) => setTypes(res.data.data || [])).catch(() => {});
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  const onSubmit = async (e) => {
    e.preventDefault();
    setSaving(true);
    setError('');
    try {
      await createAssessment({
        ...form,
        payer_id: Number(form.payer_id),
        amount_due: Number(form.amount_due),
      });
      setForm({
        payer_id: '',
        revenue_code: 'BIZLIC',
        amount_due: '',
        due_date: '',
        period: '',
        notes: '',
      });
      await load();
    } catch (err) {
      const errors = err.response?.data?.errors;
      setError(errors ? Object.values(errors).flat().join(' ') : (err.response?.data?.message || 'Create failed.'));
    } finally {
      setSaving(false);
    }
  };

  return (
    <>
      <div className="page-titles">
        <ol className="breadcrumb">
          <li className="breadcrumb-item"><Link to="/dashboard">Dashboard</Link></li>
          <li className="breadcrumb-item active"><Link to="/assessments">Assessments</Link></li>
        </ol>
      </div>

      <div className="row">
        {canCreate && (
          <div className="col-xl-4">
            <div className="card">
              <div className="card-header"><h4 className="card-title">Create assessment</h4></div>
              <div className="card-body">
                <form onSubmit={onSubmit}>
                  <div className="mb-3">
                    <label className="form-label">Payer</label>
                    <select className="form-control" value={form.payer_id} onChange={(e) => setForm({ ...form, payer_id: e.target.value })} required>
                      <option value="">Select payer</option>
                      {payers.map((p) => (
                        <option key={p.id} value={p.id}>{p.tin} — {p.full_name}</option>
                      ))}
                    </select>
                  </div>
                  <div className="mb-3">
                    <label className="form-label">Revenue type</label>
                    <select className="form-control" value={form.revenue_code} onChange={(e) => setForm({ ...form, revenue_code: e.target.value })} required>
                      {types.map((t) => (
                        <option key={t.id} value={t.revenue_code}>{t.revenue_code} — {t.name}</option>
                      ))}
                    </select>
                  </div>
                  <div className="mb-3">
                    <label className="form-label">Amount due</label>
                    <input type="number" step="0.01" min="0.01" className="form-control" value={form.amount_due} onChange={(e) => setForm({ ...form, amount_due: e.target.value })} required />
                  </div>
                  <div className="mb-3">
                    <label className="form-label">Due date</label>
                    <input type="date" className="form-control" value={form.due_date} onChange={(e) => setForm({ ...form, due_date: e.target.value })} required />
                  </div>
                  <div className="mb-3">
                    <label className="form-label">Period</label>
                    <input className="form-control" placeholder="2026-10" value={form.period} onChange={(e) => setForm({ ...form, period: e.target.value })} />
                  </div>
                  <button className="btn btn-primary w-100" disabled={saving}>{saving ? 'Saving...' : 'Create assessment'}</button>
                </form>
              </div>
            </div>
          </div>
        )}

        <div className={canCreate ? 'col-xl-8' : 'col-xl-12'}>
          <div className="card">
            <div className="card-header">
              <h4 className="card-title">Tax assessments</h4>
            </div>
            <div className="card-body">
              <div className="row g-2 mb-3">
                <div className="col-md-4">
                  <input className="form-control" placeholder="Search control no, TIN, name..." value={q} onChange={(e) => setQ(e.target.value)} onKeyDown={(e) => e.key === 'Enter' && load()} />
                </div>
                <div className="col-md-2">
                  <select className="form-control" value={revenueCode} onChange={(e) => setRevenueCode(e.target.value)}>
                    <option value="">All revenue types</option>
                    {types.map((t) => (
                      <option key={t.id} value={t.revenue_code}>{t.revenue_code}</option>
                    ))}
                  </select>
                </div>
                <div className="col-md-2">
                  <select className="form-control" value={status} onChange={(e) => setStatus(e.target.value)}>
                    <option value="">All statuses</option>
                    <option value="OPEN">OPEN</option>
                    <option value="PART_PAID">PART_PAID</option>
                    <option value="PAID">PAID</option>
                    <option value="REVERSED">REVERSED</option>
                  </select>
                </div>
                <div className="col-md-2">
                  <input type="number" className="form-control" placeholder="Min amount" value={amountMin} onChange={(e) => setAmountMin(e.target.value)} />
                </div>
                <div className="col-md-2">
                  <input type="number" className="form-control" placeholder="Max amount" value={amountMax} onChange={(e) => setAmountMax(e.target.value)} />
                </div>
                <div className="col-md-3">
                  <input type="date" className="form-control" value={dueFrom} onChange={(e) => setDueFrom(e.target.value)} title="Due from" />
                </div>
                <div className="col-md-3">
                  <input type="date" className="form-control" value={dueTo} onChange={(e) => setDueTo(e.target.value)} title="Due to" />
                </div>
                <div className="col-md-3">
                  <button className="btn btn-outline-primary w-100" onClick={() => load()}>Apply filters</button>
                </div>
                <div className="col-md-3">
                  <button
                    className="btn btn-outline-secondary w-100"
                    type="button"
                    onClick={() => {
                      const cleared = {
                        q: '',
                        status: '',
                        revenue_code: '',
                        amount_min: '',
                        amount_max: '',
                        due_from: '',
                        due_to: '',
                      };
                      setQ('');
                      setStatus('');
                      setRevenueCode('');
                      setAmountMin('');
                      setAmountMax('');
                      setDueFrom('');
                      setDueTo('');
                      load(1, cleared);
                    }}
                  >
                    Clear
                  </button>
                </div>
              </div>

              {error && <div className="alert alert-danger">{error}</div>}
              {loading ? <p>Loading...</p> : (
                <div className="table-responsive">
                  <table className="table table-hover">
                    <thead>
                      <tr>
                        <th>Control No</th>
                        <th>Payer</th>
                        <th>Revenue</th>
                        <th>Due</th>
                        <th>Paid</th>
                        <th>Status</th>
                        <th>Due date</th>
                      </tr>
                    </thead>
                    <tbody>
                      {items.map((a) => (
                        <tr key={a.id}>
                          <td>{a.control_number}</td>
                          <td>{a.payer?.tin}<br /><small>{a.payer?.full_name}</small></td>
                          <td>{a.revenue_code}</td>
                          <td>{Number(a.amount_due).toFixed(2)}</td>
                          <td>{Number(a.amount_paid).toFixed(2)}</td>
                          <td><span className="badge badge-primary">{a.status}</span></td>
                          <td>{a.due_date}</td>
                        </tr>
                      ))}
                      {!items.length && <tr><td colSpan="7" className="text-muted text-center">No assessments found.</td></tr>}
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
        </div>
      </div>
    </>
  );
};

export default AssessmentsPage;
