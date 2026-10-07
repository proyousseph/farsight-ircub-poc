export const isAuthenticated = (state) => {
  if (state.auth.auth.sessionActive) return true;
  if (state.auth.auth.localId) return true;
  // Legacy: older sessions may still have idToken in memory during upgrade.
  if (state.auth.auth.idToken) return true;
  return false;
};
