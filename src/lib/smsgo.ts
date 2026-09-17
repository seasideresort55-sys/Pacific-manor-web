export const SMSGO_SEND_URL = "https://www.smsgo.com.tw/sms_gw/verify.aspx";
export const SMSGO_ACK_URL = "https://www.smsgo.com.tw/sms_gw/verifyAck.aspx";

export const SMSGO_REQUIRED_ENV = ["SMSGO_USERNAME", "SMSGO_API_KEY"] as const;
export const SMSGO_OPTIONAL_ENV = [
  "SMSGO_PASSWORD",
  "SMSGO_OTP_LENGTH",
  "SMSGO_SENDER_NAME",
  "SMSGO_SIGNATURE",
] as const;

export type SmsGoResult = {
  msgid: string;
  statuscode: number;
  statusstr: string;
  point: number;
};

export type SmsGoConfig = {
  username: string;
  apiKey: string;
  otpLength: 4 | 6;
  senderName: string;
  signature: string;
  configured: boolean;
  missing: string[];
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

export function smsGoConfig(): SmsGoConfig {
  const username = process.env.SMSGO_USERNAME?.trim() || "";
  const apiKey = process.env.SMSGO_API_KEY?.trim() || process.env.SMSGO_PASSWORD?.trim() || "";
  const rawLength = process.env.SMSGO_OTP_LENGTH?.trim();
  const otpLength: 4 | 6 = rawLength === "4" ? 4 : 6;
  const missing: string[] = [];
  if (!username) missing.push("SMSGO_USERNAME");
  if (!process.env.SMSGO_API_KEY?.trim() && !process.env.SMSGO_PASSWORD?.trim()) {
    missing.push("SMSGO_API_KEY");
  }
  return {
    username,
    apiKey,
    otpLength,
    senderName: process.env.SMSGO_SENDER_NAME?.trim() || "",
    signature: process.env.SMSGO_SIGNATURE?.trim() || "",
    configured: Boolean(username && apiKey),
    missing,
  };
}

export function smsGoPublicStatus() {
  const config = smsGoConfig();
  return {
    wired: true,
    provider: "smsgo" as const,
    configured: config.configured,
    otpLength: config.otpLength,
    missing: config.missing,
    requiredEnv: [...SMSGO_REQUIRED_ENV],
    optionalEnv: [...SMSGO_OPTIONAL_ENV],
  };
}

export function parseSmsGoResponse(body: string): SmsGoResult {
  const text = body.trim();
  if (!text) {
    return { msgid: "", statuscode: -10, statusstr: "empty response", point: 0 };
  }

  if (text.startsWith("{") || text.startsWith("[")) {
    try {
      const parsed = JSON.parse(text) as {
        result?: Record<string, unknown>;
        msgid?: unknown;
        statuscode?: unknown;
        statusstr?: unknown;
        point?: unknown;
      };
      const node = parsed.result && typeof parsed.result === "object" ? parsed.result : parsed;
      return normalizeSmsGoFields(node);
    } catch {
      // fall through to key=value
    }
  }

  const fields: Record<string, string> = {};
  for (const line of text.split(/[\r\n&]+/)) {
    const index = line.indexOf("=");
    if (index < 1) continue;
    fields[line.slice(0, index).trim().toLowerCase()] = line.slice(index + 1).trim();
  }
  if (Object.keys(fields).length > 0) {
    return normalizeSmsGoFields(fields);
  }

  return { msgid: "", statuscode: -10, statusstr: text.slice(0, 160), point: 0 };
}

function normalizeSmsGoFields(node: Record<string, unknown>): SmsGoResult {
  const msgid = String(node.msgid ?? node.serial_number ?? "").trim();
  const statuscode = Number(node.statuscode ?? node.code ?? NaN);
  const statusstr = String(node.statusstr ?? node.text ?? node.message ?? "").trim();
  const point = Number(node.point ?? 0);
  return {
    msgid,
    statuscode: Number.isFinite(statuscode) ? statuscode : -10,
    statusstr,
    point: Number.isFinite(point) ? point : 0,
  };
}

export function smsGoUserMessage(statuscode: number, fallback = "簡訊閘道暫時無法完成，請稍後再試。") {
  const messages: Record<number, string> = {
    0: "成功",
    [-1]: "簡訊閘道參數格式不正確。",
    [-2]: "SMS Go 帳號、API Key 或來源 IP 驗證失敗。",
    [-3]: "尚未設定 SMS Go 帳號或 API Key。",
    [-5]: "手機號碼格式不被簡訊閘道接受。",
    [-8]: "SMS Go 點數不足，請先加值。",
    [-10]: "簡訊發送失敗。",
    [-11]: "簡訊閘道資料庫錯誤。",
    [-15]: "此伺服器 IP 尚未加入 SMS Go 允許清單。",
    [-16]: "SMS Go 尚未開通 API。",
    [-18]: "此門號不在服務範圍。",
    [-19]: "此號碼已被封鎖。",
    [-21]: "已達發送上限，請稍後再試。",
    [-23]: "簡訊缺少 NCC 署名。請在 SMS Go 後台 OTP 範本補上署名，或設定 SMSGO_SIGNATURE。",
    [-24]: "驗證碼不正確。",
    [-25]: "驗證碼已過期，請重新發送。",
  };
  return messages[statuscode] || fallback;
}

export function assertSmsGoSuccess(result: SmsGoResult, fallback: string) {
  if (result.statuscode === 0 && result.msgid && !result.msgid.startsWith("-")) {
    return result;
  }
  throw new SmsGoError(smsGoUserMessage(result.statuscode, fallback), result.statuscode);
}

async function postSmsGo(
  url: string,
  fields: Record<string, string>,
  fetchImpl: SmsGoFetch,
): Promise<SmsGoResult> {
  const body = new URLSearchParams(fields);
  const response = await fetchImpl(url, {
    method: "POST",
    headers: { "Content-Type": "application/x-www-form-urlencoded" },
    body,
    cache: "no-store",
  });
  const text = await response.text();
  const result = parseSmsGoResponse(text);
  if (!response.ok && result.statuscode === 0) {
    throw new SmsGoError("簡訊閘道連線失敗。", -10);
  }
  return result;
}

function authFields(config: SmsGoConfig) {
  return {
    username: config.username,
    password: config.apiKey,
    rtype: "JSON",
  };
}

export async function sendSmsGoOtp(phone: string, fetchImpl: SmsGoFetch = fetch) {
  const config = smsGoConfig();
  if (!config.configured) {
    throw new SmsGoError(
      `SMS Go 已接線，但尚未設定金鑰，無法發送真實簡訊。請設定環境變數 ${config.missing.join("、")}。`,
      -3,
    );
  }
  const fields: Record<string, string> = {
    ...authFields(config),
    dstaddr: phone,
    codelength: String(config.otpLength),
  };
  const signature = config.signature || config.senderName;
  if (signature) fields.smsheader = signature;
  const result = await postSmsGo(SMSGO_SEND_URL, fields, fetchImpl);
  return assertSmsGoSuccess(result, "簡訊驗證碼發送失敗。");
}

export async function ackSmsGoOtp(
  phone: string,
  code: string,
  serial: string,
  fetchImpl: SmsGoFetch = fetch,
) {
  const config = smsGoConfig();
  if (!config.configured) {
    throw new SmsGoError(
      `SMS Go 已接線，但尚未設定金鑰。請設定環境變數 ${config.missing.join("、")}。`,
      -3,
    );
  }
  const result = await postSmsGo(
    SMSGO_ACK_URL,
    {
      ...authFields(config),
      dstaddr: phone,
      OtpCode: code.trim(),
      serial_number: serial,
    },
    fetchImpl,
  );
  return assertSmsGoSuccess(result, "驗證碼不正確或已過期。");
}
