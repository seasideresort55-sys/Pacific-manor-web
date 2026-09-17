import { normalizeTwPhone } from "./auth";

export const MEMBER_API_DEFAULT_BASE = "https://seasideresort.com.tw/booking/pm_roomboard";
export const MEMBER_API_REQUIRED_ENV = ["MEMBER_API_TOKEN"] as const;
export const MEMBER_API_OPTIONAL_ENV = ["MEMBER_API_BASE_URL", "MEMBER_API_SMS_ACTION"] as const;

export type PortalHandoffStatus = "linked" | "pending" | "unavailable";

export type PortalMemberLink = {
  portalMemberId: string;
  memberIdentifier: string;
  handoff: PortalHandoffStatus;
  reason?: string;
};

export type MemberPortalConfig = {
  baseUrl: string;
  token: string;
  action: string;
  tokenConfigured: boolean;
};

export function memberPortalConfig(): MemberPortalConfig {
  const baseUrl = (process.env.MEMBER_API_BASE_URL?.trim() || MEMBER_API_DEFAULT_BASE).replace(/\/$/, "");
  const token = process.env.MEMBER_API_TOKEN?.trim() || "";
  const action = process.env.MEMBER_API_SMS_ACTION?.trim() || "sms_verified_upsert";
  return { baseUrl, token, action, tokenConfigured: Boolean(token) };
}

export function memberPortalPublicStatus() {
  const config = memberPortalConfig();
  return {
    aligned: true,
    system: "pm_member_portal",
    baseUrl: config.baseUrl,
    tokenConfigured: config.tokenConfigured,
    missing: config.tokenConfigured ? [] : ["MEMBER_API_TOKEN"],
    optionalEnv: [...MEMBER_API_OPTIONAL_ENV],
  };
}

/** 與官網密碼登入同一識別：電話號碼（09xxxxxxxx） */
export function portalIdentifierFromPhone(phone: string) {
  return normalizeTwPhone(phone) || phone.replace(/\D/g, "");
}

export function portalMemberIdFromPhone(phone: string) {
  return `pm:${portalIdentifierFromPhone(phone)}`;
}

export async function handoffVerifiedPhone(input: {
  phone: string;
  name?: string;
  email?: string;
}): Promise<PortalMemberLink> {
  const memberIdentifier = portalIdentifierFromPhone(input.phone);
  const localId = portalMemberIdFromPhone(memberIdentifier);
  const config = memberPortalConfig();

  if (!config.tokenConfigured) {
    return {
      portalMemberId: localId,
      memberIdentifier,
      handoff: "pending",
      reason: "missing_MEMBER_API_TOKEN",
    };
  }

  try {
    const apiUrl = `${config.baseUrl}/pm_member_api_v17.php`;
    const bootstrap = await fetch(apiUrl, { cache: "no-store" });
    const state = (await bootstrap.json()) as { ok?: boolean; csrf_token?: string };
    if (!bootstrap.ok || !state.csrf_token) {
      return {
        portalMemberId: localId,
        memberIdentifier,
        handoff: "unavailable",
        reason: "csrf_bootstrap_failed",
      };
    }

    const body = new URLSearchParams({
      action: config.action,
      phone: memberIdentifier,
      identifier: memberIdentifier,
      name: input.name || "",
      email: input.email || "",
      source: "smsgo",
    });
    const response = await fetch(apiUrl, {
      method: "POST",
      headers: {
        "Content-Type": "application/x-www-form-urlencoded",
        "X-PM-CSRF": state.csrf_token,
        "X-PM-SERVER-TOKEN": config.token,
        Authorization: `Bearer ${config.token}`,
      },
      body,
      cache: "no-store",
    });
    const json = (await response.json()) as {
      ok?: boolean;
      error?: string;
      member?: { id?: string; member_id?: string; phone?: string };
    };
    const remoteId = json.member?.id || json.member?.member_id;
    if (response.ok && json.ok && remoteId) {
      return {
        portalMemberId: String(remoteId),
        memberIdentifier: json.member?.phone || memberIdentifier,
        handoff: "linked",
      };
    }
    return {
      portalMemberId: localId,
      memberIdentifier,
      handoff: json.error === "請先重新登入會員" ? "pending" : "unavailable",
      reason: json.error || `http_${response.status}`,
    };
  } catch {
    return {
      portalMemberId: localId,
      memberIdentifier,
      handoff: "unavailable",
      reason: "portal_unreachable",
    };
  }
}
