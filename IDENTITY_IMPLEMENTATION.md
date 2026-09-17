# 會員身分實作說明（正式 qlo_pm_member）

日期：2026-09-17  
**禁止覆蓋** 線上 `pm_member_social_lib.php`（約 12091 bytes）與 `pm_google_lib.php`（約 8426 bytes）。  
上一版 6KB stub 會讓 `pm_member_social.php?action=providers` 變成 503「登入服務暫時無法完成…」。

建議正式入口：

https://seasideresort.com.tw/booking/pm_roomboard/pm_member_portal_v17.php#socialLogin

先看：`deploy/pm_roomboard/UPLOAD.txt`、`PATCH_LIVE_SOCIAL_LIB.txt`

---

## 實際表結構

| 欄位／表 | 角色 |
|----------|------|
| `qlo_pm_member.id_member` | 主鍵＝**`user_id`** |
| `qlo_pm_member.phone` | E.164，UNIQUE NULL |
| `qlo_pm_member.email` | 輔助；**不可 UNIQUE** |
| `qlo_pm_member.apple_sub`／`google_sub` | 可選 UNIQUE NULL |
| `qlo_pm_social_identity` | 線上既有：`UNIQUE(provider,subject)` → `id_member` |

`qlo_pm_social_identity($provider,$subject)` 已先用 sub 找會員。不要重寫這支函式。

---

## 檔案策略

| 檔案 | 動作 |
|------|------|
| **線上 `pm_member_social_lib.php`** | **保留原檔。** 只加 4 行 require hooks + 字串補丁 |
| **線上 `pm_google_lib.php`** | **保留原檔。** deploy 的 701b 是薄包裝，不是完整替身 |
| `pm_member_identity_hooks.php` | **新傳**。不宣告 `pms_*` |
| `pm_member_identity_*.php`／`pm_apple_*`／`pm_member_identity.js` | 可留 |
| `deploy/do-not-upload/` | 誤傳紀錄，不要上傳 |

`pm_google_api.php`（deploy）讀 `google-client-id.txt`，與線上 social.php 的 Google／LINE **不是同一條路**。正式社群登入以還原後的 `pm_member_social.php` 為準。

---

## 線上 social_lib 外科補丁

見 `deploy/pm_roomboard/PATCH_LIVE_SOCIAL_LIB.txt`：

1. require `pm_member_identity_hooks.php`
2. `Unique email migration required` → `pm_identity_schema_relax_email()`
3. `登入服務未提供 Email` → 允許空 Email 繼續用 sub 建檔
4. Email 已在別的會員 → `pm_identity_after_oauth_profile()`（`confirm_bind`，不靜默合併）
5. 訪客 SMS：`pm_member_identity_api.php`（SMS Go），不要動原 adapter

JWT、LINE、Apple `.p8` 全部留在原 social_lib。

---

## 身分 API（已上傳可留）

`pm_member_identity_api.php`：`request_phone_login_code`／`verify_phone_login_code`／`oauth_bind`／`bind_phone`

衝突：「這個 Google／Apple／手機號碼已綁定其他會員，請先用該方式登入，或聯絡客服。」

---

## 驗收

| 項目 | 狀態 |
|------|------|
| 上傳後 `?action=providers` 仍回 google/line | **靠不覆蓋 social_lib** |
| Email 非 UNIQUE | hooks `pm_identity_schema_relax_email` |
| 空 Email 可建 | hooks `pm_identity_after_oauth_profile` |
| Email 已存在 → 綁定提示 | 同上 |
| 訪客 SMS | identity API |
| 本 pack **不再附** `pm_member_social_lib.php` | **已改** |

```bash
php deploy/pm_roomboard/tests/identity_logic_test.php
```
