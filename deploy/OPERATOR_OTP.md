# 會員驗證碼：主機／產品決策（僅內部）

**不要**把這份內容、路徑或金鑰檔名顯示給客人。

客人看到的失敗文案應只有：

> 暫時無法寄送驗證碼，請稍後再試或改用其他方式

正式 v17 檔以 cPanel FTPS／Drive 鏡像為準，不是本 repo 的完整拷貝。

## 簡訊（SMS Go）

Live 金鑰路徑外洩來自 `pm_smsgo_identity.php`（及類似檢查）。本包用 `pm_guest_errors.*` 把該類字串洗掉。

主機私密目錄（勿進 git、勿寫進客人頁）：

- `/home/tdwhhyfe/pm_member_private/runtime.php`
- `smsgo-api-key.txt`
- `smsgo-username.txt`

沒有金鑰時驗證碼不會寄出。這是預期；只需對客人友善、對站務 log。

**不要覆蓋** live `pm_smsgo_adapter.php`（身分包複本不可取代正式 adapter）。

上傳 `pm_phone_host_v18.php` 可補 live 已呼叫但缺失的 `pm_phone_host_dispatch()`。

## 產品衝突：Email UNIQUE（需人工決策，本 PR 不改 schema）

兩條正式路徑互相打架，**本 PR 不 ADD 也不 DROP 索引**：

| 路徑 | 檔案 | 對 `qlo_pm_member.email` 的假設 |
| --- | --- | --- |
| Email OTP | live `pm_member_email_code.php`（`pm_code_schema`） | **必須**已有只含 `email` 的 UNIQUE，否則丟「會員 Email 唯一索引尚未準備完成」 |
| 安裝／舊 schema | live `pm_member_install.php` | 建表時帶 `UNIQUE KEY uk_email (email)` |
| 身分綁定（Apple Hide My Email） | PR #3 `pm_member_identity_*` | **刻意拿掉** Email UNIQUE（`email` 只當輔助聯絡；認人用 `apple_sub`／`user_id`） |

在未做產品決策前：

- Email OTP 在沒有 UNIQUE 時會失敗；客人只看到友善句。
- 若為 OTP 補上 `uk_email`，Apple 隱藏信箱／同一聯絡信箱綁多身分可能衝突。
- 若依身分包 DROP UNIQUE，Email OTP 會繼續被 `pm_member_email_code.php` 擋住。

請產品／站務擇一（或改 Email OTP 不再依賴 UNIQUE），再動資料表。

## page_id 11240／2107／901

WordPress 已 301 到首頁。這是內容／SEO 決策，不是 `pm_roomboard` PHP bug。本包不改 WP 轉向；只要本 repo 的 CTA 不要再連這些舊網址。

## `pm_front`

維持體驗／入住交接（handoff）。會員註冊／登入請連 portal／center，不要把 `pm_front` 假裝成入會漏斗。只拿掉客人可見的內部用語。
