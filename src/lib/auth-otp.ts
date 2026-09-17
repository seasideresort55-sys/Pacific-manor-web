import { hashOtp, newOtpSalt, otpHashMatches } from "./otp-hash";
import { OTP_TTL_MS } from "./auth";
import type { PendingOtp } from "./types";

export function issueSmsGoPending(destination: string, code: string, serial?: string): PendingOtp {
  const salt = newOtpSalt();
  return {
    channel: "sms",
    destination,
    codeHash: hashOtp(code, salt),
    salt,
    serial,
    gateway: "smsgo",
    expiresAt: new Date(Date.now() + OTP_TTL_MS).toISOString(),
  };
}

export function smsOtpMatches(pending: PendingOtp | null, destination: string, code: string) {
  if (!pending) return false;
  if (pending.channel !== "sms" || pending.destination !== destination) return false;
  if (Date.now() > new Date(pending.expiresAt).getTime()) return false;
  return Boolean(pending.salt && pending.codeHash && otpHashMatches(code, pending.salt, pending.codeHash));
}
