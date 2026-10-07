import api from './api';

export function listWaterAccounts(params = {}) {
  return api.get('/water-accounts', { params });
}

export function listMeterReadings(params = {}) {
  return api.get('/meter-readings', { params });
}

export function createMeterReading(payload) {
  return api.post('/meter-readings', payload);
}

export function uploadMeterReadingsCsv(file) {
  const form = new FormData();
  form.append('file', file);
  return api.post('/meter-readings/upload', form, {
    headers: { 'Content-Type': 'multipart/form-data' },
  });
}

export function listBillingCycles(params = {}) {
  return api.get('/billing-cycles', { params });
}

export function runBillingCycle(period) {
  return api.post('/billing-cycles', { period });
}

export function getBillingCycle(id) {
  return api.get(`/billing-cycles/${id}`);
}

export function listWaterBills(params = {}) {
  return api.get('/water-bills', { params });
}

export function getWaterBill(id) {
  return api.get(`/water-bills/${id}`);
}

export function releaseWaterBill(id) {
  return api.post(`/water-bills/${id}/release`);
}

export function getWaterStatement(payerId) {
  return api.get('/water-bills/statement', { params: { payer_id: payerId } });
}

export function waterBillPdfUrl(id) {
  return `/water-bills/${id}/pdf`;
}
