import { Suspense } from "react";
import { MemberVerify } from "@/components/MemberVerify";

export default function MembershipVerifyPage() {
  return (
    <div className="login-shell">
      <div className="mx-auto w-full max-w-[440px] px-4 py-10 md:max-w-[480px] md:py-16">
        <Suspense fallback={<p className="text-lg text-ocean">載入登入頁…</p>}>
          <MemberVerify />
        </Suspense>
      </div>
    </div>
  );
}
