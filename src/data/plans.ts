import type { MemberPlanId } from "@/lib/types";

export type MemberPlan = {
  id: MemberPlanId;
  name: string;
  tag: string;
  monthlyLabel: string;
  firstMonth?: number;
  monthlyFromSecond?: number;
  monthly: number;
  notes: string[];
};

export const memberPlans: MemberPlan[] = [
  {
    id: "seascape_list",
    name: "人生海景清單",
    tag: "月租",
    monthlyLabel: "首月 NT$18,000，次月起 NT$59,800／月",
    firstMonth: 18000,
    monthlyFromSecond: 59800,
    monthly: 59800,
    notes: [
      "須簽約後依合約收款，不做分期。",
      "成為月租會員後，才可在會員專區安排體驗。",
    ],
  },
  {
    id: "quarter",
    name: "季租",
    tag: "連續三個月",
    monthlyLabel: "NT$45,000／月",
    monthly: 45000,
    notes: [
      "以連續居住三個月為節奏。",
      "須簽約；網站第一版先送出簽約申請。",
    ],
  },
  {
    id: "year",
    name: "年租",
    tag: "長期安頓",
    monthlyLabel: "NT$33,000／月",
    monthly: 33000,
    notes: [
      "適合希望把海邊生活當成日常的人。",
      "須簽約；不做電商分期付款。",
    ],
  },
];

export function getPlan(id: MemberPlanId) {
  const plan = memberPlans.find((item) => item.id === id);
  if (!plan) throw new Error(`未知方案：${id}`);
  return plan;
}
