# 部署說明

這包是 v14.1。沒有部署到任何主機。

前台放在 `public_html/garden`。文件根目錄維持 `public_html`，不要改成 `garden/public`，也不要使用 `router.php`。Apache 用包裡的 `garden/.htaccess`（`RewriteBase /garden/`）。

## 網址

- 全站前台：https://seasideresort.com.tw/garden/
- 檢視庫單店：https://seasideresort.com.tw/garden/store/太平洋莊園
- 轉換後的 live 店名是「東海岸太平洋莊園」：https://seasideresort.com.tw/garden/store/東海岸太平洋莊園
- 廠商後台：https://seasideresort.com.tw/garden/vendor

金流固定是 MOCK。程式會忽略設定檔裡的其他金流。

## 私人設定（不要放進 public_html）

在主機建立 `/home/irihvsuu/garden_private/`，把 live 的 `garden.sqlite` 留在這裡。另放 `config.php`：

```php
<?php
return [
    'db' => '/home/irihvsuu/garden_private/garden.sqlite',
    'payment' => 'mock',
    'base_path' => '/garden',
    'phone' => '0937667917',
    'line' => '@a0937667917',
    'host' => 'seasideresort.com.tw',
];
```

程式會讀這個檔。資料庫路徑的鍵名 `db` 和 `database` 都認；正式站現有的 `database` 不用改檔。環境變數 `GARDEN_CONFIG` 可以改路徑。`GARDEN_DB` 可以改資料庫路徑。沒讀到路徑時才用 `garden/data/garden.sqlite`。

zip 不含 `garden/data/`，沒有檢視庫 `garden.sqlite`，也沒有玫瑰庫的 json、csv。檢視庫仍在版本庫的 `garden/data/garden.sqlite`，不要把它覆蓋 `/home/irihvsuu/garden_private/garden.sqlite`。不要執行 `GARDEN_RESET=1`。`bin/setup.php` 與 `tests/` 不在 zip 裡。

## cPanel API

正式部署用「用 3f-upload 上傳」那一節。這裡的 cPanel 指令不會備份，也不會跑 v13 轉換。

帳號 `irihvsuu`。下面的 `HOST` 與密碼用主機自己的 cPanel 登入。上傳用 UAPI，解壓仍是 API 2（cPanel 沒有對應的 UAPI 解壓）。

上傳 zip 到家目錄下的 `public_html`。`dir` 相對家目錄，目錄不存在時 UAPI 會建立。檔案要用 multipart，欄位名是 `file-0`。

```bash
curl -u 'irihvsuu:CPANEL_PASSWORD' \
  -F 'dir=public_html' \
  -F 'file-0=@garden-v14.1.zip' \
  https://HOST:2083/execute/Fileman/upload_files
```

解壓。zip 的最外層就是 `garden/`，所以目的地是 `public_html`，解完是 `public_html/garden/`。API 2 的 `fileop` 已標成舊介面，UAPI 沒有 extract。`destfiles` 在官方說明裡寫給 copy、move、rename；extract 實務上用同一個參數當解壓目錄。若 API Shell 報錯，改在檔案管理員把 zip 解到 `public_html`，不要解進已經存在的 `garden/`，以免變成 `garden/garden`。

```bash
cpapi2 --user=irihvsuu Fileman fileop \
  op=extract \
  sourcefiles=public_html/garden-v14.1.zip \
  destfiles=public_html \
  doubledecode=1
```

若要先建目錄（UAPI 沒有 mkdir）：

```bash
cpapi2 --user=irihvsuu Fileman mkdir \
  path=/home/irihvsuu/public_html \
  name=garden \
  permissions=0755
```

確認 `public_html/garden/.htaccess` 有被允許（AllowOverride 含 FileInfo）。PHP 需要 8.3、pdo_sqlite、mbstring。

## 先探查 live v13，再轉換

`migrate/v13_map.php` 已依這次的去識別複本寫齊。那份複本 dry-run 是 `unmapped=0`。`--apply` 跑過一次，再跑一次是 `copied 0`，店家、商品、登入筆數不變。

