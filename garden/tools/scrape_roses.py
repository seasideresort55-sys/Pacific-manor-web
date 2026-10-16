#!/usr/bin/env python3
"""Scrape rose facts for the garden library.

Order: 冬冬 detail pages (in-stock menus first) → 尚實 shop2000 blurbs.
Unknown stays 待補. Numbers are copied only when the page states them.
"""
from __future__ import annotations

import csv
import json
import re
import time
import urllib.request
from concurrent.futures import ThreadPoolExecutor, as_completed
from html import unescape
from pathlib import Path

ROOT = Path("/workspace/garden")
CSV_PATH = Path("/tmp/rose-inputs/v13/data/rose-varieties.csv")
DD_LIST = Path("/tmp/rose-inputs/roses/dd_list.json")
OUT = ROOT / "data" / "rose-library.json"
MEDIA = ROOT / "public" / "media" / "roses"
UA = {"User-Agent": "Mozilla/5.0 (compatible; GardenRoseLibrary/1.0)"}

MENUS = [
    ("31", "玫瑰新苗", "自根"),
    ("36", "玫瑰嫁接新苗", "低接"),
    ("32", "羅莎歐麗起源", "自根"),
    ("48", "羅莎歐麗新星", "自根"),
    ("34", "羅莎歐麗進化", "自根"),
    ("33", "羅莎歐麗嫁接苗", "低接"),
    ("43", "愛禮花卉", "自根"),
    ("136", "砧木用", "砧木"),
]

BREEDERS = {
    "英國奧斯汀": ("David Austin", "英國"),
    "法國吉優": ("Guillot", "法國"),
    "德國坦圖": ("Tantau", "德國"),
    "德國寇德士": ("Kordes", "德國"),
    "法國梅安": ("Meilland", "法國"),
    "河本系列": ("河本", "日本"),
    "今井系列": ("今井", "日本"),
    "戴爾巴德": ("Delbard", "法國"),
    "國枝系列": ("國枝", "日本"),
    "羅莎歐麗": ("羅莎歐麗", "日本"),
}

PENDING = "待補"


def fetch(url: str, timeout: int = 30) -> str:
    req = urllib.request.Request(url, headers=UA)
    with urllib.request.urlopen(req, timeout=timeout) as resp:
        raw = resp.read()
    return raw.decode("utf-8", "replace")


def fetch_bytes(url: str) -> bytes:
    req = urllib.request.Request(url, headers=UA)
    with urllib.request.urlopen(req, timeout=30) as resp:
        return resp.read()


def lines_of(html: str) -> list[str]:
    html = re.sub(r"<script[\s\S]*?</script>", " ", html, flags=re.I)
    html = re.sub(r"<style[\s\S]*?</style>", " ", html, flags=re.I)
    html = re.sub(r"<br\s*/?>", "\n", html, flags=re.I)
    html = re.sub(r"</(p|div|tr|li|h\d|td)>", "\n", html, flags=re.I)
    text = re.sub(r"<[^>]+>", " ", html)
    text = unescape(text)
    text = text.replace("\xa0", " ")
    out = []
    for ln in text.splitlines():
        ln = re.sub(r"[ \t]+", " ", ln).strip()
        if ln:
            out.append(ln)
    return out


def crawl_menu(firstid: str) -> list[dict]:
    """In-stock SKUs only. 冬冬 lists 培育中 on later pages; the sidebar count is the cart-ready set."""
    found: dict[str, dict] = {}
    empty_pages = 0
    for page in range(0, 60):
        url = f"https://www.dongdongs.com/storemenu.php?firstid={firstid}&pageNum_flowersmenu={page}"
        try:
            html = fetch(url)
        except Exception as exc:
            print(f"menu {firstid} p{page} fail {exc}", flush=True)
            break
        main = html.split("你可能有興趣")[0]
        blocks = main.split('class="storeMenu_Item"')[1:]
        if not blocks:
            empty_pages += 1
            if empty_pages >= 1:
                break
            continue
        added = 0
        for block in blocks:
            if "培育中" in block or "nocheck" in block:
                continue
            if "cart_sessiongo" not in block and "加入購物車" not in block:
                continue
            mid = re.search(r"flowersid=(\d+)", block)
            if not mid:
                continue
            fid = mid.group(1)
            if fid in found:
                continue
            name_m = re.search(r'class="storeMenu_name">\s*([^<]+)', block)
            price_m = re.search(r"NT\$\s*(\d+)", block)
            img_m = re.search(r"url\(([^)]+)\)", block)
            name = re.sub(r"\s+", " ", name_m.group(1)).strip() if name_m else ""
            if not name:
                continue
            found[fid] = {
                "id": fid,
                "name": name,
                "price": int(price_m.group(1)) if price_m else None,
                "image": img_m.group(1).split("?")[0] if img_m else "",
            }
            added += 1
        print(f"menu {firstid} page {page} +{added} total {len(found)}", flush=True)
        if added == 0 and page > 0:
            # later pages are mostly 培育中; keep going a little in case in-stock is mixed
            empty_pages += 1
            if empty_pages >= 3:
                break
        else:
            empty_pages = 0
    return list(found.values())


