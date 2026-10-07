import api from './api';

export function listGlMappings() {
  return api.get('/gl-mappings');
}

export function createGlMapping(payload) {
  return api.post('/gl-mappings', payload);
}

export function updateGlMapping(id, payload) {
  return api.put(`/gl-mappings/${id}`, payload);
}

export function listFmisBatches(params = {}) {
  return api.get('/fmis/batches', { params });
}

export function createFmisBatch(payload) {
  return api.post('/fmis/batches', payload);
}

export function getFmisBatch(id) {
  return api.get(`/fmis/batches/${id}`);
}

export function postFmisBatch(id) {
  return api.post(`/fmis/batches/${id}/post`);
}

export function reverseFmisBatch(id) {
  return api.post(`/fmis/batches/${id}/reverse`);
}

export function getFmisReconciliation(date) {
  return api.get('/fmis/reconciliation', { params: { date } });
}
