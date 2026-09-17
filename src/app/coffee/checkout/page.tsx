"use client";

import Link from "next/link";
import { useRouter } from "next/navigation";
import { useEffect, useMemo, useState } from "react";
import { formatNT } from "@/lib/format";
import { readCart, writeCart } from "@/lib/cart";
import { quoteCoffeeOrder } from "@/lib/shipping";
import type {
  Cadence,
  CartItem,
  DeliveryMethod,
  InvoiceType,
  PaymentMethod,
  SubscriptionPayMode,
} from "@/lib/types";

export default function CheckoutPage() {
  const router = useRouter();
  const [cart, setCart] = useState<CartItem[]>([]);
  const [gift, setGift] = useState(false);
  const [recipientName, setRecipientName] = useState("");
  const [recipientPhone, setRecipientPhone] = useState("");
  const [deliveryMethod, setDeliveryMethod] = useState<DeliveryMethod>("home");
  const [address, setAddress] = useState("");
  const [cvsStore, setCvsStore] = useState("");
  const [cadence, setCadence] = useState<Cadence>("once");
  const [paymentMethod, setPaymentMethod] = useState<PaymentMethod>("line_pay");
  const [subscriptionPayMode, setSubscriptionPayMode] = useState<SubscriptionPayMode>("per_shipment_cod");
  const [invoiceType, setInvoiceType] = useState<InvoiceType>("personal");
  const [taxId, setTaxId] = useState("");
  const [companyName, setCompanyName] = useState("");
  const [error, setError] = useState("");
  const [busy, setBusy] = useState(false);

  useEffect(() => {
    setCart(readCart());
  }, []);

  const quote = useMemo(
    () =>
      quoteCoffeeOrder({
        items: cart,
        cadence,
        subscriptionPayMode: cadence === "once" ? null : subscriptionPayMode,
      }),
    [cart, cadence, subscriptionPayMode],
  );

  async function submit() {
    setBusy(true);
    setError("");
    const res = await fetch("/api/coffee", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        items: cart.map((item) => ({ productId: item.productId, qty: item.qty })),
        gift,
        recipientName,
        recipientPhone,
        deliveryMethod,
        address,
        cvsStore,
        cadence,
        paymentMethod,
        subscriptionPayMode: cadence === "once" ? null : subscriptionPayMode,
        invoiceType,
        taxId,
        companyName,
      }),
    });
    const data = await res.json();
    setBusy(false);
    if (!res.ok) {
      setError(data.error || "結帳失敗");
      return;
    }
    writeCart([]);
    router.push(`/coffee/success?id=${data.record.id}&pay=${data.record.status}&amount=${data.record.payableNow}`);
  }

  if (cart.length === 0) {
    return (
      <div className="mx-auto max-w-3xl px-4 py-12">
        <h1 className="text-4xl font-bold">購物車是空的</h1>
        <Link href="/coffee" className="btn-primary mt-6">
          回萬歲咖啡
        </Link>
      </div>
    );
  }

  return (
    <div className="mx-auto max-w-3xl px-4 py-12">
      <h1 className="text-4xl font-bold">咖啡結帳</h1>
      <p className="mt-3 text-xl leading-8 text-[#3d5a66]">
        本頁只會列出咖啡商品與運費。沒有住宿兌換，也沒有月租綁定。
      </p>

      <form
        className="mt-8 grid gap-5 rounded-[2rem] bg-white p-6 shadow-card"
        onSubmit={(event) => {
          event.preventDefault();
          void submit();
        }}
      >
        <section>
          <h2 className="text-2xl font-bold">商品</h2>
          <ul className="mt-3 grid gap-2">
            {cart.map((item) => (
              <li key={item.productId}>
                {item.name} × {item.qty}｜{formatNT(item.unitPrice * item.qty)}
              </li>
            ))}
          </ul>
        </section>

        <label className="flex items-start gap-3 rounded-2xl bg-cream p-4">
          <input type="checkbox" className="mt-1 h-5 w-5" checked={gift} onChange={(event) => setGift(event.target.checked)} />
          <span>這筆是送禮（收件人可與購買人不同）</span>
        </label>

        <label className="grid gap-2">
          <span>收件人</span>
          <input className="field" required value={recipientName} onChange={(event) => setRecipientName(event.target.value)} />
        </label>
        <label className="grid gap-2">
          <span>收件電話</span>
          <input className="field" required inputMode="tel" value={recipientPhone} onChange={(event) => setRecipientPhone(event.target.value)} />
        </label>

        <fieldset className="grid gap-2">
          <legend className="mb-1">物流</legend>
          <label className="choice" data-active={deliveryMethod === "home"}>
            <input type="radio" name="delivery" className="mr-2" checked={deliveryMethod === "home"} onChange={() => setDeliveryMethod("home")} />
            宅急便
          </label>
          <label className="choice" data-active={deliveryMethod === "cvs"}>
            <input type="radio" name="delivery" className="mr-2" checked={deliveryMethod === "cvs"} onChange={() => setDeliveryMethod("cvs")} />
            超商取貨
          </label>
        </fieldset>
        {deliveryMethod === "home" ? (
          <label className="grid gap-2">
            <span>地址</span>
            <input className="field" required value={address} onChange={(event) => setAddress(event.target.value)} />
          </label>
        ) : (
          <label className="grid gap-2">
            <span>超商門市（預覽可自由填寫）</span>
            <input className="field" required value={cvsStore} onChange={(event) => setCvsStore(event.target.value)} placeholder="例如：7-ELEVEN 豐濱門市" />
          </label>
        )}

        <fieldset className="grid gap-2">
          <legend className="mb-1">寄送節奏</legend>
          {(
            [
              ["once", "一次送"],
              ["biweekly", "兩週寄一次（預設 6 次）"],
              ["monthly", "按月寄送（預設 3 次）"],
            ] as const
          ).map(([id, label]) => (
            <label key={id} className="choice" data-active={cadence === id}>
              <input type="radio" name="cadence" className="mr-2" checked={cadence === id} onChange={() => setCadence(id)} />
              {label}
            </label>
          ))}
        </fieldset>

        {cadence !== "once" ? (
          <fieldset className="grid gap-2">
            <legend className="mb-1">訂閱付款</legend>
            <label className="choice" data-active={subscriptionPayMode === "per_shipment_cod"}>
              <input
                type="radio"
                name="subpay"
                className="mr-2"
                checked={subscriptionPayMode === "per_shipment_cod"}
                onChange={() => {
                  setSubscriptionPayMode("per_shipment_cod");
                  setPaymentMethod("cod");
                }}
              />
              每次出貨貨到付款（宅急便、超商皆可）
            </label>
            <label className="choice" data-active={subscriptionPayMode === "pay_all_now"}>
              <input
                type="radio"
                name="subpay"
                className="mr-2"
                checked={subscriptionPayMode === "pay_all_now"}
                onChange={() => {
                  setSubscriptionPayMode("pay_all_now");
                  if (paymentMethod === "cod") setPaymentMethod("line_pay");
                }}
              />
              一次付清、分次寄送
            </label>
          </fieldset>
        ) : null}

        <fieldset className="grid gap-2">
          <legend className="mb-1">付款方式</legend>
          <label className="choice" data-active={paymentMethod === "line_pay"}>
            <input type="radio" name="pay" className="mr-2" checked={paymentMethod === "line_pay"} onChange={() => setPaymentMethod("line_pay")} />
            LINE Pay（模擬成功）
          </label>
          <label className="choice" data-active={paymentMethod === "apple_pay"}>
            <input type="radio" name="pay" className="mr-2" checked={paymentMethod === "apple_pay"} onChange={() => setPaymentMethod("apple_pay")} />
            Apple Pay（模擬成功）
          </label>
          <label className="choice" data-active={paymentMethod === "cod"}>
            <input
              type="radio"
              name="pay"
              className="mr-2"
              checked={paymentMethod === "cod"}
              onChange={() => {
                setPaymentMethod("cod");
                if (cadence !== "once") setSubscriptionPayMode("per_shipment_cod");
              }}
            />
            貨到付款（宅急便與超商取貨都可以）
          </label>
        </fieldset>

        <fieldset className="grid gap-2">
          <legend className="mb-1">發票</legend>
          <label className="choice" data-active={invoiceType === "personal"}>
            <input type="radio" name="invoice" className="mr-2" checked={invoiceType === "personal"} onChange={() => setInvoiceType("personal")} />
            個人發票（無統編）
          </label>
          <label className="choice" data-active={invoiceType === "company"}>
            <input type="radio" name="invoice" className="mr-2" checked={invoiceType === "company"} onChange={() => setInvoiceType("company")} />
            公司發票（請自行填統編）
          </label>
        </fieldset>
        {invoiceType === "company" ? (
          <>
            <label className="grid gap-2">
              <span>公司名稱</span>
              <input className="field" value={companyName} onChange={(event) => setCompanyName(event.target.value)} />
            </label>
            <label className="grid gap-2">
              <span>統一編號（8 碼）</span>
              <input className="field" inputMode="numeric" value={taxId} onChange={(event) => setTaxId(event.target.value)} />
            </label>
          </>
        ) : null}

        <section className="rounded-2xl bg-cream p-4 leading-8">
          <p>本箱商品 {formatNT(quote.shipmentGoodsAmount)}</p>
          <p>運費 {formatNT(quote.shippingFee)}{quote.shippingFee === 0 ? "（已滿 NT$1,200 免運）" : "（未滿 NT$1,200）"}</p>
          {quote.shipmentCount > 1 ? <p>訂閱共 {quote.shipmentCount} 次，商品總額 {formatNT(quote.productSubtotal)}</p> : null}
          <p className="text-xl font-semibold">本次應付 {formatNT(quote.payableNow)}</p>
          {quote.remainingNote ? <p>{quote.remainingNote}</p> : null}
        </section>

        {error ? <p className="text-coral">{error}</p> : null}
        <button type="submit" className="btn-primary" disabled={busy}>
          {busy ? "送出中…" : "確認咖啡訂單"}
        </button>
      </form>
    </div>
  );
}
