"use client";

import { useRouter, useSearchParams } from "next/navigation";
import { useEffect, useMemo, useState } from "react";
import { memberPlans } from "@/data/plans";
import type { MemberPlanId } from "@/lib/types";
import { useSession } from "./SessionProvider";

export function MembershipApplyForm() {
  const router = useRouter();
  const params = useSearchParams();
  const { session, refresh } = useSession();
  const defaultPlan = (params.get("plan") as MemberPlanId) || "seascape_list";
  const [planId, setPlanId] = useState<MemberPlanId>(
    memberPlans.some((item) => item.id === defaultPlan) ? defaultPlan : "seascape_list",
  );
  const [name, setName] = useState(session?.name || "");
  const [phone, setPhone] = useState(session?.phone || "");
  const [email, setEmail] = useState(session?.email || "");
  const [acceptContract, setAcceptContract] = useState(false);
  const [noInstallmentAck, setNoInstallmentAck] = useState(false);
  const [error, setError] = useState("");
  const [busy, setBusy] = useState(false);

  useEffect(() => {
    if (session?.name) setName(session.name);
    if (session?.phone) setPhone(session.phone);
    if (session?.email) setEmail(session.email);
  }, [session]);

  const plan = useMemo(() => memberPlans.find((item) => item.id === planId), [planId]);
  const passed = session?.quizOutcome === "pass";

  async function submit(simulateSign: boolean) {
    setBusy(true);
    setError("");
    const res = await fetch("/api/membership", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        name,
        phone,
        email,
        planId,
        acceptContract,
        noInstallmentAck,
        simulateSign,
      }),
    });
    const data = await res.json();
    setBusy(false);
    if (!res.ok) {
      setError(data.error || "送出失敗");
      return;
    }
    await refresh();
    router.push("/membership/success");
  }

  if (!passed) {
    return (
      <div className="rounded-[2rem] bg-white p-6 shadow-card">
        <h2 className="text-2xl font-bold">需先通過了解問卷</h2>
        <p className="mt-3 leading-8">
          月租邀請只發給問卷結果為「通過」的人。待人工或未通過者，請先等待聯繫或改逛介紹與咖啡。
        </p>
        <a href="/quiz" className="btn-primary mt-6">
          前往了解問卷
        </a>
      </div>
    );
  }

  return (
    <form
      className="grid gap-5 rounded-[2rem] bg-white p-6 shadow-card"
      onSubmit={(event) => {
        event.preventDefault();
        void submit(false);
      }}
    >
      <label className="grid gap-2">
        <span>選擇方案</span>
        <select className="field" value={planId} onChange={(event) => setPlanId(event.target.value as MemberPlanId)}>
          {memberPlans.map((item) => (
            <option key={item.id} value={item.id}>
              {item.name}｜{item.monthlyLabel}
            </option>
          ))}
        </select>
      </label>
      {plan ? <p className="rounded-2xl bg-cream px-4 py-3 leading-8">{plan.monthlyLabel}。須簽約，不做分期。</p> : null}
      <label className="grid gap-2">
        <span>姓名</span>
        <input className="field" required value={name} onChange={(event) => setName(event.target.value)} />
      </label>
      <label className="grid gap-2">
        <span>電話</span>
        <input className="field" required inputMode="tel" value={phone} onChange={(event) => setPhone(event.target.value)} />
      </label>
      <label className="grid gap-2">
        <span>電子信箱（選填）</span>
        <input className="field" type="email" value={email} onChange={(event) => setEmail(event.target.value)} />
      </label>
      <label className="flex items-start gap-3 rounded-2xl bg-cream p-4">
        <input
          type="checkbox"
          className="mt-1 h-5 w-5"
          checked={acceptContract}
          onChange={(event) => setAcceptContract(event.target.checked)}
        />
        <span>我了解月租必須簽約，本頁是簽約申請，正式合約仍待專人確認。</span>
      </label>
      <label className="flex items-start gap-3 rounded-2xl bg-cream p-4">
        <input
          type="checkbox"
          className="mt-1 h-5 w-5"
          checked={noInstallmentAck}
          onChange={(event) => setNoInstallmentAck(event.target.checked)}
        />
        <span>我了解月租依合約收款，不做分期付款。</span>
      </label>
      {error ? <p className="text-coral">{error}</p> : null}
      <button type="submit" className="btn-primary" disabled={busy}>
        送出簽約申請
      </button>
      <button type="button" className="btn-secondary" disabled={busy} onClick={() => void submit(true)}>
        送出並模擬完成簽約（成為會員）
      </button>
    </form>
  );
}
