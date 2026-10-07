import axios from 'axios';

const api = axios.create({
  baseURL: import.meta.env.VITE_API_URL || 'http://localhost:8001/api',
  withCredentials: true,
  headers: {
    Accept: 'application/json',
    'Content-Type': 'application/json',
  },
});

let handlingUnauthorized = false;

api.interceptors.response.use(
  (response) => response,
  (error) => {
    const status = error.response?.status;
    const data = error.response?.data;
    const url = String(error.config?.url || '');

    if (status === 403 && data?.must_change_password && !url.includes('/auth/password')) {
      if (window.location.pathname !== '/change-password') {
        window.location.assign('/change-password');
      }
      return Promise.reject(error);
    }

    if (status === 401 && !handlingUnauthorized) {
      if (!url.includes('/auth/login')) {
        handlingUnauthorized = true;
        sessionStorage.removeItem('userDetails');
        localStorage.removeItem('userDetails');
        if (window.location.pathname !== '/login') {
          window.location.assign('/login');
        }
        handlingUnauthorized = false;
      }
    }
    return Promise.reject(error);
  },
);

export default api;
