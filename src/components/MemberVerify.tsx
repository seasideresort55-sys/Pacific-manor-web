"use client";

import Link from "next/link";
import { useSearchParams } from "next/navigation";
import { useEffect, useState } from "react";
import { AUTH_PROVIDER_LABEL } from "@/lib/auth";
import { GUEST_OTP_UNAVAILABLE, guestSafeError } from "@/lib/guest-errors";
import type { AuthProvider } from "@/lib/types";
import { AppleGlyph, GoogleGlyph, LineGlyph } from "./BrandIcons";
import { useSession } from "./SessionProvider";

type Channel = "email" | "sms";

type AuthStatus = {
  smsGo?: {
    available?: boolean;
    configured?: boolean;
    otpLength?: 4 | 6;
  };
};

export function MemberVerify() {
  const params = useSearchParams();
  const plan = params.get("plan");
  const { session, loading, refresh } = useSession();
  const [channel, setChannel] = useState<Channel>("sms");
  const [destination, setDestination] = useState("");
  const [code, setCode] = useState("");
  const [sentTo, setSentTo] = useState("");
  const [otpLength, setOtpLength] = useState(6);
  const [hint, setHint] = useState("");
  const [busy, setBusy] = useState(false);
  const [consent, setConsent] = useState(false);
  const [showNotice, setShowNotice] = useState(false);
  const [redo, setRedo] = useState(false);
  const [status, setStatus] = useState<AuthStatus | null>(null);

  const applyHref = plan ? `/membership/apply?plan=${plan}` : "/membership/apply";
  const smsReady = status?.smsGo?.available === true;

  useEffect(() => {
    let cancelled = false;
    void fetch("/api/auth", { cache: "no-store" })
      .then((res) => res.json())
      .then((data: AuthStatus) => {
        if (cancelled) return;
        setStatus(data);
        if (data.smsGo?.otpLength) setOtpLength(data.smsGo.otpLength);
      })
      .catch(() => {
        if (!cancelled) setStatus(null);
      });
    return () => {
      cancelled = true;
    };
  }, []);

  async function post(body: Record<string, unknown>) {
    setBusy(true);
    setHint("");
    const res = await fetch("/api/auth", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(body),
    });
    const data = await res.json();
    setBusy(false);
    if (!res.ok) {
      setHint(guestSafeError(data.error || GUEST_OTP_UNAVAILABLE));
      return null;
    }
    await refresh();
    return data;
  }

  function needConsent() {
    if (consent) return false;
    setHint("請先勾選同意《會員資料使用說明》，再繼續。");
    return true;
  }

  async function oauth(provider: Extract<AuthProvider, "google" | "line" | "apple">) {
    if (needConsent()) return;
    const data = await post({ action: "oauth", provider });
    if (data) setRedo(false);
  }

  async function sendOtp() {
    if (needConsent()) return;
    const data = await post({ action: "send_otp", channel, destination });
    if (!data) return;
    setSentTo(data.destination);
    setCode("");
    if (channel === "sms") {
      if (data.otpLength) setOtpLength(Number(data.otpLength));
      setHint("驗證碼已發送到手機。請輸入簡訊中的數字。");
      return;
    }
    setHint("驗證碼已寄到電子郵件。請輸入信中的 6 位數字。");
  }

  async function verifyOtp() {
    const data = await post({
      action: "verify_otp",
      channel,
      destination: sentTo || destination,
      code,
    });
    if (data) setRedo(false);
  }

  function switchChannel(next: Channel) {
    setChannel(next);
    setDestination("");
    setCode("");
    setSentTo("");
    setHint("");
  }

  if (loading) {
    return <p className="px-2 py-10 text-lg text-ocean">正在準備登入…</p>;
  }

  if (session?.authVerified && !redo) {
    const nextHref = session.quizOutcome === "pass" ? applyHref : "/quiz";
    const nextLabel = session.quizOutcome === "pass" ? "繼續簽約申請" : "下一步：了解是否適合";
    return (
      <section className="login-card">
        <p className="login-kicker">太平洋莊園 · 會員</p>
        <h1 className="login-title">歡迎回來</h1>
        <p className="login-lead">
          已用{session.authProvider ? AUTH_PROVIDER_LABEL[session.authProvider] : "已驗證方式"}登入。簽約資料可稍後補齊。
        </p>
        <ul className="mt-6 grid gap-2 text-lg leading-8 text-deep">
          <li>姓名：{session.name || "尚未填"}</li>
          <li>電子郵件：{session.email || "尚未填"}</li>
          <li>手機：{session.phone || "尚未填"}</li>
        </ul>
        <Link href={nextHref} className="login-primary mt-8">
          {nextLabel}
        </Link>
        <button type="button" className="login-text-link mt-4" onClick={() => setRedo(true)}>
          改用其他方式
        </button>
      </section>
    );
  }

  return (
    <section className="login-card">
      <p className="login-kicker">太平洋莊園 · 會員</p>
      <h1 className="login-title">登入或註冊</h1>
      <p className="login-lead">用手機簡訊最快；也可 Google、LINE 或電子郵件驗證碼。不用記密碼，選一種方式即可。</p>
      {!smsReady && status ? (
        <p className="mt-4 rounded-2xl bg-cream px-4 py-3 text-base leading-7 text-[#3d5a66]" data-testid="sms-gateway-status">
          簡訊若暫時無法使用，請改用電子郵件驗證碼或其他登入方式。
        </p>
      ) : null}

      {!sentTo ? (
        <div className="mt-8 grid gap-4">
          {channel === "sms" ? (
            <label className="grid gap-2">
              <span className="text-lg font-semibold text-deep">手機號碼</span>
              <input
                className="login-field"
                inputMode="tel"
                autoComplete="tel"
                placeholder="0912-345-678"
                value={destination}
                onChange={(event) => setDestination(event.target.value)}
              />
            </label>
          ) : (
            <label className="grid gap-2">
              <span className="text-lg font-semibold text-deep">電子郵件</span>
              <input
                className="login-field"
                type="email"
                inputMode="email"
                autoComplete="email"
                placeholder="you@example.com"
                value={destination}
                onChange={(event) => setDestination(event.target.value)}
              />
            </label>
          )}
          <button type="button" className="login-primary" disabled={busy} onClick={() => void sendOtp()}>
            {busy ? "請稍候…" : channel === "sms" ? "繼續，送出簡訊驗證碼" : "繼續，寄送驗證碼"}
          </button>
          <button
            type="button"
            className="login-text-link"
            onClick={() => switchChannel(channel === "sms" ? "email" : "sms")}
          >
            {channel === "sms" ? "改用電子郵件驗證碼" : "改回手機簡訊"}
          </button>
        </div>
      ) : (
        <div className="mt-8 grid gap-4">
          <p className="text-lg leading-8 text-[#3d5a66]">已送至 {sentTo}</p>
          <label className="grid gap-2">
            <span className="text-lg font-semibold text-deep">{otpLength} 位數驗證碼</span>
            <input
              className="login-field text-center text-[28px] tracking-[0.45em]"
              inputMode="numeric"
              autoComplete="one-time-code"
              maxLength={otpLength}
              placeholder={otpLength === 4 ? "0000" : "000000"}
              value={code}
              onChange={(event) => setCode(event.target.value.replace(/\D/g, "").slice(0, otpLength))}
            />
          </label>
          <p className="text-base leading-7 text-[#5d6f75]">
            {channel === "sms" ? "請輸入手機簡訊中的驗證碼。" : "請輸入電子郵件中的驗證碼。"}
          </p>
          <button
            type="button"
            className="login-primary"
            disabled={busy || code.length !== otpLength}
            onClick={() => void verifyOtp()}
          >
            驗證並繼續
          </button>
          <div className="flex flex-wrap gap-x-5 gap-y-2">
            <button type="button" className="login-text-link" onClick={() => setSentTo("")}>
              {channel === "sms" ? "更改手機號碼" : "更改電子郵件"}
            </button>
            <button type="button" className="login-text-link" disabled={busy} onClick={() => void sendOtp()}>
              重新寄送
            </button>
          </div>
        </div>
      )}

      <div className="login-or" role="separator">
        <span>或使用</span>
      </div>

      <div className="grid gap-3">
        <button type="button" className="login-google" disabled={busy} onClick={() => void oauth("google")}>
          <GoogleGlyph />
          使用 Google 繼續
        </button>
        <button type="button" className="login-line" disabled={busy} onClick={() => void oauth("line")}>
          <LineGlyph />
          使用 LINE 繼續
        </button>
        <button type="button" className="login-apple" disabled={busy} onClick={() => void oauth("apple")}>
          <AppleGlyph />
          使用 Apple 繼續
        </button>
      </div>

      <label className="mt-6 flex items-start gap-3 text-base leading-7 text-[#3d5a66]">
        <input
          type="checkbox"
          className="mt-1 h-5 w-5 accent-ocean"
          checked={consent}
          onChange={(event) => setConsent(event.target.checked)}
        />
        <span>
          我已閱讀並同意
          <button type="button" className="mx-1 font-semibold text-ocean underline underline-offset-4" onClick={() => setShowNotice((value) => !value)}>
            《會員資料使用說明》
          </button>
        </span>
      </label>
      {showNotice ? (
        <p className="mt-3 rounded-2xl bg-cream px-4 py-3 text-base leading-7 text-[#3d5a66]">
          本站保存驗證後的電子郵件、手機與驗證紀錄，用於登入與會員服務。第三方登入不接收對方密碼。簽約資料可稍後補齊。
        </p>
      ) : null}

      {hint ? <p className="login-hint">{hint}</p> : null}
    </section>
  );
}
