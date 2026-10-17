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

對應齊之後的第二次 `--apply` 不會再插一筆。若第一次已經成功、只是想確認可重跑，不要用備份蓋回去，否則會回到轉換前。要回到轉換前，才用第一次輸出的那個 `backup` 檔。

## v14 玫瑰庫遷移

`php migrate/v14.php` 也會先複製當時 `db()` 指到的 sqlite，檔名同樣是 `.bak-YYYYmmddHHMMSS`。它不刪列。若在正式庫上跑過，回復方式與上面相同：用該次備份蓋回。

zip 不含 `garden/data/`，也沒有檢視庫。不要另找 `garden/data/garden.sqlite` 蓋上正式庫。

## 程式檔與部署備份

`mode=rollback`，以及 `mode=apply` 失敗時，會從最新的目錄備份把 `public_html/garden` 與 `garden_private` 蓋回去。`_deploy_*.php` 留在原位，不會被刪。apply 不會另外做一份新備份，避免把寫到一半的資料庫又存成最新備份。

先前那次部署已經把當時的 `public_html/garden` 與 `garden_private` 複製到：

```text
/home/irihvsuu/garden_backup_YYYYmmddHHMMSS/
```

裡面有 `garden/` 與 `garden_private/`。時間戳是亞洲台北時間。這個目錄不在 `public_html`。要回到該次部署前，把這兩個目錄蓋回原位。轉換程式另外會在正式庫旁邊留 `garden.sqlite.bak-YYYYmmddHHMMSS`（UTC）。目錄備份裡的 sqlite 是轉換前的整份。
