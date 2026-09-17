import Link from "next/link";
import { memberPlans } from "@/data/plans";

export default function MembershipPage() {
  return (
    <div className="mx-auto max-w-6xl px-4 py-12">
      <p className="text-sm tracking-[0.2em] text-ocean">主商品</p>
      <h1 className="mt-2 text-4xl font-bold text-deep">月租會員方案</h1>
      <p className="mt-4 max-w-2xl text-xl leading-8 text-[#3d5a66]">
        月租必須簽約，依合約收款，不做分期。通過了解問卷後，先用 Google／LINE／Apple／電子郵件／簡訊驗證，再送出簽約申請。
      </p>

      <div className="mt-8 grid gap-4 md:grid-cols-3">
        {memberPlans.map((plan) => (
          <article key={plan.id} className="flex flex-col rounded-[2rem] bg-white p-6 shadow-card">
            <p className="text-ocean">{plan.tag}</p>
            <h2 className="mt-2 text-2xl font-bold">{plan.name}</h2>
            <p className="mt-4 text-xl font-semibold text-deep">{plan.monthlyLabel}</p>
            <ul className="mt-4 grid gap-2 leading-8 text-[#3d5a66]">
              {plan.notes.map((note) => (
                <li key={note}>· {note}</li>
              ))}
            </ul>
            <Link href={`/membership/verify?plan=${plan.id}`} className="btn-primary mt-auto">
              驗證後申請此方案
            </Link>
          </article>
        ))}
      </div>

      <p className="mt-8 leading-8 text-[#3d5a66]">
        體驗安排屬於會員權益，不會做成可單獨購買的商品，也不會用咖啡禮包兌換。
      </p>
    </div>
  );
}
