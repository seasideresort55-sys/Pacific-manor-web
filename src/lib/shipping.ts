import type { Cadence, CartItem, SubscriptionPayMode } from "./types";

export const FREE_SHIPPING_THRESHOLD = 1200;
export const SHIPPING_FEE = 100;

export const DEFAULT_SHIPMENT_COUNTS: Record<Cadence, number> = {
  once: 1,
  biweekly: 6,
  monthly: 3,
};

export function calcShippingFee(shipmentGoodsAmount: number): number {
  if (shipmentGoodsAmount <= 0) return 0;
  return shipmentGoodsAmount >= FREE_SHIPPING_THRESHOLD ? 0 : SHIPPING_FEE;
}

export function cartGoodsAmount(items: CartItem[]): number {
  return items.reduce((sum, item) => sum + item.unitPrice * item.qty, 0);
}

export function resolveShipmentCount(cadence: Cadence): number {
  return DEFAULT_SHIPMENT_COUNTS[cadence];
}

export function resolveSubscriptionPayMode(
  cadence: Cadence,
  requested: SubscriptionPayMode | null,
): SubscriptionPayMode | null {
  if (cadence === "once") return null;
  return requested ?? "per_shipment_cod";
}

export type CoffeeQuote = {
  shipmentCount: number;
  productSubtotal: number;
  shipmentGoodsAmount: number;
  shippingFee: number;
  payableNow: number;
  remainingNote: string;
};

/**
 * 運費以「每一箱／每一次出貨」的商品金額計算。
 * 一次付清：商品總額一次收取，本次另收第一箱運費。
 * 每次貨到付款：本次只顯示第一箱商品＋該箱運費。
 */
export function quoteCoffeeOrder(input: {
  items: CartItem[];
  cadence: Cadence;
  subscriptionPayMode: SubscriptionPayMode | null;
}): CoffeeQuote {
  const shipmentGoodsAmount = cartGoodsAmount(input.items);
  const shipmentCount = resolveShipmentCount(input.cadence);
  const shippingFee = calcShippingFee(shipmentGoodsAmount);
  const payMode = resolveSubscriptionPayMode(
    input.cadence,
    input.subscriptionPayMode,
  );

  if (input.cadence === "once" || payMode === null) {
    return {
      shipmentCount: 1,
      productSubtotal: shipmentGoodsAmount,
      shipmentGoodsAmount,
      shippingFee,
      payableNow: shipmentGoodsAmount + shippingFee,
      remainingNote: "",
    };
  }

  const productSubtotal = shipmentGoodsAmount * shipmentCount;

  if (payMode === "pay_all_now") {
    return {
      shipmentCount,
      productSubtotal,
      shipmentGoodsAmount,
      shippingFee,
      payableNow: productSubtotal + shippingFee,
      remainingNote:
        shipmentCount > 1
          ? `其餘 ${shipmentCount - 1} 次出貨的運費，於各次寄出時依該箱金額另計（滿 NT$1,200 免運）。`
          : "",
    };
  }

  return {
    shipmentCount,
    productSubtotal,
    shipmentGoodsAmount,
    shippingFee,
    payableNow: shipmentGoodsAmount + shippingFee,
    remainingNote: `之後 ${shipmentCount - 1} 次出貨可繼續貨到付款，各箱運費依當時商品金額計算。`,
  };
}
