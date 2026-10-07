import React, { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { getSystemConfig, updateSystemConfig } from '../../../services/AdminService';

const SystemConfigPage = () => {
  const [catalog, setCatalog] = useState([]);
  const [form, setForm] = useState({});
  const [error, setError] = useState('');
  const [info, setInfo] = useState('');
  const [saving, setSaving] = useState(false);

  const load = async () => {
    setError('');
    try {
      const { data } = await getSystemConfig();
      setCatalog(data.data || []);
      setForm(data.values || {});
    } catch (err) {
      setError(err.response?.data?.message || 'Failed to load system configuration.');
    }
  };

  useEffect(() => { load(); }, []);

  const onChange = (key, type, raw) => {
    let value = raw;
    if (type === 'bool') value = raw === true || raw === 'true' || raw === 1 || raw === '1';
    if (type === 'int') value = Number(raw);
    setForm((prev) => ({ ...prev, [key]: value }));
  };

  const onSave = async (e) => {
    e.preventDefault();
    setSaving(true);
    setInfo('');
    setError('');
    try {
      const { data } = await updateSystemConfig(form);
      setInfo(data.message || 'Saved.');
      setCatalog(data.data || []);
      setForm(data.values || form);
    } catch (err) {
      setError(err.response?.data?.message || Object.values(err.response?.data?.errors || {})[0]?.[0] || 'Save failed.');
    } finally {
      setSaving(false);
    }
  };

  const groups = [...new Set(catalog.map((c) => c.group))];

  return (
    <>
      <div className="page-titles">
        <ol className="breadcrumb">
          <li className="breadcrumb-item"><Link to="/dashboard">Dashboard</Link></li>
          <li className="breadcrumb-item active"><Link to="#">System Configuration</Link></li>
        </ol>
      </div>
      <h3 className="mb-3">System Configuration</h3>
      <p className="text-muted">Tunable POC settings (password policy, 2FA stub, water anomaly threshold, channel retries).</p>
      {error && <div className="alert alert-danger">{error}</div>}
      {info && <div className="alert alert-success">{info}</div>}

      <form onSubmit={onSave} className="row ircub-admin-grid">
        {groups.map((group) => (
          <div className="col-12 col-lg-6 mb-3" key={group}>
            <div className="card h-100">
              <div className="card-header"><h4 className="card-title mb-0 text-capitalize">{group}</h4></div>
              <div className="card-body d-grid gap-3 ircub-admin-form">
                {catalog.filter((c) => c.group === group).map((item) => (
                  <div key={item.key}>
                    <label className="form-label mb-1">{item.label}</label>
                    {item.type === 'bool' ? (
                      <div className="form-check form-switch">
                        <input
                          className="form-check-input"
                          type="checkbox"
                          checked={Boolean(form[item.key])}
                          onChange={(e) => onChange(item.key, 'bool', e.target.checked)}
                        />
                        <label className="form-check-label">{form[item.key] ? 'Enabled' : 'Disabled'}</label>
                      </div>
                    ) : (
                      <input
                        className="form-control"
                        type={item.type === 'int' ? 'number' : 'text'}
                        value={form[item.key] ?? ''}
                        onChange={(e) => onChange(item.key, item.type, e.target.value)}
                      />
                    )}
                    {item.description && <small className="text-muted d-block mt-1">{item.description}</small>}
                  </div>
                ))}
              </div>
            </div>
          </div>
        ))}
        <div className="col-12">
          <button className="btn btn-primary" type="submit" disabled={saving}>
            {saving ? 'Saving…' : 'Save configuration'}
          </button>
        </div>
      </form>
    </>
  );
};

export default SystemConfigPage;
