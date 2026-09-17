import { describe, expect, it } from "vitest";
import { decodeSession, emptySession, encodeSession } from "./session-codec";

describe("session codec", () => {
  it("往返後仍保留會員門禁欄位", () => {
    const session = {
      ...emptySession(),
      isMember: true,
      quizOutcome: "pass" as const,
      authVerified: true,
      authProvider: "sms" as const,
      portalMemberId: "pm:0912345678",
      memberIdentifier: "0912345678",
      portalHandoff: "pending" as const,
    };
    const again = decodeSession(encodeSession(session));
    expect(again?.isMember).toBe(true);
    expect(again?.quizOutcome).toBe("pass");
    expect(again?.authVerified).toBe(true);
    expect(again?.authProvider).toBe("sms");
    expect(again?.portalMemberId).toBe("pm:0912345678");
    expect(again?.memberIdentifier).toBe("0912345678");
  });

  it("壞掉的 cookie 當成未登入", () => {
    expect(decodeSession("not-valid")).toBeNull();
  });
});
