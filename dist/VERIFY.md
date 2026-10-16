# 驗證

登入只寫在這份文件。畫面上沒有帳號或密碼。

| 角色 | 帳號 | 密碼 |
| --- | --- | --- |
| 平台管理員 | admin | garden-admin-55 |
| 太平洋莊園 | taiping | garden-taiping |
| 山風花圃 | shanfeng | garden-shanfeng |
| 河岸苗園 | hean | garden-hean |

舊版 `garden.sqlite` 不在這個儲存庫裡，也沒有在雲端磁碟找到 v13 程式。47 筆「新苗預購」與另外兩家賣家的 5 件商品，是依當時已知筆數重建的起點，用來證明遷移不會改到它們。名稱不是從遺失的 v13 資料庫抄來的。

## 檢查遷移結果

在含 `tests/verify.php` 的原始目錄（zip 不含 tests）：

```bash
php garden/tests/verify.php
```

預期最後一行是 `ALL PASSED`，並且包含：

```text
OK  47 新苗預購  count=47
OK  vendor 2 products stay
OK  vendor 3 products stay
OK  太平洋莊園運費 200 / 2000
OK  山風花圃運費未代填
OK  河岸苗園維持自己的運費
OK  代賣上架數量接近在售數  count=315
OK  玫瑰售價取整到 10
OK  前台商品文字沒有供應商名稱  hits=0
OK  商品照片與規格沒有供應商網址  hits=0
OK  仍有未開放的品種  unlisted=1333
OK  樹玫瑰不是分類
OK  免運只算未排除商品，運費 200
OK  未開放不能上架
OK  沒庫存不能上架
OK  介面文案沒有測試或展示
OK  代賣庫存預設 5  value=5
OK  代賣不再以 1 棵為未公布棵數的占位
OK  代賣可售棵數用平台設定  count=315
OK  新苗預購庫存維持 20  count=47
OK  保留冬冬可買、棵數未公布的註記
ALL PASSED
```

2026-10-16 再跑一次 `php garden/migrate/v14.php` 的輸出：

```text
varieties_inserted 0
varieties_updated 0
offers_inserted 0
consigned 0
preorder_kept 47
```

語法：

```bash
php -l garden/src/bootstrap.php
php -l garden/public/index.php
php -l garden/migrate/v14.php
```

預期三行都是 `No syntax errors detected`。

## zip 不該含的路徑

```bash
unzip -l dist/garden-v14.zip | grep -E 'tests/|bin/setup.php|sqlite.bak'
```

預期沒有輸出，結束碼 1。

```bash
cd dist && sha256sum -c SHA256SUMS
```

預期 `garden-v14.zip: OK`。

## 在售數量

2026-10-16 重查冬冬目錄，側欄可加入購物車的玫瑰是 315，不是約 310。加總：自根新苗 157、低接 81、起源 19、新星 2、進化 18、嫁接苗 33、愛禮 5。愛禮 5 株是玫瑰，所以算進去。

冬冬頁面沒有公布棵數。代賣可售棵數用平台設定，預設 5，管理員可改全站預設，也可單品種或整批改。註記是「冬冬目錄目前可購買；頁面未公布棵數，可售棵數依平台庫存設定」。選苗用的平台庫存仍在第一次代賣時把占位的 1 扣成 0。售價是成本 ÷ 0.7 後取整到 10。例：伊織成本 180、售價 260；伊織（低接）成本 350、售價 500。

代賣之後平台在售庫存為 0，選苗頁會沒有可上架的苗。因此另加 8 筆平台自有庫存（每筆 3 棵），註記「平台自有庫存，不是供應商公布的棵數」：季節女神、達芙妮、新綠、守護家園、栗子、藍色天空、水果、康斯坦茨。這 8 筆不是冬冬棵數。

## 購物車應看到的運費

太平洋莊園：運費 NT$200，計入免運的商品滿 NT$2,000 才免運。

截圖那車是：

- 伊織 NT$260、伊織（低接）NT$500，計入免運，合計 NT$760
- 搖籃曲（低接）NT$710，標「不計入免運」，不計入門檻
- 畫面上寫再買 NT$1,240（2000 − 760）即免運，並說明被標成不計入免運的商品不算進去
- 運費 NT$200，本店應付 NT$1,670

結帳頁對同一車會在品項旁標「不計入免運」，並寫再買 NT$1,240（只算計入免運的商品）。

山風花圃運費是空的。該店商品進購物車時應寫「這家店還沒有設定運費」，運費不當成 0。
