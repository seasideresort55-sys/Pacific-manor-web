# 會員驗證碼主機設定（僅內部）

這份文件給站務／主機操作，**不要**把路徑、金鑰檔名或資料表指令顯示在瀏覽器。

客人看到的失敗文案應只有：

> 暫時無法寄送驗證碼，請稍後再試或改用其他方式

程式已把內部錯誤洗成這句。驗證碼**不會**在未完成下列設定時真的寄出。

## 簡訊（SMS Go）

1. 在主機私密目錄放入金鑰（勿進 git）：
   - `/home/tdwhhyfe/pm_member_private/smsgo-api-key.txt`
   - 可選 `smsgo-username.txt`、`runtime.php`
2. 環境變數（800+ Next 預覽）或 `runtime.php` 旗標：
   - `SMSGO_USERNAME`
   - `SMSGO_API_KEY`（也可用 `SMSGO_PASSWORD`）
   - 正式發送還要 `SMSGO_ENABLED=true`、受控測試旗標與允許手機清單
3. SMS Go 後台：開通 API，並把本站出站 IP 加入允許清單。
4. 上傳 `pm_phone_host_v18.php`，補上 live v17 已呼叫但缺失的 `pm_phone_host_dispatch()`。

沒有金鑰時，API 必須回客人友善句，不可出現「簡訊金鑰尚未設定」或 `pm_member_private`。

## 電子郵件唯一索引

live `request_email_code` 目前會回「會員 Email 唯一索引尚未準備完成」。

請在會員資料表（`qlo_pm_member` 或實際使用的會員表）為電子郵件欄位補上 **UNIQUE** 索引；空值策略依現有資料清理後再加。具體欄位名以主機 `SHOW COLUMNS` 為準。

完成前，前端只顯示「暫時無法寄送驗證碼…」，不提示索引或資料表名稱。

## 建議上傳（客人頁）

| 檔案 | 用途 |
| --- | --- |
| `pm_guest_errors.php` / `pm_guest_errors.js` | 洗掉內部錯誤 |
| `pm_member_portal_v17.php` + `pm_member_entry_v18.js` | 驗證碼登入文案 |
| `pm_member_center_guest.js` | 會員中心未登入改走同一入口 |
| `pm_front/index.html` | 會員旅程，不再是「安排入住」 |
| `wordpress/pm-legacy-member-redirects.php` | `page_id=11240/2107/901` 改指到會員／方案頁 |
| `www/pm_eligibility.js` | 官網體驗表拿掉 1960 預設年 |

可選：把現有 `pm_member_api_v17.php` 改名為 `pm_member_api_v17.core.php`，再上傳 `pm_member_api_v17.wrapper.php` 作為同名檔，從伺服器端洗 JSON。
