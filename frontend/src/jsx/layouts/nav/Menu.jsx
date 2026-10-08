export const MenuList = [
  {
    title: 'Main',
    classsChange: 'menu-title',
  },
  {
    title: 'Dashboard',
    iconStyle: <i className="flaticon-025-dashboard"></i>,
    to: 'dashboard',
    permission: 'dashboard.view',
  },
  {
    title: 'Operations',
    classsChange: 'menu-title',
  },
  {
    title: 'Payer Registry',
    iconStyle: <i className="flaticon-381-user-9"></i>,
    to: 'payers',
    permission: 'payers.view',
  },
  {
    title: 'Assessments',
    iconStyle: <i className="flaticon-381-note"></i>,
    to: 'assessments',
    permission: 'assessments.view',
  },
  {
    title: 'Payments',
    iconStyle: <i className="flaticon-381-diamond"></i>,
    classsChange: 'mm-collapse',
    // Parent visible if any child permission matches (see getVisibleMenu).
    permissionAnyOf: ['payments.view', 'payments.view_own', 'payments.pay_own', 'payments.capture'],
    content: [
      {
        title: 'Cash / Manual',
        to: 'payments',
        permission: 'payments.view',
      },
      {
        title: 'Channel Payments',
        to: 'channel-payments',
        permissionAnyOf: ['payments.view', 'payments.view_own', 'payments.pay_own', 'payments.capture'],
      },
    ],
  },
  {
    title: 'Water Billing',
    iconStyle: <i className="flaticon-381-settings-2"></i>,
    classsChange: 'mm-collapse',
    permission: 'bills.view',
    content: [
      {
        title: 'Meter Readings',
        to: 'meter-readings',
        permission: 'meters.capture',
      },
      {
        title: 'Billing Cycles',
        to: 'billing-cycles',
        permission: 'billing.run',
      },
      {
        title: 'Water Bills',
        to: 'water-bills',
        permission: 'bills.view',
      },
    ],
  },
  {
    title: 'Finance',
    classsChange: 'menu-title',
  },
  {
    title: 'FMIS Journals',
    iconStyle: <i className="flaticon-381-network"></i>,
    to: 'fmis',
    permission: 'fmis.post',
  },
  {
    title: 'FMIS Reconciliation',
    iconStyle: <i className="flaticon-381-list"></i>,
    to: 'fmis',
    permission: 'fmis.reconcile',
  },
  {
    title: 'Channel Reconciliation',
    iconStyle: <i className="flaticon-381-notepad"></i>,
    to: 'reconciliation',
    permission: 'channels.reconcile',
  },
  {
    title: 'Reports',
    iconStyle: <i className="flaticon-381-interactive"></i>,
    to: 'reports',
    permission: 'reports.view',
  },
  {
    title: 'Administration',
    classsChange: 'menu-title',
  },
  {
    title: 'Users & Roles',
    iconStyle: <i className="flaticon-381-user-7"></i>,
    to: 'users',
    permission: 'users.manage',
  },
  {
    title: 'System Config',
    iconStyle: <i className="flaticon-381-controls-3"></i>,
    to: 'system-config',
    permission: 'config.manage',
  },
  {
    title: 'Audit Logs',
    iconStyle: <i className="flaticon-381-search-1"></i>,
    to: 'audit-logs',
    permission: 'audit.view',
  },
  {
    title: 'Self Service',
    classsChange: 'menu-title',
  },
  {
    title: 'My Bills',
    iconStyle: <i className="flaticon-381-notepad-2"></i>,
    to: 'my-bills',
    permission: 'bills.view_own',
  },
  {
    title: 'My Assessments',
    iconStyle: <i className="flaticon-381-file"></i>,
    to: 'my-assessments',
    permission: 'assessments.view_own',
  },
];

export function getVisibleMenu(permissions = []) {
  const can = (permission) => !permission || permissions.includes(permission);
  const canAny = (item) => {
    if (item.permissionAnyOf?.length) {
      return item.permissionAnyOf.some((p) => permissions.includes(p));
    }
    return can(item.permission);
  };

  return MenuList
    .map((item) => {
      if (item.classsChange === 'menu-title') {
        return item;
      }

      if (item.content?.length) {
        const content = item.content.filter((child) => canAny(child));
        if (!content.length || !canAny(item)) {
          return null;
        }
        return { ...item, content };
      }

      return canAny(item) ? item : null;
    })
    .filter(Boolean)
    .filter((item, index, list) => {
      if (item.classsChange !== 'menu-title') {
        return true;
      }
      const next = list[index + 1];
      return next && next.classsChange !== 'menu-title';
    });
}
