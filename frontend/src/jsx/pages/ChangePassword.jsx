import React, { useState } from 'react';
import { useDispatch } from 'react-redux';
import { useNavigate } from 'react-router-dom';
import {
  changePassword,
  getStoredUser,
  saveTokenInLocalStorage,
} from '../../services/AuthService';
import { loginConfirmedAction, Logout } from '../../store/actions/AuthActions';

function buildSession(user) {
  return {
    sessionActive: true,
    idToken: '',
    localId: String(user.id),
    email: user.email,
    displayName: user.name,
    expiresIn: String(60 * 60 * 8),
    refreshToken: '',
    user,
    permissions: user.permissions || [],
    roles: user.roles || [],
    must_change_password: Boolean(user.must_change_password),
  };
}

const ChangePassword = () => {
  const dispatch = useDispatch();
  const navigate = useNavigate();
  const stored = getStoredUser();
  const [currentPassword, setCurrentPassword] = useState('');
  const [password, setPassword] = useState('');
  const [passwordConfirmation, setPasswordConfirmation] = useState('');
  const [error, setError] = useState('');
  const [info, setInfo] = useState('');
  const [saving, setSaving] = useState(false);

  const onSubmit = async (e) => {
    e.preventDefault();
    setError('');
    setInfo('');
    if (password !== passwordConfirmation) {
      setError('New password confirmation does not match.');
      return;
    }
    setSaving(true);
    try {
      const { data } = await changePassword(currentPassword, password, passwordConfirmation);
      const session = buildSession(data.user);
      saveTokenInLocalStorage(session);
      dispatch(loginConfirmedAction(session));
      setInfo(data.message || 'Password updated.');
      navigate('/dashboard', { replace: true });
    } catch (err) {
      setError(
        err.response?.data?.errors?.current_password?.[0]
        || err.response?.data?.errors?.password?.[0]
        || err.response?.data?.message
        || 'Unable to update password.',
      );
    } finally {
      setSaving(false);
    }
  };

  return (
    <div className="authincation h-100 p-meddle">
      <div className="container h-100">
        <div className="row justify-content-center h-100 align-items-center">
          <div className="col-md-6">
            <div className="authincation-content">
              <div className="row no-gutters">
                <div className="col-xl-12">
                  <div className="auth-form">
                    <h4 className="mb-2">Change password</h4>
                    <p className="mb-4 text-muted">
                      {stored?.must_change_password
                        ? 'Your account requires a new password before you can continue.'
                        : 'Update your IRCUB account password.'}
                    </p>
                    {error && <div className="alert alert-danger">{error}</div>}
                    {info && <div className="alert alert-success">{info}</div>}
                    <form onSubmit={onSubmit}>
                      <div className="form-group mb-3">
                        <label><strong>Current password</strong></label>
                        <input
                          type="password"
                          className="form-control"
                          value={currentPassword}
                          onChange={(e) => setCurrentPassword(e.target.value)}
                          required
                          autoComplete="current-password"
                        />
                      </div>
                      <div className="form-group mb-3">
                        <label><strong>New password</strong></label>
                        <input
                          type="password"
                          className="form-control"
                          value={password}
                          onChange={(e) => setPassword(e.target.value)}
                          required
                          autoComplete="new-password"
                        />
                        <small className="text-muted">Min 10 chars with upper, lower, number, symbol.</small>
                      </div>
                      <div className="form-group mb-3">
                        <label><strong>Confirm new password</strong></label>
                        <input
                          type="password"
                          className="form-control"
                          value={passwordConfirmation}
                          onChange={(e) => setPasswordConfirmation(e.target.value)}
                          required
                          autoComplete="new-password"
                        />
                      </div>
                      <div className="d-flex gap-2">
                        <button type="submit" className="btn btn-primary" disabled={saving}>
                          {saving ? 'Saving…' : 'Update password'}
                        </button>
                        {!stored?.must_change_password && (
                          <button
                            type="button"
                            className="btn btn-outline-secondary"
                            onClick={() => navigate(-1)}
                          >
                            Cancel
                          </button>
                        )}
                        {stored?.must_change_password && (
                          <button
                            type="button"
                            className="btn btn-outline-danger"
                            onClick={() => dispatch(Logout(navigate))}
                          >
                            Sign out
                          </button>
                        )}
                      </div>
                    </form>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
};

export default ChangePassword;
