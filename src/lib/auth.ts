import type { AuthProvider, PendingOtp } from "./types";

export const MOCK_OTP_CODE = process.env.SMS_MOCK_CODE || "123456";
export const OTP_TTL_MS = 10 * 60 * 1000;

export const AUTH_PROVIDER_LABEL: Record<AuthProvider, string> = {
  google: "Google",
  line: "LINE",
  apple: "Apple",
  email: "電子郵件",
  sms: "手機簡訊",
};

export function normalizeTwPhone(input: string): string | null {
  const digits = input.replace(/\D/g, "");
  if (digits.startsWith("886") && digits.length >= 12) {
    return `0${digits.slice(3, 12)}`;
  }
  if (/^09\d{8}$/.test(digits)) return digits;
  return null;
}

export function isValidEmail(input: string): boolean {
  return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(input.trim());
}

export function mockOauthProfile(provider: Extract<AuthProvider, "google" | "line" | "apple">) {
  const profiles = {
    google: {
      name: "林海寧",
      email: "haining.lin@gmail.com",
      phone: "0912345678",
    },
    line: {
      name: "陳慢活",
      email: "manhuo@line.me",
      phone: "0987654321",
    },
    apple: {
      name: "張遠端",
      email: "remote.chang@privaterelay.appleid.com",
      phone: "0955123456",
    },
  };
  return profiles[provider];
}

export function nameFromEmail(email: string) {
  const local = email.split("@")[0] || "會員";
  return local.replace(/[._-]+/g, " ").trim() || "會員";
}

export function issueOtp(channel: "sms" | "email", destination: string): PendingOtp {
  return {
    channel,
    destination,
    code: MOCK_OTP_CODE,
    expiresAt: new Date(Date.now() + OTP_TTL_MS).toISOString(),
  };
}

export function otpMatches(pending: PendingOtp | null, channel: "sms" | "email", destination: string, code: string) {
  if (!pending) return false;
  if (pending.channel !== channel) return false;
  if (pending.destination !== destination) return false;
  if (Date.now() > new Date(pending.expiresAt).getTime()) return false;
  return pending.code === code.trim();
}

export function canApplyMembership(outcome: string | null, authVerified: boolean) {
  return outcome === "pass" && authVerified === true;
}
