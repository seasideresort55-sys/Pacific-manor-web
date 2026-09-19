import { NextResponse } from "next/server";
import {
  AUTH_PROVIDER_LABEL,
  EMAIL_PREVIEW_OTP,
  isValidEmail,
  issueEmailOtp,
  mockOauthProfile,
  nameFromEmail,
  normalizeTwPhone,
  otpMatches,
} from "@/lib/auth";
import { issueSmsGoPending, smsOtpMatches } from "@/lib/auth-otp";
import { handoffVerifiedPhone, memberPortalPublicStatus } from "@/lib/member-portal";
import { readSession, writeSession } from "@/lib/session";
import { GUEST_OTP_UNAVAILABLE, guestSafeError } from "@/lib/guest-errors";
import { generateSmsGoOtp, sendSmsGoOtp, SmsGoError, smsGoPublicStatus } from "@/lib/smsgo";
import type { AuthProvider, SessionState } from "@/lib/types";

function publicSession(session: SessionState) {
  if (!session.pendingOtp) return session;
  const { code: _code, codeHash: _hash, salt: _salt, ...pending } = session.pendingOtp;
  return { ...session, pendingOtp: pending };
}

export async function GET() {
  return NextResponse.json({
    smsGo: smsGoPublicStatus(),
    memberPortal: memberPortalPublicStatus(),
  });
}

async function attachPortalMember(session: SessionState, input: {
  phone: string;
  name?: string;
  email?: string;
}) {
  const link = await handoffVerifiedPhone({
    phone: input.phone,
    name: input.name,
    email: input.email,
  });
  session.portalMemberId = link.portalMemberId;
  session.memberIdentifier = link.memberIdentifier;
  session.portalHandoff = link.handoff;
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
    session.authVerified = true;
    session.authProvider = body.provider;
    session.pendingOtp = null;
    session.name = profile.name;
    session.email = profile.email;
    session.phone = profile.phone;
    await attachPortalMember(session, profile);
    await writeSession(session);
    return NextResponse.json({
      session: publicSession(session),
      mock: true,
      message: `已用 ${AUTH_PROVIDER_LABEL[body.provider]} 完成驗證。`,
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
      if (!status.configured || !status.available) {
        return NextResponse.json(
          {
            error: GUEST_OTP_UNAVAILABLE,
            smsGo: status,
          },
          { status: 503 },
        );
      }
      const code = generateSmsGoOtp();
      try {
        const sent = await sendSmsGoOtp(phone, code);
        session.pendingOtp = issueSmsGoPending(phone, code, sent.messageId);
        await writeSession(session);
        return NextResponse.json({
          sent: true,
          channel: "sms",
          destination: phone,
          otpLength: 6,
          mock: false,
          message: "驗證碼已發送到手機，請輸入簡訊中的數字。",
        });
      } catch (error) {
        const message =
          error instanceof SmsGoError
            ? guestSafeError(error.message)
            : GUEST_OTP_UNAVAILABLE;
        return NextResponse.json({ error: message, smsGo: status }, { status: 502 });
      }
    }
    if (channel === "email") {
      if (!isValidEmail(raw)) {
        return NextResponse.json({ error: "請輸入有效的電子郵件。" }, { status: 400 });
      }
      const email = raw.toLowerCase();
      try {
        session.pendingOtp = issueEmailOtp(email);
        await writeSession(session);
      } catch {
        return NextResponse.json({ error: GUEST_OTP_UNAVAILABLE }, { status: 503 });
      }
      const payload: Record<string, unknown> = {
        sent: true,
        channel: "email",
        destination: email,
        mock: process.env.NODE_ENV !== "production",
        message: "驗證碼已寄到電子郵件信箱，請輸入信中的 6 位數字。",
      };
      // Preview-only helper: never show a fixed code on a public host.
      if (process.env.NODE_ENV !== "production" && process.env.EMAIL_PREVIEW_OTP) {
        payload.previewCode = EMAIL_PREVIEW_OTP;
      }
      return NextResponse.json(payload);
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

    const matched =
      channel === "sms"
        ? smsOtpMatches(session.pendingOtp, destination, body.code || "")
        : otpMatches(session.pendingOtp, channel, destination, body.code || "");
    if (!matched) {
      return NextResponse.json({ error: "驗證碼不正確或已過期。" }, { status: 400 });
    }

    session.authVerified = true;
    session.authProvider = channel === "sms" ? "sms" : "email";
    session.pendingOtp = null;
    if (channel === "sms") {
      session.phone = destination;
      if (!session.name) session.name = "簡訊驗證會員";
      await attachPortalMember(session, {
        phone: destination,
        name: session.name,
        email: session.email,
      });
    } else {
      session.email = destination;
      if (!session.name) session.name = nameFromEmail(destination);
    }
    await writeSession(session);
    return NextResponse.json({
      session: publicSession(session),
      mock: channel !== "sms",
      gateway: channel === "sms" ? "smsgo" : "preview",
      message: channel === "sms" ? "手機簡訊驗證完成。" : "電子郵件驗證完成。",
    });
  }

  return NextResponse.json({ error: "未知的驗證動作。" }, { status: 400 });
}
