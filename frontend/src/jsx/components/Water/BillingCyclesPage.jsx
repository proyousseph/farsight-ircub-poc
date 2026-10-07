import React, { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { hasPermission } from '../../../services/AuthService';
import { getBillingCycle, listBillingCycles, runBillingCycle } from '../../../services/WaterService';

const BillingCyclesPage = () => {
  const canRun = hasPermission('billing.run');
  const [items, setItems] = useState([]);
  const [period, setPeriod] = useState(new Date().toISOString().slice(0, 7));
  const [loading, setLoading] = useState(true);
  const [running, setRunning] = useState(false);
  const [error, setError] = useState('');
  const [result, setResult] = useState(null);
  const [selected, setSelected] = useState(null);

  const load = async () => {
    setLoading(true);
    setError('');
    try {
      const { data } = await listBillingCycles({ per_page: 20 });
      setItems(data.data || []);
    } catch (err) {
      setError(err.response?.data?.message || 'Failed to load billing cycles.');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    load();
  }, []);

  const onRun = async (e) => {
    e.preventDefault();
    setRunning(true);
    setError('');
    setResult(null);
    try {
      const { data } = await runBillingCycle(period);
      setResult(data);
      await load();
    } catch (err) {
      setError(err.response?.data?.message || 'Billing cycle failed.');
    } finally {
      setRunning(false);
    }
  };

  const openCycle = async (id) => {
    try {
      const { data } = await getBillingCycle(id);
      setSelected(data);
    } catch (err) {
      setError(err.response?.data?.message || 'Failed to load cycle details.');
    }
  };

  return (
    <>
      <div className="page-titles">
        <ol className="breadcrumb">
          <li className="breadcrumb-item"><Link to="/dashboard">Dashboard</Link></li>
          <li className="breadcrumb-item active"><Link to="/billing-cycles">Billing Cycles</Link></li>
        </ol>
      </div>

      <div className="row">
        {canRun && (
          <div className="col-xl-4">
            <div className="card">
              <div className="card-header"><h4 className="card-title">Run monthly cycle</h4></div>
              <div className="card-body">
                <form onSubmit={onRun}>
                  <div className="mb-3">
                    <label className="form-label">Billing period</label>
                    <input type="month" className="form-control" value={period} onChange={(e) => setPeriod(e.target.value)} required />
                  </div>
                  <p className="small text-muted">
                    Applies tiered tariffs, carries arrears, nets WATER payments, flags abnormal consumption (&gt;200% of 3-month average), generates bill PDFs and mock SMS/email for released bills.
                  </p>
                  <button className="btn btn-primary w-100" disabled={running}>{running ? 'Running...' : 'Run billing cycle'}</button>
                </form>
                {result && (
                  <div className="alert alert-success mt-3 mb-0">
                    Generated {result.summary?.period}: {result.cycle?.bills_generated} bills, {result.cycle?.bills_held} held, {result.cycle?.exceptions_count} exceptions.
                  </div>
                )}
              </div>
            </div>
          </div>
        )}

        <div className={canRun ? 'col-xl-8' : 'col-xl-12'}>
          <div className="card">
            <div className="card-header"><h4 className="card-title">Billing cycles</h4></div>
            <div className="card-body">
              {error && <div className="alert alert-danger">{error}</div>}
              {loading ? <p>Loading...</p> : (
                <div className="table-responsive">
                  <table className="table table-hover">
                    <thead>
                      <tr>
                        <th>Period</th>
                        <th>Status</th>
                        <th>Accounts</th>
                        <th>Bills</th>
                        <th>Held</th>
                        <th>Exceptions</th>
                        <th></th>
                      </tr>
                    </thead>
                    <tbody>
                      {items.map((c) => (
                        <tr key={c.id}>
                          <td>{c.period}</td>
                          <td><span className="badge badge-primary">{c.status}</span></td>
                          <td>{c.accounts_processed}</td>
                          <td>{c.bills_generated}</td>
                          <td>{c.bills_held}</td>
                          <td>{c.exceptions_count}</td>
                          <td><button className="btn btn-sm btn-outline-primary" onClick={() => openCycle(c.id)}>Report</button></td>
                        </tr>
                      ))}
                      {!items.length && <tr><td colSpan="7" className="text-muted text-center">No cycles yet.</td></tr>}
                    </tbody>
                  </table>
                </div>
              )}

              {selected && (
                <div className="mt-3">
                  <h5>Exception report — {selected.cycle?.period}</h5>
                  <p className="text-muted">
                    Total billed: ${Number(selected.cycle?.summary?.total_billed || 0).toFixed(2)}
                  </p>
                  <div className="table-responsive">
                    <table className="table table-sm">
                      <thead><tr><th>Account</th><th>Meter</th><th>Reason</th></tr></thead>
                      <tbody>
                        {(selected.exception_report || []).map((ex, idx) => (
                          <tr key={`${ex.account_no}-${idx}`}>
                            <td>{ex.account_no}</td>
                            <td>{ex.meter_no}</td>
                            <td>{ex.reason}</td>
                          </tr>
                        ))}
                        {!(selected.exception_report || []).length && (
                          <tr><td colSpan="3" className="text-muted">No exceptions.</td></tr>
                        )}
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

export default BillingCyclesPage;
