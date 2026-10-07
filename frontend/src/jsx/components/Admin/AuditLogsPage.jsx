import React, { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { listAuditLogs } from '../../../services/AdminService';

const AuditLogsPage = () => {
  const [items, setItems] = useState([]);
  const [entityType, setEntityType] = useState('');
  const [action, setAction] = useState('');
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(true);

  const load = async () => {
    setLoading(true);
    setError('');
    try {
      const { data } = await listAuditLogs({
        entity_type: entityType || undefined,
        action: action || undefined,
        per_page: 30,
      });
      setItems(data.data || []);
    } catch (err) {
      setError(err.response?.data?.message || 'Failed to load audit logs.');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => { load(); }, []);

  return (
    <>
      <div className="page-titles">
        <ol className="breadcrumb">
          <li className="breadcrumb-item"><Link to="/dashboard">Dashboard</Link></li>
          <li className="breadcrumb-item active"><Link to="#">Audit Logs</Link></li>
        </ol>
      </div>
      <h3 className="mb-3">Audit Logs</h3>
      <div className="card">
        <div className="card-body">
          <div className="row g-2 mb-3">
            <div className="col-md-3">
              <input className="form-control" placeholder="Entity type (Payment, Assessment…)" value={entityType} onChange={(e) => setEntityType(e.target.value)} />
            </div>
            <div className="col-md-3">
              <input className="form-control" placeholder="Action (CREATED, REVERSED…)" value={action} onChange={(e) => setAction(e.target.value)} />
            </div>
            <div className="col-md-2">
              <button type="button" className="btn btn-outline-primary w-100" onClick={load}>Filter</button>
            </div>
          </div>
          {error && <div className="alert alert-danger">{error}</div>}
          {loading ? <p>Loading…</p> : (
            <div className="table-responsive">
              <table className="table table-sm table-hover">
                <thead>
                  <tr>
                    <th>When</th>
                    <th>User</th>
                    <th>Entity</th>
                    <th>Action</th>
                    <th>IP</th>
                  </tr>
                </thead>
                <tbody>
                  {items.map((log) => (
                    <tr key={log.id}>
                      <td>{log.created_at ? new Date(log.created_at).toLocaleString() : '—'}</td>
                      <td>{log.user?.name || '—'}</td>
                      <td>{log.entity_type} #{log.entity_id}</td>
                      <td>{log.action}</td>
                      <td>{log.ip_address || '—'}</td>
                    </tr>
                  ))}
                  {!items.length && <tr><td colSpan="5" className="text-center text-muted">No audit entries.</td></tr>}
                </tbody>
              </table>
            </div>
          )}
        </div>
      </div>
    </>
  );
};

export default AuditLogsPage;
