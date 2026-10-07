import React, { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { listPayers } from '../../../services/PayerService';
import { hasPermission } from '../../../services/AuthService';

const PayersList = () => {
  const [payers, setPayers] = useState([]);
  const [meta, setMeta] = useState(null);
  const [q, setQ] = useState('');
  const [duplicatesOnly, setDuplicatesOnly] = useState(false);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const canCreate = hasPermission('payers.create');

  const load = async (page = 1) => {
    setLoading(true);
    setError('');
    try {
      const { data } = await listPayers({
        q: q || undefined,
        duplicates_only: duplicatesOnly || undefined,
        page,
        per_page: 15,
      });
      setPayers(data.data || []);
      setMeta(data);
    } catch (err) {
      setError(err.response?.data?.message || 'Failed to load payers.');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    load();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [duplicatesOnly]);

  return (
    <>
      <div className="page-titles">
        <ol className="breadcrumb">
          <li className="breadcrumb-item"><Link to="/dashboard">Dashboard</Link></li>
          <li className="breadcrumb-item active"><Link to="/payers">Payer Registry</Link></li>
        </ol>
      </div>

      <div className="row">
        <div className="col-xl-12">
          <div className="card">
            <div className="card-header d-flex flex-wrap gap-2 justify-content-between align-items-center">
              <div>
                <h4 className="card-title mb-0">Taxpayer & Customer Registry</h4>
                <small className="text-muted">Search by TIN, phone, email, national ID or water account</small>
              </div>
              {canCreate && (
                <Link to="/payers/create" className="btn btn-primary btn-sm">
                  Register Payer
                </Link>
              )}
            </div>
            <div className="card-body">
              <div className="row g-2 mb-3">
                <div className="col-md-6">
                  <input
                    className="form-control"
                    placeholder="Search TIN, name, phone, email, account..."
                    value={q}
                    onChange={(e) => setQ(e.target.value)}
                    onKeyDown={(e) => e.key === 'Enter' && load()}
                  />
                </div>
                <div className="col-md-3 d-flex align-items-center">
                  <div className="form-check">
                    <input
                      className="form-check-input"
                      type="checkbox"
                      id="duplicatesOnly"
                      checked={duplicatesOnly}
                      onChange={(e) => setDuplicatesOnly(e.target.checked)}
                    />
                    <label className="form-check-label" htmlFor="duplicatesOnly">
                      Duplicates only
                    </label>
                  </div>
                </div>
                <div className="col-md-3">
                  <button className="btn btn-outline-primary w-100" onClick={() => load()}>
                    Search
                  </button>
                </div>
              </div>

              {error && <div className="alert alert-danger">{error}</div>}
              {loading ? (
                <p>Loading payers...</p>
              ) : (
                <div className="table-responsive">
                  <table className="table table-hover table-responsive-sm">
                    <thead>
                      <tr>
                        <th>TIN</th>
                        <th>Name</th>
                        <th>Type</th>
                        <th>Phone</th>
                        <th>Status</th>
                        <th>Accounts</th>
                        <th></th>
                      </tr>
                    </thead>
                    <tbody>
                      {payers.length === 0 && (
                        <tr>
                          <td colSpan="7" className="text-center text-muted">No payers found.</td>
                        </tr>
                      )}
                      {payers.map((payer) => (
                        <tr key={payer.id}>
                          <td>{payer.tin}</td>
                          <td>
                            {payer.full_name}
                            {payer.duplicate_flagged && (
                              <span className="badge badge-warning ms-2">Duplicate</span>
                            )}
                          </td>
                          <td>{payer.payer_type}</td>
                          <td>{payer.phone}</td>
                          <td>
                            <span className={`badge badge-${payer.status === 'FLAGGED' ? 'warning' : 'success'}`}>
                              {payer.status}
                            </span>
                          </td>
                          <td>{payer.water_accounts_count ?? 0}</td>
                          <td>
                            <Link to={`/payers/${payer.id}`} className="btn btn-xs btn-primary">
                              Profile
                            </Link>
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              )}

              {meta?.last_page > 1 && (
                <div className="d-flex justify-content-between align-items-center mt-3">
                  <small className="text-muted">
                    Page {meta.current_page} of {meta.last_page} ({meta.total} total)
                  </small>
                  <div className="btn-group">
                    <button
                      className="btn btn-sm btn-outline-secondary"
                      disabled={meta.current_page <= 1}
                      onClick={() => load(meta.current_page - 1)}
                    >
                      Prev
                    </button>
                    <button
                      className="btn btn-sm btn-outline-secondary"
                      disabled={meta.current_page >= meta.last_page}
                      onClick={() => load(meta.current_page + 1)}
                    >
                      Next
                    </button>
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

export default PayersList;
