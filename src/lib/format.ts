export function formatNT(amount: number) {
  return `NT$${amount.toLocaleString("zh-TW")}`;
}

export function newId(prefix: string) {
  return `${prefix}_${Date.now().toString(36)}_${Math.random().toString(36).slice(2, 8)}`;
}