def section_after(lines: list[str], start_label: str, stops: list[str]) -> list[str]:
    try:
        i = next(n for n, ln in enumerate(lines) if ln.startswith(start_label) or ln == start_label)
    except StopIteration:
        return []
    got = []
    for ln in lines[i + 1 :]:
        if any(ln.startswith(s) or ln == s for s in stops):
            break
        got.append(ln)
    return got


def parse_detail(html: str) -> dict:
    lines = lines_of(html)
    stops = ["植株型態：", "品種特性：", "花型與開花性：", "育種公司：", "商品簡介", "有相似處的品種", "提及此商品"]
    habit = section_after(lines, "植株型態：", stops)
    traits = section_after(lines, "品種特性：", stops)
    bloom = section_after(lines, "花型與開花性：", stops)
    breeder = section_after(lines, "育種公司：", stops)
    intro = section_after(lines, "商品簡介", ["有相似處的品種", "提及此商品", "我願稱", "去年還是"])
    # keep intro factual; drop review-like lines that start after the product blurb
    intro_clean = []
    for ln in intro:
        if ln.startswith("有相似") or "評價" in ln:
            break
        if len(ln) > 40 and any(w in ln for w in ("我", "去年", "今年", "陽台養")):
            break
        intro_clean.append(ln)

    def value_after(label: str) -> str:
        for i, ln in enumerate(lines):
            if ln == label and i + 1 < len(lines):
                return lines[i + 1]
        return ""

    price = None
    category = ""
    for i, ln in enumerate(lines):
        if ln == "售價":
            window = " ".join(lines[i : i + 4])
            m = re.search(r"NT\$\s*(\d+)", window)
            if m:
                price = int(m.group(1))
        if ln == "分類" and i + 1 < len(lines):
            category = lines[i + 1]
    return {
        "name": value_after("名稱"),
        "price": price,
        "orig": value_after("外文名"),
        "alias": value_after("別名"),
        "habit": [x for x in habit if x not in ("品種性狀",)],
        "traits": traits,
        "bloom": bloom,
        "breeder": breeder[:3],
        "intro": intro_clean[:8],
        "category": category,
    }


def norm_name(name: str) -> str:
    name = name.strip()
    name = re.sub(r"[（(](?:大接|接)[)）]", "", name)
    name = re.sub(r"\s+", "", name)
    return name


def map_class(bits: list[str]) -> tuple[str, str]:
    text = " ".join(bits)
    ogr = PENDING
    if any(k in text for k in ("砧木",)):
        return "RS", ogr
    if "原種" in text and "古典" not in text and "老玫瑰" not in text:
        return "SP", ogr
    if any(k in text for k in ("茶玫瑰", "老玫瑰", "古典")):
        for key, label in (
            ("Tea", "Tea"),
            ("茶香玫瑰", "Tea"),
            ("China", "China"),
            ("中國月季", "China"),
            ("Bourbon", "Bourbon"),
            ("波旁", "Bourbon"),
            ("Noisette", "Noisette"),
            ("諾伊塞特", "Noisette"),
            ("Damask", "Damask"),
            ("大馬士革", "Damask"),
            ("Gallica", "Gallica"),
            ("Moss", "Moss"),
            ("苔薔薇", "Moss"),
            ("Portland", "Portland"),
            ("Hybrid Perpetual", "Hybrid Perpetual"),
            ("雜交長春", "Hybrid Perpetual"),
            ("Centifolia", "Centifolia"),
            ("Alba", "Alba"),
        ):
            if key in text:
                ogr = label
                break
        return "OGR", ogr
    if "地被" in text or "Ground" in text:
        return "GC", ogr
    if "迷你" in text or "Mini" in text or "Miniflora" in text:
        return "MIN", ogr
    if any(k in text for k in ("蔓性藤本", "Rambler", "Hybrid Wichurana", "長藤")):
        return "RA", ogr
    if any(k in text for k in ("攀緣", "小型藤本", "(CL)", "蔓性(CL)", "Climber")):
        return "CL", ogr
    if "蔓性玫瑰" in text:
        return "CL", ogr
    if "灌木" in text or "(S)" in text or "Shrub" in text:
        return "SHR", ogr
    if "豐花" in text or "(FL)" in text or "Floribunda" in text:
        return "FL", ogr
    if any(k in text for k in ("雜交茶", "(HT)", "切花", "大輪矮叢", "Grandiflora", "(GR)", "大花叢")):
        return "HT", ogr
    return PENDING, ogr


