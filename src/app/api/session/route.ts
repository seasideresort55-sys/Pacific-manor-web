import { NextResponse } from "next/server";
import { emptySession, readSession, resetSession, writeSession } from "@/lib/session";
import type { MemberPlanId, QuizOutcome, SessionState } from "@/lib/types";

export async function GET() {
  const session = await readSession();
  return NextResponse.json(session);
}

type PatchBody = {
  reset?: boolean;
  name?: string;
  phone?: string;
  email?: string;
  quizOutcome?: QuizOutcome | null;
  isMember?: boolean;
  memberPlan?: MemberPlanId | null;
};

export async function POST(request: Request) {
  const body = (await request.json()) as PatchBody;
  if (body.reset) {
    await resetSession();
    return NextResponse.json(emptySession());
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
  };
  await writeSession(next);
  return NextResponse.json(next);
}
