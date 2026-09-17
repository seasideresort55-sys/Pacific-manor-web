import { describe, expect, it } from "vitest";
import { portalIdentifierFromPhone, portalMemberIdFromPhone, memberPortalPublicStatus } from "./member-portal";

describe("預約系統會員識別", () => {
  it("手機對齊官網「電話號碼或 Email」同一識別", () => {
    expect(portalIdentifierFromPhone("0912-345-678")).toBe("0912345678");
    expect(portalMemberIdFromPhone("+886912345678")).toBe("pm:0912345678");
  });

  it("公開狀態不暴露 token 內容", () => {
    const status = memberPortalPublicStatus();
    expect(status.system).toBe("pm_member_portal");
    expect(status.baseUrl).toContain("pm_roomboard");
    expect(JSON.stringify(status)).not.toMatch(/Bearer |sk_|password/i);
  });
});
