export default function AboutPage() {
  return (
    <div className="mx-auto max-w-3xl px-4 py-12">
      <p className="text-sm tracking-[0.2em] text-ocean">認識莊園</p>
      <h1 className="mt-2 text-4xl font-bold">面朝太平洋的日常，不是短暫停留</h1>
      <p className="mt-5 text-xl leading-8">
        太平洋莊園位於花蓮豐濱、花東海岸公路靠海一側。800+ 生活家是生活方式聯盟概念：先了解適配，再決定是否加入月租會員網絡。
      </p>
      <section className="mt-10 grid gap-6">
        <article className="rounded-[2rem] bg-white p-6 shadow-card">
          <h2 className="text-2xl font-bold">海岸節奏</h2>
          <p className="mt-3 leading-8">
            園區面向太平洋，臨海、依山、傍溪。清晨看日出，午後在平台避曬看海，夜裡吹山風。這裡談的是把生活安頓下來。
          </p>
        </article>
        <article id="life" className="rounded-[2rem] bg-white p-6 shadow-card">
          <h2 className="text-2xl font-bold">住民生活</h2>
          <p className="mt-3 leading-8">
            散步、園藝、交誼廳泡茶、公共廚房簡單料理。萬歲咖啡在週末對外，平日是住民共享空間；長者可依體力自願當一日店長。
          </p>
        </article>
        <article className="rounded-[2rem] bg-white p-6 shadow-card">
          <h2 className="text-2xl font-bold">我們刻意不這樣賣</h2>
          <p className="mt-3 leading-8">
            不把體驗當成主打商品，也不用咖啡禮包兌換停留。想長住，請先走了解問卷與月租簽約；只想喝咖啡，走萬歲咖啡即可。
          </p>
        </article>
      </section>
    </div>
  );
}
