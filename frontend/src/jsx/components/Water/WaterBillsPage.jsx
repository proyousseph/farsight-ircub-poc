import React, { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { hasPermission, getStoredUser } from '../../../services/AuthService';
import api from '../../../services/api';
import { getWaterStatement, listWaterBills, releaseWaterBill } from '../../../services/WaterService';
import { listPayers } from '../../../services/PayerService';

const WaterBillsPage = () => {
  const canRelease = hasPermission('billing.run');
  const canView = hasPermission('bills.view') || hasPermission('bills.view_own');
  const user = getStoredUser();
  const [items, setItems] = useState([]);
  const [meta, setMeta] = useState(null);
  const [payers, setPayers] = useState([]);
  const [q, setQ] = useState('');
  const [period, setPeriod] = useState('');
  const [status, setStatus] = useState('');
  const [abnormal, setAbnormal] = useState('');
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [statementPayerId, setStatementPayerId] = useState('');
  const [statement, setStatement] = useState(null);

  const load = async (page = 1) => {
    setLoading(true);
    setError('');
    try {
      const { data } = await listWaterBills({
        q: q || undefined,
        period: period || undefined,
        status: status || undefined,
        abnormal: abnormal === '' ? undefined : abnormal,
        page,
        per_page: 15,
      });
      setItems(data.data || []);
      setMeta(data);
    } catch (err) {
      setError(err.response?.data?.message || 'Failed to load water bills.');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    if (canView) load();
    listPayers({ per_page: 100 }).then((res) => setPayers(res.data.data || [])).catch(() => {});
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  const onRelease = async (id) => {
    setError('');
    try {
      await releaseWaterBill(id);
      await load(meta?.current_page || 1);
    } catch (err) {
      setError(err.response?.data?.message || 'Release failed.');
    }
  };

  const onPdf = async (id) => {
    try {
      const res = await api.get(`/water-bills/${id}/pdf`, { responseType: 'blob' });
      const url = window.URL.createObjectURL(new Blob([res.data], { type: res.headers['content-type'] || 'application/pdf' }));
      window.open(url, '_blank');
    } catch (err) {
      setError(err.response?.data?.message || 'PDF not available.');
    }
  };

  const onStatement = async () => {
    if (!statementPayerId) return;
    setError('');
    try {
      const { data } = await getWaterStatement(Number(statementPayerId));
      setStatement(data);
    } catch (err) {
      setError(err.response?.data?.message || 'Failed to load statement.');
    }
  };

  if (!canView) {
    return <div className="alert alert-warning">You do not have permission to view water bills.</div>;
  }

  return (
    <>
      <div className="page-titles">
        <ol className="breadcrumb">
          <li className="breadcrumb-item"><Link to="/dashboard">Dashboard</Link></li>
          <li className="breadcrumb-item active"><Link to="/water-bills">Water Bills</Link></li>
        </ol>
      </div>

      <div className="row">
        <div className="col-xl-12">
          <div className="card">
            <div className="card-header"><h4 className="card-title">Water bills</h4></div>
            <div className="card-body">
              <div className="row g-2 mb-3">
                <div className="col-md-3">
                  <input className="form-control" placeholder="Search bill, TIN, meter..." value={q} onChange={(e) => setQ(e.target.value)} onKeyDown={(e) => e.key === 'Enter' && load()} />
                </div>
                <div className="col-md-2">
                  <input className="form-control" placeholder="Period YYYY-MM" value={period} onChange={(e) => setPeriod(e.target.value)} />
                </div>
                <div className="col-md-2">
                  <select className="form-control" value={status} onChange={(e) => setStatus(e.target.value)}>
                    <option value="">All statuses</option>
                    <option value="HELD">HELD</option>
                    <option value="RELEASED">RELEASED</option>
                    <option value="PART_PAID">PART_PAID</option>
                    <option value="PAID">PAID</option>
                  </select>
                </div>
                <div className="col-md-2">
                  <select className="form-control" value={abnormal} onChange={(e) => setAbnormal(e.target.value)}>
                    <option value="">All consumption</option>
                    <option value="true">Abnormal only</option>
                    <option value="false">Normal only</option>
                  </select>
                </div>
                <div className="col-md-2">
                  <button className="btn btn-outline-primary w-100" onClick={() => load()}>Apply filters</button>
                </div>
              </div>

              {error && <div className="alert alert-danger">{error}</div>}
              {loading ? <p>Loading...</p> : (
                <div className="table-responsive">
                  <table className="table table-hover">
                    <thead>
                      <tr>
                        <th>Bill</th>
                        <th>Payer</th>
                        <th>Account</th>
                        <th>Period</th>
                        <th>Usage</th>
                        <th>Total due</th>
                        <th>Status</th>
                        <th></th>
                      </tr>
                    </thead>
                    <tbody>
                      {items.map((b) => (
                        <tr key={b.id}>
                          <td>
                            {b.bill_number}
                            {b.abnormal_flag && <div><small className="text-danger">ABNORMAL</small></div>}
                          </td>
                          <td>{b.payer?.tin}<br /><small>{b.payer?.full_name}</small></td>
                          <td>{b.water_account?.account_no}<br /><small>{b.water_account?.meter_no}</small></td>
                          <td>{b.period}</td>
                          <td>{Number(b.consumption).toFixed(3)} m³</td>
                          <td>{Number(b.total_due).toFixed(2)}</td>
                          <td><span className="badge badge-primary">{b.status}</span></td>
                          <td className="text-nowrap">
                            {b.pdf_path && (
                              <button className="btn btn-sm btn-outline-secondary me-1" onClick={() => onPdf(b.id)}>PDF</button>
                            )}
                            {canRelease && b.status === 'HELD' && (
                              <button className="btn btn-sm btn-outline-success" onClick={() => onRelease(b.id)}>Release</button>
                            )}
                          </td>
                        </tr>
                      ))}
                      {!items.length && <tr><td colSpan="8" className="text-muted text-center">No bills found.</td></tr>}
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

        <div className="col-xl-12">
          <div className="card">
            <div className="card-header"><h4 className="card-title">Customer statement</h4></div>
            <div className="card-body">
              <div className="row g-2 mb-3">
                <div className="col-md-6">
                  <select className="form-control" value={statementPayerId} onChange={(e) => setStatementPayerId(e.target.value)}>
                    <option value="">Select payer</option>
                    {payers.map((p) => (
                      <option key={p.id} value={p.id}>{p.tin} — {p.full_name}</option>
                    ))}
                  </select>
                </div>
                <div className="col-md-3">
                  <button className="btn btn-outline-primary w-100" onClick={onStatement}>Load statement</button>
                </div>
              </div>
              {statement && (
                <>
                  <p>
                    Closing balance: <strong>${Number(statement.closing_balance).toFixed(2)}</strong>
                    {' '}| Open bills outstanding: <strong>${Number(statement.open_bills_outstanding).toFixed(2)}</strong>
                    {user?.email ? <span className="text-muted"> — requested by {user.email}</span> : null}
                  </p>
                  <div className="table-responsive">
                    <table className="table table-sm">
                      <thead>
                        <tr>
                          <th>Date</th>
                          <th>Type</th>
                          <th>Reference</th>
                          <th>Debit</th>
                          <th>Credit</th>
                          <th>Running balance</th>
                        </tr>
                      </thead>
                      <tbody>
                        {statement.lines.map((line, idx) => (
                          <tr key={`${line.reference}-${idx}`}>
                            <td>{line.date}</td>
                            <td>{line.type}</td>
                            <td>{line.reference}</td>
                            <td>{Number(line.debit).toFixed(2)}</td>
                            <td>{Number(line.credit).toFixed(2)}</td>
                            <td>{Number(line.running_balance).toFixed(2)}</td>
                          </tr>
                        ))}
                        {!statement.lines.length && <tr><td colSpan="6" className="text-muted">No statement lines.</td></tr>}
                      </tbody>
                    </table>
                  </div>
                </>
              )}
            </div>
          </div>
        </div>
      </div>
    </>
  );
};

export default WaterBillsPage;
