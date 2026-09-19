import { describe, expect, it } from "vitest";
import { portalIdentifierFromPhone, portalMemberIdFromPhone, memberPortalPublicStatus } from "./member-portal";

describe("預約系統會員識別", () => {
  it("手機對齊官網「電話號碼或電子郵件」同一識別", () => {
    expect(portalIdentifierFromPhone("0912-345-678")).toBe("0912345678");
    expect(portalMemberIdFromPhone("+886912345678")).toBe("pm:0912345678");
  });

  it("公開狀態不暴露 token 內容", () => {
    const status = memberPortalPublicStatus();
    expect(JSON.stringify(status)).not.toMatch(/Bearer |sk_|password|MEMBER_API_TOKEN|missing/i);
  });
});
