import { NextResponse } from "next/server";
import {
  AUTH_PROVIDER_LABEL,
  EMAIL_PREVIEW_OTP,
  isValidEmail,
  issueEmailOtp,
  issueSmsGoPending,
  mockOauthProfile,
  nameFromEmail,
  normalizeTwPhone,
  otpMatches,
} from "@/lib/auth";
import { upsertMemberByPhone } from "@/lib/members";
import { handoffVerifiedPhone, memberPortalPublicStatus } from "@/lib/member-portal";
import { readSession, writeSession } from "@/lib/session";
import { ackSmsGoOtp, sendSmsGoOtp, SmsGoError, smsGoPublicStatus } from "@/lib/smsgo";
import type { AuthProvider } from "@/lib/types";

export async function GET() {
  return NextResponse.json({
    smsGo: smsGoPublicStatus(),
    memberPortal: memberPortalPublicStatus(),
  });
}

async function attachPortalMember(input: {
  phone: string;
  name?: string;
  email?: string;
  provider: AuthProvider;
}) {
  const link = await handoffVerifiedPhone({
    phone: input.phone,
    name: input.name,
    email: input.email,
  });
  await upsertMemberByPhone({
    phone: input.phone,
    email: input.email,
    name: input.name,
    provider: input.provider,
    portalMemberId: link.portalMemberId,
    memberIdentifier: link.memberIdentifier,
    portalHandoff: link.handoff,
  });
  return link;
}

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
    const link = await attachPortalMember({
      phone: profile.phone,
      name: profile.name,
      email: profile.email,
      provider: body.provider,
    });
    session.authVerified = true;
    session.authProvider = body.provider;
    session.pendingOtp = null;
    session.name = profile.name;
    session.email = profile.email;
    session.phone = profile.phone;
    session.portalMemberId = link.portalMemberId;
    session.memberIdentifier = link.memberIdentifier;
    session.portalHandoff = link.handoff;
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
      const status = smsGoPublicStatus();
      if (!status.configured) {
        return NextResponse.json(
          {
            error: `SMS Go 已接真閘道，但尚未設定金鑰，無法發送真實簡訊。請提供環境變數：${status.missing.join("、")}。`,
            smsGo: status,
            missing: status.missing,
          },
          { status: 503 },
        );
      }
      try {
        const sent = await sendSmsGoOtp(phone);
        session.pendingOtp = issueSmsGoPending(phone, sent.msgid);
        await writeSession(session);
        return NextResponse.json({
          sent: true,
          channel: "sms",
          destination: phone,
          gateway: "smsgo",
          otpLength: status.otpLength,
          mock: false,
          message: "驗證碼已由 SMS Go 發送到手機，請輸入簡訊中的數字。",
        });
      } catch (error) {
        const message = error instanceof SmsGoError ? error.message : "簡訊發送失敗，請稍後再試。";
        return NextResponse.json({ error: message, smsGo: status }, { status: 502 });
      }
    }
    if (channel === "email") {
      if (!isValidEmail(raw)) {
        return NextResponse.json({ error: "請輸入有效的電子郵件。" }, { status: 400 });
      }
      const email = raw.toLowerCase();
      session.pendingOtp = issueEmailOtp(email);
      await writeSession(session);
      return NextResponse.json({
        sent: true,
        channel: "email",
        destination: email,
        gateway: "preview",
        previewCode: EMAIL_PREVIEW_OTP,
        mock: true,
        message: "驗證信已送出（電子郵件閘道尚未接入，預覽模式請輸入固定驗證碼）。",
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

    if (channel === "sms") {
      const pending = session.pendingOtp;
      if (!pending || pending.channel !== "sms" || pending.destination !== destination || !pending.serial) {
        return NextResponse.json({ error: "請先送出簡訊驗證碼。" }, { status: 400 });
      }
      if (Date.now() > new Date(pending.expiresAt).getTime()) {
        return NextResponse.json({ error: "驗證碼已過期，請重新發送。" }, { status: 400 });
      }
      try {
        await ackSmsGoOtp(destination, body.code || "", pending.serial);
      } catch (error) {
        const message = error instanceof SmsGoError ? error.message : "驗證碼不正確或已過期。";
        return NextResponse.json({ error: message }, { status: 400 });
      }
      if (!session.name) session.name = "簡訊驗證會員";
      session.phone = destination;
      const link = await attachPortalMember({
        phone: destination,
        name: session.name,
        email: session.email,
        provider: "sms",
      });
      session.authVerified = true;
      session.authProvider = "sms";
      session.pendingOtp = null;
      session.portalMemberId = link.portalMemberId;
      session.memberIdentifier = link.memberIdentifier;
      session.portalHandoff = link.handoff;
      await writeSession(session);
      return NextResponse.json({
        session,
        mock: false,
        gateway: "smsgo",
        message: "手機簡訊驗證完成，已對齊預約系統會員。",
      });
    }

    if (!otpMatches(session.pendingOtp, channel, destination, body.code || "")) {
      return NextResponse.json({ error: "驗證碼不正確或已過期。" }, { status: 400 });
    }
    session.authVerified = true;
    session.authProvider = "email";
    session.pendingOtp = null;
    session.email = destination;
    if (!session.name) session.name = nameFromEmail(destination);
    await writeSession(session);
    return NextResponse.json({
      session,
      mock: true,
      message: "電子郵件驗證完成。",
    });
  }

  return NextResponse.json({ error: "未知的驗證動作。" }, { status: 400 });
}
