import Link from "next/link";

export default function ExperienceSuccessPage() {
  return (
    <div className="mx-auto max-w-3xl px-4 py-12">
      <article className="rounded-[2rem] bg-white p-6 shadow-card md:p-8">
        <p className="text-ocean">experience_request</p>
        <h1 className="mt-2 text-4xl font-bold">體驗安排已送出</h1>
        <p className="mt-4 text-xl leading-8">
          我們會以電話或 LINE 回覆可安排的時段。這份申請不會產生可轉賣的住宿憑證。
        </p>
      </article>
      <Link href="/member" className="btn-secondary mt-8">
        回會員專區
      </Link>
    </div>
  );
}
