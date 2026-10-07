import React, { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { hasPermission } from '../../../services/AuthService';
import {
  createFmisBatch,
  getFmisBatch,
  getFmisReconciliation,
  listFmisBatches,
  listGlMappings,
  postFmisBatch,
  reverseFmisBatch,
  updateGlMapping,
} from '../../../services/FmisService';

const FmisPage = () => {
  const canPost = hasPermission('fmis.post');
  const canReconcile = hasPermission('fmis.reconcile') || hasPermission('fmis.post');
  const canMap = hasPermission('revenue_types.manage') || hasPermission('fmis.post');
  const [tab, setTab] = useState('batches');
  const [batches, setBatches] = useState([]);
  const [selected, setSelected] = useState(null);
  const [mappings, setMappings] = useState([]);
  const [report, setReport] = useState(null);
  const [error, setError] = useState('');
  const [info, setInfo] = useState('');
  const [loading, setLoading] = useState(true);
  const [busy, setBusy] = useState(false);
  const [journalDate, setJournalDate] = useState(new Date().toISOString().slice(0, 10));
  const [reconDate, setReconDate] = useState(new Date().toISOString().slice(0, 10));
  const [expandedGl, setExpandedGl] = useState('');

  const loadBatches = async () => {
    setLoading(true);
    setError('');
    try {
      const { data } = await listFmisBatches({ per_page: 20 });
      setBatches(data.data || []);
    } catch (err) {
      setError(err.response?.data?.message || 'Failed to load FMIS batches.');
    } finally {
      setLoading(false);
    }
  };

  const loadMappings = async () => {
    try {
      const { data } = await listGlMappings();
      setMappings(data.data || []);
    } catch (err) {
      setError(err.response?.data?.message || 'Failed to load GL mappings.');
    }
  };

  useEffect(() => {
    loadBatches();
    loadMappings();
  }, []);

  const onCreate = async (postImmediately) => {
    setBusy(true);
    setError('');
    setInfo('');
    try {
      const { data } = await createFmisBatch({
        journal_date: journalDate,
        post_immediately: postImmediately,
      });
      setInfo(data.message);
      setSelected({ batch: data.batch });
      await loadBatches();
    } catch (err) {
      setError(err.response?.data?.message || 'Batch create/post failed.');
    } finally {
      setBusy(false);
    }
  };

  const openBatch = async (id) => {
    try {
      const { data } = await getFmisBatch(id);
      setSelected(data);
      setTab('batches');
    } catch (err) {
      setError(err.response?.data?.message || 'Failed to open batch.');
    }
  };

  const onPost = async (id) => {
    setBusy(true);
    setError('');
    try {
      const { data } = await postFmisBatch(id);
      setInfo(data.message);
      setSelected({ batch: data.batch });
      await loadBatches();
    } catch (err) {
      setError(err.response?.data?.message || 'Post failed.');
    } finally {
      setBusy(false);
    }
  };

  const onReverse = async (id) => {
    setBusy(true);
    setError('');
    try {
      const { data } = await reverseFmisBatch(id);
      setInfo(data.message);
      setSelected({ batch: data.batch });
      await loadBatches();
    } catch (err) {
      setError(err.response?.data?.message || 'Reverse failed.');
    } finally {
      setBusy(false);
    }
  };

  const onReconcile = async () => {
    setBusy(true);
    setError('');
    try {
      const { data } = await getFmisReconciliation(reconDate);
      setReport(data);
      setTab('reconcile');
    } catch (err) {
      setError(err.response?.data?.message || 'Reconciliation failed.');
    } finally {
      setBusy(false);
    }
  };

  const onToggleMapping = async (mapping) => {
    if (!canMap) return;
    try {
      await updateGlMapping(mapping.id, { is_active: !mapping.is_active });
      await loadMappings();
    } catch (err) {
      setError(err.response?.data?.message || 'Failed to update mapping.');
    }
  };

  return (
    <>
      <div className="page-titles">
        <ol className="breadcrumb">
          <li className="breadcrumb-item"><Link to="/dashboard">Dashboard</Link></li>
          <li className="breadcrumb-item active"><Link to="/fmis">FMIS</Link></li>
        </ol>
      </div>

      <div className="card">
        <div className="card-body">
          <ul className="nav nav-pills mb-3">
            <li className="nav-item">
              <button className={`nav-link ${tab === 'batches' ? 'active' : ''}`} onClick={() => setTab('batches')}>Journal batches</button>
            </li>
            <li className="nav-item">
              <button className={`nav-link ${tab === 'mappings' ? 'active' : ''}`} onClick={() => setTab('mappings')}>GL mappings</button>
            </li>
            {canReconcile && (
              <li className="nav-item">
                <button className={`nav-link ${tab === 'reconcile' ? 'active' : ''}`} onClick={() => setTab('reconcile')}>FMIS reconciliation</button>
              </li>
            )}
          </ul>

          {error && <div className="alert alert-danger">{error}</div>}
          {info && <div className="alert alert-info">{info}</div>}

          {tab === 'batches' && (
            <div className="row">
              {canPost && (
                <div className="col-xl-4">
                  <div className="border rounded p-3 mb-3">
                    <h5>Create daily journal</h5>
                    <div className="mb-3">
                      <label className="form-label">Journal date</label>
                      <input type="date" className="form-control" value={journalDate} onChange={(e) => setJournalDate(e.target.value)} />
                    </div>
                    <p className="small text-muted">
                      Picks SUCCESS payments for the date that are not yet posted, maps revenue codes to GL, then posts to the mock FMIS.
                    </p>
                    <button className="btn btn-outline-primary w-100 mb-2" disabled={busy} onClick={() => onCreate(false)}>Create PENDING batch</button>
                    <button className="btn btn-primary w-100" disabled={busy} onClick={() => onCreate(true)}>Create &amp; post now</button>
                  </div>
                </div>
              )}
              <div className={canPost ? 'col-xl-8' : 'col-xl-12'}>
                {loading ? <p>Loading...</p> : (
                  <div className="table-responsive">
                    <table className="table table-hover">
                      <thead>
                        <tr>
                          <th>Batch</th>
                          <th>Date</th>
                          <th>Status</th>
                          <th>Lines</th>
                          <th>Total</th>
                          <th>FMIS ref</th>
                          <th></th>
                        </tr>
                      </thead>
                      <tbody>
                        {batches.map((b) => (
                          <tr key={b.id}>
                            <td>{b.batch_number}</td>
                            <td>{b.journal_date}</td>
                            <td><span className="badge badge-primary">{b.status}</span></td>
                            <td>{b.line_count}</td>
                            <td>{Number(b.total_amount).toFixed(2)}</td>
                            <td>{b.fmis_reference || '—'}</td>
                            <td className="text-nowrap">
                              <button className="btn btn-sm btn-outline-secondary me-1" onClick={() => openBatch(b.id)}>View</button>
                              {canPost && b.status === 'PENDING' && (
                                <button className="btn btn-sm btn-outline-primary me-1" disabled={busy} onClick={() => onPost(b.id)}>Post</button>
                              )}
                              {canPost && b.status === 'POSTED' && (
                                <button className="btn btn-sm btn-outline-warning" disabled={busy} onClick={() => onReverse(b.id)}>Reverse</button>
                              )}
                            </td>
                          </tr>
                        ))}
                        {!batches.length && <tr><td colSpan="7" className="text-muted text-center">No journal batches yet.</td></tr>}
                      </tbody>
                    </table>
                  </div>
                )}

                {selected?.batch && (
                  <div className="mt-3">
                    <h5>{selected.batch.batch_number} — {selected.batch.status}</h5>
                    <p className="text-muted mb-2">
                      FMIS ref: {selected.batch.fmis_reference || '—'} | Total: ${Number(selected.batch.total_amount || 0).toFixed(2)}
                    </p>
                    <div className="table-responsive">
                      <table className="table table-sm">
                        <thead>
                          <tr>
                            <th>Payment</th>
                            <th>Revenue</th>
                            <th>GL</th>
                            <th>Amount</th>
                            <th>FMIS line</th>
                          </tr>
                        </thead>
                        <tbody>
                          {(selected.batch.lines || []).map((line) => (
                            <tr key={line.id}>
                              <td>{line.payment?.external_ref || line.payment_id}</td>
                              <td>{line.revenue_code}</td>
                              <td>{line.gl_code}</td>
                              <td>{Number(line.amount).toFixed(2)}</td>
                              <td>{line.fmis_line_ref || '—'}</td>
                            </tr>
                          ))}
                          {!(selected.batch.lines || []).length && (
                            <tr><td colSpan="5" className="text-muted">No lines (reversed batches clear lines for re-post eligibility).</td></tr>
                          )}
                        </tbody>
                      </table>
                    </div>
                  </div>
                )}
              </div>
            </div>
          )}

          {tab === 'mappings' && (
            <div className="table-responsive">
              <table className="table table-hover">
                <thead>
                  <tr>
                    <th>Revenue code</th>
                    <th>GL code</th>
                    <th>GL name</th>
                    <th>Active</th>
                    <th></th>
                  </tr>
                </thead>
                <tbody>
                  {mappings.map((m) => (
                    <tr key={m.id}>
                      <td>{m.revenue_code}</td>
                      <td>{m.gl_code}</td>
                      <td>{m.gl_name}</td>
                      <td>{m.is_active ? 'Yes' : 'No'}</td>
                      <td>
                        {canMap && (
                          <button className="btn btn-sm btn-outline-secondary" onClick={() => onToggleMapping(m)}>
                            {m.is_active ? 'Deactivate' : 'Activate'}
                          </button>
                        )}
                      </td>
                    </tr>
                  ))}
                  {!mappings.length && <tr><td colSpan="5" className="text-muted text-center">No GL mappings. Run GlMappingSeeder.</td></tr>}
                </tbody>
              </table>
            </div>
          )}

          {tab === 'reconcile' && canReconcile && (
            <div>
              <div className="row g-2 mb-3">
                <div className="col-md-3">
                  <input type="date" className="form-control" value={reconDate} onChange={(e) => setReconDate(e.target.value)} />
                </div>
                <div className="col-md-3">
                  <button className="btn btn-primary" disabled={busy} onClick={onReconcile}>Run FMIS reconciliation</button>
                </div>
              </div>
              {report && (
                <>
                  <div className="alert alert-secondary">
                    IRCUB ${Number(report.summary.ircub_total).toFixed(2)} vs FMIS ${Number(report.summary.fmis_total).toFixed(2)}
                    {' '}(diff {Number(report.summary.difference).toFixed(2)}) |
                    Matched GLs {report.summary.matched_gl_codes} |
                    Unposted payments {report.summary.unposted_payments}
                  </div>
                  <div className="table-responsive">
                    <table className="table table-hover">
                      <thead>
                        <tr>
                          <th>GL code</th>
                          <th>IRCUB</th>
                          <th>FMIS</th>
                          <th>Diff</th>
                          <th>Status</th>
                          <th></th>
                        </tr>
                      </thead>
                      <tbody>
                        {report.rows.map((row) => (
                          <React.Fragment key={row.gl_code}>
                            <tr>
                              <td>{row.gl_code}</td>
                              <td>{Number(row.ircub_total).toFixed(2)} ({row.ircub_count})</td>
                              <td>{Number(row.fmis_total).toFixed(2)} ({row.fmis_count})</td>
                              <td>{Number(row.difference).toFixed(2)}</td>
                              <td>{row.status}</td>
                              <td>
                                <button className="btn btn-sm btn-outline-secondary" onClick={() => setExpandedGl(expandedGl === row.gl_code ? '' : row.gl_code)}>
                                  {expandedGl === row.gl_code ? 'Hide' : 'Drill-down'}
                                </button>
                              </td>
                            </tr>
                            {expandedGl === row.gl_code && (
                              <tr>
                                <td colSpan="6">
                                  <strong>IRCUB transactions</strong>
                                  <ul className="mb-2">
                                    {row.ircub_transactions.map((t) => (
                                      <li key={`i-${t.journal_line_id}`}>{t.external_ref} — ${Number(t.amount).toFixed(2)} — {t.fmis_line_ref}</li>
                                    ))}
                                    {!row.ircub_transactions.length && <li className="text-muted">None</li>}
                                  </ul>
                                  <strong>FMIS transactions</strong>
                                  <ul className="mb-0">
                                    {row.fmis_transactions.map((t, idx) => (
                                      <li key={`f-${t.fmis_line_ref || idx}`}>Payment #{t.payment_id} — ${Number(t.amount).toFixed(2)} — {t.fmis_line_ref}</li>
                                    ))}
                                    {!row.fmis_transactions.length && <li className="text-muted">None</li>}
                                  </ul>
                                </td>
                              </tr>
                            )}
                          </React.Fragment>
                        ))}
                      </tbody>
                    </table>
                  </div>
                </>
              )}
            </div>
          )}
        </div>
      </div>
    </>
  );
};

export default FmisPage;
