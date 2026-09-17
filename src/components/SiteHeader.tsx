"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";
import { useState } from "react";
import { useSession } from "./SessionProvider";

const links = [
  { href: "/", label: "首頁" },
  { href: "/quiz", label: "了解是否適合" },
  { href: "/membership", label: "月租會員方案" },
  { href: "/coffee", label: "萬歲咖啡" },
  { href: "/about", label: "認識莊園" },
];

function statusLabel(session: ReturnType<typeof useSession>["session"]) {
  if (!session) return "尚未開始";
  if (session.isMember) return "月租會員";
  if (session.quizOutcome === "pass") return "已通過，待簽約";
  if (session.quizOutcome === "review") return "待專人聯繫";
  if (session.quizOutcome === "reject") return "未通過";
  return "訪客";
}

export function SiteHeader() {
  const pathname = usePathname();
  const { session } = useSession();
  const [open, setOpen] = useState(false);

  return (
    <header className="sticky top-0 z-40 border-b border-sand/80 bg-cream/95 backdrop-blur">
      <div className="mx-auto flex max-w-6xl items-center justify-between gap-3 px-4 py-3">
        <Link href="/" className="min-w-0" onClick={() => setOpen(false)}>
          <p className="text-xs tracking-[0.22em] text-ocean">PACIFIC MANOR · 800+</p>
          <p className="truncate text-xl font-bold text-deep">太平洋莊園</p>
        </Link>
        <div className="flex items-center gap-2">
          <span className="hidden rounded-full bg-white px-3 py-1 text-sm text-ocean sm:inline">
            {statusLabel(session)}
          </span>
          <button
            type="button"
            className="min-h-12 rounded-2xl border-2 border-ocean px-4 text-base font-semibold text-ocean md:hidden"
            onClick={() => setOpen((value) => !value)}
            aria-expanded={open}
          >
            選單
          </button>
        </div>
        <nav className="hidden items-center gap-1 md:flex">
          {links.map((link) => (
            <Link
              key={link.href}
              href={link.href}
              className={`rounded-full px-3 py-2 text-base ${
                pathname === link.href ? "bg-ocean text-white" : "text-deep hover:bg-white"
              }`}
            >
              {link.label}
            </Link>
          ))}
          <Link
            href={session?.isMember ? "/member/experience" : "/member"}
            className="rounded-full bg-deep px-3 py-2 text-base text-white"
          >
            會員專區
          </Link>
        </nav>
      </div>
      {open ? (
        <nav className="border-t border-sand bg-cream px-4 py-3 md:hidden">
          <p className="mb-2 text-sm text-ocean">{statusLabel(session)}</p>
          <div className="grid gap-2">
            {links.map((link) => (
              <Link
                key={link.href}
                href={link.href}
                onClick={() => setOpen(false)}
                className="min-h-12 rounded-2xl bg-white px-4 py-3 text-lg"
              >
                {link.label}
              </Link>
            ))}
            <Link
              href={session?.isMember ? "/member/experience" : "/member"}
              onClick={() => setOpen(false)}
              className="min-h-12 rounded-2xl bg-deep px-4 py-3 text-lg text-white"
            >
              會員專區
            </Link>
          </div>
        </nav>
      ) : null}
    </header>
  );
}
