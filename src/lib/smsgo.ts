import { randomInt } from "crypto";

/** 對齊正式站 pm_smsgo_adapter.php：POST /sms_gw/sendsms.aspx */
export const SMSGO_SEND_URL = "https://www.smsgo.com.tw/sms_gw/sendsms.aspx";
export const SMSGO_QUERY_URL = "https://www.smsgo.com.tw/sms_gw/query.aspx";

export const SMSGO_REQUIRED_ENV = ["SMSGO_USERNAME", "SMSGO_API_KEY"] as const;
export const SMSGO_OPTIONAL_ENV = [
  "SMSGO_PASSWORD",
  "SMSGO_ENABLED",
  "SMSGO_CONTROLLED_TEST",
  "SMSGO_ALLOWED_PHONES",
  "SMSGO_APPROVED_TEMPLATE",
  "SMSGO_ACCOUNT_VERIFIED",
  "SMSGO_CONTRACT_VERIFIED",
] as const;

/** 正式 runtime.php 已核可的 OTP 文案（不是密鑰） */
export const SMSGO_OFFICIAL_TEMPLATE = "太平洋莊園手機驗證碼：{code}，10分鐘內有效，請勿提供他人。";

export type SmsGoSendState = "accepted" | "rejected" | "unknown";

export type SmsGoParseResult = {
  state: SmsGoSendState;
  message_id: string | null;
  statuscode: number;
  statusstr: string;
};

export type SmsGoConfig = {
  username: string;
  apiKey: string;
  enabled: boolean;
  accountVerified: boolean;
  contractVerified: boolean;
  controlledTest: boolean;
  allowedPhones: string[];
  template: string;
  configured: boolean;
  authorizedToSend: boolean;
  missing: string[];
  blockedGates: string[];
};

type SmsGoFetch = typeof fetch;

export class SmsGoError extends Error {
  statuscode: number;
  constructor(message: string, statuscode: number) {
    super(message);
    this.name = "SmsGoError";
    this.statuscode = statuscode;
  }
}

function envFlag(name: string, fallback: boolean) {
  const raw = process.env[name]?.trim().toLowerCase();
  if (!raw) return fallback;
  return raw === "1" || raw === "true" || raw === "yes";
}

export function toE164TwMobile(input: string): string | null {
  const digits = input.replace(/\D/g, "");
  if (digits.startsWith("886") && /^8869\d{8}$/.test(digits)) return `+${digits}`;
  if (/^09\d{8}$/.test(digits)) return `+886${digits.slice(1)}`;
  if (/^\+8869\d{8}$/.test(input.trim())) return input.trim();
  return null;
}

export function toSmsGoDstaddr(e164: string) {
  return `0${e164.slice(4)}`;
}

export function generateSmsGoOtp() {
  return String(randomInt(0, 1_000_000)).padStart(6, "0");
}

export function smsGoConfig(): SmsGoConfig {
  const username = process.env.SMSGO_USERNAME?.trim() || "";
  const apiKey = process.env.SMSGO_API_KEY?.trim() || process.env.SMSGO_PASSWORD?.trim() || "";
  const template = process.env.SMSGO_APPROVED_TEMPLATE?.trim() || SMSGO_OFFICIAL_TEMPLATE;
  const allowedPhones = (process.env.SMSGO_ALLOWED_PHONES || "")
    .split(",")
    .map((item) => toE164TwMobile(item.trim()))
    .filter((item): item is string => Boolean(item));
  const missing: string[] = [];
  if (!username) missing.push("SMSGO_USERNAME");
  if (!process.env.SMSGO_API_KEY?.trim() && !process.env.SMSGO_PASSWORD?.trim()) missing.push("SMSGO_API_KEY");

  const enabled = envFlag("SMSGO_ENABLED", false);
  const accountVerified = envFlag("SMSGO_ACCOUNT_VERIFIED", true);
  const contractVerified = envFlag("SMSGO_CONTRACT_VERIFIED", true);
  const controlledTest = envFlag("SMSGO_CONTROLLED_TEST", false);
  const blockedGates: string[] = [];
  if (!enabled) blockedGates.push("SMSGO_ENABLED");
  if (!accountVerified) blockedGates.push("SMSGO_ACCOUNT_VERIFIED");
  if (!contractVerified) blockedGates.push("SMSGO_CONTRACT_VERIFIED");
  if (!controlledTest) blockedGates.push("SMSGO_CONTROLLED_TEST");
  if (allowedPhones.length === 0) blockedGates.push("SMSGO_ALLOWED_PHONES");

  const configured = Boolean(username && apiKey);
  const templateOk = (template.match(/\{code\}/g) || []).length === 1;
  if (!templateOk) blockedGates.push("SMSGO_APPROVED_TEMPLATE");

  return {
    username,
    apiKey,
    enabled,
    accountVerified,
    contractVerified,
    controlledTest,
    allowedPhones,
    template,
    configured,
    authorizedToSend: configured && blockedGates.length === 0 && templateOk,
    missing,
    blockedGates,
  };
}

