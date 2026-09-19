/* Unify logged-out member center with portal OTP login. */
(function () {
  function ready(fn) {
    if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", fn);
    else fn();
  }

  ready(function () {
    var portal = "pm_member_portal_v17.php";
    var bind = document.querySelector('a[href*="socialLogin"], a[href*="pm_member_portal_v17.php#socialLogin"]');
    if (bind && bind.parentElement && bind.parentElement.tagName === "P") {
      bind.parentElement.style.display = "none";
    }

    var login = document.getElementById("login");
    if (!login) return;
    login.innerHTML =
      '<h2>登入或註冊</h2>' +
      '<p>請使用與會員入口相同的方式：手機簡訊或電子郵件驗證碼。也可在下一頁使用 Google、LINE 或 Apple。</p>' +
      '<p><a class="pm-center-otp" href="' + portal + '">前往驗證碼登入／註冊</a></p>' +
      '<p class="small">若您已設定密碼，也可在登入頁改用密碼。</p>';

    var style = document.createElement("style");
    style.textContent =
      "#login .pm-center-otp{display:inline-flex;min-height:48px;align-items:center;padding:0 18px;border-radius:12px;background:#2E5E73;color:#fff;text-decoration:none;font-weight:800}" +
      "#login .small{color:#5a6f78;line-height:1.6}";
    document.head.appendChild(style);
  });
})();
