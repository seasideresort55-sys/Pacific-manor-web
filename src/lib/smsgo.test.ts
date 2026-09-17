import { afterEach, describe, expect, it } from "vitest";
import {
  parseSmsGoResponse,
  sendSmsGoOtp,
  ackSmsGoOtp,
  smsGoConfig,
  smsGoPublicStatus,
  smsGoUserMessage,
  SmsGoError,
} from "./smsgo";

const ORIGINAL = { ...process.env };

afterEach(() => {
  process.env.SMSGO_USERNAME = ORIGINAL.SMSGO_USERNAME;
  process.env.SMSGO_API_KEY = ORIGINAL.SMSGO_API_KEY;
  process.env.SMSGO_PASSWORD = ORIGINAL.SMSGO_PASSWORD;
  process.env.SMSGO_OTP_LENGTH = ORIGINAL.SMSGO_OTP_LENGTH;
  delete process.env.SMSGO_USERNAME;
  delete process.env.SMSGO_API_KEY;
  delete process.env.SMSGO_PASSWORD;
  delete process.env.SMSGO_OTP_LENGTH;
});

describe("parseSmsGoResponse", () => {
  it("讀 JSON result 包裝", () => {
    const result = parseSmsGoResponse(
      JSON.stringify({ result: { msgid: "2601270523943266", statuscode: "0", statusstr: "Verify success", point: 1 } }),
    );
    expect(result).toEqual({
      msgid: "2601270523943266",
      statuscode: 0,
      statusstr: "Verify success",
      point: 1,
    });
  });

  it("讀 key=value 純文字", () => {
    const result = parseSmsGoResponse("msgid=-24\nstatuscode=-24\nstatusstr=Verify Fail\npoint=0");
    expect(result.statuscode).toBe(-24);
    expect(result.msgid).toBe("-24");
  });
});

describe("smsGoConfig", () => {
  it("沒有帳號與金鑰時列出需要的變數名稱", () => {
    const config = smsGoConfig();
    expect(config.configured).toBe(false);
    expect(config.missing).toEqual(["SMSGO_USERNAME", "SMSGO_API_KEY"]);
    expect(smsGoPublicStatus().wired).toBe(true);
  });

  it("接受 SMSGO_PASSWORD 當 API Key", () => {
    process.env.SMSGO_USERNAME = "manor";
    process.env.SMSGO_PASSWORD = "not-a-real-key";
    expect(smsGoConfig().configured).toBe(true);
    expect(smsGoConfig().missing).toEqual([]);
  });
});

describe("SMS Go OTP 呼叫", () => {
  it("未設定金鑰時不發送、錯誤不含密碼", async () => {
    await expect(sendSmsGoOtp("0912345678")).rejects.toMatchObject({
      statuscode: -3,
    });
    try {
      await sendSmsGoOtp("0912345678");
    } catch (error) {
      expect(String(error)).not.toMatch(/password|secret|api[_-]?key=/i);
      expect((error as SmsGoError).message).toContain("SMSGO_USERNAME");
      expect((error as SmsGoError).message).toContain("SMSGO_API_KEY");
    }
  });

  it("發送成功只留下 msgid，驗證走 verifyAck", async () => {
    process.env.SMSGO_USERNAME = "manor";
    process.env.SMSGO_API_KEY = "test-key";
    const calls: { url: string; body: string }[] = [];
    const fetchImpl: typeof fetch = async (input, init) => {
      calls.push({ url: String(input), body: String(init?.body || "") });
      return new Response(
        JSON.stringify({ result: { msgid: "2601270523943266", statuscode: "0", statusstr: "OK", point: 1 } }),
        { status: 200 },
      );
    };
    const sent = await sendSmsGoOtp("0987654321", fetchImpl);
    expect(sent.msgid).toBe("2601270523943266");
    expect(calls[0]?.url).toContain("/sms_gw/verify.aspx");
    expect(calls[0]?.body).toContain("dstaddr=0987654321");
    expect(calls[0]?.body).toContain("codelength=6");
    expect(calls[0]?.body).not.toContain("OtpCode=");

    const ackFetch: typeof fetch = async (input, init) => {
      calls.push({ url: String(input), body: String(init?.body || "") });
      return new Response(JSON.stringify({ result: { msgid: "ack1", statuscode: "0", statusstr: "OK", point: 0 } }), {
        status: 200,
      });
    };
    await ackSmsGoOtp("0987654321", "654321", "2601270523943266", ackFetch);
    expect(calls[1]?.url).toContain("/sms_gw/verifyAck.aspx");
    expect(calls[1]?.body).toContain("OtpCode=654321");
    expect(calls[1]?.body).toContain("serial_number=2601270523943266");
  });

  it("錯碼對應 -24", () => {
    expect(smsGoUserMessage(-24)).toContain("不正確");
    expect(smsGoUserMessage(-25)).toContain("過期");
  });
});
