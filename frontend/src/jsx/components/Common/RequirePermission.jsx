import React from 'react';
import { hasPermission } from '../../../services/AuthService';

/**
 * Route guard — UX only; API remains the source of truth for authorization.
 */
const RequirePermission = ({ anyOf = [], children }) => {
  const allowed = anyOf.some((permission) => hasPermission(permission));
  if (!allowed) {
    return (
      <div className="alert alert-warning mt-3" role="alert">
        You do not have permission to view this page.
      </div>
    );
  }

  return children;
};

export default RequirePermission;
