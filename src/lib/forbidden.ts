/** 客人可見 UI／訂單名禁用詞（交接規格第 1 節） */
export const FORBIDDEN_GUEST_WORDS = [
  "房費",
  "訂房",
  "訂房訂單",
  "每晚",
  "日租價",
] as const;

export function findForbiddenWords(text: string): string[] {
  return FORBIDDEN_GUEST_WORDS.filter((word) => text.includes(word));
}

export function assertNoForbiddenWords(text: string, label = "copy") {
  const hits = findForbiddenWords(text);
  if (hits.length > 0) {
    throw new Error(`${label} 含禁用詞：${hits.join("、")}`);
  }
}
