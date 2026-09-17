import { describe, expect, it } from "vitest";
import { canEnterExperience, evaluateQuiz } from "./quiz";
import type { QuizAnswers } from "./types";

const passAnswers: QuizAnswers = {
  identities: ["age50"],
  life: "coastal",
  duration: "try_then_stay",
  livingWith: "solo",
  budget: "band_40_60",
  priorities: ["ocean_pace", "health"],
  when: "within_quarter",
};

describe("evaluateQuiz", () => {
  it("通過：50+ 或遠端＋海岸適配＋合理預算時程", () => {
    expect(evaluateQuiz(passAnswers).outcome).toBe("pass");
    expect(evaluateQuiz({ ...passAnswers, identities: ["remote"], life: "outdoor" }).outcome).toBe(
      "pass",
    );
  });

  it("未通過：缺少身分硬條件", () => {
    const result = evaluateQuiz({ ...passAnswers, identities: [] });
    expect(result.outcome).toBe("reject");
    expect(result.body).toContain("體驗僅提供給月租會員");
  });

  it("未通過：只要幾天觀光或拒絕會員制", () => {
    expect(evaluateQuiz({ ...passAnswers, duration: "days_tourism" }).outcome).toBe("reject");
    expect(evaluateQuiz({ ...passAnswers, duration: "no_membership" }).outcome).toBe("reject");
  });

  it("未通過：把體驗當便宜住幾晚", () => {
    expect(
      evaluateQuiz({ ...passAnswers, priorities: ["cheap_nights"] }).outcome,
    ).toBe("reject");
  });

  it("待人工：還沒決定、先看環境、時程未定", () => {
    expect(evaluateQuiz({ ...passAnswers, life: "undecided" }).outcome).toBe("review");
    expect(evaluateQuiz({ ...passAnswers, budget: "see_first" }).outcome).toBe("review");
    expect(evaluateQuiz({ ...passAnswers, when: "undecided" }).outcome).toBe("review");
    expect(evaluateQuiz({ ...passAnswers, life: "city" }).outcome).toBe("review");
    expect(evaluateQuiz({ ...passAnswers, budget: "under_20k" }).outcome).toBe("review");
  });

  it("拒絕優先於待人工", () => {
    expect(
      evaluateQuiz({
        ...passAnswers,
        duration: "days_tourism",
        life: "undecided",
      }).outcome,
    ).toBe("reject");
  });
});

describe("experience gate", () => {
  it("只有已是月租會員才能進入體驗安排", () => {
    expect(canEnterExperience(false)).toBe(false);
    expect(canEnterExperience(true)).toBe(true);
  });
});
