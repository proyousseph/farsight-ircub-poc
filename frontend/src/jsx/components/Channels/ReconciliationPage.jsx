import React, { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { hasPermission } from '../../../services/AuthService';
import { getReconciliationRun, listReconciliationRuns, runReconciliation } from '../../../services/ChannelService';

const ReconciliationPage = () => {
  const canRun = hasPermission('channels.reconcile') || hasPermission('fmis.reconcile');
  const [items, setItems] = useState([]);
  const [selected, setSelected] = useState(null);
  const [loading, setLoading] = useState(true);
  const [running, setRunning] = useState(false);
  const [error, setError] = useState('');
  const [form, setForm] = useState({
    report_date: new Date().toISOString().slice(0, 10),
    channel: 'MOBILE_MONEY',
  });

  const load = async () => {
    setLoading(true);
    setError('');
    try {
      const { data } = await listReconciliationRuns({ per_page: 20 });
      setItems(data.data || []);
    } catch (err) {
      setError(err.response?.data?.message || 'Failed to load reconciliation runs.');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => { load(); }, []);

  const onRun = async (e) => {
    e.preventDefault();
    setRunning(true);
    setError('');
    try {
      const { data } = await runReconciliation(form);
      setSelected({ run: data.run });
      await load();
    } catch (err) {
      setError(err.response?.data?.message || 'Reconciliation failed.');
    } finally {
      setRunning(false);
    }
  };

  const openRun = async (id) => {
    try {
      const { data } = await getReconciliationRun(id);
      setSelected(data);
    } catch (err) {
      setError(err.response?.data?.message || 'Failed to open run.');
    }
  };

  if (!canRun) {
    return <div className="alert alert-warning">You do not have permission to view channel reconciliation.</div>;
  }

  return (
    <>
      <div className="page-titles">
        <ol className="breadcrumb">
          <li className="breadcrumb-item"><Link to="/dashboard">Dashboard</Link></li>
          <li className="breadcrumb-item active"><Link to="/reconciliation">Channel Reconciliation</Link></li>
        </ol>
      </div>

      <div className="row">
        <div className="col-xl-4">
          <div className="card">
            <div className="card-header"><h4 className="card-title">Daily channel reconciliation</h4></div>
            <div className="card-body">
              <form onSubmit={onRun}>
                <div className="mb-3">
                  <label className="form-label">Report date</label>
                  <input type="date" className="form-control" value={form.report_date} onChange={(e) => setForm({ ...form, report_date: e.target.value })} required />
                </div>
                <div className="mb-3">
                  <label className="form-label">Channel</label>
                  <select className="form-control" value={form.channel} onChange={(e) => setForm({ ...form, channel: e.target.value })}>
                    <option value="MOBILE_MONEY">MOBILE_MONEY</option>
                    <option value="BANK">BANK</option>
                  </select>
                </div>
                <p className="small text-muted">
                  Compares IRCUB SUCCESS payments with the mock channel statement file for the day.
                </p>
                <button className="btn btn-primary w-100" disabled={running}>{running ? 'Running...' : 'Run reconciliation'}</button>
              </form>
            </div>
          </div>
        </div>

        <div className="col-xl-8">
          <div className="card">
            <div className="card-header"><h4 className="card-title">Reconciliation runs</h4></div>
            <div className="card-body">
              {error && <div className="alert alert-danger">{error}</div>}
              {loading ? <p>Loading...</p> : (
                <div className="table-responsive">
                  <table className="table table-hover">
                    <thead>
                      <tr>
                        <th>Date</th>
                        <th>Channel</th>
                        <th>IRCUB</th>
                        <th>Channel</th>
                        <th>Diff</th>
                        <th>Matched</th>
                        <th>Exceptions</th>
                        <th></th>
                      </tr>
                    </thead>
                    <tbody>
                      {items.map((r) => (
                        <tr key={r.id}>
                          <td>{r.report_date}</td>
                          <td>{r.channel}</td>
                          <td>{Number(r.ircub_total).toFixed(2)}</td>
                          <td>{Number(r.channel_total).toFixed(2)}</td>
                          <td>{Number(r.difference).toFixed(2)}</td>
                          <td>{r.matched_count}</td>
                          <td>{r.ircub_only_count + r.channel_only_count}</td>
                          <td><button className="btn btn-sm btn-outline-primary" onClick={() => openRun(r.id)}>Details</button></td>
                        </tr>
                      ))}
                      {!items.length && <tr><td colSpan="8" className="text-muted text-center">No runs yet.</td></tr>}
                    </tbody>
                  </table>
                </div>
              )}

              {selected?.run && (
                <div className="mt-3">
                  <h5>
                    {selected.run.channel} — {selected.run.report_date}
                  </h5>
                  <p className="text-muted">
                    Matched {selected.run.matched_count}, IRCUB-only {selected.run.ircub_only_count}, Channel-only {selected.run.channel_only_count}
                  </p>
                  <div className="table-responsive">
                    <table className="table table-sm">
                      <thead>
                        <tr>
                          <th>Status</th>
                          <th>External ref</th>
                          <th>IRCUB amount</th>
                          <th>Channel amount</th>
                        </tr>
                      </thead>
                      <tbody>
                        {(selected.run.items || []).map((item) => (
                          <tr key={item.id}>
                            <td>{item.match_status}</td>
                            <td>{item.external_ref}</td>
                            <td>{item.ircub_amount != null ? Number(item.ircub_amount).toFixed(2) : '—'}</td>
                            <td>{item.channel_amount != null ? Number(item.channel_amount).toFixed(2) : '—'}</td>
                          </tr>
                        ))}
                      </tbody>
                    </table>
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

export default ReconciliationPage;
