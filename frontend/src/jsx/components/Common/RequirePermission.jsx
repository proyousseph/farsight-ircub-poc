import React from 'react';
import { Navigate } from 'react-router-dom';
import { hasPermission } from '../../../services/AuthService';

/**
 * Route guard — UX only; API remains the source of truth for authorization.
 */
const RequirePermission = ({ anyOf = [], children }) => {
  const allowed = anyOf.some((permission) => hasPermission(permission));
  if (!allowed) {
    return <Navigate to="/dashboard" replace />;
  }

  return children;
};

export default RequirePermission;
