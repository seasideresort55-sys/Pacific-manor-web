/** Guest-facing copy when SMS / email OTP backends are not ready. */
export const GUEST_OTP_UNAVAILABLE =
  "暫時無法寄送驗證碼，請稍後再試或改用其他方式";

export const GUEST_SERVICE_UNAVAILABLE = "會員服務暫時無法使用，請稍後再試。";

const LEAK_PATTERNS: RegExp[] = [
  /pm_member_private/i,
  /\/home\/[^\s"'<>]+/,
  /smsgo-api-key/i,
  /SMSGO_[A-Z0-9_]+/,
  /MEMBER_API_[A-Z0-9_]+/,
  /簡訊金鑰/,
  /唯一索引/,
  /尚未準備完成/,
  /Call to undefined/i,
  /undefined function/i,
  /runtime\.php/i,
  /\.inc\.php/i,
  /API Key/i,
  /adapter/i,
  /QloApps|QoApps/i,
  /允許清單/,
  /NCC 署名/,
  /請管理員/,
  /尚未初始化/,
  /尚未設定/,
  /enabled\s*=/i,
  /controlled_test/i,
  /qlo_pm_member/i,
  /gap['"]?\s*[:=]/i,
];

const SAFE_SUBSTRINGS = [
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
  "請使用自己的台灣手機",
  "嘗試次數過多",
];

function looksLikeHostPath(text: string): boolean {
  return /(?:^|[\s"'=(])(?:\/[A-Za-z0-9._-]+){2,}\.(?:php|txt|js|inc)\b/i.test(text);
}

export function isLeakyGuestError(raw: string): boolean {
  const msg = String(raw || "");
  if (!msg.trim()) return false;
  if (LEAK_PATTERNS.some((re) => re.test(msg))) return true;
  return looksLikeHostPath(msg);
}

export function guestSafeError(
  raw: unknown,
  fallback: string = GUEST_OTP_UNAVAILABLE,
): string {
  const msg = String(raw ?? "").trim();
  if (!msg) return fallback;
  const isSafeUserInput = SAFE_SUBSTRINGS.some((part) => msg.includes(part));
  if (isSafeUserInput && !isLeakyGuestError(msg)) return msg;
  if (isLeakyGuestError(msg)) return fallback;
  return msg;
}

export function publicSmsStatus(input: {
  configured: boolean;
  authorizedToSend: boolean;
  otpLength?: number;
}) {
  return {
    available: Boolean(input.authorizedToSend),
    configured: Boolean(input.configured),
    otpLength: input.otpLength ?? 6,
  };
}
