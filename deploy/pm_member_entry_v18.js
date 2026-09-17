// Guest login: phone SMS first, Email second, brand social, password as a text link.
document.addEventListener('DOMContentLoaded', () => {
  const byId = (id) => document.getElementById(id);
  const card = byId('loginCard');
  const passwordCard = byId('passwordLogin');
  if (!card || !passwordCard) return;

  const API = 'pm_member_api_v18.php';
  const phoneRequest = byId('phoneRequest');
  const phoneVerify = byId('phoneVerify');
  const emailRequest = byId('emailCodeRequest');
  const emailVerify = byId('emailCodeVerify');
  const emailComplete = byId('emailCodeComplete');
  const message = byId('loginMessage');
  const title = byId('loginTitle');
  const lead = byId('loginLead');
  const consent = byId('loginConsent');
  const hint = byId('consentHint');
  let busy = false;
  let channel = 'sms';
  let destination = '';
  let readyAt = 0;
  let timer;
  let emailNext = '';

  const say = (text, error = false) => {
    message.textContent = text || '';
    message.hidden = !text;
    message.dataset.error = String(!!error);
  };
  const showHint = (text) => {
    hint.textContent = text;
    hint.hidden = !text;
  };
  const needConsent = () => {
    if (consent.checked) {
      showHint('');
      return false;
    }
    showHint('請先勾選同意《會員資料使用說明》，再繼續。');
    consent.focus({ preventScroll: true });
    return true;
  };
  const setBusy = (value) => {
    busy = value;
    card.querySelectorAll('button').forEach((button) => {
      if (button.dataset.keepEnabled === '1') return;
      if (button.classList.contains('login-apple')) return;
      if (button.id === 'phoneResend' || button.id === 'emailCodeResend') return;
      button.disabled = value;
    });
  };
  const showPanel = (name) => {
    phoneRequest.classList.toggle('hidden', name !== 'phone');
    phoneVerify.classList.toggle('hidden', name !== 'phoneCode');
    emailRequest.classList.toggle('hidden', name !== 'email');
    emailVerify.classList.toggle('hidden', name !== 'emailCode');
    emailComplete.classList.toggle('hidden', name !== 'emailComplete');
    passwordCard.classList.add('hidden');
    card.classList.remove('hidden');
    if (name === 'phone') {
      title.textContent = '登入或註冊';
      lead.textContent = '用手機簡訊最快；也可 Google、LINE 或 Email。不用記密碼，選一種方式即可。';
    } else if (name === 'phoneCode') {
      title.textContent = '輸入驗證碼';
      lead.textContent = '請查看手機簡訊，輸入 6 位數字。';
    } else if (name === 'email') {
      title.textContent = '登入或註冊';
      lead.textContent = '用 Email 收取驗證碼，不用記密碼。';
    } else if (name === 'emailCode') {
      title.textContent = '輸入驗證碼';
      lead.textContent = '請查看信箱，輸入信中的 6 位數字。';
    }
  };
  const tick = () => {
    const left = Math.max(0, Math.ceil((readyAt - Date.now()) / 1000));
    const resend = channel === 'sms' ? byId('phoneResend') : byId('emailCodeResend');
    if (!resend) return;
    resend.disabled = busy || left > 0;
    resend.textContent = left > 0 ? `${left} 秒後可主動重寄` : '重新寄送驗證碼';
    if (left === 0) clearInterval(timer);
  };

  async function submit(action, data) {
    const body = new FormData();
    body.set('action', action);
    Object.entries(data).forEach(([key, value]) => body.set(key, value ?? ''));
    const response = await pmMemberFetch(API, { method: 'POST', body });
    let result;
    try {
      result = await response.json();
    } catch (_) {
      throw new Error('目前無法完成，請稍後再試。');
    }
    if (!response.ok || !result.ok) {
      const error = new Error(result.error || result.message || '這次沒有完成，請再試一次，或改用另一種方式。');
      error.nextStep = result.next_step;
      error.payload = result;
      throw error;
    }
    return result;
  }

  async function sendPhone() {
    const phone = byId('phoneNumber').value.trim();
    if (needConsent() || busy) return;
    if (!phoneRequest.reportValidity()) return;
    setBusy(true);
    say('正在送出簡訊驗證碼，請稍候…');
    try {
      const result = await submit('phone_login_request', { phone, consent: '1' });
      destination = phone;
      channel = 'sms';
      readyAt = Date.now() + 60000;
      byId('phoneDestination').textContent = '驗證碼已送至 ' + phone;
      byId('phoneCode').value = '';
      showPanel('phoneCode');
      say(result.message || '驗證碼已送到手機。');
      if (result.preview_code) {
        say((result.message || '') + ' 預覽碼：' + result.preview_code);
      }
      clearInterval(timer);
      timer = setInterval(tick, 1000);
      tick();
      byId('phoneCode').focus();
    } catch (error) {
      say(error.message, true);
    } finally {
      setBusy(false);
      tick();
    }
  }

  async function verifyPhone() {
    if (busy || !phoneVerify.reportValidity()) return;
    setBusy(true);
    say('正在驗證…');
    try {
      await submit('phone_login_verify', {
        phone: destination || byId('phoneNumber').value.trim(),
        code: byId('phoneCode').value.trim(),
        consent: consent.checked ? '1' : '0',
      });
      location.assign('pm_member_center_v17.php');
    } catch (error) {
      say(error.message, true);
    } finally {
      setBusy(false);
    }
  }

  async function sendEmail() {
    const email = byId('emailCodeAddress').value.trim();
    if (needConsent() || busy) return;
    if (!emailRequest.reportValidity()) return;
    setBusy(true);
    say('正在寄送驗證碼，請稍候…');
    try {
      const result = await submit('request_email_code', { email });
      destination = email;
      channel = 'email';
      readyAt = Date.now() + 60000;
      byId('emailCodeDestination').textContent = '驗證收件位置：' + email;
      byId('emailCodeValue').value = '';
      showPanel('emailCode');
      say(result.message || '驗證信已送出。');
      if (result.preview_code) say((result.message || '') + ' 預覽碼：' + result.preview_code);
      clearInterval(timer);
      timer = setInterval(tick, 1000);
      tick();
      byId('emailCodeValue').focus();
    } catch (error) {
      say(error.message, true);
    } finally {
      setBusy(false);
      tick();
    }
  }

  async function verifyEmail(event) {
    event.preventDefault();
    const form = event.currentTarget;
    if (busy || !form.reportValidity()) return;
    setBusy(true);
    say('正在驗證…');
    try {
      await submit('verify_email_code', {
        email: destination,
        code: byId('emailCodeValue').value,
        consent: emailNext === 'signup_consent' && byId('emailCodeConsent').checked ? '1' : consent.checked ? '1' : '0',
        legacy_password: emailNext === 'legacy_password' ? byId('emailLegacyPassword').value : '',
      });
      location.assign('pm_member_center_v17.php');
    } catch (error) {
      if (['signup_consent', 'legacy_password'].includes(error.nextStep)) {
        emailNext = error.nextStep;
        byId('emailSignupStep').classList.toggle('hidden', emailNext !== 'signup_consent');
        byId('emailLegacyStep').classList.toggle('hidden', emailNext !== 'legacy_password');
        showPanel('emailComplete');
        title.textContent = emailNext === 'signup_consent' ? '完成註冊' : '確認舊會員身分';
        lead.textContent = emailNext === 'signup_consent'
          ? '信箱驗證碼正確。請閱讀資料使用說明，同意後即可建立會員。'
          : '請完成這一步，啟用此帳戶的 Email 驗證碼登入。';
        say(emailNext === error.nextStep ? error.message : '', !!error.message);
      } else {
        showPanel('emailCode');
        say(error.message, true);
      }
    } finally {
      setBusy(false);
    }
  }

  phoneRequest.addEventListener('submit', (event) => {
    event.preventDefault();
    sendPhone();
  });
  phoneVerify.addEventListener('submit', (event) => {
    event.preventDefault();
    verifyPhone();
  });
  emailRequest.addEventListener('submit', (event) => {
    event.preventDefault();
    sendEmail();
  });
  emailVerify.addEventListener('submit', verifyEmail);
  emailComplete.addEventListener('submit', verifyEmail);
  byId('phoneResend').addEventListener('click', () => sendPhone());
  byId('emailCodeResend').addEventListener('click', () => sendEmail());
  byId('phoneEdit').addEventListener('click', () => {
    showPanel('phone');
    say('');
    byId('phoneNumber').focus();
  });
  byId('emailCodeEdit').addEventListener('click', () => {
    showPanel('email');
    say('');
    byId('emailCodeAddress').focus();
  });
  byId('emailCompleteBack').addEventListener('click', () => {
    emailNext = '';
    showPanel('emailCode');
    say('');
    byId('emailCodeValue').focus();
  });
  byId('switchToEmail').addEventListener('click', () => {
    channel = 'email';
    showPanel('email');
    say('');
  });
  byId('switchToPhone').addEventListener('click', () => {
    channel = 'sms';
    showPanel('phone');
    say('');
  });
  byId('showPassword').addEventListener('click', () => {
    card.classList.add('hidden');
    passwordCard.classList.remove('hidden');
    byId('loginIdentifier').focus({ preventScroll: true });
  });
  byId('backToOtp').addEventListener('click', () => {
    passwordCard.classList.add('hidden');
    card.classList.remove('hidden');
    showPanel(channel === 'email' ? 'email' : 'phone');
  });
  byId('noticeToggle').addEventListener('click', () => {
    const box = byId('noticeBox');
    box.hidden = !box.hidden;
  });

  const social = byId('socialRow');
  const socialMessage = byId('socialMessage');
  fetch('pm_member_social.php', { credentials: 'same-origin', cache: 'no-store' })
    .then(async (response) => {
      const data = await response.json();
      const providers = data.providers || {};
      social.querySelectorAll('button[data-provider]').forEach((button) => {
        const name = button.dataset.provider;
        const ready = providers[name] === true;
        if (name === 'apple') {
          button.disabled = true;
          button.classList.add('login-soon');
          return;
        }
        if (!ready) {
          button.disabled = true;
          button.classList.add('login-soon');
          const label = button.querySelector('[data-label]');
          if (label) label.textContent = button.dataset.soonLabel || label.textContent;
          return;
        }
        button.disabled = false;
        button.addEventListener('click', async () => {
          if (needConsent() || social.dataset.busy === '1') return;
          social.dataset.busy = '1';
          button.disabled = true;
          socialMessage.textContent = '正在前往登入服務…';
          try {
            const body = new FormData();
            body.set('provider', name);
            body.set('consent', '1');
            const r = await pmMemberFetch('pm_member_social.php', { method: 'POST', body });
            const j = await r.json();
            if (!r.ok || !j.ok) throw new Error(j.error || '無法開始登入');
            const url = new URL(j.url);
            const allowed = { google: 'accounts.google.com', apple: 'appleid.apple.com', line: 'access.line.me' };
            if (url.protocol !== 'https:' || url.hostname !== allowed[name]) throw new Error('登入網址不正確');
            location.assign(url.href);
          } catch (error) {
            socialMessage.textContent = error.message;
            button.disabled = false;
            delete social.dataset.busy;
          }
        });
      });
    })
    .catch(() => {
      social.querySelectorAll('button[data-provider="google"], button[data-provider="line"]').forEach((button) => {
        button.disabled = true;
        button.classList.add('login-soon');
      });
      socialMessage.textContent = '社群登入暫時無法連線，請改用手機簡訊或 Email。';
    });

  if (location.hash === '#socialLogin' || location.hash === '#passwordLogin') {
    history.replaceState(null, '', location.pathname + location.search);
  }
  if (new URLSearchParams(location.search).get('login') === 'password') {
    card.classList.add('hidden');
    passwordCard.classList.remove('hidden');
  } else {
    showPanel('phone');
  }
});
