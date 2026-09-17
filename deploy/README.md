# 會員登入 v18（可上傳至正式 `pm_roomboard/`）

取代訪客主畫面的醜 `#socialLogin`「其他登入方式」區塊。正式入口改為 **登入或註冊**，手機簡訊為主路徑。

## 建議正式網址（覆蓋現有檔名）

```
https://seasideresort.com.tw/booking/pm_roomboard/pm_member_portal_v17.php
```

請用本目錄的 `pm_member_portal_v17.php` **覆蓋** 正式站同名檔。舊 `#socialLogin` 不再當獨立入口，會落在同一張「登入或註冊」卡。`pm_member_portal_v18.php` 只是同一頁的別名。

```html
<script src="pm_retire_social_login.js"></script>
```

瀏覽器 hash `#socialLogin` 不會送到伺服器，所以無法只靠 PHP 301。

## 上傳清單（同一資料夾 `public_html/booking/pm_roomboard/`）

| 檔案 | 說明 |
| --- | --- |
| `pm_member_portal_v17.php` | **覆蓋正式同名檔**：訪客登入／註冊（含可點的 Apple） |
| `pm_member_portal_v18.php` | 同一頁別名 |
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
3. **或使用**：Google、LINE、**可點的 Apple**（`pm_member_social.php` → fallback `pm_member_social_identity.php`／`pm_apple_api.php`）。認人用 **apple_sub**，不把 Email 當主鍵（對齊 PR #3 IDENTITY）。隱藏信箱可用。
4. **密碼**：僅文字連結，仍走 v17 `login`。
5. 未勾同意就送碼／點社群：卡片內輕提示，不整頁紅字。
6. **正式路徑沒有固定碼 123456**。驗證只比對 SMS Go 寄出的 hash。

## Apple 登入（可點，不是「即將開放」）

按鈕預設啟用。點擊順序：

1. `POST pm_member_social.php`（`provider=apple`）→ 應回 `https://appleid.apple.com/auth/authorize`
2. 若 live social 仍回 `providers.apple=false`，改打 PR #3 的 `pm_member_social_identity.php`／`pm_apple_api.php`
3. Callback 用 **apple_sub** 找／建 `qlo_pm_member`（`qlo_pm_social_identity` 或欄位 `apple_sub`）。Hide My Email 可以建檔。

主機要有（皆在 `/home/tdwhhyfe/pm_member_private/`，勿進 git）：

| 檔／環境變數 | 用途 |
| --- | --- |
| `apple-service-id.txt` 或 `APPLE_CLIENT_ID`／`APPLE_SERVICE_ID` | Sign in with Apple **Service ID**（也就是 client_id，例如 `com.seasideresort.web`） |
| `apple-redirect-uri.txt` 或 `APPLE_REDIRECT_URI` | 可選；預設 `…/pm_apple_callback.php` |
| Apple `.p8` 金鑰／Team ID／Key ID | 若之後要 server-to-server 換 token；目前認人只驗 **id_token 的 sub**（Apple JWKS），callback 在 PR #3 |

Apple Developer 後台還要：Return URL 對齊 callback、網站用 Service ID、勾選 Sign in with Apple。

**現況：** live `pm_member_social.php` 回 `apple: false`，PR #3 的 `pm_apple_*.php` 尚未在正式站。上傳本頁後 Apple **鈕可點**；要真正走完 OAuth，需放上 `apple-service-id.txt`，並一併上傳 PR #3 身分檔（或在 live social 打開 apple）。沒金鑰時點擊會得到白話錯誤，不會整頁壞掉。

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
