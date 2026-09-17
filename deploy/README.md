# 會員登入 v18（可上傳至正式 `pm_roomboard/`）

取代訪客主畫面的醜 `#socialLogin`「其他登入方式」區塊。正式入口改為 **登入或註冊**，手機簡訊為主路徑。

## 建議正式網址

```
https://seasideresort.com.tw/booking/pm_roomboard/pm_member_portal_v18.php
```

舊連結 `pm_member_portal_v17.php#socialLogin` 不應再當公開入口。上傳後請把官網／信件／QR 改指向 v18。若 v17 網址必須暫留，把 `pm_retire_social_login.js` 加進 v17：

```html
<script src="pm_retire_social_login.js"></script>
```

瀏覽器 hash `#socialLogin` 不會送到伺服器，所以無法只靠 PHP 301。

## 上傳清單（同一資料夾 `public_html/booking/pm_roomboard/`）

| 檔案 | 說明 |
| --- | --- |
| `pm_member_portal_v18.php` | 訪客登入／註冊新畫面 + 原入住申請 |
| `pm_member_api_v18.php` | 先載入 phone host，再 `require` 現有 `pm_member_api_v17.php` |
| `pm_phone_host_v18.php` | 補上 live v17 已呼叫但缺失的 `pm_phone_host_dispatch()` |
| `pm_phone_lib_v18.php` | 電話正規化、OTP hash、SMS Go 解析 |
| `pm_member_client_v18.js` | CSRF 握手改打 v18 |
| `pm_member_entry_v18.js` | 手機 → Email → 社群 → 密碼文字連結 |
| `pm_retire_social_login.js` | 可選：把舊 `#socialLogin` 導向 v18 |

**不要覆蓋** live 的 `pm_member_social.php`（Google／LINE 正式 OAuth）。

現有 live `pm_member_api_v18.php` 是較舊的 stub（無 CSRF、未知操作）。請用本目錄檔案覆蓋。

## 行為

1. **主路徑**：台灣手機 → `phone_login_request` → 6 碼 → `phone_login_verify` → 寫入／對齊 `qlo_pm_member` → 同一套 `PMMEMBER` session。
2. **次路徑**：既有 `request_email_code` / `verify_email_code`（v17 寄信）。
3. **或使用**：Google、LINE 品牌鈕（`pm_member_social.php`）；Apple 灰態「即將開放」。
4. **密碼**：僅文字連結，仍走 v17 `login`。
5. 未勾同意就送碼／點社群：卡片內輕提示，不整頁紅字。
6. **正式路徑沒有固定碼 123456**。驗證只比對 SMS Go 寄出的 hash。

## 主機依賴（簡訊）

與現況相同：

- `/home/tdwhhyfe/pm_member_private/smsgo-api-key.txt`
- 可選 `smsgo-username.txt`、`runtime.php`（`$pm_smsgo` 或 `SMSGO_*`）
- 若 `pm_smsgo_adapter.php` 已定義 `pm_smsgo_send` 等函式，優先走 adapter

Live 探測結果：v17 對 `phone_*`（含 `phone_login_request`）已路由到 `pm_phone_host_dispatch()`，但函式未載入，回 `Call to undefined function pm_phone_host_dispatch()`。本包補上該函式。

## 若上傳後簡訊登入仍失敗

依 API JSON 的 `gap` 欄位：

| gap | 意思 |
| --- | --- |
| `smsgo_credentials` | 主機讀不到 SMS Go 帳號／API Key |
| `smsgo_enabled` | runtime 關閉發送 |
| `qlo_pm_member_write` | 驗證碼對了，但 `qlo_pm_member` 無法自動 INSERT（欄位與假設不符） |
| `pm_member_session` | 會員列已對齊，但 v17 session 鍵無法寫入；請改用 Email 碼登入，並把 v17 的 `pm_member_set_session` 類函式名告訴我們 |

Email 與密碼不依賴這層，應維持可用。

## 本機預覽（無 v17）

```bash
php deploy/tests/run.php
php -S 127.0.0.1:8088 -t deploy
```

開啟 http://127.0.0.1:8088/pm_member_portal_v18.php

本機沒有 SMS Go 時，送碼回應會帶 `preview_code`（隨機 6 碼，不是 123456）。正式網域不會回傳預覽碼。
