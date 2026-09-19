export type CoffeeProduct = {
  id: string;
  name: string;
  price: number;
  weight: string;
  blurb: string;
  note: string;
  accent: string;
};

export const coffeeProducts: CoffeeProduct[] = [
  {
    id: "drip-10",
    name: "萬歲日常濾掛 10 入",
    price: 480,
    weight: "10 包",
    blurb: "住民店長日常會泡的溫潤濾掛，適合自己慢慢喝。",
    note: "可自用或送禮，與月租、體驗安排無關。",
    accent: "#C47B5A",
  },
  {
    id: "beans-227",
    name: "海風深焙豆 227g",
    price: 520,
    weight: "227g",
    blurb: "帶一點海風味道的深焙，適合手沖或美式壺。",
    note: "伴手禮訂單，不含任何住宿權益。",
    accent: "#2E5E73",
  },
  {
    id: "gift-box",
    name: "莊園伴手禮盒",
    price: 880,
    weight: "濾掛＋豆",
    blurb: "給家人或同事的海邊日常禮，打開就能分享。",
    note: "這是咖啡／伴手禮訂單，不是體驗兌換券。",
    accent: "#5A7A62",
  },
  {
    id: "manager-bag",
    name: "一日店長聯名禮袋",
    price: 1280,
    weight: "禮袋",
    blurb: "較完整的分享組合，單筆即達免運門檻。",
    note: "滿 NT$1,200 免運；仍不綁定體驗安排。",
    accent: "#1C3D4C",
  },
  {
    id: "drip-20",
    name: "月配濾掛 20 入",
    price: 860,
    weight: "20 包",
    blurb: "適合兩週或按月寄送的日常量。",
    note: "訂閱可每次貨到付款，或一次付清、分次寄送。",
    accent: "#8AA4B0",
  },
];

export function getProduct(id: string) {
  return coffeeProducts.find((item) => item.id === id);
}
