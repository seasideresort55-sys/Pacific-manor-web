import { describe, expect, it } from "vitest";
import {
  MOCK_OTP_CODE,
  canApplyMembership,
  issueOtp,
  isValidEmail,
  normalizeTwPhone,
  otpMatches,
} from "./auth";

describe("normalizeTwPhone", () => {
  it("接受 09 開頭 10 碼與 886 國碼", () => {
    expect(normalizeTwPhone("0912-345-678")).toBe("0912345678");
    expect(normalizeTwPhone("+886912345678")).toBe("0912345678");
    expect(normalizeTwPhone("123")).toBeNull();
  });
});

describe("OTP", () => {
  it("預覽模式固定驗證碼可通過，過期或錯碼不行", () => {
    const pending = issueOtp("sms", "0912345678");
    expect(pending.code).toBe(MOCK_OTP_CODE);
    expect(otpMatches(pending, "sms", "0912345678", MOCK_OTP_CODE)).toBe(true);
    expect(otpMatches(pending, "sms", "0912345678", "000000")).toBe(false);
    expect(otpMatches(pending, "email", "0912345678", MOCK_OTP_CODE)).toBe(false);
    expect(otpMatches({ ...pending, expiresAt: "2000-01-01T00:00:00.000Z" }, "sms", "0912345678", MOCK_OTP_CODE)).toBe(
      false,
    );
  });

  it("電子郵件格式", () => {
    expect(isValidEmail("a@b.com")).toBe(true);
    expect(isValidEmail("nope")).toBe(false);
  });
});

describe("簽約申請門禁", () => {
  it("須問卷通過且已驗證，不能只靠手填", () => {
    expect(canApplyMembership("pass", false)).toBe(false);
    expect(canApplyMembership("pass", true)).toBe(true);
    expect(canApplyMembership("review", true)).toBe(false);
  });
});
