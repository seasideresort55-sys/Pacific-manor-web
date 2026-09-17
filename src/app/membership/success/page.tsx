"use client";

import Link from "next/link";
import { memberPlans } from "@/data/plans";
import { useSession } from "@/components/SessionProvider";

export default function MembershipSuccessPage() {
  const { session, refresh } = useSession();
  const plan = memberPlans.find((item) => item.id === session?.memberPlan);

  async function simulateSign() {
    await fetch("/api/membership", {
      method: "PATCH",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ simulateSign: true }),
    });
    await refresh();
  }

  return (
    <div className="mx-auto max-w-3xl px-4 py-12">
      <article className="rounded-[2rem] bg-white p-6 shadow-card md:p-8">
        <p className="text-ocean">membership_application</p>
        <h1 className="mt-2 text-4xl font-bold">簽約申請已送出</h1>
        <p className="mt-4 text-xl leading-8">
          {session?.isMember
            ? "狀態已標記為月租會員，可以前往會員專區安排體驗。"
            : "目前是待簽約。正式電子簽約尚未接上；預覽可用下方按鈕模擬完成。"}
        </p>
        {plan ? <p className="mt-4 leading-8">方案：{plan.name}｜{plan.monthlyLabel}</p> : null}
      </article>
      <div className="mt-8 flex flex-col gap-3 sm:flex-row">
        {session?.isMember ? (
          <Link href="/member/experience" className="btn-primary">
            前往體驗安排
          </Link>
        ) : (
          <button type="button" className="btn-primary" onClick={() => void simulateSign()}>
            模擬完成簽約，成為會員
          </button>
        )}
        <Link href="/member" className="btn-secondary">
          會員專區
        </Link>
      </div>
    </div>
  );
}