COLOR_MAP = {
    "白色": "白",
    "乳白": "乳白",
    "粉色": "粉",
    "紅色": "紅",
    "深紅": "深紅",
    "橘色": "橘",
    "杏色": "杏",
    "桃色": "杏",
    "黃色": "黃",
    "紫色": "紫",
    "綠色": "綠",
    "複色": "複色",
    "條紋": "條紋",
    "白": "白",
    "粉": "粉",
    "紅": "紅",
    "橘": "橘",
    "杏": "杏",
    "黃": "黃",
    "紫": "紫",
    "綠": "綠",
}

FORM_MAP = {
    "高芯劍瓣": "劍瓣高心",
    "高芯半劍瓣": "半劍瓣",
    "高芯型": "劍瓣高心",
    "半劍瓣": "半劍瓣",
    "杯型": "杯狀",
    "杯狀": "杯狀",
    "淺杯": "淺杯",
    "四分簇開型": "四分",
    "四分": "四分",
    "蓮座": "蓮座",
    "彩球型": "絨球",
    "絨球": "絨球",
    "單瓣平開型": "平開",
    "平開": "平開",
    "單瓣": "單瓣",
}

BLOOM_MAP = {
    "四季開花": "四季開花",
    "季節反覆開": "反覆開",
    "反覆開": "反覆開",
    "一季開": "一季開",
}


def pick_color(traits: list[str]) -> tuple[str, str]:
    found = []
    desc = []
    for t in traits:
        if t in COLOR_MAP:
            found.append(COLOR_MAP[t])
        elif t in ("茶色", "褐色", "藍色", "薰衣草"):
            desc.append(t)
    found = list(dict.fromkeys(found))
    if len(found) > 1:
        return "複色", "、".join(found + desc) if desc else "、".join(found)
    if len(found) == 1:
        return found[0], "、".join(desc) if desc else PENDING
    if desc:
        return PENDING, "、".join(desc)
    return PENDING, PENDING


def pick_fragrance(traits: list[str], intro: str) -> str:
    blob = " ".join(traits) + " " + intro
    for word in ("強香", "中香", "淡香", "無香", "不香"):
        if word in blob:
            return "無" if word in ("無香", "不香") else word
    return PENDING


def pick_form(bloom: list[str]) -> str:
    for b in bloom:
        if b in FORM_MAP:
            return FORM_MAP[b]
    return PENDING


def pick_bloom(bloom: list[str], traits: list[str]) -> str:
    for b in list(bloom) + list(traits):
        if b in BLOOM_MAP:
            return BLOOM_MAP[b]
    return PENDING


def pick_size_word(traits: list[str]) -> str:
    for t in traits:
        if t in ("小輪", "中輪", "大輪", "巨大輪"):
            return t
    return PENDING


def parse_measures(intro: str) -> dict:
    """Only explicit numbers in the seller blurb. Ignore pot dimensions."""
    text = intro
    text = re.sub(r"盆口直徑約?\s*\d+\s*公分", " ", text)
    text = re.sub(r"深\s*\d+\s*公分", " ", text)
    text = re.sub(r"植株高度含盆約?\s*\d+\s*[~～\-到至]\s*\d+\s*公分", " ", text)
    out = {"flower_dia_cm": PENDING, "height_cm": PENDING, "width_cm": PENDING, "petals": PENDING, "year": PENDING}

    def rng(pat: str) -> str | None:
        m = re.search(pat, text)
        if not m:
            return None
        a = m.group(1)
        b = m.group(2) if m.lastindex and m.lastindex >= 2 and m.group(2) else None
        if b and b != a:
            return f"{a}–{b}"
        return a

    dia = rng(r"(?:花徑|花朵直徑|花直径|花徑約)\s*(\d+(?:\.\d+)?)\s*(?:[~～\-到至]\s*(\d+(?:\.\d+)?)\s*)?(?:公分|cm|CM)")
    if dia:
        out["flower_dia_cm"] = dia
    height = rng(r"株高(?:約|大約)?\s*(\d+(?:\.\d+)?)\s*(?:[~～\-到至]\s*(\d+(?:\.\d+)?)\s*)?(?:公分|cm|CM)")
    if height:
        out["height_cm"] = height
    width = rng(r"(?:株寬|冠幅|橫張(?:約)?)\s*(\d+(?:\.\d+)?)\s*(?:[~～\-到至]\s*(\d+(?:\.\d+)?)\s*)?(?:公分|cm|CM)")
    if width:
        out["width_cm"] = width
    petals = rng(r"花瓣(?:數)?(?:約)?\s*(\d+)\s*(?:[~～\-到至]\s*(\d+)\s*)?(?:片|瓣)?")
    if petals:
        out["petals"] = petals
    years = re.findall(r"((?:19|20)\d{2})\s*年", text)
    if len(years) == 1:
        out["year"] = years[0]
    return out


