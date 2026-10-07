import swal from 'sweetalert';
import api from './api';
import { loginConfirmedAction, Logout } from '../store/actions/AuthActions';

const TOKEN_TTL_SECONDS = 60 * 60 * 8; // 8 hours (aligned with Sanctum default)

export function signUp() {
  return Promise.reject(new Error('Self-registration is disabled for IRCUB POC.'));
}

export function login(email, password, otp = null) {
  const payload = { email, password };
  if (otp) {
    payload.otp = otp;
  }

  return api.post('/auth/login', payload).then((response) => {
    const { token, user } = response.data;
    return {
      data: {
        idToken: token,
        localId: String(user.id),
        email: user.email,
        displayName: user.name,
        expiresIn: String(TOKEN_TTL_SECONDS),
        refreshToken: '',
        user,
        permissions: user.permissions || [],
        roles: user.roles || [],
        must_change_password: Boolean(user.must_change_password),
      },
    };
  });
}

export function fetchMe() {
  return api.get('/auth/me');
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

export function saveTokenInLocalStorage(tokenDetails) {
  tokenDetails.expireDate = new Date(
    new Date().getTime() + Number(tokenDetails.expiresIn) * 1000,
  );
  localStorage.setItem('userDetails', JSON.stringify(tokenDetails));
}

export function runLogoutTimer(dispatch, timer, navigate) {
  setTimeout(() => {
    dispatch(Logout(navigate));
  }, timer);
}

export async function checkAutoLogin(dispatch, navigate) {
  const tokenDetailsString = localStorage.getItem('userDetails');
  if (!tokenDetailsString) {
    dispatch(Logout(navigate));
    return;
  }

  let tokenDetails;
  try {
    tokenDetails = JSON.parse(tokenDetailsString);
  } catch {
    localStorage.removeItem('userDetails');
    dispatch(Logout(navigate));
    return;
  }

  const expireDate = new Date(tokenDetails.expireDate);
  const todaysDate = new Date();

  if (!tokenDetails.idToken || todaysDate > expireDate) {
    dispatch(Logout(navigate));
    return;
  }

  try {
    const { data } = await fetchMe();
    const user = data.user;
    const refreshed = {
      ...tokenDetails,
      user,
      permissions: user.permissions || [],
      roles: user.roles || [],
      email: user.email,
      displayName: user.name,
      must_change_password: Boolean(user.must_change_password),
    };
    saveTokenInLocalStorage({
      ...refreshed,
      expiresIn: String(
        Math.max(60, Math.floor((expireDate.getTime() - Date.now()) / 1000)),
      ),
    });
    dispatch(loginConfirmedAction(refreshed));
    const timer = expireDate.getTime() - todaysDate.getTime();
    runLogoutTimer(dispatch, timer, navigate);
  } catch {
    dispatch(Logout(navigate));
  }
}

export function getStoredUser() {
  const raw = localStorage.getItem('userDetails');
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
