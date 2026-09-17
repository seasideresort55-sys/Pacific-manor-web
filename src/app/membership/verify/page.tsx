import { Suspense } from "react";
import { MemberVerify } from "@/components/MemberVerify";

export default function MembershipVerifyPage() {
  return (
    <div className="mx-auto max-w-3xl px-4 py-12">
      <h1 className="text-4xl font-bold text-deep">填入會員資料</h1>
      <p className="mt-4 text-xl leading-8 text-[#3d5a66]">
        這一步是驗證／登入，不是手填當唯一入口。可用 Google、LINE、Apple、電子郵件或手機簡訊 OTP。
      </p>
      <div className="mt-8">
        <Suspense fallback={<p>載入驗證頁中…</p>}>
          <MemberVerify />
        </Suspense>
      </div>
    </div>
  );
}