export function smsGoPublicStatus() {
  const config = smsGoConfig();
  return {
    available: config.authorizedToSend,
    configured: config.configured,
    otpLength: 6 as const,
  };
}

export function parseSmsGoSendResponse(http: number, body: string): SmsGoParseResult {
  const unknown: SmsGoParseResult = { state: "unknown", message_id: null, statuscode: -10, statusstr: "" };
  if (http !== 200 || body.length > 32768) return unknown;
  let node: Record<string, unknown> | null = null;
  const text = body.trim();
  if (text.startsWith("{")) {
    try {
      const parsed = JSON.parse(text) as { result?: Record<string, unknown> };
      node = parsed.result && typeof parsed.result === "object" ? parsed.result : (parsed as Record<string, unknown>);
    } catch {
      node = null;
    }
  }
  if (!node) {
    const fields: Record<string, string> = {};
    for (const line of text.split(/[\r\n&]+/)) {
      const index = line.indexOf("=");
      if (index < 1) continue;
      fields[line.slice(0, index).trim().toLowerCase()] = line.slice(index + 1).trim();
    }
    if (Object.keys(fields).length) node = fields;
  }
  if (!node) return { ...unknown, statusstr: text.slice(0, 160) };
  const code = String(node.statuscode ?? "");
  const id = String(node.msgid ?? "").trim();
  const messageId = /^[0-9]{1,96}$/.test(id) ? id : null;
  const statuscode = Number(code);
  const statusstr = String(node.statusstr ?? "").trim();
  if (code === "0" && messageId) return { state: "accepted", message_id: messageId, statuscode: 0, statusstr };
  const rejected = new Set([
    "-1", "-2", "-3", "-5", "-8", "-9", "-10", "-11", "-12", "-13", "-14",
    "-15", "-16", "-17", "-18", "-19", "-20", "-21", "-22", "-23", "-24", "-25", "-30",
  ]);
  if (rejected.has(code)) return { state: "rejected", message_id: null, statuscode: Number.isFinite(statuscode) ? statuscode : -10, statusstr };
  return { state: "unknown", message_id: messageId, statuscode: Number.isFinite(statuscode) ? statuscode : -10, statusstr };
}

export function smsGoUserMessage(statuscode: number, fallback = "暫時無法寄送驗證碼，請稍後再試或改用其他方式") {
  const messages: Record<number, string> = {
    0: "成功",
    [-5]: "請輸入台灣手機號碼，例如 09 開頭的十位數字。",
    [-21]: "已達發送上限，請稍後再試。",
  };
  return messages[statuscode] || fallback;
}

function authorizedPhone(config: SmsGoConfig, e164: string) {
  return (
    config.authorizedToSend &&
    config.allowedPhones.includes(e164) &&
    Boolean(config.username && config.apiKey)
  );
}

export async function sendSmsGoOtp(
  localPhone: string,
  code: string,
  fetchImpl: SmsGoFetch = fetch,
): Promise<{ messageId: string; state: SmsGoSendState }> {
  const config = smsGoConfig();
  if (!config.configured) {
    // Operator setup is documented in deploy/OPERATOR_OTP.md — never tell guests.
    throw new SmsGoError("暫時無法寄送驗證碼，請稍後再試或改用其他方式", -3);
  }
  if (!config.authorizedToSend) {
    throw new SmsGoError("暫時無法寄送驗證碼，請稍後再試或改用其他方式", -16);
  }
  const e164 = toE164TwMobile(localPhone);
  if (!e164) throw new SmsGoError("請輸入台灣手機號碼，例如 09 開頭的十位數字。", -5);
  if (!authorizedPhone(config, e164)) {
    throw new SmsGoError("暫時無法寄送驗證碼，請稍後再試或改用其他方式", -5);
  }
  if (!/^\d{6}$/.test(code)) throw new SmsGoError("驗證碼格式不正確。", -1);
  if ((config.template.match(/\{code\}/g) || []).length !== 1) {
    throw new SmsGoError("暫時無法寄送驗證碼，請稍後再試或改用其他方式", -1);
  }
  const smbody = config.template.replace("{code}", code);
  if ([...smbody].length > 70) throw new SmsGoError("簡訊內容超過 70 字。", -1);

  const body = new URLSearchParams({
    username: config.username,
    password: config.apiKey,
    dstaddr: toSmsGoDstaddr(e164),
    smbody,
    encoding: "BIG5",
    rtype: "JSON",
  });
  const response = await fetchImpl(SMSGO_SEND_URL, {
    method: "POST",
    headers: { "Content-Type": "application/x-www-form-urlencoded" },
    body,
    cache: "no-store",
  });
  const text = await response.text();
  const parsed = parseSmsGoSendResponse(response.status, text);
  if (parsed.state === "accepted" && parsed.message_id) {
    return { messageId: parsed.message_id, state: "accepted" };
  }
  throw new SmsGoError(smsGoUserMessage(parsed.statuscode, "簡訊服務未接受請求，請稍後再試。"), parsed.statuscode);
}
