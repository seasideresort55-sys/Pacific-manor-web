"use client";

import Link from "next/link";
import { useSession } from "@/components/SessionProvider";

const tone = {
  pass: "bg-[#e7f0f3]",
  review: "bg-[#f4efe4]",
  reject: "bg-[#f7ece6]",
} as const;

export default function QuizResultPage() {
  const { session, loading } = useSession();

  if (loading) {
    return <p className="px-4 py-16">讀取結果中…</p>;
  }

  if (!session?.quizOutcome) {
    return (
      <div className="mx-auto max-w-3xl px-4 py-16">
        <h1 className="text-3xl font-bold">還沒做過了解問卷</h1>
        <Link href="/quiz" className="btn-primary mt-6">
          開始了解
        </Link>
      </div>
    );
  }

  const outcome = session.quizOutcome;
  const title =
    outcome === "pass" ? "通過" : outcome === "review" ? "待人工" : "未通過";

  return (
    <div className="mx-auto max-w-3xl px-4 py-12">
      <article className={`rounded-[2rem] p-6 shadow-card md:p-8 ${tone[outcome]}`}>
        <p className="text-ocean">問卷結果</p>
        <h1 className="mt-2 text-4xl font-bold text-deep">{title}</h1>
        {outcome === "pass" ? (
          <p className="mt-4 text-xl leading-8">
            適合進一步了解月租會員；成為會員後即可安排體驗。
          </p>
        ) : null}
        {outcome === "reject" ? (
          <p className="mt-4 text-xl leading-8">
            體驗僅提供給月租會員。你仍可認識莊園、逛萬歲咖啡，只是體驗入口會關閉。
          </p>
        ) : null}
        {outcome === "review" ? (
          <p className="mt-4 text-xl leading-8">
            專人會與你聯繫後再決定下一步。在確認前，暫不開放體驗安排。
          </p>
        ) : null}
        <ul className="mt-6 grid gap-2 leading-8">
          {session.quizReasons.map((reason) => (
            <li key={reason}>· {reason}</li>
          ))}
        </ul>
      </article>

      <div className="mt-8 flex flex-col gap-3 sm:flex-row">
        {outcome === "pass" ? (
          <Link href="/membership/apply" className="btn-primary">
            查看方案並申請簽約
          </Link>
        ) : null}
        {outcome === "review" ? (
          <Link href="/contact" className="btn-primary">
            留下或確認聯絡方式
          </Link>
        ) : null}
        <Link href="/about" className="btn-secondary">
          認識莊園
        </Link>
        <Link href="/coffee" className="btn-secondary">
          逛萬歲咖啡
        </Link>
      </div>
    </div>
  );
}
