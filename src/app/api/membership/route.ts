import { NextResponse } from "next/server";
import { getPlan } from "@/data/plans";
import { canApplyMembership } from "@/lib/auth";
import { newId } from "@/lib/format";
import { readSession, writeSession } from "@/lib/session";
import { appendRecord, updateRecord } from "@/lib/store";
import type { MemberPlanId, MembershipApplication } from "@/lib/types";

export async function POST(request: Request) {
  const session = await readSession();
  if (!canApplyMembership(session.quizOutcome, session.authVerified)) {
    return NextResponse.json(
      { error: "需先通過了解問卷，並用 Google、LINE、Apple、電子郵件或手機簡訊完成驗證。" },
      { status: 403 },
    );
  }

  const body = (await request.json()) as {
    name: string;
    phone: string;
    email: string;
    planId: MemberPlanId;
    acceptContract: boolean;
    noInstallmentAck: boolean;
    simulateSign?: boolean;
  };

  if (!body.name?.trim() || !body.phone?.trim()) {
    return NextResponse.json({ error: "請留下姓名與電話，方便簽約聯繫。" }, { status: 400 });
  }
  if (!body.acceptContract || !body.noInstallmentAck) {
    return NextResponse.json({ error: "月租須簽約，且不做分期付款。" }, { status: 400 });
  }

  getPlan(body.planId);

  const record: MembershipApplication = {
    id: newId("membership"),
    type: "membership_application",
    sessionId: session.id,
    name: body.name.trim(),
    phone: body.phone.trim(),
    email: (body.email || "").trim(),
    planId: body.planId,
    acceptContract: true,
    noInstallmentAck: true,
    status: body.simulateSign ? "signed_member" : "pending_contract",
    createdAt: new Date().toISOString(),
  };

  await appendRecord(record);

  session.name = record.name;
  session.phone = record.phone;
  session.email = record.email;
  session.memberPlan = record.planId;
  session.membershipApplicationId = record.id;
  session.isMember = record.status === "signed_member";
  await writeSession(session);

  return NextResponse.json({ record, session });
}

export async function PATCH(request: Request) {
  const session = await readSession();
  if (!session.membershipApplicationId) {
    return NextResponse.json({ error: "尚無簽約申請。" }, { status: 400 });
  }

  const body = (await request.json()) as { simulateSign?: boolean };
  if (!body.simulateSign) {
    return NextResponse.json({ error: "僅支援模擬完成簽約。" }, { status: 400 });
  }

  const record = await updateRecord<MembershipApplication>(session.membershipApplicationId, {
    status: "signed_member",
  });
  session.isMember = true;
  await writeSession(session);
  return NextResponse.json({ record, session });
}
