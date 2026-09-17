"use client";

import Link from "next/link";
import { useSession } from "@/components/SessionProvider";

export default function MemberPage() {
  const { session, loading } = useSession();

  if (loading) return <p className="px-4 py-16">讀取會員狀態中…</p>;

  if (!session?.isMember) {
    return (
      <div className="mx-auto max-w-3xl px-4 py-12">
        <h1 className="text-4xl font-bold">會員專區尚未開啟</h1>
        <p className="mt-4 text-xl leading-8">
          體驗安排只提供給月租會員。請先完成了解問卷；通過後申請簽約，狀態變成會員才能進入。
        </p>
        <div className="mt-8 flex flex-col gap-3 sm:flex-row">
          <Link href="/quiz" className="btn-primary">
            了解是否適合
          </Link>
          <Link href="/coffee" className="btn-secondary">
            改逛萬歲咖啡
          </Link>
        </div>
      </div>
    );
  }

  return (
    <div className="mx-auto max-w-3xl px-4 py-12">
      <p className="text-ocean">月租會員</p>
      <h1 className="mt-2 text-4xl font-bold">你好，{session.name || "會員"}</h1>
      <p className="mt-4 text-xl leading-8">
        這裡沒有促銷日曆。你可以提出希望的體驗日期區間，由莊園確認後安排。
      </p>
      <Link href="/member/experience" className="btn-primary mt-8">
        預約體驗／體驗安排
      </Link>
    </div>
  );
}
