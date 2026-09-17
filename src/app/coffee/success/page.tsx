"use client";

import Link from "next/link";
import { useSearchParams } from "next/navigation";
import { Suspense } from "react";
import { formatNT } from "@/lib/format";

function SuccessBody() {
  const params = useSearchParams();
  const amount = Number(params.get("amount") || 0);
  const pay = params.get("pay");

  return (
    <article className="rounded-[2rem] bg-white p-6 shadow-card md:p-8">
      <p className="text-ocean">coffee_order</p>
      <h1 className="mt-2 text-4xl font-bold">
        {pay === "mock_cod" ? "已建立貨到付款訂單" : "模擬付款成功"}
      </h1>
      <p className="mt-4 text-xl leading-8">
        這是咖啡／伴手禮訂單。本次金額 {formatNT(amount)}。正式 LINE Pay／Apple Pay 金鑰尚未接入，本頁為整合介面 stub。
      </p>
      <p className="mt-3 leading-8">訂單不會附贈體驗或月租權益。</p>
    </article>
  );
}

export default function CoffeeSuccessPage() {
  return (
    <div className="mx-auto max-w-3xl px-4 py-12">
      <Suspense fallback={<p>讀取訂單中…</p>}>
        <SuccessBody />
      </Suspense>
      <Link href="/coffee" className="btn-secondary mt-8">
        再逛萬歲咖啡
      </Link>
    </div>
  );
}
