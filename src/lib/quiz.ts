import type { QuizAnswers, QuizOutcome, QuizResult } from "./types";

const COPY: Record<QuizOutcome, { headline: string; body: string }> = {
  pass: {
    headline: "適合進一步了解月租會員",
    body: "依你勾選的生活節奏與身分，太平洋莊園的月租生活可能對得上。成為月租會員後，即可在會員專區安排體驗。體驗是會員權益，不是可單獨購買的商品。",
  },
  review: {
    headline: "先留給專人與你確認",
    body: "有幾項答案還在邊緣，我們不會立刻關閉或開啟體驗安排。請留下聯絡方式，專人會與你聯繫後再決定下一步。",
  },
  reject: {
    headline: "目前較不適合進入月租生活",
    body: "體驗僅提供給月租會員。若你主要想短暫停留、或尚未準備加入會員網絡，仍可認識莊園與選購萬歲咖啡，不必勉強走體驗安排。",
  },
};

function unique(reasons: string[]) {
  return [...new Set(reasons)];
}

export function evaluateQuiz(answers: QuizAnswers): QuizResult {
  const rejectReasons: string[] = [];
  const reviewReasons: string[] = [];

  const hasIdentity =
    answers.identities.includes("age50") || answers.identities.includes("remote");

  if (!hasIdentity) {
    rejectReasons.push("需年滿 50 歲，或為需要長住節奏的遠端工作者，至少符合一項。");
  }

  if (answers.duration === "days_tourism") {
    rejectReasons.push("目前以幾天觀光停留為主，與月租會員的生活節奏不同。");
  }

  if (answers.duration === "no_membership") {
    rejectReasons.push("體驗安排僅提供給月租會員，無法單獨申請。");
  }

  if (answers.priorities.includes("cheap_nights")) {
    rejectReasons.push("體驗不是可單賣的住宿商品，也不作為促銷主賣點。");
  }

  if (answers.priorities.length < 1 || answers.priorities.length > 3) {
    reviewReasons.push("請選擇 1 至 3 項最在意的事，方便專人對齊期待。");
  }

  if (answers.life === "undecided") {
    reviewReasons.push("生活概念尚未決定，需專人陪你釐清是否適合海邊慢生活。");
  }

  if (answers.life === "mountain" || answers.life === "city") {
    reviewReasons.push(
      "你較偏向山邊田園或城市便利；太平洋莊園以海岸生活為主，需再確認是否適配。",
    );
  }

  if (answers.budget === "see_first") {
    reviewReasons.push("預算想先看環境再談，先由專人說明月租方案後再決定。");
  }

  if (answers.budget === "under_20k") {
    reviewReasons.push("目前預算低於月租方案門檻，需專人協助評估是否合適。");
  }

  if (answers.when === "undecided") {
    reviewReasons.push("開始時程尚未確定。");
  }

  if (answers.livingWith === "unsure") {
    reviewReasons.push("同住安排尚未確定。");
  }

  if (rejectReasons.length > 0) {
    const outcome: QuizOutcome = "reject";
    return {
      outcome,
      reasons: unique(rejectReasons),
      ...COPY[outcome],
    };
  }

  if (reviewReasons.length > 0) {
    const outcome: QuizOutcome = "review";
    return {
      outcome,
      reasons: unique(reviewReasons),
      ...COPY[outcome],
    };
  }

  const outcome: QuizOutcome = "pass";
  return {
    outcome,
    reasons: ["身分符合，生活意向與太平洋莊園月租節奏適配。"],
    ...COPY[outcome],
  };
}

export function canViewMembershipInvite(outcome: QuizOutcome | null) {
  return outcome === "pass";
}

export function canApplyMembership(outcome: QuizOutcome | null) {
  return outcome === "pass";
}

export function canEnterExperience(isMember: boolean) {
  return isMember === true;
}
