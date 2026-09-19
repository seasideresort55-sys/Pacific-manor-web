import Link from "next/link";

export function SiteFooter() {
  return (
    <footer className="mt-16 border-t border-sand bg-deep text-cream">
      <div className="mx-auto grid max-w-6xl gap-8 px-4 py-12 md:grid-cols-3">
        <div>
          <p className="text-sm tracking-[0.2em] text-mist">800+ 生活家</p>
          <p className="mt-2 text-2xl font-bold">太平洋莊園</p>
          <p className="mt-3 text-base leading-7 text-sand">
            花蓮豐濱的月租生活網絡。主商品是月租會員；體驗是會員權益，萬歲咖啡是獨立選物。
          </p>
        </div>
        <div className="grid gap-2 text-lg">
          <Link href="/quiz" className="underline-offset-4 hover:underline">
            了解是否適合
          </Link>
          <Link href="/membership" className="underline-offset-4 hover:underline">
            月租會員方案
          </Link>
          <Link href="/coffee" className="underline-offset-4 hover:underline">
            萬歲咖啡
          </Link>
          <Link href="/faq" className="underline-offset-4 hover:underline">
            常見問題
          </Link>
          <Link href="/contact" className="underline-offset-4 hover:underline">
            聯繫
          </Link>
        </div>
        <div className="text-base leading-8">
          <p>花蓮縣豐濱鄉豐濱村小港 55 號</p>
          <p>市話 03-879-1468</p>
          <p>手機 09-3766-7917</p>
          <p>官方 LINE @a0937667917</p>
        </div>
      </div>
    </footer>
  );
}
