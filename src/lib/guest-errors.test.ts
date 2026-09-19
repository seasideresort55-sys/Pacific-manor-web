import { describe, expect, it } from "vitest";
import {
  GUEST_OTP_UNAVAILABLE,
  guestSafeError,
  isLeakyGuestError,
  publicSmsStatus,
} from "./guest-errors";

describe("guestSafeError", () => {
  it("hides SMS key / host path hints", () => {
    expect(guestSafeError("簡訊金鑰尚未設定，請放入 /home/tdwhhyfe/pm_member_private/smsgo-api-key.txt")).toBe(
      GUEST_OTP_UNAVAILABLE,
    );
    expect(isLeakyGuestError("請設定 SMSGO_API_KEY")).toBe(true);
  });

  it("hides Email unique-index and PHP internals", () => {
    expect(guestSafeError("會員 Email 唯一索引尚未準備完成")).toBe(GUEST_OTP_UNAVAILABLE);
    expect(guestSafeError("Call to undefined function pm_phone_host_dispatch()")).toBe(
      GUEST_OTP_UNAVAILABLE,
    );
  });

  it("keeps ordinary guest validation copy", () => {
    expect(guestSafeError("請輸入有效手機號碼，例如 0912-345-678。")).toBe(
      "請輸入有效手機號碼，例如 0912-345-678。",
    );
    expect(guestSafeError("驗證碼不正確或已過期。")).toBe("驗證碼不正確或已過期。");
  });

  it("public SMS status never lists env names", () => {
    const status = publicSmsStatus({ configured: false, authorizedToSend: false });
    expect(JSON.stringify(status)).not.toMatch(/SMSGO_|API_KEY|pm_member_private/);
    expect(status.available).toBe(false);
  });
});
