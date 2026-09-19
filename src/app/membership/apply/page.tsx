import { Suspense } from "react";
import { MembershipApplyForm } from "@/components/MembershipApplyForm";

export default function MembershipApplyPage() {
  return (
    <div className="mx-auto max-w-3xl px-4 py-12">
      <h1 className="text-4xl font-bold text-deep">月租簽約申請</h1>
      <p className="mt-4 text-xl leading-8 text-[#3d5a66]">
        這是月租簽約申請，不是線上付款。金額依方案展示；實際收款由莊園依合約確認。須先完成驗證。了解與體驗以 50 歲以上為原則；長住入住原則年滿 60 歲。
      </p>
      <div className="mt-8">
        <Suspense fallback={<p>載入表單中…</p>}>
          <MembershipApplyForm />
        </Suspense>
      </div>
    </div>
  );
}
