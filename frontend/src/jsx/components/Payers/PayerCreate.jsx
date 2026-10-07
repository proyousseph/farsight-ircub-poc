import React, { useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { createPayer } from '../../../services/PayerService';

const emptyAccount = {
  account_no: '',
  meter_no: '',
  tariff_class: 'DOMESTIC',
  location: '',
};

const PayerCreate = () => {
  const navigate = useNavigate();
  const [form, setForm] = useState({
    payer_type: 'INDIVIDUAL',
    tin: '',
    full_name: '',
    national_id: '',
    phone: '',
    email: '',
    address: '',
    notes: '',
    revenue_code: 'BIZLIC',
    revenue_name: 'Business Licence',
  });
  const [waterAccount, setWaterAccount] = useState(emptyAccount);
  const [includeWater, setIncludeWater] = useState(true);
  const [forceCreate, setForceCreate] = useState(false);
  const [matches, setMatches] = useState([]);
  const [error, setError] = useState('');
  const [saving, setSaving] = useState(false);

  const onChange = (e) => setForm((prev) => ({ ...prev, [e.target.name]: e.target.value }));
  const onAccountChange = (e) => setWaterAccount((prev) => ({ ...prev, [e.target.name]: e.target.value }));

  const onSubmit = async (e) => {
    e.preventDefault();
    setSaving(true);
    setError('');
    setMatches([]);

    const payload = {
      payer_type: form.payer_type,
      tin: form.tin,
      full_name: form.full_name,
      national_id: form.national_id || null,
      phone: form.phone,
      email: form.email || null,
      address: form.address || null,
      notes: form.notes || null,
      force_create: forceCreate,
      obligations: form.revenue_code
        ? [{
            revenue_code: form.revenue_code,
            name: form.revenue_name || form.revenue_code,
            category: 'TAX',
          }]
        : [],
      water_accounts: includeWater && waterAccount.account_no
        ? [waterAccount]
        : [],
    };

    try {
      const { data } = await createPayer(payload);
      navigate(`/payers/${data.payer.id}`);
    } catch (err) {
      if (err.response?.status === 409) {
        setMatches(err.response.data.matches || []);
        setError(err.response.data.message);
      } else {
        const errors = err.response?.data?.errors;
        if (errors) {
          setError(Object.values(errors).flat().join(' '));
        } else {
          setError(err.response?.data?.message || 'Failed to register payer.');
        }
      }
    } finally {
      setSaving(false);
    }
  };

  return (
    <>
      <div className="page-titles">
        <ol className="breadcrumb">
          <li className="breadcrumb-item"><Link to="/payers">Payer Registry</Link></li>
          <li className="breadcrumb-item active"><Link to="/payers/create">Register</Link></li>
        </ol>
      </div>

      <div className="row">
        <div className="col-xl-8">
          <div className="card">
            <div className="card-header">
              <h4 className="card-title">Register Taxpayer / Customer</h4>
            </div>
            <div className="card-body">
              {error && <div className="alert alert-warning">{error}</div>}
              {matches.length > 0 && (
                <div className="alert alert-danger">
                  <strong>Possible duplicates:</strong>
                  <ul className="mb-2 mt-2">
                    {matches.map((m) => (
                      <li key={m.id}>
                        {m.full_name} ({m.tin}) — matched on {m.matched_on.join(', ')}
                        {' '}
                        <Link to={`/payers/${m.id}`}>View</Link>
                      </li>
                    ))}
                  </ul>
                  <div className="form-check">
                    <input
                      className="form-check-input"
                      type="checkbox"
                      id="forceCreate"
                      checked={forceCreate}
                      onChange={(e) => setForceCreate(e.target.checked)}
                    />
                    <label className="form-check-label" htmlFor="forceCreate">
                      Force create and flag for review
                    </label>
                  </div>
                </div>
              )}

              <form onSubmit={onSubmit}>
                <div className="row">
                  <div className="col-md-4 mb-3">
                    <label className="form-label">Payer type</label>
                    <select className="form-control" name="payer_type" value={form.payer_type} onChange={onChange}>
                      <option value="INDIVIDUAL">Individual</option>
                      <option value="BUSINESS">Business</option>
                    </select>
                  </div>
                  <div className="col-md-4 mb-3">
                    <label className="form-label">TIN *</label>
                    <input className="form-control" name="tin" value={form.tin} onChange={onChange} required />
                  </div>
                  <div className="col-md-4 mb-3">
                    <label className="form-label">Full name / Business name *</label>
                    <input className="form-control" name="full_name" value={form.full_name} onChange={onChange} required />
                  </div>
                  <div className="col-md-4 mb-3">
                    <label className="form-label">National ID</label>
                    <input className="form-control" name="national_id" value={form.national_id} onChange={onChange} />
                  </div>
                  <div className="col-md-4 mb-3">
                    <label className="form-label">Phone *</label>
                    <input className="form-control" name="phone" value={form.phone} onChange={onChange} required />
                  </div>
                  <div className="col-md-4 mb-3">
                    <label className="form-label">Email</label>
                    <input type="email" className="form-control" name="email" value={form.email} onChange={onChange} />
                  </div>
                  <div className="col-md-12 mb-3">
                    <label className="form-label">Address</label>
                    <input className="form-control" name="address" value={form.address} onChange={onChange} />
                  </div>
                </div>

                <h5 className="mt-3">Revenue obligation</h5>
                <div className="row">
                  <div className="col-md-4 mb-3">
                    <label className="form-label">Revenue code</label>
                    <input className="form-control" name="revenue_code" value={form.revenue_code} onChange={onChange} />
                  </div>
                  <div className="col-md-8 mb-3">
                    <label className="form-label">Revenue name</label>
                    <input className="form-control" name="revenue_name" value={form.revenue_name} onChange={onChange} />
                  </div>
                </div>

                <div className="form-check mb-3">
                  <input
                    className="form-check-input"
                    type="checkbox"
                    id="includeWater"
                    checked={includeWater}
                    onChange={(e) => setIncludeWater(e.target.checked)}
                  />
                  <label className="form-check-label" htmlFor="includeWater">
                    Link water account / meter
                  </label>
                </div>

                {includeWater && (
                  <div className="row">
                    <div className="col-md-3 mb-3">
                      <label className="form-label">Account no</label>
                      <input className="form-control" name="account_no" value={waterAccount.account_no} onChange={onAccountChange} />
                    </div>
                    <div className="col-md-3 mb-3">
                      <label className="form-label">Meter no</label>
                      <input className="form-control" name="meter_no" value={waterAccount.meter_no} onChange={onAccountChange} />
                    </div>
                    <div className="col-md-3 mb-3">
                      <label className="form-label">Tariff class</label>
                      <select className="form-control" name="tariff_class" value={waterAccount.tariff_class} onChange={onAccountChange}>
                        <option value="DOMESTIC">Domestic</option>
                        <option value="COMMERCIAL">Commercial</option>
                        <option value="INSTITUTIONAL">Institutional</option>
                      </select>
                    </div>
                    <div className="col-md-3 mb-3">
                      <label className="form-label">Location</label>
                      <input className="form-control" name="location" value={waterAccount.location} onChange={onAccountChange} />
                    </div>
                  </div>
                )}

                <div className="mb-3">
                  <label className="form-label">Notes</label>
                  <textarea className="form-control" name="notes" rows="2" value={form.notes} onChange={onChange} />
                </div>

                <button className="btn btn-primary" type="submit" disabled={saving}>
                  {saving ? 'Saving...' : 'Register payer'}
                </button>
              </form>
            </div>
          </div>
        </div>
      </div>
    </>
  );
};

export default PayerCreate;
