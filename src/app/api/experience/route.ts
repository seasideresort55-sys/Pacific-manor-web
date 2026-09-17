import { NextResponse } from "next/server";
import { newId } from "@/lib/format";
import { canEnterExperience } from "@/lib/quiz";
import { readSession } from "@/lib/session";
import { appendRecord } from "@/lib/store";
import type { ExperienceRequest } from "@/lib/types";

export async function POST(request: Request) {
  const session = await readSession();
  if (!canEnterExperience(session.isMember)) {
    return NextResponse.json(
      { error: "體驗安排僅提供給月租會員。請先通過了解問卷並完成簽約申請。" },
      { status: 403 },
    );
  }

  const body = (await request.json()) as {
    name: string;
    phone: string;
    guestCount: number;
    startDate: string;
    endDate: string;
    notes?: string;
  };

  if (!body.name?.trim() || !body.phone?.trim() || !body.startDate || !body.endDate) {
    return NextResponse.json({ error: "請填寫姓名、電話與希望體驗日期區間。" }, { status: 400 });
  }
  if (new Date(body.endDate) <= new Date(body.startDate)) {
    return NextResponse.json({ error: "結束日期需晚於開始日期。" }, { status: 400 });
  }
  const guests = Number(body.guestCount);
  if (!Number.isFinite(guests) || guests < 1 || guests > 4) {
    return NextResponse.json({ error: "入住人請填 1 至 4 人。" }, { status: 400 });
  }

  const record: ExperienceRequest = {
    id: newId("experience"),
    type: "experience_request",
    sessionId: session.id,
    name: body.name.trim(),
    phone: body.phone.trim(),
    guestCount: guests,
    startDate: body.startDate,
    endDate: body.endDate,
    notes: (body.notes || "").trim(),
    status: "submitted",
    createdAt: new Date().toISOString(),
  };

  await appendRecord(record);
  return NextResponse.json({ record });
}
