import axios from 'axios';

const api = axios.create({
  baseURL: import.meta.env.VITE_API_URL || 'http://127.0.0.1:8001/api',
  headers: {
    Accept: 'application/json',
    'Content-Type': 'application/json',
  },
});

api.interceptors.request.use((config) => {
  const raw = localStorage.getItem('userDetails');
  if (raw) {
    try {
      const stored = JSON.parse(raw);
      if (stored?.idToken) {
        config.headers.Authorization = `Bearer ${stored.idToken}`;
      }
    } catch {
      // ignore invalid local storage
    }
  }
  return config;
});

let handlingUnauthorized = false;

api.interceptors.response.use(
  (response) => response,
  (error) => {
    const status = error.response?.status;
    if (status === 401 && !handlingUnauthorized) {
      const url = String(error.config?.url || '');
      if (!url.includes('/auth/login')) {
        handlingUnauthorized = true;
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
