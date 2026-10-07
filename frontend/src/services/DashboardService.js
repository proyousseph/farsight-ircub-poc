import api from './api';

export function getDashboard(params = {}) {
  return api.get('/dashboard', { params });
}

export function getDashboardAlerts(params = {}) {
  return api.get('/dashboard/alerts', { params });
}

export function refreshDashboard(payload = {}) {
  return api.post('/dashboard/refresh', payload);
}
