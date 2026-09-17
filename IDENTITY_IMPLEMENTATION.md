# 會員身分實作說明（正式 qlo_pm_member）

日期：2026-09-17  
對象：seasideresort.com.tw 線上 PHP 會員（`pm_member_*`／QloApps `qlo_pm_member`）  
**沒有另建 Next／mock 會員庫。**

建議正式入口：

https://seasideresort.com.tw/booking/pm_roomboard/pm_member_portal_v17.php#socialLogin

綁定頁（上傳後）：

https://seasideresort.com.tw/booking/pm_roomboard/pm_member_bind.php

---

## 實際表結構

資料表：`qlo_pm_member`（QloApps 前綴 `qlo_`）。

| 欄位 | 角色 |
|------|------|
| `id_member` | 主鍵＝規格的 `user_id`（網站會員編號） |
| `apple_sub` | Sign in with Apple `sub`，UNIQUE，可 NULL |
| `google_sub` | Google OIDC `sub`，UNIQUE，可 NULL |
| `phone` | E.164（例 `+886986770718`），UNIQUE，可 NULL |
| `email` | 輔助聯絡（Apple Relay 可用）。**不是 UNIQUE** |
| `name` | 顯示名稱 |
| `date_add`／`created_at` | 既有註冊時間欄（哪個存在就寫哪個） |

約束（安裝程式會偵測後再改，可重跑）：

- 主鍵維持 `id_member`
- `apple_sub`／`google_sub`／`phone`：UNIQUE NULL（空字串先改成 NULL，避免多筆 `''` 撞唯一）
- `email`：若原本 UNIQUE，改成普通 `idx_pm_member_email`
- **不刪欄位、不另開會員表**

安裝：

1. 上傳 `deploy/pm_roomboard/` 到 `/booking/pm_roomboard/`
2. CLI：`php pm_member_identity_install.php`  
   或瀏覽器加 `?key=`（金鑰放 `/home/tdwhhyfe/pm_member_private/identity-install.key`）
3. 對照 SQL：`deploy/pm_roomboard/sql/qlo_pm_member_identity.sql`（線上請優先跑 PHP，避免重複 ALTER）

---

## 登入／綁定 API

基底：`https://seasideresort.com.tw/booking/pm_roomboard/`

| 檔案 | 用途 |
|------|------|
| `pm_member_identity_api.php` | 身分 API（CSRF 與 v17 相同用法：先 GET 再 POST + `X-PM-CSRF`） |
| `pm_google_api.php` | Google：`prepare`／`login`／`bind`，**只認 `google_sub`** |
| `pm_apple_api.php` | Apple：`prepare`／`login`／`bind`，**只認 `apple_sub`** |
| `pm_apple_callback.php` | Apple `form_post` 回呼 |
| `pm_member_social_identity.php` | Google／Apple 開關（**不要覆蓋**線上 `pm_member_social.php`，以免 LINE 中斷） |
| `pm_smsgo_identity.php` | 訪客簡訊登入／綁定，走 SMS Go `sendsms.aspx`（**不要覆蓋** `pm_smsgo_adapter.php`） |
| `pm_member_identity_bridge.php` | 可選：讓舊的 `pm_member_api_v17.php` 轉送新 action |

`pm_member_identity_api.php` action：

| action | 說明 |
|--------|------|
| `status`／`identity_status` | 是否登入、`user_id`、已綁項目、聯絡優先序 |
| `request_phone_login_code` | 手機 OTP（別名：`request_sms_login_code`、`request_phone_code`） |
| `verify_phone_login_code` | 驗證後**只依 phone** 登入或新建 |
| `oauth_login` | Apple／Google：請求帶 `provider` + `id_token`／`credential` |
| `oauth_bind`／`bind_google`／`bind_apple` | 已登入綁定；需 `confirm_bind=1` |
| `bind_phone` | 已登入＋已驗證手機後綁定 |
| `sms_verified_upsert` | 伺服器 token（`X-PM-SERVER-TOKEN`）用已驗證手機對齊同一會員 |
| `logout` | 登出 |

規則：

