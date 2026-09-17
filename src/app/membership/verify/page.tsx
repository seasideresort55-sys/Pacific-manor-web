import { Suspense } from "react";
import { MemberVerify } from "@/components/MemberVerify";

export default function MembershipVerifyPage() {
  return (
    <div className="mx-auto max-w-[540px] px-4 py-10">
      <p className="text-sm text-ocean">會員與簽約申請</p>
      <h1 className="mt-1 text-3xl font-bold leading-tight text-deep">登入或建立會員</h1>
      <p className="mt-3 text-lg leading-8 text-[#3d5a66]">
        登入或建立會員，簽約資料可稍後補齊。可用手機簡訊、電子郵件，或 Google／LINE／Apple。
      </p>
      <div className="mt-6">
        <Suspense fallback={<p>載入驗證頁中…</p>}>
          <MemberVerify />
        </Suspense>
      </div>
    </div>
  );
}
