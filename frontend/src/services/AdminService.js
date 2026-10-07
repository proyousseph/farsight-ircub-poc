import api from './api';

export function listUsers(params = {}) {
  return api.get('/users', { params });
}

export function createUser(payload) {
  return api.post('/users', payload);
}

export function updateUser(id, payload) {
  return api.put(`/users/${id}`, payload);
}

export function listRoles() {
  return api.get('/roles');
}

export function createRole(payload) {
  return api.post('/roles', payload);
}

export function updateRole(id, payload) {
  return api.put(`/roles/${id}`, payload);
}

export function listPermissions() {
  return api.get('/permissions');
}

export function listAuditLogs(params = {}) {
  return api.get('/audit-logs', { params });
}

export function getSystemConfig() {
  return api.get('/system-config');
}

export function updateSystemConfig(settings) {
  return api.put('/system-config', { settings });
}
