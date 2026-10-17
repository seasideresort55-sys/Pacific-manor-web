# 部署說明

這包是 v14.1。沒有部署到任何主機。

前台放在 `public_html/garden`。文件根目錄維持 `public_html`，不要改成 `garden/public`，也不要使用 `router.php`。Apache 用包裡的 `garden/.htaccess`（`RewriteBase /garden/`）。

## 網址

- 全站前台：https://seasideresort.com.tw/garden/
- 太平洋莊園單店：https://seasideresort.com.tw/garden/store/太平洋莊園
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

程式會讀這個檔。環境變數 `GARDEN_CONFIG` 可以改路徑。`GARDEN_DB` 可以改資料庫路徑。

zip 裡的 `garden/data/garden.sqlite` 是玫瑰庫檢視檔，不是 live v13。不要用它覆蓋 `/home/irihvsuu/garden_private/garden.sqlite`。不要執行 `GARDEN_RESET=1`。`bin/setup.php` 不在 zip 裡。

## cPanel API

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

`migrate/v13_map.php` 現在是空的。對不到的欄位會列出來並停下，不會猜。

在主機上先跑 dry-run，把整段輸出留起來。這一步只讀，不備份、不改檔：

```bash
cd /home/irihvsuu/public_html/garden
php migrate/v13_convert.php --dry-run --db=/home/irihvsuu/garden_private/garden.sqlite
```

輸出有每一張表、每一個欄位，以及 `UNMAPPED 表.欄`。把這份結果對完、寫進 `v13_map.php` 之後，才跑：

```bash
php migrate/v13_convert.php --apply --db=/home/irihvsuu/garden_private/garden.sqlite
```

`--apply` 在還有未對應欄位時，會先停下，不備份、不改檔。對應齊了才會：

1. 複製 `garden.sqlite.bak-YYYYmmddHHMMSS` 在原檔旁邊。
2. 把對應表改名成 `legacy_*`。
3. 依 `legacy_id` 抄進 v14 表。已存在的 `legacy_id` 略過，所以可以重跑。
4. 轉換裡沒有 DELETE。
5. 只有店名剛好是「太平洋莊園」時，才把運費設成 200、滿 2000 免運。「東海岸太平洋莊園」不會被這一步改掉。

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
