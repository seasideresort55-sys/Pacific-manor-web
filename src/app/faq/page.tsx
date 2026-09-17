import { faqs } from "@/data/faq";

export default function FaqPage() {
  return (
    <div className="mx-auto max-w-3xl px-4 py-12">
      <h1 className="text-4xl font-bold">常見問題</h1>
      <div className="mt-8 grid gap-4">
        {faqs.map((item) => (
          <article key={item.q} className="rounded-[2rem] bg-white p-6 shadow-card">
            <h2 className="text-2xl font-bold">{item.q}</h2>
            <p className="mt-3 leading-8">{item.a}</p>
          </article>
        ))}
      </div>
    </div>
  );
}
