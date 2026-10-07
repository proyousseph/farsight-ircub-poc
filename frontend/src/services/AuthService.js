import swal from 'sweetalert';
import api from './api';
import { loginConfirmedAction, Logout } from '../store/actions/AuthActions';

const TOKEN_TTL_SECONDS = 60 * 60 * 8; // 8 hours

export function signUp() {
  return Promise.reject(new Error('Self-registration is disabled for IRCUB POC.'));
}

export function login(email, password) {
  return api.post('/auth/login', { email, password }).then((response) => {
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
      },
    };
  });
}

export function fetchMe() {
  return api.get('/auth/me');
}

export function logoutRequest() {
  return api.post('/auth/logout').catch(() => null);
}

export function formatError(errorResponse) {
  const message =
    errorResponse?.message ||
    errorResponse?.errors?.email?.[0] ||
    errorResponse?.errors?.password?.[0] ||
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

export function checkAutoLogin(dispatch, navigate) {
  const tokenDetailsString = localStorage.getItem('userDetails');
  if (!tokenDetailsString) {
    dispatch(Logout(navigate));
    return;
  }

  const tokenDetails = JSON.parse(tokenDetailsString);
  const expireDate = new Date(tokenDetails.expireDate);
  const todaysDate = new Date();

  if (todaysDate > expireDate) {
    dispatch(Logout(navigate));
    return;
  }

  dispatch(loginConfirmedAction(tokenDetails));

  const timer = expireDate.getTime() - todaysDate.getTime();
  runLogoutTimer(dispatch, timer, navigate);
}

export function hasPermission(permission) {
  const raw = localStorage.getItem('userDetails');
  if (!raw) return false;
  try {
    const stored = JSON.parse(raw);
    return Array.isArray(stored.permissions) && stored.permissions.includes(permission);
  } catch {
    return false;
  }
}
