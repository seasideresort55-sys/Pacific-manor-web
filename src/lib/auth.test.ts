import { describe, expect, it } from "vitest";
import {
  EMAIL_PREVIEW_OTP,
  canApplyMembership,
  issueEmailOtp,
  issueOtp,
  issueSmsGoPending,
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
  it("電子郵件預覽碼可通過，過期或錯碼不行", () => {
    const pending = issueEmailOtp("a@b.com");
    expect(pending.code).toBe(EMAIL_PREVIEW_OTP);
    expect(otpMatches(pending, "email", "a@b.com", EMAIL_PREVIEW_OTP)).toBe(true);
    expect(otpMatches(pending, "email", "a@b.com", "000000")).toBe(false);
    expect(otpMatches({ ...pending, expiresAt: "2000-01-01T00:00:00.000Z" }, "email", "a@b.com", EMAIL_PREVIEW_OTP)).toBe(
      false,
    );
  });

  it("正式簡訊不發本地假碼，須走 SMS Go serial", () => {
    expect(() => issueOtp("sms", "0912345678")).toThrow(/SMS Go/);
    const pending = issueSmsGoPending("0912345678", "2601270523943266");
    expect(pending.code).toBeUndefined();
    expect(pending.serial).toBe("2601270523943266");
    expect(otpMatches(pending, "sms", "0912345678", "123456")).toBe(false);
    expect(otpMatches(pending, "sms", "0912345678", EMAIL_PREVIEW_OTP)).toBe(false);
    expect(
      otpMatches(
        { channel: "sms", destination: "0912345678", code: EMAIL_PREVIEW_OTP, gateway: "preview", expiresAt: pending.expiresAt },
        "sms",
        "0912345678",
        EMAIL_PREVIEW_OTP,
      ),
    ).toBe(false);
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