- Apple／Google：**只用 sub** find-or-create。Email／Relay 可寫入 `email`，不當查找條件。
- 已登入再完成另一家：回 `next_step=confirm_bind` 與白話提示，**未勾選不合併**。
- 衝突（sub／phone 已在別的 `id_member`）：  
  「這個 Google／Apple／手機號碼已綁定其他會員，請先用該方式登入，或聯絡客服。」
- 聯絡優先：有手機→手機；否則 Email；OAuth 只負責登入。
- 預約／入住仍是同一套 `qlo_pm_member`。簡訊仍是已付費 SMS Go。

可選，在 `pm_member_api_v17.php` 啟動後加 4 行（讓入口頁既有 `request_phone_login_code` 探測直接成功）：

```php
if (is_file(__DIR__ . '/pm_member_identity_bridge.php')) {
    require_once __DIR__ . '/pm_member_identity_bridge.php';
    if (function_exists('pm_identity_bridge_dispatch') && pm_identity_bridge_dispatch()) { return; }
}
```

入口頁請加：

```html
<script src="pm_member_identity.js"></script>
```

主機私密檔（勿進 git）：

- `/home/tdwhhyfe/pm_member_private/smsgo-username.txt`
- `/home/tdwhhyfe/pm_member_private/smsgo-api-key.txt`（既有）
- `/home/tdwhhyfe/pm_member_private/google-client-id.txt`
- `/home/tdwhhyfe/pm_member_private/apple-service-id.txt`
- `/home/tdwhhyfe/pm_member_private/member-api-token.txt`（給 `sms_verified_upsert`）
- `/home/tdwhhyfe/pm_member_private/identity-install.key`

---

## 驗收清單

| 項目 | 狀態 |
|------|------|
| Apple（含 Hide My Email）註冊 → 一筆會員，主鍵非 Email | **程式已做**（無 Email 或只有 Relay 都可建）。線上需 Apple Service ID + Return URL=`…/pm_apple_callback.php` |
| 同一 Apple 再登 → 同一 `user_id` | **程式已做**（只查 `apple_sub`） |
| 再綁 Google → 同一 `user_id`，兩邊都能登 | **程式已做**（已登入先提示再寫 `google_sub`） |
| 再綁手機 → SMS 登同一帳 | **程式已做**（E.164 UNIQUE） |
| 已綁定的 Google／Apple／手機再登不開新帳 | **程式已做** |
| Email／Relay 變更不影響登入 | **程式已做**（登入不查 Email） |
| 衝突時白話中文、無英文代碼 | **程式已做** |
| 未驗證不靜默合併 | **程式已做**（`confirm_bind`） |
| 不擋 Apple Hide My Email | **程式已做** |
| 簡訊仍走 SMS Go | **程式已做**（`pm_smsgo_identity.php`，不覆蓋原 adapter） |
| 與預約同一會員表 | **程式已做**（只碰 `qlo_pm_member`） |

線上尚未由本環境直接寫入資料庫（此 repo 沒有正式 DB 帳密）。部署後請用安裝頁確認欄位，再用真 Apple／Google／一支測試手機走一次。

單元測試（不連線上庫）：

```bash
php deploy/pm_roomboard/tests/identity_logic_test.php
```

---

## 部署注意

- **可替換**（線上 Google API 目前是「尚未啟用」）：`pm_google_api.php`、`pm_google_login.php`、`pm_google_notice.php`
- **只新增、勿覆蓋**：`pm_smsgo_adapter.php`、`pm_member_social.php`、`pm_member_api_v17.php`（除非只加 bridge 4 行）
- 既有 Email OTP／密碼登入仍由 v17 處理；**新的 Apple／Google／手機路徑不再用 Email 當唯一身分**
- Session 會寫 `id_member`／`pm_id_member`／`pm_identity_user_id`。若 v17 登入態沒接上，請加 bridge 或在 v17 讀這些 key

覆蓋 `pm_google_login.php` 後，舊的「用信箱＋莊園密碼綁 Google」會拿掉，改為 **sub 找或建／已登入再確認綁定**。