def flower_size_from_cm(dia: str) -> str:
    if dia == PENDING:
        return PENDING
    nums = [float(x) for x in re.findall(r"\d+(?:\.\d+)?", dia)]
    if not nums:
        return PENDING
    n = max(nums)
    if n < 5:
        return "小輪"
    if n <= 8:
        return "中輪"
    if n <= 12:
        return "大輪"
    return "巨大輪"


def doubleness_from(form: str, petals: str, bloom: list[str]) -> str:
    if petals != PENDING:
        nums = [int(x) for x in re.findall(r"\d+", petals)]
        if nums:
            n = max(nums)
            if n <= 8:
                return "單瓣"
            if n <= 16:
                return "半重瓣"
            if n <= 40:
                return "重瓣"
            return "極重瓣"
    blob = " ".join(bloom)
    if "單瓣" in form or "單瓣" in blob:
        return "單瓣"
    return PENDING


def breeder_of(raw: list[str], intro: str, series_hint: str) -> tuple[str, str, str]:
    blob = " ".join(raw)
    for key, (name, country) in BREEDERS.items():
        if key in blob or key in series_hint:
            return name, country, PENDING
    if "其他育種公司" in blob or not blob.strip():
        m = re.search(r"((?:19|20)\d{2})年\s*(日本|英國|法國|德國|美國)?\s*([A-Za-z][A-Za-z .'-]{2,40}|河本|今井|國枝|羅莎歐麗)", intro)
        if m:
            country = m.group(2) or PENDING
            who = m.group(3).strip(" .")
            return who, country, PENDING
        if "羅莎歐麗" in series_hint or "羅莎歐麗" in intro:
            return "羅莎歐麗", "日本", PENDING
    return PENDING, PENDING, PENDING


def original_desc(name: str, fields: dict) -> str:
    bits = []
    color = fields["color_group"]["v"]
    size = fields["flower_size"]["v"]
    form = fields["form"]["v"]
    klass = fields["class_main"]["v"]
    labels = {
        "HT": "大輪直立玫瑰",
        "FL": "豐花玫瑰",
        "MIN": "迷你玫瑰",
        "SHR": "灌木玫瑰",
        "GC": "地被玫瑰",
        "CL": "小型藤本玫瑰",
        "RA": "蔓性藤本玫瑰",
        "OGR": "古典玫瑰",
        "SP": "原種玫瑰",
        "RS": "砧木",
    }
    head = []
    if color != PENDING:
        head.append(color + "色")
    if size != PENDING:
        head.append(size)
    if form != PENDING:
        head.append(form)
    kind = labels.get(klass, "玫瑰")
    if head:
        bits.append("".join(head) + "的" + kind)
    else:
        bits.append(kind)
    habit = fields["bloom_habit"]["v"]
    if habit != PENDING:
        bits.append(habit)
    h = fields["height_cm"]["v"]
    if h != PENDING:
        bits.append(f"株高 {h} 公分")
    w = fields["width_cm"]["v"]
    if w != PENDING:
        bits.append(f"株寬 {w} 公分")
    dia = fields["flower_dia_cm"]["v"]
    if dia != PENDING:
        bits.append(f"花徑 {dia} 公分")
    br = fields["breeder"]["v"]
    year = fields["year"]["v"]
    country = fields["breeder_country"]["v"]
    if br != PENDING:
        when = (year + " 年") if year != PENDING else ""
        where = (country + " ") if country != PENDING else ""
        bits.append(f"{when}由{where}{br} 育成".strip())
    text = "，".join(bits) + "。"
    text = re.sub(r"冬冬|dongdongs|尚實|玫瑰天地|四季有花", "", text, flags=re.I)
    return text


