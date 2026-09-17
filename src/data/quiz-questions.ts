import type {
  Budget,
  Duration,
  Identity,
  LifeConcept,
  LivingWith,
  Priority,
  StartWhen,
} from "@/lib/types";

export const identityOptions: {
  id: Identity;
  title: string;
  description: string;
}[] = [
  {
    id: "age50",
    title: "年滿 50 歲，想過長住節奏",
    description: "希望把生活安頓下來，而不是週末短暫停留。",
  },
  {
    id: "remote",
    title: "遠端工作者，需要長住式生活節奏",
    description: "工作可移動，但日常需要穩定、可預期的居住節奏，不是觀光日租。",
  },
];

export const lifeOptions: {
  id: LifeConcept;
  title: string;
  description: string;
}[] = [
  {
    id: "coastal",
    title: "海邊慢生活",
    description: "面向太平洋，把日出、海風與散步當成每天的節奏，而不是週末打卡。",
  },
  {
    id: "mountain",
    title: "山邊田園",
    description: "較嚮往縱谷農田與山居靜好。本莊園以海岸為主，需再評估是否合適。",
  },
  {
    id: "city",
    title: "城市便利",
    description: "習慣百貨、捷運與即時醫療；豐濱節奏較慢，生活機能要重新安排。",
  },
  {
    id: "outdoor",
    title: "溫暖戶外",
    description: "喜歡走路、園藝、看星空與戶外泡茶，能自然融入海邊莊園日常。",
  },
  {
    id: "undecided",
    title: "還沒決定",
    description: "還在摸索想過什麼日子。我們會請專人陪你釐清，不會立刻開啟體驗。",
  },
];

export const durationOptions: {
  id: Duration;
  title: string;
  description: string;
}[] = [
  {
    id: "try_then_stay",
    title: "想先體驗，再考慮長住",
    description: "可以先感受日常，但須接受：體驗只提供給月租會員，不是單次商品。",
  },
  {
    id: "quarter",
    title: "想連續住三個月左右",
    description: "與季租節奏接近，適合先把生活安下來觀察。",
  },
  {
    id: "year",
    title: "想住一年或更久",
    description: "以年租生活為目標，把太平洋當成日常風景。",
  },
  {
    id: "days_tourism",
    title: "只要來幾天看看／觀光",
    description: "短暫停留、打卡或度假。這與月租會員網絡的定位不同。",
  },
  {
    id: "no_membership",
    title: "不想加入會員，只想單獨安排體驗",
    description: "體驗不是可單賣的主商品，也沒有買咖啡就送住的做法。",
  },
];

export const livingOptions: {
  id: LivingWith;
  title: string;
  description: string;
}[] = [
  { id: "solo", title: "一人", description: "自己安排節奏，保有安靜與彈性。" },
  { id: "partner", title: "伴侶", description: "兩人一起過海邊日常。" },
  { id: "friends", title: "朋友", description: "與朋友共同嘗試一段長住生活。" },
  { id: "unsure", title: "不確定", description: "同住對象還在商量，可先留待專人確認。" },
];

export const budgetOptions: {
  id: Budget;
  title: string;
  description: string;
}[] = [
  { id: "under_20k", title: "未滿 NT$20,000／月", description: "低於目前月租方案門檻，需再評估。" },
  { id: "band_20_40", title: "NT$20,000–40,000／月", description: "接近年租節奏，可再對齊方案。" },
  { id: "band_40_60", title: "NT$40,000–60,000／月", description: "接近季租與人生海景清單節奏。" },
  { id: "over_60k", title: "NT$60,000 以上／月", description: "預算與現行月租方案大致對得上。" },
  { id: "see_first", title: "先看環境再說", description: "先了解現場再談費用，屬待專人聯繫。" },
];

export const priorityOptions: {
  id: Priority;
  title: string;
  description: string;
}[] = [
  { id: "ocean_pace", title: "海景與生活節奏", description: "把看海、散步當成每天的背景。" },
  { id: "community", title: "社區與交流", description: "交誼廳、一起泡茶、認識新朋友。" },
  { id: "health", title: "健康與安心", description: "可自理生活，並在意求助與醫療銜接。" },
  { id: "remote_space", title: "遠端工作空間", description: "需要穩定網路與可長待的安靜角落。" },
  { id: "coffee_ritual", title: "咖啡與日常儀式", description: "萬歲咖啡是生活的一部分，不是兌換住宿。" },
  { id: "cheap_nights", title: "只要便宜住幾晚", description: "把體驗當成短期住宿商品。這不符合本站定位。" },
];

export const whenOptions: {
  id: StartWhen;
  title: string;
  description: string;
}[] = [
  { id: "within_month", title: "一個月內", description: "想很快開始對齊生活節奏。" },
  { id: "within_quarter", title: "三個月內", description: "有明確窗口，可進入方案說明。" },
  { id: "within_half", title: "半年內", description: "先完成了解與簽約準備。" },
  { id: "undecided", title: "還沒想好", description: "時程未定，先由專人聯繫。" },
];
