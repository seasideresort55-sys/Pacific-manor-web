import { describe, expect, it } from "vitest";
import {
  FREE_SHIPPING_THRESHOLD,
  SHIPPING_FEE,
  calcShippingFee,
  quoteCoffeeOrder,
} from "./shipping";
import type { CartItem } from "./types";

const item = (unitPrice: number, qty = 1): CartItem => ({
  productId: "x",
  name: "測試咖啡",
  unitPrice,
  qty,
});

describe("calcShippingFee", () => {
  it("滿 1200 免運，未滿收 100", () => {
    expect(calcShippingFee(0)).toBe(0);
    expect(calcShippingFee(1199)).toBe(SHIPPING_FEE);
    expect(calcShippingFee(FREE_SHIPPING_THRESHOLD)).toBe(0);
    expect(calcShippingFee(1280)).toBe(0);
  });
});

describe("quoteCoffeeOrder", () => {
  it("一次送：商品＋該箱運費", () => {
    const quote = quoteCoffeeOrder({
      items: [item(880)],
      cadence: "once",
      subscriptionPayMode: null,
    });
    expect(quote.shippingFee).toBe(100);
    expect(quote.payableNow).toBe(980);
    expect(quote.shipmentCount).toBe(1);
  });

  it("一次送且達免運", () => {
    const quote = quoteCoffeeOrder({
      items: [item(1280)],
      cadence: "once",
      subscriptionPayMode: null,
    });
    expect(quote.shippingFee).toBe(0);
    expect(quote.payableNow).toBe(1280);
  });

  it("按月＋每次貨到付款：本次只收第一箱", () => {
    const quote = quoteCoffeeOrder({
      items: [item(860)],
      cadence: "monthly",
      subscriptionPayMode: "per_shipment_cod",
    });
    expect(quote.shipmentCount).toBe(3);
    expect(quote.productSubtotal).toBe(2580);
    expect(quote.payableNow).toBe(960);
    expect(quote.remainingNote).toContain("貨到付款");
  });

  it("兩週＋一次付清：商品全收，運費收第一箱", () => {
    const quote = quoteCoffeeOrder({
      items: [item(480, 2)],
      cadence: "biweekly",
      subscriptionPayMode: "pay_all_now",
    });
    expect(quote.shipmentCount).toBe(6);
    expect(quote.productSubtotal).toBe(5760);
    expect(quote.shippingFee).toBe(100);
    expect(quote.payableNow).toBe(5860);
    expect(quote.remainingNote).toContain("運費");
  });
});