正式庫若和這份複本的欄位不同，dry-run 仍會把對不到的欄位印出來並停下，不會猜。先跑這一步，它只讀，不備份、不改檔：

```bash
cd /home/irihvsuu/public_html/garden
php migrate/v13_convert.php --dry-run --db=/home/irihvsuu/garden_private/garden.sqlite
```

看到 `DRY-RUN MAP COMPLETE` 才跑：

```bash
php migrate/v13_convert.php --apply --db=/home/irihvsuu/garden_private/garden.sqlite
```

`--apply` 在還有未對應欄位時，會先停下，不備份、不改檔。對應齊了才會：

1. 複製 `garden.sqlite.bak-YYYYmmddHHMMSS` 在原檔旁邊。
2. 把 v13 表改名成 `legacy_*`。
3. 依 `legacy_id` 抄進 v14 表。已有的 `legacy_id` 改既有列、不插第二筆，所以可以重跑。
4. 轉換裡沒有 DELETE。
5. 只有店名剛好是「太平洋莊園」時，才把運費設成 200、滿 2000 免運。「東海岸太平洋莊園」不會被這一步改掉。
6. `is_demo = 1` 的店設 `enabled = 0`，前台不列出。列還在。
7. 商品 `draft` 變成「草稿」。名稱或說明含「新苗預購」的 `sale` 變成「新苗預購」。其餘 `sale` 才變成「上架」。這份複本的 47 筆 `sale` 全部含「新苗預購」，沒有一筆被改成「上架」。
8. 售價、運費、`fees`、`fee_tiers`、免運門檻依 v13 前台除以 100。v13 的 `nt()` 是 `金額 / 100`。大遊行資料庫 36000，前台是 NT$360，轉換後 `price_twd` 是 360。
9. 結帳運費用 `default_service`。這份複本每家都是 HCT。同一物流的尺寸檔若是同一個數字，就用那個數字；否則用 `fees` 裡該物流的數字。東海岸太平洋莊園 HCT 的 S/M/L 都是 45000 分，所以 `shipping_fee_twd` 是 450。
10. 育種者是空白或「待補」時，寫入 `brand`。`shop_status=hidden` 的店前台不列出，列還在。`payment_days` 照原值。預計出貨月份留空。`legacy_*` 只留著，不做前台。

## 照片

v13 前台 `assets/app.js` 的實品照片是 `media.php?name=<32位雜湊>.webp`（縮圖加 `thumb=1`），相對 `/garden/`。植物對照圖是 `assets/plants/plant-001.webp` 與 `assets/plants/thumbs/plant-001.webp`。

雜湊檔不在可直接打開的網址。正式站 `garden/data/` 整層拒絕直接讀，由 `media.php` 讀出檔案。轉換把商品 `photo` 設成同一個 `media.php?name=...`。v14 的 `garden/media.php` 依序讀：

1. `garden/data/media/<檔名>.webp`
2. `garden/data/uploads/<檔名>.webp`
3. 資料庫同一層的 `media/<檔名>.webp`（正式庫在 `garden_private/garden.sqlite` 時，就是 `garden_private/media/`）
4. 家目錄下的 `garden_private/media/<檔名>.webp`

解壓不要刪掉既有的 `garden/data`。zip 裡沒有這個目錄，一般解壓不會清掉已經在那裡的 webp。照片檔不用另外搬。

## 用 3f-upload 上傳

這台讀不到 `/home/box/bin/3f-upload`。步驟檔只使用它支援的指令：`upload`、`delete`、`copy`、`http`、`list`、`domains`、`sslredirect`。引數用空格分開，沒有 `key=value`，也沒有 `extract`。

步驟檔是 `dist/3f-upload.batch`。在版本庫根目錄執行：

```bash
3f-upload --batch dist/3f-upload.batch
```

這次正式站解壓已經完成，轉換在網頁裡失敗。同一支腳本改為四種 mode，轉換在這個 PHP 程序裡做，不呼叫 exec。`set_time_limit(0)`，寫入包在交易裡。失敗會印出例外訊息、檔案、行號，以及 PHP 版本、`memory_limit`、`disable_functions`。

