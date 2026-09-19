// Drop this on the old v17 portal if that URL must stay published.
// #socialLogin is no longer a public entry; guests go to the v18 card.
(function () {
  const target = 'pm_member_portal_v18.php';
  const hash = location.hash.replace('#', '');
  if (hash === 'socialLogin' || !hash) {
    const next = new URL(target, location.href);
    if (location.search) next.search = location.search;
    location.replace(next.href);
  }
})();
