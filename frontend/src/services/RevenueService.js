import api from './api';

export function listRevenueTypes(params = {}) {
  return api.get('/revenue-types', { params });
}

export function listAssessments(params = {}) {
  return api.get('/assessments', { params });
}

export function createAssessment(payload) {
  return api.post('/assessments', payload);
}

export function getAssessment(id) {
  return api.get(`/assessments/${id}`);
}

export function listPayments(params = {}) {
  return api.get('/payments', { params });
}

export function createPayment(payload) {
  return api.post('/payments', payload);
}

export function uploadPaymentsCsv(file) {
  const form = new FormData();
  form.append('file', file);
  return api.post('/payments/upload', form, {
    headers: { 'Content-Type': 'multipart/form-data' },
  });
}
