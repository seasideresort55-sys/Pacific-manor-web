"use client";

import Link from "next/link";
import { useSearchParams } from "next/navigation";
import { useState } from "react";
import { AUTH_PROVIDER_LABEL, MOCK_OTP_CODE } from "@/lib/auth";
import type { AuthProvider } from "@/lib/types";
import { useSession } from "./SessionProvider";

type Mode = "pick" | "sms" | "email";

export function MemberVerify() {
  const params = useSearchParams();
  const plan = params.get("plan");
  const { session, loading, refresh } = useSession();
  const [mode, setMode] = useState<Mode>("pick");
  const [destination, setDestination] = useState("");
  const [code, setCode] = useState("");
  const [sentTo, setSentTo] = useState("");
  const [previewCode, setPreviewCode] = useState("");
  const [message, setMessage] = useState("");
  const [error, setError] = useState("");
  const [busy, setBusy] = useState(false);
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
    const data = await post({ action: "oauth", provider });
    if (data) {
      setRedo(false);
      setMessage(data.message);
    }
  }

  async function sendOtp(channel: "sms" | "email") {
    const data = await post({ action: "send_otp", channel, destination });
    if (!data) return;
    setSentTo(data.destination);
    setPreviewCode(data.previewCode || "");
    setMessage(data.message);
  }

  async function verifyOtp(channel: "sms" | "email") {
    const data = await post({
      action: "verify_otp",
      channel,
      destination: sentTo || destination,
      code,
    });
    if (data) {
      setRedo(false);
      setMessage(data.message);
      setMode("pick");
    }
  }

  if (loading) return <p className="px-4 py-8">讀取驗證狀態中…</p>;

  if (session?.quizOutcome !== "pass") {
    return (
      <div className="rounded-[2rem] bg-white p-6 shadow-card">
        <h2 className="text-2xl font-bold">需先通過了解問卷</h2>
        <p className="mt-3 leading-8">會員資料驗證只開放給問卷結果為「通過」的人。</p>
        <Link href="/quiz" className="btn-primary mt-6">
          前往了解問卷
        </Link>
      </div>
    );
  }

  if (session.authVerified && !redo) {
    return (
      <div className="rounded-[2rem] bg-white p-6 shadow-card">
        <p className="text-ocean">第 8 步</p>
        <h2 className="mt-2 text-3xl font-bold">已完成驗證</h2>
        <p className="mt-3 text-xl leading-8">
          以{session.authProvider ? AUTH_PROVIDER_LABEL[session.authProvider] : "已驗證方式"}帶入會員資料，可以繼續簽約申請。
        </p>
        <ul className="mt-5 grid gap-2 leading-8">
          <li>姓名：{session.name || "尚未填"}</li>
          <li>電子郵件：{session.email || "尚未填"}</li>
          <li>手機：{session.phone || "尚未填"}</li>
        </ul>
        <Link href={applyHref} className="btn-primary mt-6">
          繼續簽約申請
        </Link>
        <button
          type="button"
          className="btn-secondary mt-3"
          onClick={() => {
            setRedo(true);
            setMode("pick");
            setMessage("");
          }}
        >
          改用其他方式再驗證一次
        </button>
      </div>
    );
  }

  return (
    <div className="grid gap-5 rounded-[2rem] bg-white p-6 shadow-card">
      <div>
        <p className="text-ocean">第 8 步｜問卷後、簽約前</p>
        <h2 className="mt-2 text-3xl font-bold">驗證並帶入會員資料</h2>
        <p className="mt-3 text-xl leading-8 text-[#3d5a66]">
          請先用以下任一方式驗證／登入。不能只靠手填表單當唯一入口。正式 OAuth 與簡訊閘道本版為模擬。
        </p>
      </div>

      {mode === "pick" ? (
        <div className="grid gap-3">
          <button type="button" className="btn-secondary !justify-start gap-3 !bg-white" disabled={busy} onClick={() => void oauth("google")}>
            <span className="inline-flex h-8 w-8 items-center justify-center rounded-full bg-[#4285F4] text-white">G</span>
            使用 Google 驗證
          </button>
          <button type="button" className="btn-secondary !justify-start gap-3 !border-[#06C755] !bg-[#06C755] !text-white" disabled={busy} onClick={() => void oauth("line")}>
            <span className="inline-flex h-8 w-8 items-center justify-center rounded-full bg-white text-[#06C755] font-bold">L</span>
            使用 LINE 驗證
          </button>
          <button type="button" className="btn-secondary !justify-start gap-3 !border-black !bg-black !text-white" disabled={busy} onClick={() => void oauth("apple")}>
            <span className="text-xl"></span>
            使用 Apple 驗證
          </button>
          <button type="button" className="btn-secondary !justify-start" disabled={busy} onClick={() => setMode("email")}>
            使用電子郵件驗證
          </button>
          <button type="button" className="btn-primary !justify-start" disabled={busy} onClick={() => setMode("sms")}>
            使用手機簡訊 OTP
          </button>
        </div>
      ) : null}

      {mode === "sms" ? (
        <div className="grid gap-4">
          <p className="text-xl font-semibold">手機簡訊驗證</p>
          <label className="grid gap-2">
            <span>手機號碼</span>
            <input
              className="field"
              inputMode="tel"
              placeholder="0912-345-678"
              value={destination}
              onChange={(event) => setDestination(event.target.value)}
            />
          </label>
          <button type="button" className="btn-primary" disabled={busy} onClick={() => void sendOtp("sms")}>
            {busy ? "送出中…" : "送出簡訊驗證碼"}
          </button>
          {sentTo ? (
            <>
              <p className="rounded-2xl bg-cream px-4 py-3 leading-8">
                已送至 {sentTo}。預覽模式固定驗證碼：{previewCode || MOCK_OTP_CODE}
              </p>
              <label className="grid gap-2">
                <span>簡訊驗證碼</span>
                <input
                  className="field"
                  inputMode="numeric"
                  maxLength={6}
                  placeholder="6 碼"
                  value={code}
                  onChange={(event) => setCode(event.target.value)}
                />
              </label>
              <button type="button" className="btn-primary" disabled={busy} onClick={() => void verifyOtp("sms")}>
                確認簡訊驗證碼
              </button>
            </>
          ) : null}
          <button type="button" className="btn-secondary" onClick={() => setMode("pick")}>
            回驗證方式
          </button>
        </div>
      ) : null}

      {mode === "email" ? (
        <div className="grid gap-4">
          <p className="text-xl font-semibold">電子郵件驗證</p>
          <label className="grid gap-2">
            <span>電子郵件</span>
            <input
              className="field"
              type="email"
              placeholder="you@example.com"
              value={destination}
              onChange={(event) => setDestination(event.target.value)}
            />
          </label>
          <button type="button" className="btn-primary" disabled={busy} onClick={() => void sendOtp("email")}>
            {busy ? "送出中…" : "寄送驗證碼"}
          </button>
          {sentTo ? (
            <>
              <p className="rounded-2xl bg-cream px-4 py-3 leading-8">
                已寄至 {sentTo}。預覽模式固定驗證碼：{previewCode || MOCK_OTP_CODE}
              </p>
              <label className="grid gap-2">
                <span>信件驗證碼</span>
                <input
                  className="field"
                  inputMode="numeric"
                  maxLength={6}
                  value={code}
                  onChange={(event) => setCode(event.target.value)}
                />
              </label>
              <button type="button" className="btn-primary" disabled={busy} onClick={() => void verifyOtp("email")}>
                確認電子郵件驗證
              </button>
            </>
          ) : null}
          <button type="button" className="btn-secondary" onClick={() => setMode("pick")}>
            回驗證方式
          </button>
        </div>
      ) : null}

      {error ? <p className="text-coral">{error}</p> : null}
      {message ? <p className="leading-8 text-ocean">{message}</p> : null}
    </div>
  );
}
