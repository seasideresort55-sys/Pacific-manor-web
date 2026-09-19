import { afterEach, describe, expect, it } from "vitest";
import {
  parseSmsGoSendResponse,
  sendSmsGoOtp,
  smsGoConfig,
  smsGoPublicStatus,
  smsGoUserMessage,
  toE164TwMobile,
  toSmsGoDstaddr,
  SmsGoError,
} from "./smsgo";

afterEach(() => {
  delete process.env.SMSGO_USERNAME;
  delete process.env.SMSGO_API_KEY;
  delete process.env.SMSGO_PASSWORD;
  delete process.env.SMSGO_ENABLED;
  delete process.env.SMSGO_CONTROLLED_TEST;
  delete process.env.SMSGO_ALLOWED_PHONES;
  delete process.env.SMSGO_APPROVED_TEMPLATE;
});

describe("官方 adapter 電話轉換", () => {
  it("09 轉 +8869，dstaddr 再轉回 09", () => {
    expect(toE164TwMobile("0912-345-678")).toBe("+886912345678");
    expect(toSmsGoDstaddr("+886912345678")).toBe("0912345678");
  });
});

describe("parseSmsGoSendResponse", () => {
  it("statuscode 0 + msgid 視為 accepted", () => {
    const result = parseSmsGoSendResponse(
      200,
      JSON.stringify({ result: { msgid: "2601270523943266", statuscode: "0", statusstr: "OK" } }),
    );
    expect(result.state).toBe("accepted");
    expect(result.message_id).toBe("2601270523943266");
  });

  it("已知負碼視為 rejected", () => {
    const result = parseSmsGoSendResponse(200, "msgid=-3\nstatuscode=-3\nstatusstr=empty\npoint=0");
    expect(result.state).toBe("rejected");
    expect(result.statuscode).toBe(-3);
  });
});

describe("smsGoConfig", () => {
  it("沒有帳號與金鑰時列出變數名稱，且預設未啟用", () => {
    const config = smsGoConfig();
    expect(config.configured).toBe(false);
    expect(config.enabled).toBe(false);
    expect(config.authorizedToSend).toBe(false);
    expect(config.missing).toEqual(["SMSGO_USERNAME", "SMSGO_API_KEY"]);
    expect(smsGoPublicStatus().available).toBe(false);
    expect(JSON.stringify(smsGoPublicStatus())).not.toMatch(/SMSGO_|pm_smsgo_adapter|missing/);
  });
});

describe("SMS Go sendsms", () => {
  it("未設定金鑰時不發送", async () => {
    await expect(sendSmsGoOtp("0912345678", "654321")).rejects.toMatchObject({ statuscode: -3 });
    try {
      await sendSmsGoOtp("0912345678", "654321");
    } catch (error) {
      expect(String(error)).not.toMatch(/secret|sk_|SMSGO_|pm_member_private|api-key/i);
      expect((error as SmsGoError).message).toContain("暫時無法寄送驗證碼");
    }
  });

  it("金鑰在但旗標未開時拒絕發送", async () => {
    process.env.SMSGO_USERNAME = "manor@example.com";
    process.env.SMSGO_API_KEY = "test-key";
    await expect(sendSmsGoOtp("0912345678", "654321")).rejects.toMatchObject({ statuscode: -16 });
  });

  it("對齊 adapter：sendsms.aspx + smbody 含驗證碼", async () => {
    process.env.SMSGO_USERNAME = "manor@example.com";
    process.env.SMSGO_API_KEY = "test-key";
    process.env.SMSGO_ENABLED = "true";
    process.env.SMSGO_CONTROLLED_TEST = "true";
    process.env.SMSGO_ALLOWED_PHONES = "+886987654321";
    const calls: { url: string; body: string }[] = [];
    const fetchImpl: typeof fetch = async (input, init) => {
      calls.push({ url: String(input), body: String(init?.body || "") });
      return new Response(
        JSON.stringify({ result: { msgid: "2601270523943266", statuscode: "0", statusstr: "OK" } }),
        { status: 200 },
      );
    };
    const sent = await sendSmsGoOtp("0987654321", "654321", fetchImpl);
    expect(sent.messageId).toBe("2601270523943266");
    expect(calls[0]?.url).toContain("/sms_gw/sendsms.aspx");
    expect(calls[0]?.body).toContain("dstaddr=0987654321");
    expect(calls[0]?.body).toContain("smbody=");
    expect(decodeURIComponent(calls[0]?.body || "")).toContain("654321");
    expect(calls[0]?.body).toContain("encoding=BIG5");
  });

  it("錯碼對應文案", () => {
    expect(smsGoUserMessage(-15)).toContain("暫時無法寄送驗證碼");
    expect(smsGoUserMessage(-5)).toContain("手機號碼");
  });
});
