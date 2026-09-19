"use client";

import Link from "next/link";
import { ExperienceForm } from "@/components/ExperienceForm";
import { useSession } from "@/components/SessionProvider";

export default function ExperiencePage() {
  const { session, loading } = useSession();

  if (loading) return <p className="px-4 py-16">確認會員資格中…</p>;

  if (!session?.isMember) {
    return (
      <div className="mx-auto max-w-3xl px-4 py-12">
        <h1 className="text-4xl font-bold">無法進入體驗安排</h1>
        <p className="mt-4 text-xl leading-8">
          須先成為月租會員，才能安排體驗。這不是一般住房申請，也沒有可單獨購買的體驗商品。
        </p>
        <div className="mt-8 flex flex-col gap-3 sm:flex-row">
          <Link href="/quiz" className="btn-primary">
            先做了解問卷
          </Link>
          <Link href="/membership" className="btn-secondary">
            查看月租方案
          </Link>
          <Link href="/coffee" className="btn-secondary">
            萬歲咖啡
          </Link>
        </div>
      </div>
    );
  }

  return (
    <div className="mx-auto max-w-3xl px-4 py-12">
      <p className="text-ocean">會員專區</p>
      <h1 className="mt-2 text-4xl font-bold">體驗安排</h1>
      <p className="mt-4 text-xl leading-8 text-[#3d5a66]">
        請告訴我們希望的日期區間。送出後是體驗確認申請，不是公開日曆搶位。
      </p>
      <div className="mt-8">
        <ExperienceForm />
      </div>
    </div>
  );
}
