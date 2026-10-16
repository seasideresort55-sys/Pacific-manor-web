# 回復

`php migrate/v14.php` 在改資料前會複製：

```text
data/garden.sqlite.bak-YYYYmmddHHMMSS
```

回復就是把該檔蓋回 `data/garden.sqlite`，然後重新啟動 PHP。遷移本身不刪列，所以這份備份是回復手段。

```bash
cp data/garden.sqlite.bak-YYYYmmddHHMMSS data/garden.sqlite
```

把 `YYYYmmddHHMMSS` 換成該次遷移印出的檔名。

這次包裝前為確認可以重跑，又執行了一次遷移，確認 `consigned 0`、`preorder_kept 47` 之後，已刪掉該次產生的 `.bak`，避免把備份打進 zip。要再留一份備份，部署前自己跑一次遷移即可，它會先複製再判斷沒有新資料要寫。

若還沒跑遷移，不需要回復。這包沒有部署到遠端，沒有遠端狀態要撤。
