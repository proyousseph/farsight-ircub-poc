import api from './api';

export function fetchChannelRate(params = {}) {
  return api.get('/channel/rates', { params });
}

export function listChannelPayments(params = {}) {
  return api.get('/channel/payments', { params });
}

export function initiateChannelPayment(payload) {
  return api.post('/channel/payments', payload);
}

export function getChannelPayment(id) {
  return api.get(`/channel/payments/${id}`);
}

export function checkChannelPayment(id) {
  return api.post(`/channel/payments/${id}/check`);
}

export function runChannelRetries() {
  return api.post('/channel/retries');
}

export function listSupervisorNotifications(params = {}) {
  return api.get('/channel/notifications', { params });
}

export function listReconciliationRuns(params = {}) {
  return api.get('/channel/reconciliation', { params });
}

export function runReconciliation(payload) {
  return api.post('/channel/reconciliation', payload);
}

export function getReconciliationRun(id) {
  return api.get(`/channel/reconciliation/${id}`);
}
