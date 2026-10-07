import React, { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { hasPermission } from '../../../services/AuthService';
import {
  createMeterReading,
  listMeterReadings,
  listWaterAccounts,
  uploadMeterReadingsCsv,
} from '../../../services/WaterService';

const SAMPLE_URL = `${import.meta.env.BASE_URL}samples/meter-readings-sample.csv`;

const MeterReadingsPage = () => {
  const canCapture = hasPermission('meters.capture');
  const [items, setItems] = useState([]);
  const [meta, setMeta] = useState(null);
  const [accounts, setAccounts] = useState([]);
  const [q, setQ] = useState('');
  const [period, setPeriod] = useState('');
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [uploadResult, setUploadResult] = useState(null);
  const [saving, setSaving] = useState(false);
  const [form, setForm] = useState({
    water_account_id: '',
    reading_date: new Date().toISOString().slice(0, 10),
    reading_value: '',
    is_rollover: false,
    is_meter_replacement: false,
    notes: '',
  });

  const load = async (page = 1) => {
    setLoading(true);
    setError('');
    try {
      const { data } = await listMeterReadings({
        q: q || undefined,
        period: period || undefined,
        page,
        per_page: 15,
      });
      setItems(data.data || []);
      setMeta(data);
    } catch (err) {
      setError(err.response?.data?.message || 'Failed to load meter readings.');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    load();
    listWaterAccounts({ status: 'ACTIVE', per_page: 100 })
      .then((res) => setAccounts(res.data.data || []))
      .catch(() => {});
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  const onSubmit = async (e) => {
    e.preventDefault();
    setSaving(true);
    setError('');
    try {
      await createMeterReading({
        ...form,
        water_account_id: Number(form.water_account_id),
        reading_value: Number(form.reading_value),
      });
      setForm({
        water_account_id: '',
        reading_date: new Date().toISOString().slice(0, 10),
        reading_value: '',
        is_rollover: false,
        is_meter_replacement: false,
        notes: '',
      });
      await load();
    } catch (err) {
      setError(err.response?.data?.message || 'Capture failed.');
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
      const { data } = await uploadMeterReadingsCsv(file);
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
          <li className="breadcrumb-item active"><Link to="/meter-readings">Meter Readings</Link></li>
        </ol>
      </div>

      <div className="row">
        {canCapture && (
          <div className="col-xl-4">
            <div className="card">
              <div className="card-header"><h4 className="card-title">Capture reading</h4></div>
              <div className="card-body">
                <form onSubmit={onSubmit}>
                  <div className="mb-3">
                    <label className="form-label">Water account</label>
                    <select className="form-control" value={form.water_account_id} onChange={(e) => setForm({ ...form, water_account_id: e.target.value })} required>
                      <option value="">Select account</option>
                      {accounts.map((a) => (
                        <option key={a.id} value={a.id}>
                          {a.account_no} / {a.meter_no} — {a.payer?.full_name}
                        </option>
                      ))}
                    </select>
                  </div>
                  <div className="mb-3">
                    <label className="form-label">Reading date</label>
                    <input type="date" className="form-control" value={form.reading_date} onChange={(e) => setForm({ ...form, reading_date: e.target.value })} required />
                  </div>
                  <div className="mb-3">
                    <label className="form-label">Reading value (m³)</label>
                    <input type="number" step="0.001" className="form-control" value={form.reading_value} onChange={(e) => setForm({ ...form, reading_value: e.target.value })} required />
                  </div>
                  <div className="form-check mb-2">
                    <input className="form-check-input" type="checkbox" checked={form.is_rollover} onChange={(e) => setForm({ ...form, is_rollover: e.target.checked })} id="rollover" />
                    <label className="form-check-label" htmlFor="rollover">Meter rollover</label>
                  </div>
                  <div className="form-check mb-3">
                    <input className="form-check-input" type="checkbox" checked={form.is_meter_replacement} onChange={(e) => setForm({ ...form, is_meter_replacement: e.target.checked })} id="replacement" />
                    <label className="form-check-label" htmlFor="replacement">Meter replacement</label>
                  </div>
                  <div className="mb-3">
                    <label className="form-label">Notes</label>
                    <textarea className="form-control" rows="2" value={form.notes} onChange={(e) => setForm({ ...form, notes: e.target.value })} />
                  </div>
                  <button className="btn btn-primary w-100" disabled={saving}>{saving ? 'Saving...' : 'Save reading'}</button>
                </form>

                <hr />
                <h6>Bulk CSV upload</h6>
                <p className="small text-muted mb-2">
                  Columns: meter_no, reading_date, reading_value. Optional: is_rollover, is_meter_replacement, notes.
                  Lower readings are rejected unless flagged as rollover/replacement.
                </p>
                <a className="btn btn-outline-secondary btn-sm mb-2" href={SAMPLE_URL} download="meter-readings-sample.csv">
                  Download sample CSV
                </a>
                <input type="file" accept=".csv,text/csv" className="form-control" onChange={onUpload} />
              </div>
            </div>
          </div>
        )}

        <div className={canCapture ? 'col-xl-8' : 'col-xl-12'}>
          <div className="card">
            <div className="card-header"><h4 className="card-title">Meter readings</h4></div>
            <div className="card-body">
              <div className="row g-2 mb-3">
                <div className="col-md-5">
                  <input className="form-control" placeholder="Search meter, account, TIN..." value={q} onChange={(e) => setQ(e.target.value)} onKeyDown={(e) => e.key === 'Enter' && load()} />
                </div>
                <div className="col-md-3">
                  <input className="form-control" placeholder="Period YYYY-MM" value={period} onChange={(e) => setPeriod(e.target.value)} />
                </div>
                <div className="col-md-2">
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
                        <th>Date</th>
                        <th>Meter</th>
                        <th>Payer</th>
                        <th>Reading</th>
                        <th>Prev</th>
                        <th>Consumption</th>
                        <th>Flags</th>
                      </tr>
                    </thead>
                    <tbody>
                      {items.map((r) => (
                        <tr key={r.id}>
                          <td>{r.reading_date}</td>
                          <td>{r.water_account?.meter_no}<br /><small>{r.water_account?.account_no}</small></td>
                          <td>{r.water_account?.payer?.tin}<br /><small>{r.water_account?.payer?.full_name}</small></td>
                          <td>{Number(r.reading_value).toFixed(3)}</td>
                          <td>{Number(r.previous_reading || 0).toFixed(3)}</td>
                          <td>{Number(r.consumption).toFixed(3)}</td>
                          <td>
                            {r.is_rollover && <span className="badge badge-warning me-1">ROLLOVER</span>}
                            {r.is_meter_replacement && <span className="badge badge-info">REPLACED</span>}
                            {!r.is_rollover && !r.is_meter_replacement && '—'}
                          </td>
                        </tr>
                      ))}
                      {!items.length && <tr><td colSpan="7" className="text-muted text-center">No readings found.</td></tr>}
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

export default MeterReadingsPage;
