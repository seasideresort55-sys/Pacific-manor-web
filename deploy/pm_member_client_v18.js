// CSRF handshake. Prefer the live v17 client if it already defined pmMemberFetch.
if (typeof pmMemberFetch !== 'function') {
  async function pmMemberFetch(url, options = {}) {
    const api = window.PM_MEMBER_API || 'pm_member_api_v17.php';
    const endpoint = new URL(api, location.href);
    const bootstrap = await fetch(endpoint, { credentials: 'same-origin', cache: 'no-store' });
    const state = await bootstrap.json();
    if (!bootstrap.ok || !state.csrf_token) {
      throw new Error('無法取得會員驗證狀態，請重新整理頁面。');
    }
    const headers = new Headers(options.headers || {});
    headers.set('X-PM-CSRF', state.csrf_token);
    return fetch(url, { ...options, headers, credentials: 'same-origin', cache: 'no-store' });
  }
}
