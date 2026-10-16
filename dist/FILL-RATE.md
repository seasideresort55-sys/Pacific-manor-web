# 玫瑰品種欄位填充率

統計對象：`garden/data/rose-library.json`，1626 個品種。有值指不是空白、也不是「待補」。百分比以 1626 為分母。查不到的欄位維持「待補」，沒有估計。

抓取時間：2026-10-16T07:04:42Z。

| 欄位 | 有值 | 比例 |
| --- | ---: | ---: |
| name_zh 中文名 | 1626 | 100.0% |
| name_orig 原名 | 1623 | 99.8% |
| aliases 別名 | 219 | 13.5% |
| reg_name 登錄名 | 36 | 2.2% |
| class_main 株型 | 1121 | 68.9% |
| class_intl 國際分類 | 0 | 0.0% |
| ogr_class 古典亞類 | 31 | 1.9% |
| series 系列 | 0 | 0.0% |
| breeder 育種者 | 377 | 23.2% |
| breeder_country 育種國家 | 375 | 23.1% |
| year 年份 | 164 | 10.1% |
| color_group 花色 | 891 | 54.8% |
| color_desc 花色描述 | 173 | 10.6% |
| fragrance 香味 | 577 | 35.5% |
| scent_type 香型 | 10 | 0.6% |
| flower_dia_cm 花徑公分 | 54 | 3.3% |
| flower_size 輪徑用詞 | 853 | 52.5% |
| petals 瓣數 | 42 | 2.6% |
| doubleness 重瓣 | 162 | 10.0% |
| form 花型 | 768 | 47.2% |
| inflorescence 花序 | 192 | 11.8% |
| bloom_habit 花期 | 886 | 54.5% |
| bloom_months 開花月份 | 0 | 0.0% |
| height_cm 株高 | 93 | 5.7% |
| width_cm 株寬 | 36 | 2.2% |
| growth_habit 生長習性 | 0 | 0.0% |
| disease_bs 黑斑 1–5 | 0 | 0.0% |
| disease_pm 白粉 1–5 | 0 | 0.0% |
| heat_tw 台灣耐熱 1–5 | 0 | 0.0% |
| heat_flag 賣家標耐熱 | 395 | 24.3% |
| shade 耐陰 | 0 | 0.0% |
| usda_zone | 0 | 0.0% |
| uses 用途 | 0 | 0.0% |
| thorns 刺 | 0 | 0.0% |
| awards 獎項 | 0 | 0.0% |
| pvr | 0 | 0.0% |
| seedling_types 苗型 | 1542 | 94.8% |
| description 自寫說明 | 1626 | 100.0% |

株型有值的分布：OGR 386、FL 289、SHR 176、CL 105、HT 88、MIN 66、RS 6、GC 5，其餘 505 筆為待補。這次沒有品種被標成 RA 或 SP。樹玫瑰不是分類，資料裡沒有 STD。

## 為什麼有些欄位是 0

- `bloom_months`：台灣賣家寫的是四季開花、反覆開、一季開，沒有寫月份。花期只記在 `bloom_habit`。
- `heat_tw`、`disease_bs`、`disease_pm`：沒有人公布 1–5 分數。賣家說「耐熱」只記 `heat_flag=1`（395 筆），不換成分數。`heat_tw` 維持待補。賣家說「耐病」也不換成 1–5。
- `flower_dia_cm` 只收頁面上的公分數。`flower_size` 有公分時由公分換算；沒有公分時，只有賣家用詞正好是小輪、中輪、大輪、巨大輪才採用。3.5 吋盆的制式尺寸沒有當成花徑或株高。
- `class_intl`、`series`、`growth_habit`、`shade`、`usda_zone`、`uses`、`thorns`、`awards`、`pvr` 這次沒有可核對的出處，全部待補。
- `ogr_class` 只在亞類對得上時填入（31 筆）。其餘古典玫瑰只標 OGR。

## 出處（有值的欄位筆數）

| 出處 | 欄位筆數 |
| --- | ---: |
| 冬冬商品頁 | 4686 |
| 冬冬在售苗型／既有表 | 1542 |
| 既有品種表 | 781 |
| 冬冬或既有表標示耐熱 | 395 |
| David Austin 商品頁 | 215 |
| 尚實商品簡要說明（只取尺寸數字） | 185 |
| 由花徑換算 | 27 |

台灣花期與耐熱以台灣賣家為準。年份、育種者、瓣數若仍是待補，才用 David Austin 補，不覆蓋已有的台灣花期或株型。Austin 名單 76 個，頁面打中 36 個，補上 215 個原本待補的欄位。

這次沒有抓：J&K、玫瑰天地、四季有花、HelpMeFind、Kordes、Meilland、Tantau、河本。所以上表的 0 不是「確認沒有」，是這次沒有出處。

說明句是依已核對的事實重寫的短句，不是賣家原文。前台商品文字已清掉冬冬、dongdongs、尚實、shop2000、玫瑰天地、四季有花與網址。

照片：315 個在售 SKU 裡 314 張已複製到 `public/media/roses/`。缺照片的是「薄紗」`ROSE-1-1357`。284 個品種至少有一張在售照片。
