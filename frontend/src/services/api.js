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
    if (status === 401 && !handlingUnauthorized) {
      const url = String(error.config?.url || '');
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
