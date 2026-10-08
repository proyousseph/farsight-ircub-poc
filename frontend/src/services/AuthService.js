import swal from 'sweetalert';
import api from './api';
import { loginConfirmedAction, Logout } from '../store/actions/AuthActions';

const TOKEN_TTL_SECONDS = 60 * 60 * 8; // aligned with Sanctum default
const SESSION_KEY = 'userDetails';

export function signUp() {
  return Promise.reject(new Error('Self-registration is disabled for IRCUB POC.'));
}

function toSessionPayload(user, extras = {}) {
  return {
    // sessionActive marks auth without storing the bearer token in the browser.
    sessionActive: true,
    idToken: '',
    localId: String(user.id),
    email: user.email,
    displayName: user.name,
    expiresIn: String(TOKEN_TTL_SECONDS),
    refreshToken: '',
    user,
    permissions: user.permissions || [],
    roles: user.roles || [],
    must_change_password: Boolean(user.must_change_password),
    features: user.features || {},
    ...extras,
  };
}

export function login(email, password, otp = null) {
  const payload = { email, password };
  if (otp) {
    payload.otp = otp;
  }

  return api.post('/auth/login', payload).then((response) => {
    const { user } = response.data;
    return {
      data: toSessionPayload(user),
    };
  });
}

export function fetchMe() {
  return api.get('/auth/me');
}

export function setupTwoFactor() {
  return api.post('/auth/2fa/setup');
}

export function confirmTwoFactor(otp) {
  return api.post('/auth/2fa/confirm', { otp });
}

export function disableTwoFactor(password, otp = null) {
  return api.post('/auth/2fa/disable', { password, otp });
}

export function changePassword(currentPassword, password, passwordConfirmation) {
  return api.put('/auth/password', {
    current_password: currentPassword,
    password,
    password_confirmation: passwordConfirmation,
  });
}

export function logoutRequest() {
  return api.post('/auth/logout').catch(() => null);
}

export function formatError(errorResponse) {
  if (errorResponse?.requires_2fa) {
    return errorResponse.message || 'Two-factor authentication required.';
  }

  const message =
    errorResponse?.message ||
    errorResponse?.errors?.email?.[0] ||
    errorResponse?.errors?.password?.[0] ||
    errorResponse?.errors?.otp?.[0] ||
    'Login failed. Please check your credentials.';

  swal('Oops', message, 'error', { button: 'Try Again!' });
  return message;
}

/** Persist non-sensitive session profile only (never the bearer token). */
export function saveTokenInLocalStorage(tokenDetails) {
  const safe = {
    ...tokenDetails,
    idToken: '',
    refreshToken: '',
    sessionActive: true,
    expireDate: new Date(
      new Date().getTime() + Number(tokenDetails.expiresIn || TOKEN_TTL_SECONDS) * 1000,
    ),
  };
  sessionStorage.setItem(SESSION_KEY, JSON.stringify(safe));
  // Clear any legacy token-bearing localStorage from earlier builds.
  localStorage.removeItem(SESSION_KEY);
}

export function runLogoutTimer(dispatch, timer, navigate) {
  setTimeout(() => {
    dispatch(Logout(navigate));
  }, timer);
}

export async function checkAutoLogin(dispatch, navigate) {
  // Prefer cookie session: ask the API. Profile cache is optional UX only.
  try {
    const { data } = await fetchMe();
    const user = data.user;
    const refreshed = toSessionPayload(user);
    saveTokenInLocalStorage(refreshed);
    dispatch(loginConfirmedAction(refreshed));
    runLogoutTimer(dispatch, TOKEN_TTL_SECONDS * 1000, navigate);
  } catch {
    sessionStorage.removeItem(SESSION_KEY);
    localStorage.removeItem(SESSION_KEY);
    dispatch(Logout(navigate));
  }
}

export function getStoredUser() {
  const raw = sessionStorage.getItem(SESSION_KEY);
  if (!raw) return null;
  try {
    return JSON.parse(raw);
  } catch {
    return null;
  }
}

export function hasPermission(permission) {
  const stored = getStoredUser();
  return Array.isArray(stored?.permissions) && stored.permissions.includes(permission);
}

export function hasFeature(feature) {
  const stored = getStoredUser();
  return Boolean(stored?.features?.[feature] ?? stored?.user?.features?.[feature]);
}
