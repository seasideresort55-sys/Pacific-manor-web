import { NextResponse } from "next/server";
import { getProduct } from "@/data/products";
import { newId } from "@/lib/format";
import { quoteCoffeeOrder } from "@/lib/shipping";
import { readSession } from "@/lib/session";
import { appendRecord } from "@/lib/store";
import type {
  Cadence,
  CoffeeOrder,
  DeliveryMethod,
  InvoiceType,
  PaymentMethod,
  SubscriptionPayMode,
} from "@/lib/types";

export async function POST(request: Request) {
  const session = await readSession();
  const body = (await request.json()) as {
    items: { productId: string; qty: number }[];
    gift: boolean;
    recipientName: string;
    recipientPhone: string;
    deliveryMethod: DeliveryMethod;
    address?: string;
    cvsStore?: string;
    cadence: Cadence;
    paymentMethod: PaymentMethod;
    subscriptionPayMode?: SubscriptionPayMode | null;
    invoiceType: InvoiceType;
    taxId?: string;
    companyName?: string;
  };

  if (!body.items?.length) {
    return NextResponse.json({ error: "購物車是空的。" }, { status: 400 });
  }
  if (!body.recipientName?.trim() || !body.recipientPhone?.trim()) {
    return NextResponse.json({ error: "請填寫收件人與電話。" }, { status: 400 });
  }
  if (body.deliveryMethod === "home" && !body.address?.trim()) {
    return NextResponse.json({ error: "宅急便請填寫地址。" }, { status: 400 });
  }
  if (body.deliveryMethod === "cvs" && !body.cvsStore?.trim()) {
    return NextResponse.json({ error: "超商取貨請填寫門市。" }, { status: 400 });
  }
  if (body.invoiceType === "company" && !/^\d{8}$/.test(body.taxId || "")) {
    return NextResponse.json({ error: "公司發票請填 8 碼統編。" }, { status: 400 });
  }
  if (body.cadence !== "once" && body.paymentMethod === "cod" && body.subscriptionPayMode === "pay_all_now") {
    return NextResponse.json(
      { error: "一次付清請改用 LINE Pay 或 Apple Pay；貨到付款適用每次出貨。" },
      { status: 400 },
    );
  }

  const items = body.items.map((line) => {
    const product = getProduct(line.productId);
    if (!product || line.qty < 1) {
      throw new Error("商品不存在");
    }
    return {
      productId: product.id,
      name: product.name,
      unitPrice: product.price,
      qty: Math.min(12, Math.floor(line.qty)),
    };
  });

  const quote = quoteCoffeeOrder({
    items,
    cadence: body.cadence,
    subscriptionPayMode: body.subscriptionPayMode ?? null,
  });

  const record: CoffeeOrder = {
    id: newId("coffee"),
    type: "coffee_order",
    sessionId: session.id,
    items,
    gift: Boolean(body.gift),
    recipientName: body.recipientName.trim(),
    recipientPhone: body.recipientPhone.trim(),
    deliveryMethod: body.deliveryMethod,
    address: (body.address || "").trim(),
    cvsStore: (body.cvsStore || "").trim(),
    cadence: body.cadence,
    paymentMethod: body.paymentMethod,
    subscriptionPayMode: quote.shipmentCount > 1 ? (body.subscriptionPayMode ?? "per_shipment_cod") : null,
    invoiceType: body.invoiceType,
    taxId: (body.taxId || "").trim(),
    companyName: (body.companyName || "").trim(),
    shipmentCount: quote.shipmentCount,
    productSubtotal: quote.productSubtotal,
    shipmentGoodsAmount: quote.shipmentGoodsAmount,
    shippingFee: quote.shippingFee,
    payableNow: quote.payableNow,
    remainingNote: quote.remainingNote,
    status: body.paymentMethod === "cod" ? "mock_cod" : "mock_paid",
    createdAt: new Date().toISOString(),
  };

  await appendRecord(record);
  return NextResponse.json({ record });
}
