/* Guest-safe OTP / API error copy. Include on consumer member pages. */
(function (root) {
  var FALLBACK = "暫時無法寄送驗證碼，請稍後再試或改用其他方式";
  var LEAK = [
    /pm_member_private/i,
    /\/home\/[^\s"'<>]+/,
    /smsgo-api-key/i,
    /SMSGO_[A-Z0-9_]+/,
    /簡訊金鑰/,
    /唯一索引/,
    /尚未準備完成/,
    /Call to undefined/i,
    /undefined function/i,
    /runtime\.php/i,
    /API Key/i,
    /adapter/i,
    /QloApps|QoApps/i,
    /允許清單/,
    /NCC 署名/,
    /請管理員/,
    /尚未初始化/,
    /尚未設定/,
    /qlo_pm_member/i
  ];
  var SAFE = [
    "請輸入有效",
    "請先勾選",
    "請先寄送",
    "請先送出",
    "請先同意",
    "驗證碼不正確",
    "驗證碼已過期",
    "驗證碼已失效",
    "秒後可再寄送",
    "這個手機號碼已綁定",
    "頁面驗證已失效",
    "首次建立會員",
    "請閱讀並同意",
    "嘗試次數過多"
  ];

  function leaky(msg) {
    if (!msg) return false;
    for (var i = 0; i < LEAK.length; i++) if (LEAK[i].test(msg)) return true;
    return /(?:\/[A-Za-z0-9._-]+){2,}\.(?:php|txt|js|inc)\b/i.test(msg);
  }

  function safeUser(msg) {
    for (var i = 0; i < SAFE.length; i++) if (msg.indexOf(SAFE[i]) !== -1) return true;
    return false;
  }

  function guestSafeError(raw, fallback) {
    var msg = String(raw == null ? "" : raw).trim();
    var fb = fallback || FALLBACK;
    if (!msg) return fb;
    if (safeUser(msg) && !leaky(msg)) return msg;
    if (leaky(msg)) return fb;
    return msg;
  }

  function sanitizePayload(payload) {
    if (!payload || typeof payload !== "object") return payload;
    if (typeof payload.error === "string") payload.error = guestSafeError(payload.error);
    if (typeof payload.message === "string") payload.message = guestSafeError(payload.message, payload.message);
    delete payload.gap;
    delete payload.missing;
    delete payload.blockedGates;
    delete payload.requiredEnv;
    return payload;
  }

  root.pmGuestSafeError = guestSafeError;
  root.pmGuestSanitizePayload = sanitizePayload;
  root.PM_GUEST_OTP_UNAVAILABLE = FALLBACK;
})(typeof window !== "undefined" ? window : globalThis);
