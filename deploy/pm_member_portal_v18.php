<!doctype html>
<html lang="zh-Hant">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<title>太平洋莊園｜登入或註冊</title>
<style>
:root{--cream:#F6F1E7;--ocean:#2E5E73;--deep:#1C3D4C;--line:#ccd8d5;--muted:#60706d}
*{box-sizing:border-box}
body{margin:0;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI","Noto Sans TC","PingFang TC","Microsoft JhengHei",sans-serif;background:var(--cream);color:var(--deep)}
body.pm-guest .page-titlebar{display:none}
.page-titlebar{background:var(--deep);color:#fff;padding:14px 16px}
.page-titlebar h1{font-size:20px;margin:0}
.page-titlebar p{font-size:13px;margin:5px 0 0;opacity:.86}
main{max-width:780px;margin:auto;padding:14px}
.card{background:#fff;border-radius:16px;padding:17px;margin:12px 0;box-shadow:0 2px 14px #0000000d;border:1px solid #e7edeb}
h2{font-size:18px;margin:0 0 10px}
label{display:block;font-size:13px;font-weight:700;margin-top:10px}
input,select,textarea{width:100%;padding:12px;border:1px solid var(--line);border-radius:10px;font-size:16px;background:#fff}
textarea{min-height:88px;resize:vertical}
button{margin-top:14px;padding:12px 16px;border:0;border-radius:10px;background:#176c62;color:#fff;font-size:16px;font-weight:800;cursor:pointer}
.secondary{background:#eef4f2;color:#24453f}
.note{background:#eef7f5;padding:12px;border-radius:10px;font-size:14px;line-height:1.55}
.ok,.err,.warn{padding:11px;border-radius:10px;margin:10px 0;font-size:14px}
.ok{background:#e8f7ed}.err{background:#fdeaea;color:#8b1d1d}.warn{background:#fff6dc;color:#74520b}
.grid{display:grid;grid-template-columns:1fr 1fr;gap:10px}
.hidden{display:none!important}
.state{font-weight:900}
.app{border-top:1px solid #e7edeb;padding:10px 0;line-height:1.5}
.app:first-child{border-top:0}
.small{font-size:13px;color:var(--muted)}
.loading{padding:22px;text-align:center;color:var(--muted)}
.req{color:#c62828;font-weight:900;margin-left:3px}
@media(max-width:620px){.grid{grid-template-columns:1fr}main{padding:10px}}

.login-shell{min-height:calc(100vh - 92px);background:
  radial-gradient(900px 240px at 12% -8%,rgba(46,94,115,.12),transparent 58%),
  radial-gradient(720px 200px at 100% 8%,rgba(46,94,115,.08),transparent 50%),
  linear-gradient(180deg,#F6F1E7 0%,#eef3f1 100%)}
#guestView{max-width:480px;margin:0 auto;padding:28px 16px 48px}
.login-card{border-radius:24px;background:#fff;padding:28px 24px 32px;box-shadow:0 12px 36px rgba(28,61,76,.08)}
.login-kicker{color:var(--ocean);font-size:15px;letter-spacing:.16em;font-weight:700;margin:0}
.login-title{margin:10px 0 0;color:var(--deep);font-size:32px;line-height:1.25;font-weight:700}
.login-lead{margin:12px 0 0;color:#3d5a66;font-size:18px;line-height:1.7}
.login-field{width:100%;min-height:56px;border-radius:16px;border:2px solid #e8dfd0;background:#fff;padding:12px 16px;font-size:18px;color:var(--deep)}
.login-field:focus{outline:none;border-color:var(--ocean)}
.login-primary,.login-google,.login-line,.login-apple{display:inline-flex;min-height:56px;width:100%;align-items:center;justify-content:center;border-radius:16px;font-size:18px;font-weight:700;margin-top:0}
.login-primary{background:var(--deep);color:#fff}
.login-primary:disabled{opacity:.5}
.login-google{border:2px solid #e5e7eb;background:#fff;color:var(--deep);gap:12px}
.login-line{background:#06C755;color:#fff;gap:12px}
.login-apple,.login-soon{background:#d8dee3;color:#5c6b73;gap:12px;cursor:not-allowed}
.login-or{display:flex;align-items:center;gap:12px;margin:28px 0 20px;color:#8aa4b0;font-size:16px}
.login-or:before,.login-or:after{content:"";flex:1;height:1px;background:#e8dfd0}
.login-text-link{background:none;border:0;padding:0;margin:8px 0 0;color:var(--ocean);font-size:17px;font-weight:600;text-align:left;text-decoration:underline;text-underline-offset:4px}
.login-hint{margin-top:16px;border-radius:16px;background:#f4efe4;padding:12px 16px;color:var(--deep);font-size:17px;line-height:1.6}
.login-hint[data-error=true]{background:#fff3ee}
.login-consent{display:flex;gap:12px;align-items:flex-start;margin-top:22px;font-size:16px;line-height:1.6;color:#3d5a66}
.login-consent input{appearance:auto;width:22px;min-width:22px;height:22px;margin:2px 0 0;accent-color:var(--ocean)}
.login-stack{display:grid;gap:14px;margin-top:24px}
.login-code{text-align:center;font-size:28px;letter-spacing:.45em}
.social-row{display:grid;gap:12px}
.brand-ico{width:24px;height:24px;flex:0 0 24px}
#status{max-width:480px;margin:0 auto}
@media(min-width:768px){.login-card{padding:36px 40px 40px}.login-title{font-size:36px}}
@media(max-width:620px){#guestView{padding:16px 12px 36px}.login-card{padding:26px 20px}.login-title{font-size:28px}}
@media(prefers-reduced-motion:reduce){*{scroll-behavior:auto!important}}

.pm-sitebar-v21{background:#f7f2e8;border-bottom:1px solid #e7ddcb;position:relative;z-index:3000}
.pm-sitebar-v21 .pm-head-inner{max-width:1180px;margin:0 auto;padding:14px 18px;display:flex;align-items:center;gap:12px}
.pm-sitebar-v21 .pm-brand{display:flex;flex-direction:column;text-decoration:none;color:#173f50;min-width:188px;line-height:1}
.pm-sitebar-v21 .pm-brand strong{font-size:24px;letter-spacing:.04em;font-weight:900}
.pm-sitebar-v21 .pm-brand small{margin-top:4px;font-size:10px;letter-spacing:.12em;color:#728997}
.pm-sitebar-v21 .pm-actions{margin-left:auto;display:flex;align-items:center;gap:7px}
.pm-sitebar-v21 .pm-action{display:inline-flex;align-items:center;justify-content:center;min-height:42px;padding:8px 13px;border:2px solid #173f50;border-radius:999px;background:#fff;color:#173f50;text-decoration:none;font-weight:850;font-size:14px;white-space:nowrap}
.pm-sitebar-v21 .pm-action.primary{background:#2e5e73;color:#fff;border-color:#2e5e73}
.pm-sitebar-v21 .pm-globe{width:46px;min-width:46px;height:46px;padding:0;font-size:25px;border-radius:50%}
.pm-sitebar-v21 .pm-subnav{display:none;max-width:1180px;margin:0 auto;padding:0 18px 13px;grid-template-columns:repeat(4,minmax(0,1fr));gap:7px}
.pm-sitebar-v21 .pm-subnav a{background:#fff;border:1px solid #e5d9c4;border-radius:10px;padding:8px;text-align:center;text-decoration:none;color:#173f50;font-size:13px;font-weight:800}
.pm-sitebar-v21 .pm-menu-check{position:absolute;opacity:0;pointer-events:none}
.pm-sitebar-v21 .pm-menu-check:checked~.pm-subnav{display:grid}
.pm-sitebar-v21 .pm-menu-label{cursor:pointer}
@media(max-width:760px){
 .pm-sitebar-v21 .pm-head-inner{padding:11px 10px;gap:6px}
 .pm-sitebar-v21 .pm-brand{min-width:130px;margin-right:auto}
 .pm-sitebar-v21 .pm-brand strong{font-size:19px;letter-spacing:0}
 .pm-sitebar-v21 .pm-actions{gap:5px}
 .pm-sitebar-v21 .pm-action{min-height:38px;padding:6px 8px;font-size:11px}
 .pm-sitebar-v21 .pm-globe{width:40px;min-width:40px;height:40px;font-size:21px}
}
.phone-verification{font:20px/1.6 system-ui,sans-serif;max-width:34rem;padding:1.2rem;color:#17372c;background:#fff;box-sizing:border-box}
.phone-verification h2{font-size:1.65em}.phone-verification label{display:block;margin:1rem 0}
.phone-verification input{display:block;font:inherit;width:100%;box-sizing:border-box;padding:.7rem;border:2px solid #62756c;border-radius:.5rem}
.phone-verification button{font:inherit;min-height:48px;padding:.65rem 1rem;border:0;border-radius:.5rem;color:#fff;background:#19583f;max-width:100%}
.phone-verification button:disabled{background:#666}
</style>
</head>
<body>
<div class="pm-sitebar-v21">
  <input class="pm-menu-check" id="pm-menu-v21" type="checkbox">
  <div class="pm-head-inner">
    <a class="pm-brand" href="https://seasideresort.com.tw/">
      <strong>太平洋莊園</strong><small>PACIFIC MANOR</small>
    </a>
    <div class="pm-actions">
      <a class="pm-action primary" href="pm_member_portal_v18.php">入住申請</a>
      <a class="pm-action" href="https://seasideresort.com.tw/#familycare">安心專區</a>
      <a class="pm-action" href="https://seasideresort.com.tw/#contact">聯繫我們</a>
      <a class="pm-action" href="pm_member_center_v17.php">會員中心</a>
      <label class="pm-action pm-globe pm-menu-label" for="pm-menu-v21" title="官網導覽">🌐</label>
    </div>
  </div>
  <div class="pm-subnav">
    <a href="https://seasideresort.com.tw/#about">認識莊園</a><a href="https://seasideresort.com.tw/#story">莊園故事</a>
    <a href="https://seasideresort.com.tw/#rooms">觀海套房</a><a href="https://seasideresort.com.tw/#morning">住民生活</a>
    <a href="https://seasideresort.com.tw/#wansui">萬歲咖啡</a><a href="https://seasideresort.com.tw/#life">安心生活</a>
    <a href="https://seasideresort.com.tw/#longstay">長住方案</a><a href="https://seasideresort.com.tw/#experience">預約體驗</a>
    <a href="https://seasideresort.com.tw/#news">最新消息</a><a href="https://seasideresort.com.tw/#nearby">附近景點</a>
    <a href="https://seasideresort.com.tw/#transport">交通方式</a><a href="https://seasideresort.com.tw/#faq">常見問題</a>
  </div>
</div>
<div class="page-titlebar"><h1>會員與入住申請</h1><p>登入或建立會員，入住資料可稍後補齊。</p></div>
<div id="guestWrap" class="login-shell">
<main>
<div id="status" class="loading">正在連線會員系統…</div>

<section id="guestView" class="hidden" aria-label="登入或註冊">
  <div class="login-card" id="loginCard">
    <p class="login-kicker">太平洋莊園 · 會員</p>
    <h1 class="login-title" id="loginTitle">登入或註冊</h1>
    <p class="login-lead" id="loginLead">用手機簡訊最快；也可 Google、LINE 或 Email。不用記密碼，選一種方式即可。</p>
    <p id="loginMessage" class="login-hint" hidden role="status" aria-live="polite"></p>

    <form id="phoneRequest" class="login-stack">
      <label for="phoneNumber" style="font-size:18px;margin:0">手機號碼</label>
      <input id="phoneNumber" class="login-field" name="phone" type="tel" inputmode="tel" autocomplete="tel" maxlength="24" placeholder="0912-345-678" required>
      <button class="login-primary" type="submit">繼續，送出簡訊驗證碼</button>
      <button id="switchToEmail" class="login-text-link" type="button">改用電子郵件驗證碼</button>
    </form>

    <form id="phoneVerify" class="login-stack hidden">
      <p id="phoneDestination" class="login-lead" style="margin:0"></p>
      <label for="phoneCode" style="font-size:18px;margin:0">6 位數驗證碼</label>
      <input id="phoneCode" class="login-field login-code" name="code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" required placeholder="000000">
      <p class="small">請輸入手機簡訊中的驗證碼。正式路徑不使用預覽假碼。</p>
      <button class="login-primary" type="submit">驗證並繼續</button>
      <div style="display:flex;flex-wrap:wrap;gap:16px">
        <button id="phoneEdit" class="login-text-link" type="button">更改手機號碼</button>
        <button id="phoneResend" class="login-text-link" type="button">重新寄送驗證碼</button>
      </div>
    </form>

    <form id="emailCodeRequest" class="login-stack hidden">
      <label for="emailCodeAddress" style="font-size:18px;margin:0">電子郵件</label>
      <input id="emailCodeAddress" class="login-field" name="email" type="email" autocomplete="email" inputmode="email" maxlength="190" placeholder="you@example.com" required>
      <button class="login-primary" id="emailCodeSend" type="submit">繼續，寄送驗證碼</button>
      <button id="switchToPhone" class="login-text-link" type="button">改回手機簡訊</button>
    </form>

    <form id="emailCodeVerify" class="login-stack hidden">
      <p id="emailCodeDestination" class="login-lead" style="margin:0"></p>
      <label for="emailCodeValue" style="font-size:18px;margin:0">6 位數驗證碼</label>
      <input id="emailCodeValue" class="login-field login-code" name="code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" required>
      <p id="emailCodeExpiry" class="small">驗證碼 10 分鐘內有效。系統不會自動寄信。</p>
      <button class="login-primary" type="submit">驗證並繼續</button>
      <div style="display:flex;flex-wrap:wrap;gap:16px">
        <button id="emailCodeEdit" class="login-text-link" type="button">更改 Email</button>
        <button id="emailCodeResend" class="login-text-link" type="button">重新寄送驗證碼</button>
      </div>
    </form>

    <form id="emailCodeComplete" class="login-stack hidden">
      <div id="emailSignupStep" class="hidden">
        <label class="login-consent"><input id="emailCodeConsent" type="checkbox"><span>我已閱讀並同意建立會員。</span></label>
      </div>
      <div id="emailLegacyStep" class="hidden">
        <label for="emailLegacyPassword" style="font-size:18px">原有密碼</label>
        <input id="emailLegacyPassword" class="login-field" type="password" autocomplete="current-password">
        <p class="small">此帳戶尚未完成信箱驗證，請使用原有密碼確認身分。</p>
      </div>
      <button id="emailCompleteSubmit" class="login-primary" type="submit">繼續</button>
      <button id="emailCompleteBack" class="login-text-link" type="button">返回輸入驗證碼</button>
    </form>

    <div class="login-or" role="separator"><span>或使用</span></div>
    <div class="social-row" id="socialRow">
      <button type="button" class="login-google" data-provider="google" disabled>
        <svg class="brand-ico" viewBox="0 0 24 24" aria-hidden="true"><path fill="#4285F4" d="M23.5 12.3c0-.8-.1-1.6-.2-2.3H12v4.4h6.4c-.3 1.5-1.2 2.8-2.5 3.6v3h4c2.4-2.2 3.6-5.4 3.6-8.7z"/><path fill="#34A853" d="M12 24c3.2 0 6-1.1 8-2.9l-4-3c-1.1.8-2.5 1.2-4 1.2-3.1 0-5.7-2.1-6.6-4.9H1.3v3.1C3.3 21.3 7.3 24 12 24z"/><path fill="#FBBC05" d="M5.4 14.4c-.2-.7-.4-1.4-.4-2.4s.1-1.7.4-2.4V6.5H1.3C.5 8.2 0 10 0 12s.5 3.8 1.3 5.5l4.1-3.1z"/><path fill="#EA4335" d="M12 4.8c1.8 0 3.3.6 4.6 1.8l3.4-3.4C17.9 1.2 15.2 0 12 0 7.3 0 3.3 2.7 1.3 6.5l4.1 3.1C6.3 6.8 8.9 4.8 12 4.8z"/></svg>
        <span data-label>使用 Google 繼續</span>
      </button>
      <button type="button" class="login-line" data-provider="line" disabled>
        <svg class="brand-ico" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M20.1 3.2C18.2 1.8 15.4 1 12.4 1 6.3 1 1.3 5.1 1.3 10.1c0 4.5 4 8.3 9.4 8.3.3 0 .7 0 1-.1l2.3 1.5c.2.1.4 0 .4-.2l-.5-2c3.9-1.1 6.6-4.2 6.6-7.8 0-2.1-1-4-2.4-5.6z"/></svg>
        <span data-label>使用 LINE 繼續</span>
      </button>
      <button type="button" class="login-apple login-soon" data-provider="apple" disabled>
        <svg class="brand-ico" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M16.4 12.7c0-2.3 1.9-3.4 2-3.5-1.1-1.6-2.8-1.8-3.4-1.8-1.4-.2-2.8.9-3.5.9s-1.8-.8-3-.8c-1.5 0-2.9.9-3.7 2.3-1.6 2.7-.4 6.8 1.1 9 .8 1.1 1.7 2.3 2.9 2.2 1.2 0 1.6-.7 3-.7s1.8.7 3 .7 2-1.1 2.8-2.2c.9-1.3 1.2-2.5 1.3-2.6-.1 0-2.5-1-2.5-3.5zM14.8 5.8c.6-.8 1.1-1.8.9-2.9-1 .1-2.1.6-2.8 1.4-.6.7-1.2 1.8-1 2.8 1.1.1 2.2-.5 2.9-1.3z"/></svg>
        <span data-label data-soon-label="使用 Apple 繼續">使用 Apple 繼續 · 即將開放</span>
      </button>
    </div>
    <p id="socialMessage" class="small" role="status"></p>

    <label class="login-consent">
      <input id="loginConsent" type="checkbox">
      <span>我已閱讀並同意
        <button type="button" id="noticeToggle" class="login-text-link" style="display:inline;margin:0">《會員資料使用說明》</button>
      </span>
    </label>
    <p id="consentHint" class="login-hint" hidden></p>
    <p id="noticeBox" class="login-hint" hidden>本站保存驗證後的 Email、手機與驗證紀錄，用於登入與會員服務。第三方登入不接收對方密碼。簽約與入住資料可稍後補齊。登入後可至會員中心綁定其他方式。</p>

    <button id="showPassword" class="login-text-link" type="button" style="margin-top:22px">使用密碼登入</button>
  </div>

  <div class="login-card hidden" id="passwordLogin">
    <p class="login-kicker">太平洋莊園 · 會員</p>
    <h1 class="login-title">使用密碼登入</h1>
    <p class="login-lead">歡迎回來，請輸入您的會員帳號。</p>
    <p id="resetLoginNotice" class="ok hidden" role="status">密碼已重設，請使用新密碼登入</p>
    <form id="loginForm" class="login-stack">
      <label for="loginIdentifier" style="font-size:18px;margin:0">電話號碼或 Email</label>
      <input id="loginIdentifier" class="login-field" name="identifier" type="text" autocomplete="username" maxlength="190" required>
      <label for="loginPassword" style="font-size:18px;margin:0">密碼</label>
      <input id="loginPassword" class="login-field" name="password" type="password" autocomplete="current-password" required>
      <p style="margin:0;text-align:right"><a href="pm_member_forgot_v17.php" style="color:#2E5E73;font-weight:700">忘記密碼？</a></p>
      <p id="loginFormMessage" class="login-hint" hidden role="status"></p>
      <button class="login-primary" type="submit">登入</button>
    </form>
    <button id="backToOtp" class="login-text-link" type="button">返回簡訊／Email 登入</button>
  </div>
</section>
</main>
</div>

<main>
<section id="memberView" class="hidden">
  <div class="card"><h2 id="hello">會員您好</h2><div id="memberState" class="note"></div><a href="pm_member_center_v17.php" style="display:inline-block;margin:8px 0;color:#2E5E73;font-weight:800">進入會員中心 →</a><button id="logoutBtn" class="secondary">登出</button></div>
  <div class="card" id="phoneCard">
    <h2>驗證我的手機</h2>
    <p class="small">已登入會員可在此補綁台灣手機（與登入簡訊同一套 SMS Go）。</p>
    <p id="phoneSummary" class="note" aria-live="polite"></p>
    <div id="phoneVerification">
      <section aria-labelledby="phone-title" class="phone-verification">
        <h2 id="phone-title">驗證我的手機</h2>
        <p>請使用自己的台灣手機號碼。每次按下寄送才會要求簡訊，系統不會自動重寄。</p>
        <label>手機號碼<input name="phone" type="tel" inputmode="tel" autocomplete="tel" required maxlength="24" placeholder="09 開頭的十位數字"></label>
        <button type="button" data-send>寄送手機驗證碼</button>
        <label>簡訊中的六位數字<input name="code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" required></label>
        <p>驗證碼十分鐘內有效，請勿提供給他人。</p>
        <button type="button" data-verify disabled>驗證手機號碼</button>
        <button type="button" data-query hidden>查詢這封簡訊的發送狀態</button>
        <p role="status" aria-live="polite"></p>
      </section>
    </div>
  </div>
  <div class="card">
    <h2>提出入住申請</h2>
    <div id="applyNote" class="note"></div>
    <form id="applyForm">
      <div class="grid">
        <div id="planWrap"><label>居住方案</label><select id="planSelect" name="plan"><option value="monthly">月住</option><option value="seasonal">季租</option><option value="yearly">年租</option></select></div>
        <div id="monthlyModeWrap"><label>月住方式</label>
          <select id="monthlyMode" name="monthly_mode">
            <option value="continuous">連續月住</option>
            <option value="flex_monthly">彈性月住</option>
          </select>
        </div>
        <div><label>希望房型</label><select id="roomType" name="room_type_name"><option value="海景房">海景房</option></select></div>
        <div><label>預計入住日期<span class="req">＊</span></label><input id="stayStart" type="date" name="stay_start" required></div>
        <div id="endWrap"><label>預計入住至<span id="stayEndReq" class="req">＊</span></label><input id="stayEnd" type="date" name="stay_end"></div>
        <div><label>入住人數</label><input type="number" name="guest_count" min="1" value="1"></div>
      </div>
      <div id="trialPeriod" class="small hidden">體驗方案可自行選擇入住期間；30 天以上請改用正式會員月住。</div>
      <div id="monthlyModeNote" class="small hidden"></div>
      <label>其他入住需求</label><textarea name="request_note"></textarea>
      <button type="submit">送出入住申請</button>
    </form>
  </div>
  <div class="card"><h2>我的入住申請</h2><div id="apps"></div></div>
</section>
</main>

<script src="pm_member_client_v18.js"></script>
<script src="pm_member_entry_v18.js"></script>
<script>
const API='pm_member_api_v18.php';
let phoneMountKey='', phoneTemplate=null;
async function connectPhone(j){
  try{
    const box=document.getElementById('phoneVerification');
    if(!box)return;
    if(phoneTemplate===null)phoneTemplate=box.innerHTML;
    const key=(j.csrf_token||'')+'|'+(j.member&&j.member.id_member?j.member.id_member:'');
    if(phoneMountKey!==key){
      try{
        const {mountPhoneVerification}=await import('./pm_phone_verify.js');
        box.innerHTML=phoneTemplate;
        const root=box.querySelector('.phone-verification');
        if(root){
          mountPhoneVerification(root,API,j.csrf_token);
          root.addEventListener('phoneverified',()=>refresh().catch(x=>showStatus('err',x.message)));
        }
      }catch(_){ /* preview without live bind widget */ }
      phoneMountKey=key;
    }
    const r=await fetch(API,{method:'POST',credentials:'same-origin',cache:'no-store',headers:{'Content-Type':'application/json','X-PM-CSRF':j.csrf_token},body:JSON.stringify({action:'phone_status'})});
    const s=await r.json();
    const sum=document.getElementById('phoneSummary');
    if(sum)sum.textContent=(s.code==='status')?(s.verified?'手機已完成驗證。':'手機尚未完成驗證。'):(s.message||s.error||'手機驗證服務暫時無法連線。');
  }catch(e){
    const sum=document.getElementById('phoneSummary');
    if(sum)sum.textContent='手機驗證服務暫時無法連線。';
  }
}
const statusBox=document.getElementById('status'), guestView=document.getElementById('guestView'), memberView=document.getElementById('memberView');
function esc(s){return String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));}
function showStatus(type,msg){statusBox.className=type;statusBox.textContent=msg;statusBox.classList.remove('hidden');}
async function call(action,data={}){
  const fd=new FormData(); fd.append('action',action); Object.entries(data).forEach(([k,v])=>fd.append(k,v??''));
  const r=await pmMemberFetch(API,{method:'POST',body:fd,credentials:'same-origin',cache:'no-store'});
  let j; try{j=await r.json();}catch(e){throw new Error('伺服器沒有回傳正確資料（HTTP '+r.status+'）');}
  if(!j.ok) throw new Error(j.error||'操作失敗'); return j;
}
function formDataObj(form){return Object.fromEntries(new FormData(form).entries());}
function memberStateText(s){return ({pending:'資料確認中',approved:'會員資料已確認',rejected:'目前無法接受'}[s]||s||'');}
function appStateText(s){return ({trial_auto_pending:'系統安排住房中',pending_review:'申請處理中',need_info:'需要補充資料',approved_waiting_room:'申請已接受／安排住房中',rejected:'目前無法安排',cancelled:'已取消',confirmed:'預約成功'}[s]||s||'');}
async function refresh(){
  try{
    const j=await call('status');
    statusBox.classList.add('hidden');
    if(!j.installed){showStatus('warn','會員資料庫尚未初始化，請管理員先執行一次安裝。');guestView.classList.add('hidden');memberView.classList.add('hidden');return;}
    if(!j.logged_in){guestView.classList.remove('hidden');memberView.classList.add('hidden');document.body.classList.add('pm-guest');document.getElementById('guestWrap').classList.remove('hidden');return;}
    guestView.classList.add('hidden');memberView.classList.remove('hidden');document.body.classList.remove('pm-guest');document.getElementById('guestWrap').classList.add('hidden');
    document.getElementById('hello').textContent=(j.member.name||'會員')+'，您好';
    void connectPhone(j);
    const level=j.member.member_level||'trial';window.__memberLevel=level;
    const mmw=document.getElementById('monthlyModeWrap');
    if(mmw)mmw.classList.toggle('hidden',level!=='formal');
    const levelLabel=level==='formal'?'正式會員':'體驗會員';
    document.getElementById('memberState').innerHTML='會員類型：<span class="state">'+levelLabel+'</span><br>資料狀態：<span class="state">'+esc(memberStateText(j.member.qualification_state))+'</span>';
    const planWrap=document.getElementById('planWrap'),endWrap=document.getElementById('endWrap'),trialPeriod=document.getElementById('trialPeriod'),applyNote=document.getElementById('applyNote'),stayEnd=document.getElementById('stayEnd');
    if(level==='trial'){
      planWrap.classList.add('hidden');document.getElementById('monthlyModeWrap')?.classList.add('hidden');document.getElementById('monthlyModeNote')?.classList.add('hidden');endWrap.classList.remove('hidden');trialPeriod.classList.remove('hidden');stayEnd.required=true;document.getElementById('stayEndReq')?.classList.remove('hidden');
      applyNote.innerHTML='<b>體驗會員：</b>可自行選擇體驗期間。系統確認整段期間有可用住房後會自動安排；30 天以上請改用正式會員月住。';
    }else{
      planWrap.classList.remove('hidden');endWrap.classList.remove('hidden');trialPeriod.classList.add('hidden');stayEnd.required=true;document.getElementById('stayEndReq')?.classList.remove('hidden');
      applyNote.innerHTML='<b>正式會員：</b>可申請月住、季租或年租。送出後由工作人員接受申請，再進待排房；真正排到房號後才是「預約成功」。';
    }
    const sel=document.getElementById('roomType'); sel.innerHTML=''; (j.rooms&&j.rooms.length?j.rooms:['海景房']).forEach(x=>{const o=document.createElement('option');o.value=x;o.textContent=x;sel.appendChild(o)});
    document.getElementById('apps').innerHTML=(j.applications&&j.applications.length)?j.applications.map(a=>'<div class="app"><b>'+esc(a.application_no)+'</b><br><span style="display:inline-block;margin:6px 0;padding:5px 10px;border-radius:999px;background:#eef7f5;font-weight:900">'+esc(appStateText(a.application_state))+'</span><br>'+esc(a.stay_start)+' ～ '+esc(a.stay_end)+'｜'+esc(a.room_type_name)+'｜'+esc(({experience:'體驗方案',monthly:'月住',seasonal:'季租',yearly:'年租'}[a.plan]||a.plan))+'</div>').join(''):'<p class="small">目前沒有申請。</p>';
  }catch(e){showStatus('err','會員系統連線失敗：'+e.message+'。');guestView.classList.add('hidden');memberView.classList.add('hidden');}
}
document.getElementById('loginForm').addEventListener('submit',async e=>{
  e.preventDefault();
  const form=e.currentTarget;if(form.dataset.busy==='1')return;form.dataset.busy='1';
  const box=document.getElementById('loginFormMessage');box.hidden=true;box.textContent='';
  const submit=form.querySelector('button[type="submit"]');const label=submit?.textContent;if(submit){submit.disabled=true;submit.textContent='處理中…';}
  try{await call('login',formDataObj(form));await refresh();}
  catch(x){box.hidden=false;box.dataset.error='true';box.textContent=chineseError(x.message);}
  finally{delete form.dataset.busy;if(submit){submit.disabled=false;submit.textContent=label;}}
});
document.getElementById('applyForm').addEventListener('submit',async e=>{
  e.preventDefault();
  try{const j=await call('apply',formDataObj(e.currentTarget));alert(j.message||'完成');await refresh();}
  catch(x){alert(chineseError(x.message));}
});
document.getElementById('logoutBtn').onclick=async()=>{try{
  await call('logout');
  location.replace('pm_member_portal_v18.php?logout=1&t='+Date.now());
}catch(e){alert(e.message)}};
refresh();
function chineseError(msg){
  msg=String(msg||'');
  const rules=[
    [/Unknown column/i,'系統資料尚未更新完整，請聯絡管理員。'],
    [/Duplicate entry/i,'這個 Email 或會員資料已經註冊過，請直接登入或更換資料。'],
    [/SQLSTATE|SQL syntax|MariaDB|MySQL/i,'系統資料處理發生問題，請聯絡管理員。'],
    [/Failed to fetch|NetworkError|Load failed/i,'目前無法連線到系統，請稍後再試。'],
    [/Internal Server Error|HTTP 500/i,'系統暫時發生錯誤，請稍後再試。']
  ];
  for(const [re,text] of rules)if(re.test(msg))return text;
  return msg||'這次沒有完成，請再試一次，或改用另一種方式。';
}
</script>
</body>
</html>
