"use client";

import { useRouter } from "next/navigation";
import { useMemo, useState } from "react";
import {
  budgetOptions,
  durationOptions,
  identityOptions,
  lifeOptions,
  livingOptions,
  priorityOptions,
  whenOptions,
} from "@/data/quiz-questions";
import type { Identity, Priority, QuizAnswers } from "@/lib/types";
import { useSession } from "./SessionProvider";

const steps = [
  { key: "identity", title: "你的身分", hint: "至少勾選一項。這是進入月租生活網絡的硬條件。" },
  { key: "life", title: "你嚮往的生活概念", hint: "每一項都附說明。太平洋莊園以海邊慢生活為主。" },
  { key: "duration", title: "你想住多久", hint: "想先體驗可以，但須接受體驗只提供給月租會員。" },
  { key: "living", title: "你打算怎麼住", hint: "一人、伴侶或朋友都可以，不確定也沒關係。" },
  { key: "budget", title: "每月預算大概多少", hint: "先對齊方案門檻，不是現場議價。" },
  { key: "priority", title: "你最在意什麼", hint: "可選 1 到 3 項。" },
  { key: "when", title: "希望何時開始", hint: "有窗口較容易進入方案說明。" },
  { key: "contact", title: "若需聯繫，怎麼找到你", hint: "待人工結果會用這組資料聯絡。若通過，下一步會用 Google／LINE／Apple／電子郵件或簡訊驗證，不把這裡當唯一入口。" },
] as const;

