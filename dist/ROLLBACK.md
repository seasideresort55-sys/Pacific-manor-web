# 回復

這包沒有部署。遠端還沒有這次的檔案要撤。

## v13 轉換

`php migrate/v13_convert.php --apply` 在開始改資料之前，會把當時的資料庫複製成同一路徑的：

```text
garden.sqlite.bak-YYYYmmddHHMMSS
```

正式庫的路徑是 `/home/irihvsuu/garden_private/garden.sqlite`，備份就在它旁邊。

回復是把該檔蓋回去，然後重新載入 PHP：

```bash
cp /home/irihvsuu/garden_private/garden.sqlite.bak-YYYYmmddHHMMSS \
   /home/irihvsuu/garden_private/garden.sqlite
```

把時間戳換成該次輸出的 `backup` 那一行。

轉換若在寫入中途失敗，SQLite 交易會整筆退回。檔案備份仍然留著，以備份為準。不要為了「清乾淨」去刪 `legacy_*` 表；那些表是改名後的 v13 原表，回復備份時會一起回到改名前的狀態。

`--dry-run`，以及欄位還沒對齊時的 `--apply`，都不會產生備份，也不會改檔。那種情況不需要回復。

## v14 玫瑰庫遷移

`php migrate/v14.php` 也會先複製當時 `db()` 指到的 sqlite，檔名同樣是 `.bak-YYYYmmddHHMMSS`。它不刪列。若在正式庫上跑過，回復方式與上面相同：用該次備份蓋回。

不要把 zip 裡的 `garden/data/garden.sqlite` 蓋上正式庫。那是檢視用的玫瑰庫。

## 程式檔

若已經把 zip 解進 `public_html/garden`，而要回到解壓前的網站，把解壓前自己留的 `public_html/garden` 目錄蓋回去。這次包裝沒有在遠端留下自動備份。
