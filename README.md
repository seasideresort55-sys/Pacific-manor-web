# 太平洋莊園／800+ 前台（第一版垂直切片）

繁體中文、手機優先的前台網站。實作交接規格中的四條主線：

1. **會員過濾問卷**（第 1 題生活概念有說明）→ 通過／待人工／未通過
2. **月租會員方案與簽約申請**（模擬；月租須簽約、不做分期）
3. **會員專區：體驗安排**（僅會員可進；禁用房費／訂房用語）
4. **萬歲咖啡獨立電商**（宅急便＋超商、一次／兩週／按月、運費規則、金流與發票）

現有官網 [seasideresort.com.tw](https://seasideresort.com.tw/) 僅作品牌與生活敘事參考。本站刻意把「體驗」從可單賣商品改成會員權益，並把咖啡拆成獨立訂單。

## 如何預覽

正式會員入口走官網 v18（手機簡訊主路徑），不再使用 `#socialLogin` 當公開入口，也不再使用 Cloudflare Tunnel（`*.trycloudflare.com`）臨時公開預覽：

https://seasideresort.com.tw/booking/pm_roomboard/pm_member_portal_v18.php

可上傳檔在 [`deploy/`](deploy/README.md)。舊網址 `pm_member_portal_v17.php#socialLogin` 請改連 v18，或掛上 `pm_retire_social_login.js`。

開發者本機（可選，需要 Node.js 18+）：

```bash
npm install
npm test
npm run dev
```

瀏覽器開啟 [http://localhost:3000](http://localhost:3000)。

正式建置：

```bash
npm run build
npm start
```

右下角「預覽工具」可切換：訪客／已通過／待人工／未通過／月租會員。這不是正式後台，只方便驗收門禁。

建議用手機寬度走一遍：

1. 首頁主 CTA → 了解問卷（第 1 題生活概念有說明）
2. 選「海邊慢生活／想先體驗但接受會員前提／合理預算」→ **通過** → `/membership/verify`（對齊官網社群登入：手機簡訊走 SMS Go 真閘道，再加 Email／Google／LINE／Apple）→ 簽約申請 → 體驗安排
3. 另開無痕或按「重設為訪客」，選「只要幾天觀光」→ **未通過** → 直接開 `/member/experience` 應被擋下
4. 萬歲咖啡：放入未滿 NT$1,200 的商品，運費應為 NT$100；放入「一日店長聯名禮袋」應免運

## 價格（規格原文）

| 方案 | 價格 |
|------|------|
| 人生海景清單（月租） | 首月 NT$18,000；第二個月起 NT$59,800／月 |
| 季租 | NT$45,000／月（連續居住三個月） |
| 年租 | NT$33,000／月 |

咖啡運費：**滿 NT$1,200 免運，否則 NT$100**。以每一箱／每一次出貨的商品金額計算。

## 問卷過濾（本版實作）

- **通過**：身分為 50+ 或遠端工作者，且生活概念為海邊慢生活或溫暖戶外，意向不是觀光日租、不拒絕會員制、預算與時程合理。
- **待人工**：還沒決定、山邊田園／城市便利、預算先看環境或未滿 2 萬、時程或同住未定。
- **未通過**：沒有身分硬條件、只要幾天觀光、拒絕會員制、或把體驗當「便宜住幾晚」。拒絕優先於待人工。

結果文案依規格：通過才邀請月租；未通過關閉體驗入口；待人工留資、暫不開體驗。

## 訂單類型

本地 JSON（`data/store.json`，不進 git）只會寫這三種，**沒有** `hotel_booking`：

- `membership_application`
- `experience_request`
- `coffee_order`

後端預留註解式 handoff：正式電子簽約、LINE Pay／Apple Pay 金鑰皆未接入。相關環境變數以後可加 `LINE_PAY_*`、`APPLE_PAY_*`，本版用 mock 成功頁。

### 正式簡訊與會員對齊（環境變數，勿提交真實值）

必填才能呼叫 SMS Go：

- `SMSGO_USERNAME`：SMS Go 會員帳號
- `SMSGO_API_KEY`：SMS Go API Key（主機檔 `smsgo-api-key.txt`；也可用 `SMSGO_PASSWORD`）

正式發送還要打開交付包同一組旗標：

- `SMSGO_ENABLED=true`
- `SMSGO_CONTROLLED_TEST=true`
- `SMSGO_ALLOWED_PHONES=+8869xxxxxxxx`（受控測試允許清單）

選填：`SMSGO_APPROVED_TEMPLATE`（預設正式核准文案，須恰好一個 `{code}`）、`MEMBER_API_BASE_URL`、`MEMBER_API_TOKEN`。

SMS Go 後台需開通 API，並把本站出站 IP 加入允許清單（否則 `-15`）。範本見 `.env.example`。

## 假設清單

規格未寫死的細節，本版這樣做（沒有發明住宿型商品）：

1. 問卷題目可改 `src/data/quiz-questions.ts`；過濾規則在 `src/lib/quiz.ts`。
2. 「想先體驗再長住」只要接受會員前提，且其他條件適配，可列為通過。
3. 山邊田園、城市便利視為與海岸本場未完全對齊，先待人工。
4. 月租申請通過後，可用「模擬完成簽約」把狀態標成月租會員。簽約前必須完成驗證；不能只靠手填。
4a. Google／LINE／Apple 為 mock OAuth，點選後帶入示範姓名、信箱、手機。正式金鑰可接 `GOOGLE_*`、`LINE_LOGIN_*`、`APPLE_*`。
4b. **手機簡訊對齊正式 `pm_smsgo_adapter.php`**：`POST https://www.smsgo.com.tw/sms_gw/sendsms.aspx`（username + API Key、核准模板 `{code}`）。金鑰只讀環境變數，不寫進 repo。正式流量走 `seasideresort.com.tw` 會員入口；主機金鑰在 `/home/tdwhhyfe/pm_member_private/smsgo-api-key.txt`。交付包預設 `enabled=false`，未開旗標時送碼回 503，**不再接受 `123456` 當正式簡訊碼**。
4c. 不另造會員庫。驗證後以手機號碼作為與 [pm_member_portal v18](https://seasideresort.com.tw/booking/pm_roomboard/pm_member_portal_v18.php)／`qlo_pm_member` 同一識別。正式 PHP 已補 `phone_login_request`／`phone_login_verify`（訪客簡訊登入）；登入後綁定仍走 `phone_request`／`phone_verify`。
4d. 電子郵件仍為預覽 OTP（`EMAIL_PREVIEW_OTP`，預設 `123456`）。Google／LINE／Apple 仍為 mock OAuth。
5. 體驗表單收集日期區間、入住人、電話、備註；文案提到三天兩夜只作為生活節奏說明，不是免費住房促銷。
6. 咖啡兩週節奏預設連續 6 次、按月預設 3 次。
7. **每次貨到付款**：本次只收第一箱商品＋該箱運費。
8. **一次付清、分次寄**：本次收全部商品＋第一箱運費；後續箱運費仍依該箱金額另計。
9. 一次付清不可搭配貨到付款（貨到付款用在每次出貨）。
10. 超商門市第一版為手填（尚未接電子地圖選店）。
11. 公司發票統編由客人自填，前端與 API 檢查 8 碼。
12. 聯絡電話沿用現有官網公開資訊。
13. 視覺 token：`--ocean:#2E5E73`、`--deep:#1C3D4C`、`--cream:#F6F1E7`。

## 技術

- Next.js 15 App Router、React 19、TypeScript、Tailwind CSS
- 會員狀態存在 httpOnly cookie；`/member/experience` 另有 middleware 門禁
- 單元測試：`npm test`（問卷、運費、體驗門禁、禁用詞掃描）

禁用詞（客人可見 UI／訂單名）：房費、訂房、訂房訂單、每晚、日租價。改用：月租會員、會員方案、體驗安排／體驗確認、咖啡訂單／伴手禮訂單。
