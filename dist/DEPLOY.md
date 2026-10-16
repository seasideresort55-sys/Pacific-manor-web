# 部署說明

這包是給檢視用的。沒有部署到任何主機。

## 在本機看

需要 PHP 8.3，並啟用 pdo_sqlite、mbstring。

```bash
cd garden
php -S 127.0.0.1:8080 -t public public/router.php
```

打開 http://127.0.0.1:8080 。資料庫是同目錄的 `data/garden.sqlite`，已經含 315 筆代賣玫瑰、47 筆新苗預購，以及太平洋莊園的運費。不要對這份檔再跑會清空資料的指令。

`bin/setup.php` 不在 zip 裡。它只用來在沒有舊版 sqlite 時做出檢視用的起點，然後呼叫遷移。

## 對已有的 garden.sqlite 做遷移

把 `migrate/v14.php` 與 `data/rose-library.json`、`data/dd-list.json` 放到同一棵 garden 目錄，在該目錄執行：

```bash
php migrate/v14.php
```

遷移會先把 `data/garden.sqlite` 複製成 `data/garden.sqlite.bak-YYYYmmddHHMMSS`，再只做新增與填補：

- 新品種才 INSERT。已有品種只更新仍是「待補」或空白的欄位。
- 供應商 SKU 已存在就不重插 offer，也不再扣平台庫存、不再建第二筆代賣商品。
- 不改 `新苗預購` 商品，不改其他賣家的商品。
- 只把賣家 id 1（太平洋莊園）的運費設成 200、免運門檻設成 2000。其他賣家的運費維持原值；原本是空的就繼續空著。
- 不寫入任何「不計入免運」。那是賣家自己在後台勾的，平台沒有預設。
- 遷移裡沒有 DELETE。

第二次跑同一份庫的預期輸出：

```text
varieties_inserted 0
varieties_updated 0
offers_inserted 0
consigned 0
preorder_kept 47
```

這次實際再跑一次，輸出與上面相同。

## 賣家自己的運費與不計入免運

平台不代填任何賣家的運費或免運門檻。檢視庫裡：

| 賣家 | 運費 | 免運門檻 |
| --- | --- | --- |
| 太平洋莊園 | 200 | 2000 |
| 山風花圃 | 空 | 空 |
| 河岸苗園 | 180（原本就有） | 1500（原本就有） |

賣家可在「運費」頁勾分類，或在商品頁勾「這項不計入免運」。購物車與結帳只把沒被勾的金額拿去比門檻，並標出不計入的品項與還差多少。運費沒填時不會當成 0。

檢視庫裡有一筆太平洋莊園的商品排除（搖籃曲（低接），商品 id 245），是 `bin/setup.php` 在遷移之後為了購物車截圖加的，不是遷移寫入的平台預設。正式跑 `php migrate/v14.php` 不會產生這筆。