export function QuizForm() {
  const router = useRouter();
  const { refresh } = useSession();
  const [step, setStep] = useState(0);
  const [error, setError] = useState("");
  const [busy, setBusy] = useState(false);
  const [answers, setAnswers] = useState<QuizAnswers>({
    identities: [],
    life: "coastal",
    duration: "try_then_stay",
    livingWith: "solo",
    budget: "band_40_60",
    priorities: ["ocean_pace"],
    when: "within_quarter",
    name: "",
    phone: "",
  });

  const progress = useMemo(() => `${step + 1} / ${steps.length}`, [step]);

  function toggleIdentity(id: Identity) {
    setAnswers((prev) => ({
      ...prev,
      identities: prev.identities.includes(id)
        ? prev.identities.filter((item) => item !== id)
        : [...prev.identities, id],
    }));
  }

  function togglePriority(id: Priority) {
    setAnswers((prev) => {
      const has = prev.priorities.includes(id);
      if (has) return { ...prev, priorities: prev.priorities.filter((item) => item !== id) };
      if (prev.priorities.length >= 3) return prev;
      return { ...prev, priorities: [...prev.priorities, id] };
    });
  }

  function validateStep() {
    const current = steps[step].key;
    if (current === "identity" && answers.identities.length === 0) {
      return "請至少勾選一項身分。若都不符合，問卷仍可送出，結果會是未通過。";
    }
    if (current === "priority" && (answers.priorities.length < 1 || answers.priorities.length > 3)) {
      return "請選擇 1 至 3 項。";
    }
    return "";
  }

  async function submit() {
    setBusy(true);
    setError("");
    const res = await fetch("/api/quiz", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(answers),
    });
    const data = await res.json();
    setBusy(false);
    if (!res.ok) {
      setError(data.error || "送出失敗");
      return;
    }
    await refresh();
    router.push("/quiz/result");
  }

  return (
    <div className="rounded-[2rem] bg-white p-5 shadow-card md:p-8">
      <div className="mb-6 flex items-center justify-between gap-3">
        <p className="text-ocean">第 {progress} 題</p>
        <div className="h-2 flex-1 rounded-full bg-sand">
          <div
            className="h-2 rounded-full bg-ocean"
            style={{ width: `${((step + 1) / steps.length) * 100}%` }}
          />
        </div>
      </div>
      <h1 className="text-3xl font-bold text-deep">{steps[step].title}</h1>
      <p className="mt-3 leading-8 text-[#3d5a66]">{steps[step].hint}</p>

      <div className="mt-6 grid gap-3">
        {steps[step].key === "identity"
          ? identityOptions.map((option) => (
              <button
                key={option.id}
                type="button"
                className="choice"
                data-active={answers.identities.includes(option.id)}
                onClick={() => toggleIdentity(option.id)}
              >
                <p className="text-xl font-semibold">{option.title}</p>
                <p className="mt-2 leading-7 text-[#3d5a66]">{option.description}</p>
              </button>
            ))
          : null}

        {steps[step].key === "life"
          ? lifeOptions.map((option) => (
              <button
                key={option.id}
                type="button"
                className="choice"
                data-active={answers.life === option.id}
                onClick={() => setAnswers((prev) => ({ ...prev, life: option.id }))}
              >
                <p className="text-xl font-semibold">{option.title}</p>
                <p className="mt-2 leading-7 text-[#3d5a66]">{option.description}</p>
              </button>
            ))
          : null}

        {steps[step].key === "duration"
          ? durationOptions.map((option) => (
              <button
                key={option.id}
                type="button"
                className="choice"
                data-active={answers.duration === option.id}
                onClick={() => setAnswers((prev) => ({ ...prev, duration: option.id }))}
              >
                <p className="text-xl font-semibold">{option.title}</p>
                <p className="mt-2 leading-7 text-[#3d5a66]">{option.description}</p>
              </button>
            ))
          : null}

        {steps[step].key === "living"
          ? livingOptions.map((option) => (
              <button
                key={option.id}
                type="button"
                className="choice"
                data-active={answers.livingWith === option.id}
                onClick={() => setAnswers((prev) => ({ ...prev, livingWith: option.id }))}
              >
                <p className="text-xl font-semibold">{option.title}</p>
                <p className="mt-2 leading-7 text-[#3d5a66]">{option.description}</p>
              </button>
            ))
          : null}

        {steps[step].key === "budget"
          ? budgetOptions.map((option) => (
              <button
                key={option.id}
                type="button"
                className="choice"
                data-active={answers.budget === option.id}
                onClick={() => setAnswers((prev) => ({ ...prev, budget: option.id }))}
              >
                <p className="text-xl font-semibold">{option.title}</p>
                <p className="mt-2 leading-7 text-[#3d5a66]">{option.description}</p>
              </button>
            ))
          : null}

        {steps[step].key === "priority"
          ? priorityOptions.map((option) => (
              <button
                key={option.id}
                type="button"
                className="choice"
                data-active={answers.priorities.includes(option.id)}
                onClick={() => togglePriority(option.id)}
              >
                <p className="text-xl font-semibold">{option.title}</p>
                <p className="mt-2 leading-7 text-[#3d5a66]">{option.description}</p>
              </button>
            ))
          : null}

        {steps[step].key === "when"
          ? whenOptions.map((option) => (
              <button
                key={option.id}
                type="button"
                className="choice"
                data-active={answers.when === option.id}
                onClick={() => setAnswers((prev) => ({ ...prev, when: option.id }))}
              >
                <p className="text-xl font-semibold">{option.title}</p>
                <p className="mt-2 leading-7 text-[#3d5a66]">{option.description}</p>
              </button>
            ))
          : null}

        {steps[step].key === "contact" ? (
          <div className="grid gap-4">
            <label className="grid gap-2">
              <span>姓名（選填，待人工時建議填）</span>
              <input
                className="field"
                value={answers.name || ""}
                onChange={(event) => setAnswers((prev) => ({ ...prev, name: event.target.value }))}
              />
            </label>
            <label className="grid gap-2">
              <span>電話（選填）</span>
              <input
                className="field"
                inputMode="tel"
                value={answers.phone || ""}
                onChange={(event) => setAnswers((prev) => ({ ...prev, phone: event.target.value }))}
              />
            </label>
          </div>
        ) : null}
      </div>

      {error ? <p className="mt-4 text-coral">{error}</p> : null}

      <div className="mt-8 flex flex-col gap-3 sm:flex-row">
        {step > 0 ? (
          <button type="button" className="btn-secondary" onClick={() => setStep((value) => value - 1)}>
            上一題
          </button>
        ) : null}
        {step < steps.length - 1 ? (
          <button
            type="button"
            className="btn-primary"
            onClick={() => {
              const message = validateStep();
              if (message && steps[step].key === "priority") {
                setError(message);
                return;
              }
              setError("");
              setStep((value) => value + 1);
            }}
          >
            下一題
          </button>
        ) : (
          <button type="button" className="btn-primary" disabled={busy} onClick={() => void submit()}>
            {busy ? "送出中…" : "看我的結果"}
          </button>
        )}
      </div>
    </div>
  );
}
