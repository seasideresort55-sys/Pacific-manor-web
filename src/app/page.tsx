import Link from "next/link";

const secondary = [
  { href: "/about", title: "認識莊園", body: "花蓮豐濱、海岸節奏、住民日常與安心安排。" },
  { href: "/coffee", title: "萬歲咖啡", body: "可宅配或超商取貨的獨立選物，不綁體驗。" },
  { href: "/about#life", title: "住民生活", body: "交誼、園藝、散步與自己當店長的咖啡時光。" },
];

export default function HomePage() {
  return (
    <div>
      <section className="wave-band">
        <div className="mx-auto grid max-w-6xl gap-10 px-4 py-14 md:grid-cols-[1.2fr_0.8fr] md:items-center">
          <div>
            <p className="text-sm tracking-[0.28em] text-ocean">花蓮豐濱 · 800+ 生活家</p>
            <h1 className="mt-3 text-4xl font-bold leading-tight text-deep md:text-5xl">
              先了解自己，再決定要不要過月租的海邊生活
            </h1>
            <p className="mt-5 max-w-xl text-xl leading-8 text-[#3d5a66]">
              太平洋莊園是熟齡與遠端工作者的生活方式網絡。主商品是月租會員；體驗是會員權益，不是可單賣的停留商品。了解與體驗以 50 歲以上、生活可自理為原則；長住入住原則年滿 60 歲。
            </p>
            <div className="mt-8 flex flex-col gap-3 sm:flex-row">
              <Link href="/quiz" className="btn-primary">
                了解我是否適合長住／月租會員
              </Link>
              <Link href="/membership" className="btn-secondary">
                先看月租方案
              </Link>
            </div>
          </div>
          <div className="rounded-[2rem] bg-deep p-6 text-cream shadow-card">
            <p className="text-sm tracking-widest text-mist">不是什麼</p>
            <ul className="mt-4 grid gap-3 text-lg leading-7">
              <li>不是民宿式短暫停留</li>
              <li>不是機構式養老</li>
              <li>體驗不是主打商品</li>
              <li>買咖啡不會換成住宿權益</li>
            </ul>
          </div>
        </div>
      </section>

      <section className="mx-auto max-w-6xl px-4 py-14">
        <h2 className="text-3xl font-bold text-deep">客人進站怎麼走</h2>
        <ol className="mt-6 grid gap-4 md:grid-cols-3">
          {[
            ["01 了解", "約 6 題問卷，第 1 題會說明生活概念。結果為通過、待人工或未通過。"],
            ["02 月租", "通過後先驗證（Google／LINE／Apple／電子郵件／簡訊），再選方案簽約。不做分期。"],
            ["03 體驗或咖啡", "會員可安排體驗；任何人都能選購萬歲咖啡。"],
          ].map(([title, body]) => (
            <li key={title} className="rounded-3xl bg-white p-6 shadow-card">
              <p className="text-ocean">{title}</p>
              <p className="mt-3 leading-8 text-deep">{body}</p>
            </li>
          ))}
        </ol>
      </section>

      <section className="mx-auto max-w-6xl px-4 pb-16">
        <h2 className="text-3xl font-bold text-deep">也歡迎先認識這裡</h2>
        <div className="mt-6 grid gap-4 md:grid-cols-3">
          {secondary.map((item) => (
            <Link key={item.href} href={item.href} className="rounded-3xl border-2 border-sand bg-white p-6 hover:border-ocean">
              <h3 className="text-2xl font-bold text-deep">{item.title}</h3>
              <p className="mt-3 leading-8 text-[#3d5a66]">{item.body}</p>
            </Link>
          ))}
        </div>
      </section>
    </div>
  );
}
