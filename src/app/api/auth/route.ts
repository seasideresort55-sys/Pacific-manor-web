import { NextResponse } from "next/server";
import {
  AUTH_PROVIDER_LABEL,
  MOCK_OTP_CODE,
  isValidEmail,
  issueOtp,
  mockOauthProfile,
  nameFromEmail,
  normalizeTwPhone,
  otpMatches,
} from "@/lib/auth";
import { readSession, writeSession } from "@/lib/session";
import type { AuthProvider } from "@/lib/types";

export async function POST(request: Request) {
  const session = await readSession();
  const body = (await request.json()) as {
    action: "oauth" | "send_otp" | "verify_otp";
    provider?: AuthProvider;
    channel?: "sms" | "email";
    destination?: string;
    code?: string;
  };

  if (body.action === "oauth") {
    if (body.provider !== "google" && body.provider !== "line" && body.provider !== "apple") {
      return NextResponse.json({ error: "不支援的驗證方式。" }, { status: 400 });
    }
    const profile = mockOauthProfile(body.provider);
    session.authVerified = true;
    session.authProvider = body.provider;
    session.pendingOtp = null;
    session.name = profile.name;
    session.email = profile.email;
    session.phone = profile.phone;
    await writeSession(session);
    return NextResponse.json({
      session,
      mock: true,
      message: `已用 ${AUTH_PROVIDER_LABEL[body.provider]} 模擬驗證（正式 OAuth 金鑰尚未接入）。`,
    });
  }

  if (body.action === "send_otp") {
    const channel = body.channel;
    const raw = (body.destination || "").trim();
    if (channel === "sms") {
      const phone = normalizeTwPhone(raw);
      if (!phone) {
        return NextResponse.json({ error: "請輸入有效手機號碼，例如 0912-345-678。" }, { status: 400 });
      }
      session.pendingOtp = issueOtp("sms", phone);
      await writeSession(session);
      return NextResponse.json({
        sent: true,
        channel: "sms",
        destination: phone,
        previewCode: MOCK_OTP_CODE,
        message: "驗證碼已送出（預覽模式不走真實簡訊閘道）。",
      });
    }
    if (channel === "email") {
      if (!isValidEmail(raw)) {
        return NextResponse.json({ error: "請輸入有效的電子郵件。" }, { status: 400 });
      }
      const email = raw.toLowerCase();
      session.pendingOtp = issueOtp("email", email);
      await writeSession(session);
      return NextResponse.json({
        sent: true,
        channel: "email",
        destination: email,
        previewCode: MOCK_OTP_CODE,
        message: "驗證信已送出（預覽模式不走真實郵件閘道）。",
      });
    }
    return NextResponse.json({ error: "請選擇簡訊或電子郵件。" }, { status: 400 });
  }

  if (body.action === "verify_otp") {
    const channel = body.channel;
    const raw = (body.destination || "").trim();
    const destination = channel === "sms" ? normalizeTwPhone(raw) : raw.toLowerCase();
    if (!channel || !destination) {
      return NextResponse.json({ error: "請先送出驗證碼。" }, { status: 400 });
    }
    if (!otpMatches(session.pendingOtp, channel, destination, body.code || "")) {
      return NextResponse.json({ error: "驗證碼不正確或已過期。" }, { status: 400 });
    }
    session.authVerified = true;
    session.authProvider = channel === "sms" ? "sms" : "email";
    session.pendingOtp = null;
    if (channel === "sms") {
      session.phone = destination;
      if (!session.name) session.name = "簡訊驗證會員";
    } else {
      session.email = destination;
      if (!session.name) session.name = nameFromEmail(destination);
    }
    await writeSession(session);
    return NextResponse.json({
      session,
      mock: true,
      message: channel === "sms" ? "手機簡訊驗證完成。" : "電子郵件驗證完成。",
    });
  }

  return NextResponse.json({ error: "未知的驗證動作。" }, { status: 400 });
}
