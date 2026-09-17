import type { Metadata } from "next";
import { Noto_Sans_TC } from "next/font/google";
import { PreviewTools } from "@/components/PreviewTools";
import { SessionProvider } from "@/components/SessionProvider";
import { SiteFooter } from "@/components/SiteFooter";
import { SiteHeader } from "@/components/SiteHeader";
import "./globals.css";

const noto = Noto_Sans_TC({
  subsets: ["latin"],
  weight: ["400", "500", "700"],
  variable: "--font-noto",
  display: "swap",
});

export const metadata: Metadata = {
  title: "太平洋莊園｜800+ 生活家",
  description:
    "花蓮豐濱的月租生活網絡。先了解是否適合，再申請月租會員；體驗僅供會員安排。萬歲咖啡為獨立選物。",
};

export default function RootLayout({ children }: { children: React.ReactNode }) {
  return (
    <html lang="zh-Hant">
      <body className={`${noto.variable} font-sans text-lg antialiased`}>
        <SessionProvider>
          <SiteHeader />
          <main className="min-h-screen">{children}</main>
          <SiteFooter />
          <PreviewTools />
        </SessionProvider>
      </body>
    </html>
  );
}
