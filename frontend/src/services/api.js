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

export default api;
