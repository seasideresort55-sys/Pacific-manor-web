"use client";

import { useRouter } from "next/navigation";
import { useEffect, useState } from "react";
import { useSession } from "./SessionProvider";

export function ExperienceForm() {
  const router = useRouter();
  const { session } = useSession();
  const [name, setName] = useState(session?.name || "");
  const [phone, setPhone] = useState(session?.phone || "");
  const [guestCount, setGuestCount] = useState(1);
  const [startDate, setStartDate] = useState("");
  const [endDate, setEndDate] = useState("");
  const [notes, setNotes] = useState("");
  const [error, setError] = useState("");
  const [busy, setBusy] = useState(false);

  useEffect(() => {
    if (session?.name) setName(session.name);
    if (session?.phone) setPhone(session.phone);
  }, [session]);

  async function submit() {
    setBusy(true);
    setError("");
    const res = await fetch("/api/experience", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ name, phone, guestCount, startDate, endDate, notes }),
    });
    const data = await res.json();
    setBusy(false);
    if (!res.ok) {
      setError(data.error || "送出失敗");
      return;
    }
    router.push(`/member/experience/success?id=${data.record.id}`);
  }

  return (
    <form
      className="grid gap-5 rounded-[2rem] bg-white p-6 shadow-card"
      onSubmit={(event) => {
        event.preventDefault();
        void submit();
      }}
    >
      <p className="rounded-2xl bg-cream px-4 py-3 leading-8">
        最小體驗單位可依三天兩夜的生活節奏來想，但這是會員權益確認，不是免費住房促銷，也沒有可單賣的體驗商品。
      </p>
      <label className="grid gap-2">
        <span>姓名</span>
        <input className="field" required value={name} onChange={(event) => setName(event.target.value)} />
      </label>
      <label className="grid gap-2">
        <span>電話</span>
        <input className="field" required inputMode="tel" value={phone} onChange={(event) => setPhone(event.target.value)} />
      </label>
      <label className="grid gap-2">
        <span>入住人</span>
        <input
          className="field"
          type="number"
          min={1}
          max={4}
          value={guestCount}
          onChange={(event) => setGuestCount(Number(event.target.value))}
        />
      </label>
      <div className="grid gap-4 sm:grid-cols-2">
        <label className="grid gap-2">
          <span>希望開始日</span>
          <input className="field" type="date" required value={startDate} onChange={(event) => setStartDate(event.target.value)} />
        </label>
        <label className="grid gap-2">
          <span>希望結束日</span>
          <input className="field" type="date" required value={endDate} onChange={(event) => setEndDate(event.target.value)} />
        </label>
      </div>
      <label className="grid gap-2">
        <span>備註</span>
        <textarea className="field min-h-32" value={notes} onChange={(event) => setNotes(event.target.value)} />
      </label>
      {error ? <p className="text-coral">{error}</p> : null}
      <button type="submit" className="btn-primary" disabled={busy}>
        送出體驗安排
      </button>
    </form>
  );
}
