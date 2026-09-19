import type { CartItem } from "./types";

export const CART_KEY = "pm_coffee_cart";

export function readCart(): CartItem[] {
  if (typeof window === "undefined") return [];
  try {
    const raw = window.localStorage.getItem(CART_KEY);
    return raw ? (JSON.parse(raw) as CartItem[]) : [];
  } catch {
    return [];
  }
}

export function writeCart(items: CartItem[]) {
  window.localStorage.setItem(CART_KEY, JSON.stringify(items));
}

export function upsertCart(items: CartItem[], next: CartItem) {
  const copy = items.filter((item) => item.productId !== next.productId);
  if (next.qty > 0) copy.push(next);
  return copy;
}