def fact(v: str, src: str, url: str) -> dict:
    if not v or v == PENDING:
        return {"v": PENDING, "src": "", "url": ""}
    return {"v": v, "src": src, "url": url}


def load_csv() -> list[dict]:
    with CSV_PATH.open(encoding="utf-8-sig", newline="") as f:
        return list(csv.DictReader(f))


def main() -> None:
    MEDIA.mkdir(parents=True, exist_ok=True)
    instock: dict[str, dict] = {}
    for firstid, label, seedling in MENUS:
        rows = crawl_menu(firstid)
        for row in rows:
            row["menu"] = label
            row["seedling_hint"] = seedling
            row["firstid"] = firstid
            instock[row["id"]] = row
        print(f"INSTOCK running {len(instock)}", flush=True)

    csv_rows = load_csv()
    dd_ids = set(instock)
    for row in csv_rows:
        blob = row.get("來源網址", "") + " " + row.get("reference_page_url", "")
        dd_ids.update(re.findall(r"flowersid=(\d+)", blob))
    try:
        listing = json.loads(DD_LIST.read_text())
        for _name, rec in listing.items():
            if isinstance(rec, list) and rec:
                dd_ids.add(str(rec[0]))
    except Exception as exc:
        print("dd_list", exc)

    print(f"detail ids {len(dd_ids)} instock {len(instock)}", flush=True)

    def one(fid: str) -> tuple[str, dict | None]:
        url = f"https://www.dongdongs.com/store.php?flowersid={fid}"
        try:
            html = fetch(url)
        except Exception as exc:
            return fid, {"error": str(exc), "url": url}
        parsed = parse_detail(html)
        parsed["url"] = url
        parsed["id"] = fid
        return fid, parsed

    details: dict[str, dict] = {}
    ids = sorted(dd_ids, key=lambda x: (x not in instock, int(x) if x.isdigit() else 0))
    with ThreadPoolExecutor(max_workers=8) as pool:
        futs = [pool.submit(one, fid) for fid in ids]
        done = 0
        for fut in as_completed(futs):
            fid, parsed = fut.result()
            details[fid] = parsed
            done += 1
            if done % 50 == 0:
                print(f"details {done}/{len(ids)}", flush=True)
    print(f"details done {len(details)}", flush=True)

    # group by normalized Chinese name
    varieties: dict[str, dict] = {}

    def ensure(name: str) -> dict:
        key = norm_name(name) or name
        if key not in varieties:
            varieties[key] = {
                "name_zh": name,
                "name_orig": PENDING,
                "aliases": PENDING,
                "offers": [],
                "raw_habit": [],
                "raw_traits": [],
                "raw_bloom": [],
                "raw_breeder": [],
                "intro": "",
                "url": "",
                "csv": None,
            }
        return varieties[key]

    csv_by_name = {}
    for row in csv_rows:
        csv_by_name.setdefault(norm_name(row["台灣中文名"]), row)

    for fid, offer in instock.items():
        det = details.get(fid) or {}
        name = (det.get("name") or offer["name"] or "").strip()
        name = re.sub(r"\s+", " ", name)
        base = re.sub(r"[（(](?:大接|接)[)）]", "", name).strip()
        var = ensure(base or name)
        if det.get("orig") and var["name_orig"] == PENDING:
            var["name_orig"] = det["orig"]
        if det.get("alias") and var["aliases"] == PENDING:
            var["aliases"] = det["alias"]
        habit = list(det.get("habit") or [])
        if det.get("category"):
            habit.append(det["category"])
        var["raw_habit"] = habit or var["raw_habit"]
        var["raw_traits"] = det.get("traits") or var["raw_traits"]
        var["raw_bloom"] = det.get("bloom") or var["raw_bloom"]
        var["raw_breeder"] = det.get("breeder") or var["raw_breeder"]
        if det.get("intro"):
            var["intro"] = " ".join(det["intro"])
            var["url"] = det.get("url") or ""
        seedling = offer["seedling_hint"]
        if re.search(r"[（(]大接[)）]", name):
            seedling = "低接"
        elif re.search(r"[（(]接[)）]", name):
            seedling = "低接"
        cost = det.get("price") or offer.get("price")
        var["offers"].append(
            {
                "supplier_sku": fid,
                "list_name": name,
                "seedling": seedling,
                "cost": cost,
                "stock": 1,
                "stock_note": "冬冬目錄目前可購買；頁面未公布棵數，平台庫存記 1",
                "image": offer.get("image") or "",
                "menu": offer.get("menu") or "",
                "instock": True,
            }
        )

    # varieties only in CSV / old details
    for fid, det in details.items():
        if fid in instock or not det or det.get("error"):
            continue
        name = (det.get("name") or "").strip()
        if not name:
            continue
        base = re.sub(r"[（(](?:大接|接)[)）]", "", name).strip()
        var = ensure(base or name)
        if det.get("orig") and var["name_orig"] == PENDING:
            var["name_orig"] = det["orig"]
        if det.get("alias") and var["aliases"] == PENDING:
            var["aliases"] = det["alias"]
        if det.get("habit") or det.get("category"):
            habit = list(det.get("habit") or [])
            if det.get("category"):
                habit.append(det["category"])
            var["raw_habit"] = habit or var["raw_habit"]
        if det.get("traits"):
            var["raw_traits"] = det["traits"]
        if det.get("bloom"):
            var["raw_bloom"] = det["bloom"]
        if det.get("breeder"):
            var["raw_breeder"] = det["breeder"]
        if det.get("intro") and not var["intro"]:
            var["intro"] = " ".join(det["intro"])
            var["url"] = det.get("url") or ""

    for key, row in csv_by_name.items():
        var = varieties.get(key)
        if not var:
            var = ensure(row["台灣中文名"])
        var["csv"] = {
            "name_zh": row["台灣中文名"],
            "name_orig": row["原文名"],
            "aliases": row["別名"],
            "brand": row["品牌系列"],
            "color": row["花色"],
            "fragrance": row["香味"],
            "sub": row["子分類"],
            "seedling": row["苗型"],
            "traits": row["特性"],
            "urls": row["來源網址"],
        }
        if var["name_orig"] in (PENDING, "", None) and row["原文名"]:
            var["name_orig"] = row["原文名"]
        if var["aliases"] in (PENDING, "", None) and row["別名"]:
            var["aliases"] = row["別名"]

    # shop2000 blurbs for varieties still thin on measures
    shop_targets = []
    for key, var in varieties.items():
        intro = var.get("intro") or ""
        if "株高" in intro or "花徑" in intro:
            continue
        row = var.get("csv") or {}
        urls = re.findall(r"https?://www\.shop2000\.com\.tw[^\s|]+", row.get("urls") or "")
        if urls:
            shop_targets.append((key, urls[0]))
    print(f"shop2000 targets {len(shop_targets)}", flush=True)

    def shop_one(item):
        key, url = item
        try:
            html = fetch(url)
        except Exception as exc:
            return key, ""
        text = " ".join(lines_of(html))
        m = re.search(r"簡要說明\s*：\s*(.+?)\s*(?:消費提醒|會員方可|貨運方式|分類位置)", text)
        blurb = m.group(1).strip() if m else ""
        blurb = re.sub(r"https?://\S+", "", blurb)
        return key, blurb[:400]

    shop_blurbs = {}
    with ThreadPoolExecutor(max_workers=6) as pool:
        futs = [pool.submit(shop_one, it) for it in shop_targets[:900]]
        n = 0
        for fut in as_completed(futs):
            key, blurb = fut.result()
            if blurb:
                shop_blurbs[key] = blurb
            n += 1
            if n % 100 == 0:
                print(f"shop {n}", flush=True)
    print(f"shop blurbs {len(shop_blurbs)}", flush=True)

    # download instock images
    def dl(offer_image: str, fid: str) -> str:
        if not offer_image:
            return ""
        url = offer_image
        if url.startswith("img/"):
            url = "https://www.dongdongs.com/" + url
        elif url.startswith("/"):
            url = "https://www.dongdongs.com" + url
        ext = ".jpg"
        m = re.search(r"\.(jpg|jpeg|png|JPG|PNG)", url)
        if m:
            ext = "." + m.group(1).lower().replace("jpeg", "jpg")
        dest = MEDIA / f"{fid}{ext}"
        if dest.exists() and dest.stat().st_size > 500:
            return f"media/roses/{dest.name}"
        try:
            data = fetch_bytes(url)
        except Exception:
            return ""
        if len(data) < 400:
            return ""
        dest.write_bytes(data)
        return f"media/roses/{dest.name}"

    image_jobs = []
    for var in varieties.values():
        for offer in var["offers"]:
            if offer.get("instock"):
                image_jobs.append(offer)
    print(f"images {len(image_jobs)}", flush=True)
    with ThreadPoolExecutor(max_workers=8) as pool:
        futs = {pool.submit(dl, o.get("image") or "", o["supplier_sku"]): o for o in image_jobs}
        n = 0
        for fut in as_completed(futs):
            path = fut.result()
            futs[fut]["photo"] = path
            n += 1
            if n % 40 == 0:
                print(f"img {n}", flush=True)

    built = []
    for key, var in varieties.items():
        row = var.get("csv") or {}
        src_url = var.get("url") or ""
        src_name = "冬冬商品頁" if src_url else ""
        habit_bits = list(var.get("raw_habit") or [])
        traits = list(var.get("raw_traits") or [])
        bloom = list(var.get("raw_bloom") or [])
        csv_traits = (row.get("traits") or "").replace("待確認", "")
        if csv_traits and not habit_bits:
            habit_bits.append(csv_traits)
            traits.extend([p.strip() for p in csv_traits.split("、") if p.strip()])
        intro = var.get("intro") or ""
        shop = shop_blurbs.get(key) or ""
        if shop and "株高" not in intro and "花徑" not in intro:
            # only mine numbers, do not copy the blurb
            intro_for_measures = intro + " " + shop
            shop_src = "尚實商品簡要說明"
        else:
            intro_for_measures = intro
            shop_src = ""
        klass, ogr = map_class(habit_bits + [row.get("sub") or "", csv_traits])
        if klass == PENDING and row.get("sub") == "迷你玫瑰":
            klass = "MIN"
        if klass == PENDING and row.get("sub") == "砧木與繁殖材料":
            klass = "RS"
        if klass == PENDING and row.get("sub") == "攀爬與藤本玫瑰":
            klass = "CL"
        if klass == "HT" and "David Austin" in (row.get("brand") or ""):
            # English roses stay identifiable by breeder; class follows Taiwan habit when stated.
            pass
        if klass == PENDING and (row.get("brand") or "").startswith("David Austin"):
            klass = "SHR"
        color, color_desc = pick_color(traits)
        if color == PENDING and row.get("color") and row["color"] not in ("待確認", ""):
            color = row["color"]
            src_color = "既有品種表"
        else:
            src_color = src_name
        fragrance = pick_fragrance(traits, intro_for_measures)
        if fragrance == PENDING and row.get("fragrance") and row["fragrance"] not in ("待確認", ""):
            fragrance = row["fragrance"]
            frag_src = "既有品種表"
        else:
            frag_src = src_name or (shop_src if fragrance != PENDING else "")
        form = pick_form(bloom)
        bloom_habit = pick_bloom(bloom, traits)
        if bloom_habit == PENDING and "四季開花" in csv_traits:
            bloom_habit = "四季開花"
        if bloom_habit == PENDING and "季節反覆開" in csv_traits:
            bloom_habit = "反覆開"
        if bloom_habit == PENDING and "一季開" in csv_traits:
            bloom_habit = "一季開"
        measures = parse_measures(intro_for_measures)
        measure_src = src_name if any(k in intro for k in ("株高", "花徑", "花瓣", "年")) else (shop_src or src_name)
        size_word = pick_size_word(traits)
        dia = measures["flower_dia_cm"]
        size = flower_size_from_cm(dia)
        if size == PENDING:
            size = size_word
        heat_flag = "1" if ("耐熱" in " ".join(traits) or "耐熱" in csv_traits) else "0"
        br, country, series = breeder_of(var.get("raw_breeder") or [], intro, " ".join(habit_bits))
        brand = row.get("brand") or ""
        if br == PENDING and brand and brand != "待確認":
            br = brand.split()[0] if False else brand
            country = {
                "David Austin": "英國",
                "Meilland": "法國",
                "Kordes": "德國",
                "Tantau": "德國",
                "Delbard": "法國",
                "Guillot": "法國",
            }.get(brand.split()[0] if brand.startswith("David") else brand.split()[0], PENDING)
            if brand.startswith("David Austin"):
                br, country = "David Austin", "英國"
            elif brand.startswith("羅莎歐麗"):
                br, country = "羅莎歐麗", "日本"
            elif brand.startswith("河本"):
                br, country = "河本", "日本"
            elif brand.startswith("今井"):
                br, country = "今井", "日本"
            elif brand.startswith("國枝"):
                br, country = "國枝", "日本"
            br_src = "既有品種表"
        else:
            br_src = src_name
        seedlings = []
        for offer in var["offers"]:
            if offer["seedling"] not in seedlings:
                seedlings.append(offer["seedling"])
        csv_seed = row.get("seedling") or ""
        if "高幹" in csv_seed and "高幹" not in seedlings:
            seedlings.append("高幹")
        if row.get("sub") == "樹玫瑰" and "高幹" not in seedlings:
            seedlings.append("高幹")
        if not seedlings and csv_seed and csv_seed != "待確認":
            if "自根" in csv_seed:
                seedlings.append("自根")
            if "低接" in csv_seed:
                seedlings.append("低接")
            if "高幹" in csv_seed:
                seedlings.append("高幹")
        fields = {
            "class_main": fact(klass, src_name or "既有品種表", src_url),
            "class_intl": fact(PENDING, "", ""),
            "ogr_class": fact(ogr if klass == "OGR" else PENDING, src_name, src_url),
            "series": fact(series, "", ""),
            "breeder": fact(br, br_src, src_url if br_src == src_name else ""),
            "breeder_country": fact(country, br_src, ""),
            "year": fact(measures["year"], measure_src, src_url if measures["year"] != PENDING else ""),
            "color_group": fact(color, src_color, src_url if src_color == src_name else ""),
            "color_desc": fact(color_desc, src_name, src_url),
            "fragrance": fact(fragrance, frag_src, src_url if frag_src == src_name else ""),
            "scent_type": fact(PENDING, "", ""),
            "flower_dia_cm": fact(dia, measure_src, src_url if dia != PENDING else ""),
            "flower_size": fact(size, "由花徑換算" if dia != PENDING and size != size_word else (src_name if size != PENDING else ""), src_url if size != PENDING else ""),
            "petals": fact(measures["petals"], measure_src, src_url if measures["petals"] != PENDING else ""),
            "doubleness": fact(doubleness_from(form, measures["petals"], bloom), measure_src or src_name, ""),
            "form": fact(form, src_name, src_url),
            "inflorescence": fact(PENDING, "", ""),
            "bloom_habit": fact(bloom_habit, src_name or "既有品種表", src_url),
            "bloom_months": fact(PENDING, "", ""),
            "height_cm": fact(measures["height_cm"], measure_src, src_url if measures["height_cm"] != PENDING else ""),
            "width_cm": fact(measures["width_cm"], measure_src, src_url if measures["width_cm"] != PENDING else ""),
            "growth_habit": fact(PENDING, "", ""),
            "disease_bs": fact(PENDING, "", ""),
            "disease_pm": fact(PENDING, "", ""),
            "heat_tw": fact(PENDING, "", ""),
            "heat_flag": fact(heat_flag if heat_flag == "1" else PENDING, "冬冬或既有表標示耐熱" if heat_flag == "1" else "", ""),
            "shade": fact(PENDING, "", ""),
            "usda_zone": fact(PENDING, "", ""),
            "uses": fact(PENDING, "", ""),
            "thorns": fact(PENDING, "", ""),
            "awards": fact(PENDING, "", ""),
            "pvr": fact(PENDING, "", ""),
            "seedling_types": fact("、".join(seedlings) if seedlings else PENDING, "冬冬在售苗型／既有表", ""),
        }
        # inflorescence from bloom words only when explicit
        blobb = " ".join(bloom)
        if "單生" in blobb:
            fields["inflorescence"] = fact("單生", src_name, src_url)
        elif "大簇" in blobb:
            fields["inflorescence"] = fact("大簇", src_name, src_url)
        elif "簇開" in blobb:
            fields["inflorescence"] = fact("大簇", src_name, src_url)
        desc = original_desc(var["name_zh"], fields)
        built.append(
            {
                "key": key,
                "name_zh": var["name_zh"],
                "name_orig": var["name_orig"] or PENDING,
                "aliases": var["aliases"] or PENDING,
                "fields": fields,
                "description": desc,
                "offers": [
                    {
                        "supplier_sku": o["supplier_sku"],
                        "list_name": o["list_name"],
                        "seedling": o["seedling"],
                        "cost": o["cost"],
                        "stock": o["stock"],
                        "stock_note": o["stock_note"],
                        "photo": o.get("photo") or "",
                        "menu": o.get("menu") or "",
                        "instock": True,
                    }
                    for o in var["offers"]
                ],
            }
        )

    instock_offers = sum(len(v["offers"]) for v in built)
    payload = {
        "scraped_at": time.strftime("%Y-%m-%dT%H:%M:%SZ", time.gmtime()),
        "instock_skus": instock_offers,
        "variety_count": len(built),
        "note": "冬冬商品頁未公布庫存棵數。在售 SKU 的平台庫存記 1。查不到的欄位為待補。",
        "varieties": built,
    }
    OUT.write_text(json.dumps(payload, ensure_ascii=False), encoding="utf-8")
    print("WROTE", OUT, "varieties", len(built), "instock", instock_offers)


if __name__ == "__main__":
    main()
