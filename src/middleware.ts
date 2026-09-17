import { NextResponse } from "next/server";
import type { NextRequest } from "next/server";
import { decodeSession } from "@/lib/session-codec";

export function middleware(request: NextRequest) {
  if (!request.nextUrl.pathname.startsWith("/member/experience")) {
    return NextResponse.next();
  }

  const session = decodeSession(request.cookies.get("pm_session")?.value);
  if (session?.isMember) {
    return NextResponse.next();
  }

  const url = request.nextUrl.clone();
  url.pathname = "/member";
  url.search = "";
  return NextResponse.redirect(url);
}

export const config = {
  matcher: ["/member/experience/:path*"],
};