1. `upload dist/garden-v14.1.zip garden-v14.1.zip`  
   zip 放到家目錄 `/home/irihvsuu/garden-v14.1.zip`，不放進 `public_html`。
2. `upload garden/_deploy_b258d97fc24c0fc1.php public_html/garden/_deploy_b258d97fc24c0fc1.php`  
   蓋過先前留下的腳本。token 不對就回 403，不改檔。
3. `http` `mode=check`。只看環境，並對最新的 `garden_backup_*` 做 dry-run。不改網站、不刪腳本。
4. `http` `mode=apply`。先把該備份的 `garden.sqlite` 蓋回 `/home/irihvsuu/garden_private/garden.sqlite`（先前若只寫了一半，從這裡重來），解壓時跳過 `garden/data/`，再在同一個程序裡轉換。成功只印筆數，zip 和腳本都留著。失敗就在同一個請求裡從該備份還原 `garden/` 和 `garden_private/`，網站回到備份裡的 v13，然後印出錯誤。腳本自己不會被蓋掉。

另外兩個網址不在步驟檔裡，要手動開：

- `mode=rollback`：從最新的 `/home/irihvsuu/garden_backup_YYYYmmddHHMMSS/` 還原 garden 與 garden_private。
- `mode=cleanup`：刪掉家目錄的 zip 和這支腳本。刪完後同一個網址應為 404。

check、apply、rollback 不論成敗都不刪腳本。zip 不含 `_deploy_*.php`。不要刪 `garden/data`。

## 河岸苗園、山風花圃

這兩家用 `migrate/stores.json` 與 `migrate/import_stores.php`，不寫進轉換程式。檔裡的登入名稱與密碼雜湊是空的，運費也是空的。雜湊不是以 `$` 開頭、或登入是空的，程式會停下、不寫入。

在伺服器上填好這兩欄再執行。不要把密碼明文放進檔案。已有相同店名或登入名稱就略過，不覆蓋。

```bash
php migrate/import_stores.php --db=/home/irihvsuu/garden_private/garden.sqlite
```

這支程式不建立商品，也不代填運費。店家登入後自己在「運費設定」填。

## 本機看檢視庫

```bash
php -S 127.0.0.1:8081 garden/index.php
```

打開 http://127.0.0.1:8081/garden/ 。這份檢視庫的登入雜湊已換成隨機值，密碼沒有留下。

## 2026-10-17 小修

這次沒有在這台執行上傳。步驟檔是 `dist/3f-upload-shipmonth.batch`，在版本庫根目錄執行：

```bash
3f-upload --batch dist/3f-upload-shipmonth.batch
```

只用 `upload`、`copy`、`http`。先把要蓋掉的舊檔 `copy` 到家目錄的 `garden_backup_20261017_shipmonth/`，再 `upload`。一次性腳本先把 `/home/irihvsuu/garden_private/garden.sqlite` 備份到 `/home/irihvsuu/garden_backup_shipmonth_YYYYmmddHHMMSS/garden.sqlite`（亞洲台北時間），再把東海岸太平洋莊園、狀態是新苗預購的預計出貨改成 `2027年1月`，印出 `preorders` 筆數。`mode=cleanup` 只刪掉腳本自己。檢視庫的月份仍是空的，不拿檢視庫蓋正式庫。

## 沿用 v13、沒有另編的項目

- 金額單位跟 v13 前台：資料庫是分，顯示除以 100。大遊行 36000 分 → NT$360。
- 東海岸太平洋莊園預設物流是 HCT。尺寸檔 S/M/L 都是 45000 分，結帳運費 NT$450，寫進 `shipping_fee_twd`。
- `payment_days` 照原值。店 1 是 1，其餘是 3。
- 預計出貨月份留空，畫面是「預計出貨 待補」。
- `legacy_*` 只保留，沒有前台頁。
- 四季有花玫瑰園保留 `shop_status=hidden`，前台店名對不到，店沒有刪。
- 訂單四張表是 0 筆。`order_lines` 的原店、代賣店、分成在這次沒有列可寫。以後若有訂單，要先補這幾欄，不能猜分成。
