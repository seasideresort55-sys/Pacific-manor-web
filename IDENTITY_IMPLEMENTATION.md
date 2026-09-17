# 會員身分實作說明（正式 qlo_pm_member）

日期：2026-09-17（依線上庫存修正）  
對象：seasideresort.com.tw `/booking/pm_roomboard/`  
**沒有另建 Next／mock 會員庫。**

建議正式入口：

https://seasideresort.com.tw/booking/pm_roomboard/pm_member_portal_v17.php#socialLogin

綁定頁（上傳後）：

https://seasideresort.com.tw/booking/pm_roomboard/pm_member_bind.php

---

## 實際表結構

會員主檔仍是 **`qlo_pm_member`**。

| 欄位／表 | 角色 |
|----------|------|
| `qlo_pm_member.id_member` | 主鍵＝規格的 **`user_id`**（網站會員編號） |
| `qlo_pm_member.phone` | E.164，UNIQUE NULL（訪客簡訊登入鍵） |
| `qlo_pm_member.email` | **輔助聯絡**。Apple Relay 可用。**不可 UNIQUE**（只留普通 index） |
| `qlo_pm_member.apple_sub`／`google_sub` | 可選欄位，UNIQUE NULL |

**社群識別（線上既有路徑，可與欄位並存）：**

`qlo_pm_social_identity`  
`UNIQUE(provider, subject)` → `id_member`（= `user_id`）

| provider | subject |
|----------|---------|
| `apple` | Sign in with Apple `sub` |
| `google` | Google OIDC `sub` |

函式 `qlo_pm_social_identity($provider, $subject)` **先用 sub 查這張對照表**，再回退欄位 `apple_sub`／`google_sub`。兩種存法都可以，只要不把 Email 當唯一鍵。

`pms_schema()` **不再要求** `Unique email migration required`：若看到 Email UNIQUE 會 **DROP**，改普通 `idx_pm_member_email`，並讓 `email` 可 NULL。

---

## 線上 drop-in 檔（路徑對齊 `/booking/pm_roomboard/`）

| 檔案 | 對線上 |
|------|--------|
| `pm_member_social_lib.php` | **覆蓋**。含 `qlo_pm_social_identity`、`pms_schema`、`pms_member` |
| `pms_schema.php` | 載入上述 schema（Email 不 UNIQUE） |
| `pms_member.php` | 建立／綁定／手機登入；也可當身分 API |
| `pm_google_lib.php` | **覆蓋**。find-or-create 只認 Google `sub`，允許空 Email |
| `pm_member_identity_install.php` | 跑 `pms_schema()` |
| `pm_member_identity_api.php` | 綁定＋訪客 SMS action |
| `pm_smsgo_identity.php` | 訪客簡訊（**不要覆蓋** `pm_smsgo_adapter.php`） |
| `pm_google_api.php`／`pm_apple_api.php` | 社群登入 API |
| `pm_member_identity.js` | 入口頁掛上即可打手機／綁定 |

---

## 登入／綁定 API

基底：`https://seasideresort.com.tw/booking/pm_roomboard/`

| action | 說明 |
|--------|------|
| `status`／`identity_status` | `user_id`＝`id_member`、已綁項目 |
| `request_phone_login_code` | 訪客 SMS OTP（SMS Go） |
| `verify_phone_login_code` | 驗證後**只依 phone** 登入或新建 |
| `oauth_login`／`pms_member('create')` | Apple／Google **只認 sub**；無 Email 可建 |
| `oauth_bind`／`bind_google`／`bind_apple` | 已登入綁定，需 `confirm_bind=1` |
| `bind_phone` | 已登入＋已驗證手機 |
| `sms_verified_upsert` | 伺服器 token，已驗證手機對齊同一會員 |

規則：

1. Find-or-create **只依 sub**（`qlo_pm_social_identity` 或欄位）。沒有 Email（Hide My Email 第二次不給信箱）**可以建檔**，不再出現「登入服務未提供 Email…」。
2. 新 sub 但 Email 已在別的 `id_member`：**綁定提示**（`next_step=confirm_bind`），不是硬擋、也不是靜默合併。
3. 已登入再完成另一家：先問「是否綁定到同一個會員」。
4. sub／phone 已在別的 `user_id`：  
   「這個 Google／Apple／手機號碼已綁定其他會員，請先用該方式登入，或聯絡客服。」
5. 聯絡優先：手機 → Email → OAuth 只負責登入。
6. 預約同一套 `qlo_pm_member`。簡訊仍是 SMS Go。

---

## 驗收清單

| 項目 | 狀態 |
|------|------|
| `id_member` 即 `user_id` | **已文件化／API 回傳兩者** |
| Email **不是** UNIQUE；`pms_schema` 不要求 unique email migration | **已修**（會 drop UNIQUE） |
| Apple Hide My Email／空 Email 可只靠 sub 建檔 | **已修**（不再丟「登入服務未提供 Email」） |
| 同一 Apple／Google sub 再登 → 同一 `user_id` | **已有**（`qlo_pm_social_identity` 先查 sub） |
| Email 已在別的會員 → 綁定提示，不靜默合併、不硬擋 | **已修** |
| 已登入綁另一家 → 先確認 | **已有** |
| sub／phone 衝突白話中文 | **已有** |
| 訪客手機 SMS 登入／建檔 | **已有**（`request_phone_login_code`／`pms_member('phone_login')`） |
| SMS 仍走 SMS Go | **已有** |
| 與預約同一會員表 | **已有** |

```bash
php deploy/pm_roomboard/tests/identity_logic_test.php
```

線上寫庫需上傳 drop-in 並跑 `pms_schema()`／安裝頁。Apple／Google Client ID 與 SMS Go 金鑰仍在 `pm_member_private/`。

---

## 部署

1. 上傳 `deploy/pm_roomboard/` 全部到 `/booking/pm_roomboard/`（覆蓋 `pm_member_social_lib.php`、`pm_google_lib.php`）。
2. **不要覆蓋** `pm_smsgo_adapter.php`、`pm_member_social.php`（LINE）、除非只在 `pm_member_api_v17.php` 加 bridge 4 行。
3. 跑 `php pm_member_identity_install.php` 或瀏覽器安裝頁（`pms_schema()`）。
4. 入口頁加 `<script src="pm_member_identity.js"></script>`。

主機私密檔：`smsgo-api-key.txt`、`google-client-id.txt`、`apple-service-id.txt`、`member-api-token.txt`、`identity-install.key`。
