import api from './api';

export function listPayers(params = {}) {
  return api.get('/payers', { params });
}

export function getPayer(id) {
  return api.get(`/payers/${id}`);
}

export function createPayer(payload) {
  return api.post('/payers', payload);
}

export function updatePayer(id, payload) {
  return api.put(`/payers/${id}`, payload);
}
