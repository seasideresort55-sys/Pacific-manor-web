import { NextResponse } from "next/server";
import { evaluateQuiz } from "@/lib/quiz";
import { readSession, writeSession } from "@/lib/session";
import type { QuizAnswers } from "@/lib/types";

export async function POST(request: Request) {
  const answers = (await request.json()) as QuizAnswers;
  if (!Array.isArray(answers.identities) || !answers.life || !answers.duration) {
    return NextResponse.json({ error: "問卷尚未填完" }, { status: 400 });
  }
  if (!Array.isArray(answers.priorities) || answers.priorities.length < 1 || answers.priorities.length > 3) {
    return NextResponse.json({ error: "最在意的事請選 1 至 3 項" }, { status: 400 });
  }

  const result = evaluateQuiz(answers);
  const session = await readSession();
  session.quizOutcome = result.outcome;
  session.quizReasons = result.reasons;
  session.quizAnswers = answers;
  session.name = answers.name?.trim() || session.name;
  session.phone = answers.phone?.trim() || session.phone;
  if (result.outcome !== "pass") {
    session.isMember = false;
    session.memberPlan = null;
    session.membershipApplicationId = null;
    session.authVerified = false;
    session.authProvider = null;
    session.pendingOtp = null;
    session.portalMemberId = null;
    session.memberIdentifier = null;
    session.portalHandoff = null;
  }
  await writeSession(session);

  return NextResponse.json({ result, session });
}
