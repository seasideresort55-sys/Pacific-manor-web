/** Shared age / membership eligibility copy for guest UI. */

export const LONG_STAY_MIN_AGE = 60;
export const EXPERIENCE_OR_QUIZ_MIN_AGE = 50;

export const ELIGIBILITY_COPY = {
  longStay:
    "長住入住原則年滿 60 歲，並具日常生活自理能力。未滿 60 歲者可陪同 60 歲以上住戶一起生活。",
  experience:
    "了解問卷與體驗申請開放 50 歲以上、生活可自理者，或遠端工作者。體驗安排只提供給已簽約的月租會員，不是可單獨購買的住宿。",
  birthYear:
    "請選擇出生年，用於確認年齡資格。請勿略過或留下空白。",
  memberFirst:
    "請先完成了解問卷，再申請月租方案並完成簽約。成為月租會員後，才能進入會員專區安排體驗。",
} as const;

export function birthYearOptions(now = new Date()) {
  const current = now.getFullYear();
  const years: number[] = [];
  for (let year = current - 18; year >= current - 110; year -= 1) {
    years.push(year);
  }
  return years;
}

export function ageFromBirthYear(year: number, now = new Date()): number | null {
  if (!Number.isFinite(year) || year < 1900) return null;
  return now.getFullYear() - year;
}
