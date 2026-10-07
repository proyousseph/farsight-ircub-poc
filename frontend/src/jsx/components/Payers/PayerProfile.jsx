import React, { useEffect, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { getPayer } from '../../../services/PayerService';

const PayerProfile = () => {
  const { id } = useParams();
  const [data, setData] = useState(null);
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    const load = async () => {
      setLoading(true);
      try {
        const response = await getPayer(id);
        setData(response.data);
      } catch (err) {
        setError(err.response?.data?.message || 'Failed to load payer profile.');
      } finally {
        setLoading(false);
      }
    };
    load();
  }, [id]);

  if (loading) return <p>Loading profile...</p>;
  if (error) return <div className="alert alert-danger">{error}</div>;
  if (!data) return null;

  const { payer, profile, duplicate_matches: matches } = data;

  return (
    <>
      <div className="page-titles">
        <ol className="breadcrumb">
          <li className="breadcrumb-item"><Link to="/payers">Payer Registry</Link></li>
          <li className="breadcrumb-item active"><Link to={`/payers/${payer.id}`}>{payer.tin}</Link></li>
        </ol>
      </div>

      <div className="row">
        <div className="col-xl-4">
          <div className="card">
            <div className="card-header">
              <h4 className="card-title mb-0">Payer summary</h4>
            </div>
            <div className="card-body">
              <h5>{payer.full_name}</h5>
              <p className="mb-1"><strong>TIN:</strong> {payer.tin}</p>
              <p className="mb-1"><strong>Type:</strong> {payer.payer_type}</p>
              <p className="mb-1"><strong>Phone:</strong> {payer.phone}</p>
              <p className="mb-1"><strong>Email:</strong> {payer.email || '—'}</p>
              <p className="mb-1"><strong>National ID:</strong> {payer.national_id || '—'}</p>
              <p className="mb-1"><strong>Address:</strong> {payer.address || '—'}</p>
              <p className="mb-1">
                <strong>Status:</strong>{' '}
                <span className={`badge badge-${payer.status === 'FLAGGED' ? 'warning' : 'success'}`}>
                  {payer.status}
                </span>
              </p>
              {payer.duplicate_flagged && (
                <div className="alert alert-warning mt-3 mb-0">
                  <strong>Duplicate flagged</strong>
                  <div>{payer.duplicate_reason}</div>
                </div>
              )}
            </div>
          </div>

          <div className="card">
            <div className="card-body">
              <h5 className="mb-3">Balance snapshot</h5>
              <h2 className="text-primary">${Number(profile.balance || 0).toFixed(2)}</h2>
              <small className="text-muted">
                Assessments/bills/payments will populate this in later modules.
              </small>
            </div>
          </div>
        </div>

        <div className="col-xl-8">
          <div className="card">
            <div className="card-header"><h4 className="card-title">360° profile</h4></div>
            <div className="card-body">
              <ul className="nav nav-tabs" role="tablist">
                <li className="nav-item">
                  <button className="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-accounts" type="button">
                    Water accounts ({profile.water_accounts_count})
                  </button>
                </li>
                <li className="nav-item">
                  <button className="nav-link" data-bs-toggle="tab" data-bs-target="#tab-obligations" type="button">
                    Obligations ({profile.obligations_count})
                  </button>
                </li>
                <li className="nav-item">
                  <button className="nav-link" data-bs-toggle="tab" data-bs-target="#tab-finance" type="button">
                    Assessments / Bills / Payments
                  </button>
                </li>
                <li className="nav-item">
                  <button className="nav-link" data-bs-toggle="tab" data-bs-target="#tab-duplicates" type="button">
                    Duplicate matches ({matches.length})
                  </button>
                </li>
              </ul>

              <div className="tab-content pt-3">
                <div className="tab-pane fade show active" id="tab-accounts">
                  <div className="table-responsive">
                    <table className="table table-sm">
                      <thead>
                        <tr>
                          <th>Account</th>
                          <th>Meter</th>
                          <th>Tariff</th>
                          <th>Status</th>
                          <th>Location</th>
                        </tr>
                      </thead>
                      <tbody>
                        {payer.water_accounts?.length ? payer.water_accounts.map((a) => (
                          <tr key={a.id}>
                            <td>{a.account_no}</td>
                            <td>{a.meter_no}</td>
                            <td>{a.tariff_class}</td>
                            <td>{a.status}</td>
                            <td>{a.location || '—'}</td>
                          </tr>
                        )) : (
                          <tr><td colSpan="5" className="text-muted">No water accounts linked.</td></tr>
                        )}
                      </tbody>
                    </table>
                  </div>
                </div>

                <div className="tab-pane fade" id="tab-obligations">
                  <div className="table-responsive">
                    <table className="table table-sm">
                      <thead>
                        <tr>
                          <th>Code</th>
                          <th>Name</th>
                          <th>Category</th>
                          <th>Active</th>
                        </tr>
                      </thead>
                      <tbody>
                        {payer.obligations?.length ? payer.obligations.map((o) => (
                          <tr key={o.id}>
                            <td>{o.revenue_code}</td>
                            <td>{o.name}</td>
                            <td>{o.category}</td>
                            <td>{o.is_active ? 'Yes' : 'No'}</td>
                          </tr>
                        )) : (
                          <tr><td colSpan="4" className="text-muted">No obligations linked.</td></tr>
                        )}
                      </tbody>
                    </table>
                  </div>
                </div>

                <div className="tab-pane fade" id="tab-finance">
                  <div className="row">
                    <div className="col-md-4">
                      <div className="border rounded p-3 mb-3">
                        <h6>Assessments</h6>
                        <p className="mb-0 text-muted">{profile.assessments.length} records (Day 3)</p>
                      </div>
                    </div>
                    <div className="col-md-4">
                      <div className="border rounded p-3 mb-3">
                        <h6>Bills</h6>
                        <p className="mb-0 text-muted">{profile.bills.length} records (Day 3/4)</p>
                      </div>
                    </div>
                    <div className="col-md-4">
                      <div className="border rounded p-3 mb-3">
                        <h6>Payments</h6>
                        <p className="mb-0 text-muted">{profile.payments.length} records (Day 3/5)</p>
                      </div>
                    </div>
                  </div>
                </div>

                <div className="tab-pane fade" id="tab-duplicates">
                  {matches.length === 0 ? (
                    <p className="text-muted mb-0">No duplicate matches found.</p>
                  ) : (
                    <ul className="list-group">
                      {matches.map((m) => (
                        <li key={m.id} className="list-group-item d-flex justify-content-between align-items-center">
                          <span>{m.full_name} ({m.tin}) — {m.matched_on.join(', ')}</span>
                          <Link to={`/payers/${m.id}`} className="btn btn-xs btn-outline-primary">Open</Link>
                        </li>
                      ))}
                    </ul>
                  )}
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </>
  );
};

export default PayerProfile;
