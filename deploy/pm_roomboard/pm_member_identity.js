/**
 * 掛在 pm_member_portal_v17.php：手機登入改打身分 API；補上 Apple 與綁定提示。
 * <script src="pm_member_identity.js"></script>
 */
(function () {
  const IDENTITY_API = 'pm_member_identity_api.php';
  const SOCIAL = 'pm_member_social_identity.php';

  function byId(id) {
    return document.getElementById(id);
  }

  async function identityCall(action, data) {
    const boot = await fetch(IDENTITY_API, { credentials: 'same-origin', cache: 'no-store' });
    const state = await boot.json();
    const body = new URLSearchParams(Object.assign({ action: action }, data || {}));
    const r = await fetch(IDENTITY_API, {
      method: 'POST',
      credentials: 'same-origin',
      cache: 'no-store',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-PM-CSRF': state.csrf_token || '' },
      body: body,
    });
    let j = null;
    try { j = await r.json(); } catch (e) { j = { ok: false, error: '伺服器沒有回傳正確資料' }; }
    return j;
  }

  function ensureAppleButton() {
    const social = byId('socialLogin');
    if (!social) return;
    let apple = social.querySelector('[data-provider="apple"]');
    if (!apple) {
      const wrap = social.querySelector('.provider-buttons') || social;
      apple = document.createElement('button');
      apple.type = 'button';
      apple.className = 'provider-btn provider-apple';
      apple.dataset.provider = 'apple';
      apple.innerHTML = '<span>用 Apple 登入</span>';
      wrap.appendChild(apple);
    }
    apple.disabled = false;
    apple.classList.remove('hidden');
    apple.style.display = '';
    if (!apple.dataset.identityBound) {
      apple.dataset.identityBound = '1';
      apple.addEventListener('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        location.assign('pm_apple_login.php');
      });
    }
    const help = social.querySelector('.social-help');
    if (help && !social.querySelector('.apple-relay-note')) {
      const note = document.createElement('p');
      note.className = 'fine apple-relay-note';
      note.textContent = '可用 Apple 登入；若開啟隱藏信箱，我們會以手機聯絡您。';
      help.insertAdjacentElement('afterend', note);
    }
  }

  function enhanceGoogleButton() {
    const google = document.querySelector('#socialLogin [data-provider="google"]');
    if (!google || google.dataset.identityBound) return;
    google.dataset.identityBound = '1';
    google.addEventListener('click', function (e) {
      e.preventDefault();
      e.stopPropagation();
      location.assign('pm_google_login.php');
    }, true);
  }

  function bindBar() {
    if (byId('pmIdentityBindBar')) return;
    const bar = document.createElement('p');
    bar.id = 'pmIdentityBindBar';
    bar.className = 'fine';
    bar.innerHTML = '<a href="pm_member_bind.php">已登入？把 Google／Apple／手機綁到同一個會員</a>';
    const social = byId('socialLogin');
    if (social) social.appendChild(bar);
  }

  async function wirePhone() {
    const form = byId('phoneCodeRequest');
    const verify = byId('phoneCodeVerify');
    if (!form || form.dataset.identityBound) return;
    form.dataset.identityBound = '1';
    form.addEventListener('submit', async function (e) {
      e.preventDefault();
      e.stopImmediatePropagation();
      const phone = (byId('phoneCodeNumber') && byId('phoneCodeNumber').value || '').trim();
      const msg = byId('phoneOtpMessage');
      if (msg) msg.textContent = '正在送出簡訊驗證碼，請稍候…';
      const j = await identityCall('request_phone_login_code', { phone: phone });
      if (!j.ok) {
        if (msg) { msg.textContent = j.error || '簡訊沒有送出。'; msg.dataset.error = 'true'; }
        return;
      }
      form.classList.add('hidden');
      if (verify) verify.classList.remove('hidden');
      if (byId('phoneCodeDestination')) byId('phoneCodeDestination').textContent = '驗證碼將送至：' + phone;
      if (msg) { msg.textContent = j.message || '簡訊驗證碼已送出。'; msg.dataset.error = 'false'; }
    }, true);

    if (verify && !verify.dataset.identityBound) {
      verify.dataset.identityBound = '1';
      verify.addEventListener('submit', async function (e) {
        e.preventDefault();
        e.stopImmediatePropagation();
        const phone = (byId('phoneCodeNumber') && byId('phoneCodeNumber').value || '').trim();
        const code = (byId('phoneCodeValue') && byId('phoneCodeValue').value || '').trim();
        const msg = byId('phoneOtpMessage');
        const j = await identityCall('verify_phone_login_code', { phone: phone, code: code });
        if (!j.ok) {
          if (msg) { msg.textContent = j.error || '驗證沒有完成。'; msg.dataset.error = 'true'; }
          return;
        }
        location.assign('pm_member_center_v17.php');
      }, true);
    }
  }

  function relaxEmailRequired() {
    const email = byId('email');
    if (email && email.required) {
      const help = document.createElement('small');
      help.textContent = 'Email 是輔助聯絡；Apple 隱藏信箱可以使用。主要請留手機。';
      email.insertAdjacentElement('afterend', help);
    }
  }

  document.addEventListener('DOMContentLoaded', function () {
    ensureAppleButton();
    enhanceGoogleButton();
    bindBar();
    wirePhone();
    relaxEmailRequired();
    fetch(SOCIAL, { credentials: 'same-origin', cache: 'no-store' })
      .then(function (r) { return r.json(); })
      .then(function (j) {
        if (!j || !j.ok) return;
        const apple = document.querySelector('#socialLogin [data-provider="apple"]');
        if (apple) apple.disabled = !j.providers.apple;
        const google = document.querySelector('#socialLogin [data-provider="google"]');
        if (google && j.providers.google) {
          google.disabled = false;
          google.classList.remove('hidden');
        }
      })
      .catch(function () {});
  });
})();
