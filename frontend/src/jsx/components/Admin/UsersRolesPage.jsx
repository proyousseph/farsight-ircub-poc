import React, { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import {
  createRole,
  createUser,
  listPermissions,
  listRoles,
  listUsers,
  updateRole,
  updateUser,
} from '../../../services/AdminService';

const UsersRolesPage = () => {
  const [users, setUsers] = useState([]);
  const [roles, setRoles] = useState([]);
  const [roleTree, setRoleTree] = useState([]);
  const [permissions, setPermissions] = useState([]);
  const [error, setError] = useState('');
  const [info, setInfo] = useState('');
  const [tab, setTab] = useState('users');
  const [userForm, setUserForm] = useState({
    name: '', email: '', password: 'Password@123', role_ids: [], is_active: true, two_factor_enabled: false,
  });
  const [roleForm, setRoleForm] = useState({ name: '', description: '', parent_id: '', permission_ids: [] });

  const load = async () => {
    setError('');
    try {
      const [u, r, p] = await Promise.all([listUsers({ per_page: 50 }), listRoles(), listPermissions()]);
      setUsers(u.data.data || []);
      setRoles(r.data.data || []);
      setRoleTree(r.data.tree || []);
      setPermissions(p.data.data || []);
    } catch (err) {
      setError(err.response?.data?.message || 'Failed to load users/roles.');
    }
  };

  useEffect(() => { load(); }, []);

  const onCreateUser = async (e) => {
    e.preventDefault();
    setInfo('');
    try {
      const { data } = await createUser({
        ...userForm,
        role_ids: userForm.role_ids.map(Number),
      });
      setInfo(data.message);
      setUserForm({ name: '', email: '', password: 'Password@123', role_ids: [], is_active: true, two_factor_enabled: false });
      await load();
    } catch (err) {
      setError(err.response?.data?.message || Object.values(err.response?.data?.errors || {})[0]?.[0] || 'Create user failed.');
    }
  };

  const onCreateRole = async (e) => {
    e.preventDefault();
    setInfo('');
    try {
      const { data } = await createRole({
        name: roleForm.name,
        description: roleForm.description,
        parent_id: roleForm.parent_id ? Number(roleForm.parent_id) : null,
        permission_ids: roleForm.permission_ids.map(Number),
      });
      setInfo(data.message);
      setRoleForm({ name: '', description: '', parent_id: '', permission_ids: [] });
      await load();
    } catch (err) {
      setError(err.response?.data?.message || err.response?.data?.errors?.permission_ids?.[0] || 'Create role failed.');
    }
  };

  const renderTree = (nodes, depth = 0) => (
    <ul className={depth === 0 ? 'list-unstyled mb-0' : 'list-unstyled ms-3 border-start ps-3'}>
      {nodes.map((n) => (
        <li key={n.id} className="mb-1">
          <strong>{n.name}</strong>
          <span className="badge badge-light ms-1">L{n.level}</span>
          {n.is_system && <span className="badge badge-secondary ms-1">system</span>}
          {n.children?.length > 0 && renderTree(n.children, depth + 1)}
        </li>
      ))}
    </ul>
  );

  const toggleActive = async (user) => {
    try {
      await updateUser(user.id, { is_active: !user.is_active });
      await load();
    } catch (err) {
      setError(err.response?.data?.message || 'Update failed.');
    }
  };

  const togglePerm = (id) => {
    setRoleForm((prev) => {
      const ids = prev.permission_ids.includes(id)
        ? prev.permission_ids.filter((x) => x !== id)
        : [...prev.permission_ids, id];
      return { ...prev, permission_ids: ids };
    });
  };

  return (
    <>
      <div className="page-titles">
        <ol className="breadcrumb">
          <li className="breadcrumb-item"><Link to="/dashboard">Dashboard</Link></li>
          <li className="breadcrumb-item active"><Link to="#">Users & Roles</Link></li>
        </ol>
      </div>
      <h3 className="mb-3">Users & Roles</h3>
      {error && <div className="alert alert-danger">{error}</div>}
      {info && <div className="alert alert-success">{info}</div>}

      <ul className="nav nav-tabs mb-3">
        <li className="nav-item"><button type="button" className={`nav-link ${tab === 'users' ? 'active' : ''}`} onClick={() => setTab('users')}>Users</button></li>
        <li className="nav-item"><button type="button" className={`nav-link ${tab === 'roles' ? 'active' : ''}`} onClick={() => setTab('roles')}>Roles</button></li>
      </ul>

      {tab === 'users' && (
        <div className="row ircub-admin-grid">
          <div className="col-12 col-lg-4">
            <div className="card"><div className="card-header"><h4 className="card-title mb-0">Create user</h4></div>
              <div className="card-body">
                <form onSubmit={onCreateUser} className="d-grid gap-2 ircub-admin-form">
                  <div>
                    <label className="form-label mb-1">Full name</label>
                    <input className="form-control" placeholder="Full name" value={userForm.name} onChange={(e) => setUserForm({ ...userForm, name: e.target.value })} required />
                  </div>
                  <div>
                    <label className="form-label mb-1">Email</label>
                    <input className="form-control" type="email" placeholder="Email" value={userForm.email} onChange={(e) => setUserForm({ ...userForm, email: e.target.value })} required />
                  </div>
                  <div>
                    <label className="form-label mb-1">Password</label>
                    <input className="form-control" type="password" placeholder="Password" value={userForm.password} onChange={(e) => setUserForm({ ...userForm, password: e.target.value })} required />
                    <small className="text-muted">Password policy: min 10, upper/lower/number/symbol.</small>
                  </div>
                  <div>
                    <label className="form-label mb-1">Roles (Ctrl/Cmd multi-select)</label>
                    <select multiple className="form-control" size={Math.min(6, Math.max(3, roles.length))} value={userForm.role_ids} onChange={(e) => setUserForm({ ...userForm, role_ids: Array.from(e.target.selectedOptions).map((o) => o.value) })}>
                      {roles.map((r) => <option key={r.id} value={r.id}>{r.name}</option>)}
                    </select>
                  </div>
                  <label className="form-check"><input type="checkbox" className="form-check-input" checked={userForm.two_factor_enabled} onChange={(e) => setUserForm({ ...userForm, two_factor_enabled: e.target.checked })} /> Enable 2FA stub</label>
                  <button className="btn btn-primary" type="submit">Create</button>
                </form>
              </div>
            </div>
          </div>
          <div className="col-12 col-lg-8">
            <div className="card"><div className="card-body table-responsive">
              <table className="table table-sm align-middle">
                <thead><tr><th>Name</th><th>Email</th><th className="d-none d-md-table-cell">Roles</th><th>2FA</th><th>Active</th><th /></tr></thead>
                <tbody>
                  {users.map((u) => (
                    <tr key={u.id}>
                      <td>{u.name}</td>
                      <td className="text-break">{u.email}</td>
                      <td className="d-none d-md-table-cell">{(u.roles || []).map((r) => r.name).join(', ')}</td>
                      <td>{u.two_factor_enabled ? 'Yes' : 'No'}</td>
                      <td>{u.is_active ? 'Yes' : 'No'}</td>
                      <td className="text-nowrap"><button type="button" className="btn btn-sm btn-outline-secondary text-nowrap px-2" onClick={() => toggleActive(u)}>{u.is_active ? 'Disable' : 'Enable'}</button></td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div></div>
          </div>
        </div>
      )}

      {tab === 'roles' && (
        <div className="row ircub-admin-grid">
          <div className="col-12 col-lg-4 mb-3">
            <div className="card h-100">
              <div className="card-header"><h4 className="card-title mb-0">Role hierarchy</h4></div>
              <div className="card-body">
                <p className="text-muted small">Child roles must use a permission subset of their parent.</p>
                {roleTree.length ? renderTree(roleTree) : <span className="text-muted">No roles yet.</span>}
              </div>
            </div>
          </div>
          <div className="col-12 col-lg-4 mb-3">
            <div className="card h-100"><div className="card-header"><h4 className="card-title mb-0">Create custom role</h4></div>
              <div className="card-body">
                <form onSubmit={onCreateRole} className="ircub-admin-form">
                  <input className="form-control mb-2" placeholder="Role name" value={roleForm.name} onChange={(e) => setRoleForm({ ...roleForm, name: e.target.value })} required />
                  <textarea className="form-control mb-2" placeholder="Description" value={roleForm.description} onChange={(e) => setRoleForm({ ...roleForm, description: e.target.value })} />
                  <label className="form-label mb-1">Parent role (optional)</label>
                  <select className="form-control mb-2" value={roleForm.parent_id} onChange={(e) => setRoleForm({ ...roleForm, parent_id: e.target.value })}>
                    <option value="">— Top-level —</option>
                    {roles.map((r) => (
                      <option key={r.id} value={r.id}>{`${'—'.repeat(r.level || 0)} ${r.name}`}</option>
                    ))}
                  </select>
                  <div style={{ maxHeight: 240, overflow: 'auto' }} className="mb-2 border p-2">
                    {permissions.map((p) => (
                      <label key={p.id} className="d-block form-check">
                        <input type="checkbox" className="form-check-input" checked={roleForm.permission_ids.includes(String(p.id)) || roleForm.permission_ids.includes(p.id)} onChange={() => togglePerm(p.id)} />
                        <small>{p.module}.{p.slug}</small>
                      </label>
                    ))}
                  </div>
                  <button className="btn btn-primary" type="submit">Create role</button>
                </form>
              </div>
            </div>
          </div>
          <div className="col-12 col-lg-4 mb-3">
            <div className="card h-100"><div className="card-header"><h4 className="card-title mb-0">All roles</h4></div><div className="card-body">
              {roles.map((r) => (
                <div key={r.id} className="mb-3 border-bottom pb-2">
                  <strong>{r.name}</strong> <span className="badge badge-light">{r.slug}</span>
                  <span className="badge badge-light ms-1">L{r.level ?? 0}</span>
                  {r.is_system && <span className="badge badge-secondary ms-1">system</span>}
                  {r.parent && <div className="text-muted small">Parent: {r.parent.name}</div>}
                  <div className="text-muted small">{r.description}</div>
                  <div className="small">{(r.permissions || []).map((p) => p.slug).join(', ')}</div>
                  {!r.is_system && (
                    <button type="button" className="btn btn-xs btn-outline-danger mt-1" onClick={async () => { await updateRole(r.id, { is_active: !r.is_active }); await load(); }}>
                      {r.is_active ? 'Deactivate' : 'Activate'}
                    </button>
                  )}
                </div>
              ))}
            </div></div>
          </div>
        </div>
      )}
    </>
  );
};

export default UsersRolesPage;
