import { NextResponse } from "next/server";
import { emptySession, readSession, resetSession, writeSession } from "@/lib/session";
import type { AuthProvider, MemberPlanId, QuizOutcome, SessionState } from "@/lib/types";

function publicSession<T extends { pendingOtp: { code?: string } | null }>(session: T) {
  if (!session.pendingOtp) return session;
  const { code: _code, ...pending } = session.pendingOtp;
  return { ...session, pendingOtp: pending };
}

export async function GET() {
  const session = await readSession();
  return NextResponse.json(publicSession(session));
}

type PatchBody = {
  reset?: boolean;
  name?: string;
  phone?: string;
  email?: string;
  quizOutcome?: QuizOutcome | null;
  isMember?: boolean;
  memberPlan?: MemberPlanId | null;
  authVerified?: boolean;
  authProvider?: AuthProvider | null;
};

export async function POST(request: Request) {
  const body = (await request.json()) as PatchBody;
  if (body.reset) {
    await resetSession();
    return NextResponse.json(publicSession(emptySession()));
  }

  const current = await readSession();
  const next: SessionState = {
    ...current,
    name: body.name ?? current.name,
    phone: body.phone ?? current.phone,
    email: body.email ?? current.email,
    quizOutcome: body.quizOutcome === undefined ? current.quizOutcome : body.quizOutcome,
    isMember: body.isMember ?? current.isMember,
    memberPlan: body.memberPlan === undefined ? current.memberPlan : body.memberPlan,
    authVerified: body.authVerified ?? current.authVerified,
    authProvider: body.authProvider === undefined ? current.authProvider : body.authProvider,
  };
  await writeSession(next);
  return NextResponse.json(publicSession(next));
}
