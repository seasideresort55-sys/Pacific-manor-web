"use client";

import Link from "next/link";
import { useSearchParams } from "next/navigation";
import { useState } from "react";
import { AUTH_PROVIDER_LABEL, MOCK_OTP_CODE } from "@/lib/auth";
import type { AuthProvider } from "@/lib/types";
import { useSession } from "./SessionProvider";

type Channel = "email" | "sms";

export function MemberVerify() {
  const params = useSearchParams();
  const plan = params.get("plan");
  const { session, loading, refresh } = useSession();
  const [channel, setChannel] = useState<Channel>("sms");
  const [destination, setDestination] = useState("");
  const [code, setCode] = useState("");
  const [sentTo, setSentTo] = useState("");
  const [previewCode, setPreviewCode] = useState("");
  const [message, setMessage] = useState("");
  const [error, setError] = useState("");
  const [busy, setBusy] = useState(false);
  const [consent, setConsent] = useState(false);
  const [redo, setRedo] = useState(false);

  const applyHref = plan ? `/membership/apply?plan=${plan}` : "/membership/apply";

  async function post(body: Record<string, unknown>) {
    setBusy(true);
    setError("");
    setMessage("");
    const res = await fetch("/api/auth", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(body),
    });
    const data = await res.json();
    setBusy(false);
    if (!res.ok) {
      setError(data.error || "驗證失敗");
      return null;
    }
    await refresh();
    return data;
  }

  async function oauth(provider: Extract<AuthProvider, "google" | "line" | "apple">) {
    if (!consent) {
      setError("請先勾選資料使用說明，才能用社群帳號登入。");
      return;
    }
    const data = await post({ action: "oauth", provider });
    if (data) {
      setRedo(false);
      setMessage(data.message);
    }
  }

  async function sendOtp() {
    const data = await post({ action: "send_otp", channel, destination });
    if (!data) return;
    setSentTo(data.destination);
    setPreviewCode(data.previewCode || MOCK_OTP_CODE);
    setCode("");
    setMessage(data.message);
  }

  async function verifyOtp() {
    const data = await post({
      action: "verify_otp",
      channel,
      destination: sentTo || destination,
      code,
    });
    if (data) {
      setRedo(false);
      setMessage(data.message);
    }
  }

  function switchChannel(next: Channel) {
    setChannel(next);
    setDestination("");
    setCode("");
    setSentTo("");
    setPreviewCode("");
    setError("");
    setMessage("");
  }

  if (loading) return <p className="px-4 py-8">讀取驗證狀態中…</p>;

  if (session?.authVerified && !redo) {
    const nextLabel = session.quizOutcome === "pass" ? "繼續簽約申請" : "先去了解是否適合";
    const nextHref = session.quizOutcome === "pass" ? applyHref : "/quiz";
    return (
      <div className="rounded-[20px] border border-[#e7edeb] bg-white p-6 shadow-card">
        <p className="text-sm tracking-[0.18em] text-ocean">太平洋莊園 · PACIFIC MANOR</p>
        <h2 className="mt-2 text-3xl font-bold">歡迎回來</h2>
        <p className="mt-3 text-xl leading-8">
          已用{session.authProvider ? AUTH_PROVIDER_LABEL[session.authProvider] : "已驗證方式"}完成登入。入住／簽約資料可稍後補齊。
        </p>
        <ul className="mt-5 grid gap-2 leading-8">
          <li>姓名：{session.name || "尚未填"}</li>
          <li>電子郵件：{session.email || "尚未填"}</li>
          <li>手機：{session.phone || "尚未填"}</li>
        </ul>
        <Link href={nextHref} className="btn-primary mt-6 !w-full">
          {nextLabel}
        </Link>
        <button type="button" className="btn-secondary mt-3 !w-full" onClick={() => setRedo(true)}>
          改用其他方式再驗證一次
        </button>
      </div>
    );
  }

  return (
    <div className="grid gap-4">
      <section className="rounded-[20px] border border-[#e7edeb] bg-white p-6 shadow-card md:p-8">
        <p className="text-sm tracking-[0.18em] text-ocean">太平洋莊園 · PACIFIC MANOR</p>
        <h2 className="mt-2 text-[1.75rem] font-bold leading-tight md:text-3xl">登入或註冊</h2>
        <p className="mt-3 text-lg leading-8 text-[#3d5a66]">用 Email 或手機收取驗證碼，不用記密碼。</p>

        <div className="mt-5 grid grid-cols-2 gap-2">
          <button
            type="button"
            className={`min-h-12 rounded-xl border px-3 text-base font-semibold ${
              channel === "sms" ? "border-ocean bg-[#e7f0f3] text-deep" : "border-sand bg-white text-ocean"
            }`}
            onClick={() => switchChannel("sms")}
          >
            手機簡訊
          </button>
          <button
            type="button"
            className={`min-h-12 rounded-xl border px-3 text-base font-semibold ${
              channel === "email" ? "border-ocean bg-[#e7f0f3] text-deep" : "border-sand bg-white text-ocean"
            }`}
            onClick={() => switchChannel("email")}
          >
            電子郵件
          </button>
        </div>

        {!sentTo ? (
          <div className="mt-5 grid gap-4">
            {channel === "sms" ? (
              <label className="grid gap-2">
                <span className="font-bold">手機號碼</span>
                <input
                  className="field"
                  inputMode="tel"
                  autoComplete="tel"
                  placeholder="0912-345-678"
                  value={destination}
                  onChange={(event) => setDestination(event.target.value)}
                />
              </label>
            ) : (
              <label className="grid gap-2">
                <span className="font-bold">電子郵件 Email</span>
                <input
                  className="field"
                  type="email"
                  inputMode="email"
                  autoComplete="email"
                  placeholder="you@example.com"
                  value={destination}
                  onChange={(event) => setDestination(event.target.value)}
                />
              </label>
            )}
            <button type="button" className="btn-primary !w-full" disabled={busy} onClick={() => void sendOtp()}>
              {busy ? "送出中…" : channel === "sms" ? "繼續，送出簡訊驗證碼" : "繼續，寄送驗證碼"}
            </button>
            <p className="text-[15px] leading-7 text-[#60706d]">
              首次使用可建立會員；簽約資料可以稍後補齊。
              {channel === "sms" ? ` 預覽模式簡訊閘道為模擬，驗證碼固定 ${MOCK_OTP_CODE}。` : ""}
            </p>
          </div>
        ) : (
          <div className="mt-5 grid gap-4">
            <p className="text-[15px] leading-7 text-[#60706d]">
              已送至 {sentTo}。預覽模式固定驗證碼：{previewCode || MOCK_OTP_CODE}
            </p>
            <label className="grid gap-2">
              <span className="font-bold">6 位數驗證碼</span>
              <input
                className="field text-center text-[28px] tracking-[0.4em]"
                inputMode="numeric"
                autoComplete="one-time-code"
                pattern="[0-9]{6}"
                maxLength={6}
                placeholder="------"
                value={code}
                onChange={(event) => setCode(event.target.value.replace(/\D/g, "").slice(0, 6))}
              />
            </label>
            <p className="text-[15px] leading-7 text-[#60706d]">驗證碼 10 分鐘內有效。正式簡訊／郵件閘道尚未接入。</p>
            <button type="button" className="btn-primary !w-full" disabled={busy || code.length !== 6} onClick={() => void verifyOtp()}>
              驗證並繼續
            </button>
            <div className="flex flex-wrap gap-4">
              <button
                type="button"
                className="text-left text-base font-semibold text-ocean underline underline-offset-4"
                onClick={() => {
                  setSentTo("");
                  setCode("");
                }}
              >
                {channel === "sms" ? "更改手機號碼" : "更改 Email"}
              </button>
              <button
                type="button"
                className="text-left text-base font-semibold text-ocean underline underline-offset-4"
                disabled={busy}
                onClick={() => void sendOtp()}
              >
                重新寄送驗證碼
              </button>
            </div>
          </div>
        )}
      </section>

      <section id="socialLogin" className="rounded-[20px] border border-[#e7edeb] bg-white p-6 shadow-card md:px-8">
        <h2 className="text-xl font-bold">其他登入方式</h2>
        <p className="mt-2 text-[15px] leading-7 text-[#60706d]">已有帳號，也可直接用社群登入／綁定。</p>
        <label className="mt-4 flex items-start gap-3 text-base leading-7">
          <input
            type="checkbox"
            className="mt-1 h-6 w-6 min-w-6"
            checked={consent}
            onChange={(event) => setConsent(event.target.checked)}
          />
          <span>我已閱讀資料使用說明，同意用於會員註冊、登入及會員服務。</span>
        </label>
        <div className="mt-4 grid gap-2.5">
          <button
            type="button"
            className="inline-flex min-h-[54px] w-full items-center justify-center gap-3 rounded-[10px] border border-[#ccd8d5] bg-white text-lg font-semibold text-[#20312f]"
            disabled={busy}
            onClick={() => void oauth("google")}
          >
            <span className="inline-flex h-8 w-8 items-center justify-center rounded-full bg-[#4285F4] text-white">G</span>
            使用 Google 登入／綁定
          </button>
          <button
            type="button"
            className="inline-flex min-h-[54px] w-full items-center justify-center gap-3 rounded-[10px] border border-[#06C755] bg-[#06C755] text-lg font-semibold text-white"
            disabled={busy}
            onClick={() => void oauth("line")}
          >
            <span className="inline-flex h-8 w-8 items-center justify-center rounded-full bg-white font-bold text-[#06C755]">L</span>
            使用 LINE 登入／綁定
          </button>
          <button
            type="button"
            className="inline-flex min-h-[54px] w-full items-center justify-center gap-3 rounded-[10px] border border-black bg-black text-lg font-semibold text-white"
            disabled={busy}
            onClick={() => void oauth("apple")}
          >
            <span className="text-xl"></span>
            使用 Apple 登入／綁定
          </button>
        </div>
        <details className="mt-4 text-[15px] leading-7 text-[#60706d]">
          <summary className="cursor-pointer font-semibold text-deep">會員資料使用說明</summary>
          <p className="mt-2">
            第三方服務處理帳號驗證，本站不接收第三方帳號密碼。本站使用服務回傳的帳號識別與 Email／手機建立或綁定會員。正式 OAuth 與簡訊閘道本版為模擬。資料查詢、更正或移除請聯絡莊園。
          </p>
        </details>
      </section>

      {error ? <p className="rounded-xl bg-[#fdeaea] px-4 py-3 text-[#8b1d1d]">{error}</p> : null}
      {message ? <p className="rounded-xl bg-[#e8f7ed] px-4 py-3">{message}</p> : null}
    </div>
  );
}
