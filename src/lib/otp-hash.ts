import { createHmac, randomBytes, timingSafeEqual } from "crypto";

export function newOtpSalt() {
  return randomBytes(16).toString("hex");
}

export function hashOtp(code: string, salt: string) {
  return createHmac("sha256", salt).update(code.trim()).digest("hex");
}

export function otpHashMatches(code: string, salt: string, expectedHex: string) {
  if (!salt || !expectedHex || !/^[0-9]{4,6}$/.test(code.trim())) return false;
  const actual = hashOtp(code, salt);
  if (actual.length !== expectedHex.length) return false;
  try {
    return timingSafeEqual(Buffer.from(actual, "hex"), Buffer.from(expectedHex, "hex"));
  } catch {
    return false;
  }
}
