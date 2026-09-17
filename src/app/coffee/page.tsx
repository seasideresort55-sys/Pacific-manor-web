"use client";

import Link from "next/link";
import { useEffect, useMemo, useState } from "react";
import { coffeeProducts } from "@/data/products";
import { formatNT } from "@/lib/format";
import { readCart, upsertCart, writeCart } from "@/lib/cart";
import { calcShippingFee, cartGoodsAmount } from "@/lib/shipping";
import type { CartItem } from "@/lib/types";

export default function CoffeePage() {
  const [cart, setCart] = useState<CartItem[]>([]);

  useEffect(() => {
    setCart(readCart());
  }, []);

  function add(productId: string, name: string, unitPrice: number) {
    const existing = cart.find((item) => item.productId === productId);
    const next = upsertCart(cart, {
      productId,
      name,
      unitPrice,
      qty: (existing?.qty || 0) + 1,
    });
    setCart(next);
    writeCart(next);
  }

  const goods = useMemo(() => cartGoodsAmount(cart), [cart]);
  const shipping = calcShippingFee(goods);

  return (
    <div className="mx-auto max-w-6xl px-4 py-12">
      <p className="text-sm tracking-[0.2em] text-ocean">獨立選物</p>
      <h1 className="mt-2 text-4xl font-bold">萬歲咖啡</h1>
      <p className="mt-4 max-w-2xl text-xl leading-8 text-[#3d5a66]">
        可自用、可送禮。宅急便或超商取貨；一次送、兩週或按月。滿 NT$1,200 免運，否則運費 NT$100。
        這是咖啡／伴手禮訂單，不開體驗權益，也不和月租綁在一起。
      </p>

      <div className="mt-8 grid gap-4 md:grid-cols-2">
        {coffeeProducts.map((product) => (
          <article key={product.id} className="rounded-[2rem] bg-white p-6 shadow-card">
            <div className="h-3 w-16 rounded-full" style={{ background: product.accent }} />
            <h2 className="mt-4 text-2xl font-bold">{product.name}</h2>
            <p className="mt-1 text-ocean">{product.weight}</p>
            <p className="mt-3 leading-8">{product.blurb}</p>
            <p className="mt-2 text-base text-[#3d5a66]">{product.note}</p>
            <p className="mt-4 text-2xl font-semibold">{formatNT(product.price)}</p>
            <button
              type="button"
              className="btn-primary mt-4"
              onClick={() => add(product.id, product.name, product.price)}
            >
              放入購物車
            </button>
          </article>
        ))}
      </div>

      <aside className="mt-10 rounded-[2rem] bg-deep p-6 text-cream">
        <h2 className="text-2xl font-bold">購物車</h2>
        {cart.length === 0 ? (
          <p className="mt-3">還沒有咖啡商品。</p>
        ) : (
          <ul className="mt-4 grid gap-2">
            {cart.map((item) => (
              <li key={item.productId}>
                {item.name} × {item.qty}｜{formatNT(item.unitPrice * item.qty)}
              </li>
            ))}
          </ul>
        )}
        <p className="mt-4">商品 {formatNT(goods)} ＋ 運費 {formatNT(shipping)}</p>
        <p className="text-sand">未滿 NT$1,200 收運費 NT$100；結帳頁可改配送與付款。</p>
        <Link href="/coffee/checkout" className="btn-primary mt-5 !bg-coral">
          前往結帳
        </Link>
      </aside>
    </div>
  );
}
