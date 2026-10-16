#!/usr/bin/env python3
"""Fill still-待補 rose fields from breeder pages and HelpMeFind.

Taiwan shop pages for J&K, 玫瑰天地 and 四季有花 are on Shopee. This environment
gets a login wall, so those shops contribute no invented facts.

Unknown stays 待補. A value is written only when the page states it.
"""
from __future__ import annotations

import gzip
import json
import re
import time
import unicodedata
import urllib.parse
import urllib.request
from concurrent.futures import ThreadPoolExecutor, as_completed
from html import unescape
from pathlib import Path

ROOT = Path("/workspace/garden")
LIB = ROOT / "data/rose-library.json"
CACHE = Path("/tmp/rose-fill/cache")
LOG = ROOT / "data/fill-log.json"
PENDING = "待補"
UA = {"User-Agent": "Mozilla/5.0 (compatible; GardenLibrary/1.0; +https://example.invalid)"}

FOCUS = [
    "flower_dia_cm", "petals", "form", "height_cm", "width_cm",
    "breeder", "breeder_country", "year", "fragrance", "bloom_months",
    "heat_tw", "heat_flag", "disease_bs", "disease_pm",
]


def norm(s: str) -> str:
    s = unicodedata.normalize("NFKD", s or "")
    s = "".join(c for c in s if not unicodedata.combining(c))
    s = s.lower().replace("®", " ").replace("™", " ")
    s = re.sub(r"[^a-z0-9\u4e00-\u9fff]+", " ", s)
    return re.sub(r"\s+", " ", s).strip()


def english_name(orig: str) -> str:
    orig = re.sub(r"\([^)]*\)", " ", orig or "")
    words = re.findall(r"[A-Za-z][A-Za-z'’.-]*", orig)
    stop = {"rose", "roses", "the", "and", "de", "la", "le", "von"}
    kept = [w for w in words if w.lower() not in stop or len(words) <= 3]
    return re.sub(r"\s+", " ", " ".join(kept)).strip()


def fetch(url: str, timeout: int = 25) -> bytes:
    CACHE.mkdir(parents=True, exist_ok=True)
    key = re.sub(r"[^a-zA-Z0-9]+", "_", url)[:180]
    path = CACHE / key
    if path.exists() and path.stat().st_size > 200:
        return path.read_bytes()
    req = urllib.request.Request(url, headers=UA)
    with urllib.request.urlopen(req, timeout=timeout) as resp:
        data = resp.read()
    if len(data) > 200:
        path.write_bytes(data)
    return data


def textify(html: str) -> str:
    html = re.sub(r"<script[\s\S]*?</script>", " ", html, flags=re.I)
    html = re.sub(r"<style[\s\S]*?</style>", " ", html, flags=re.I)
    html = re.sub(r"<[^>]+>", " ", html)
    return unescape(re.sub(r"\s+", " ", html))


def cm_range(a: str, b: str | None = None) -> str:
    if b and b != a:
        return f"{int(a)}–{int(b)}"
    return str(int(a))


def flower_size_from_cm(dia: str) -> str | None:
    nums = [float(x) for x in re.findall(r"\d+(?:\.\d+)?", dia)]
    if not nums:
        return None

    def bands_of(n: float) -> set[str]:
        opts = set()
        if n < 5:
            opts.add("小輪")
        if 5 <= n <= 8:
            opts.add("中輪")
        if 8 <= n <= 12:
            opts.add("大輪")
        if n > 12:
            opts.add("巨大輪")
        return opts

    shared = bands_of(nums[0])
    for n in nums[1:]:
        shared &= bands_of(n)
    if len(shared) == 1:
        return shared.pop()
    return None


def doubleness_from_petals(petals: str) -> str | None:
    if petals.startswith("約"):
        return None
    if re.fullmatch(r"\d+\+", petals):
        n = int(petals[:-1])
        if n >= 41:
            return "極重瓣"
        return None
    if re.fullmatch(r"\d+", petals):
        n = int(petals)
    elif re.fullmatch(r"\d+–\d+", petals):
        a, b = [int(x) for x in petals.split("–")]
        bands = set()
        for n in (a, b):
            if n <= 8:
                bands.add("單瓣")
            elif n <= 16:
                bands.add("半重瓣")
            elif n <= 40:
                bands.add("重瓣")
            else:
                bands.add("極重瓣")
        return bands.pop() if len(bands) == 1 else None
    else:
        return None
    if n <= 8:
        return "單瓣"
    if n <= 16:
        return "半重瓣"
    if n <= 40:
        return "重瓣"
    return "極重瓣"


def empty() -> dict:
    return {}


def add(rec: dict, field: str, value: str) -> None:
    value = (value or "").strip()
    if not value or value == PENDING:
        return
    rec.setdefault("fields", {})[field] = value


def parse_kordes(html: str, url: str) -> dict | None:
    title = ""
    m = re.search(r"<h1[^>]*>(.*?)</h1>", html, re.S)
    if m:
        title = textify(m.group(1))
    title = re.sub(r"®", "", title).strip()
    if not title or len(title) > 80:
        return None
    rec = {"name": title, "url": url, "src": "Kordes 商品頁", "fields": {}}
    rows = re.findall(
        r'<th class="properties-label">(.*?)</th>\s*<td class="properties-value">(.*?)</td>',
        html,
        re.S,
    )
    blob = {textify(a).rstrip(":"): textify(b) for a, b in rows}

    def pick(*keys: str) -> str:
        for k in keys:
            if k in blob and blob[k]:
                return blob[k]
        return ""

    dia = pick("Blütendurchmesser", "diameter")
    m = re.search(r"(\d+)\s*(?:-|–|bis)\s*(\d+)\s*cm", dia)
    if m:
        add(rec, "flower_dia_cm", cm_range(m.group(1), m.group(2)))
    else:
        m = re.search(r"(\d+)\s*cm", dia)
        if m and not re.search(r",\s*\d+\s*$", dia):
            add(rec, "flower_dia_cm", m.group(1))
    for field, keys in (
        ("height_cm", ("Wuchshöhe", "natural height")),
        ("width_cm", ("Wuchsbreite", "natural width")),
    ):
        raw = pick(*keys)
        m = re.search(r"(\d+)\s*(?:-|–|bis)?\s*(\d+)?\s*cm", raw)
        if m:
            add(rec, field, cm_range(m.group(1), m.group(2)))
    year = pick("Erscheinungsjahr", "year of introduction")
    m = re.search(r"((?:19|20)\d{2})", year)
    if m:
        add(rec, "year", m.group(1))
    breeder = pick("Züchter", "breeder")
    if breeder:
        add(rec, "breeder", breeder.split(",")[0].strip())
        if re.search(r"Kordes", breeder, re.I):
            add(rec, "breeder_country", "德國")
    bloom = pick("Blühverhalten", "flowering period")
    if "öfter" in bloom or "repeat" in bloom.lower():
        add(rec, "bloom_habit", "反覆開")
    elif "einmal" in bloom or "once" in bloom.lower():
        add(rec, "bloom_habit", "一季開")
    use = pick("Standort & Verwendung", "location / use")
    if re.search(r"Hitzefest|heat resistant", use, re.I):
        add(rec, "heat_flag", "1")
    # star ratings sit in a second table
    for label, field in (("Sternrußtau", "disease_bs"), ("Black Spot", "disease_bs"), ("Mehltau", "disease_pm"), ("Mildew", "disease_pm")):
        m = re.search(
            rf'properties-label">\s*{label}\s*</td>\s*<td class="properties-value">\s*<img[^>]+icon_star_(\d)-5',
            html,
        )
        if m:
            add(rec, field, m.group(1))
    m = re.search(r'properties-label">\s*(?:Blütenfüllung|Petal Count)\s*</td>\s*<td class="properties-value">\s*<img[^>]+alt="([^"]+)"', html)
    if m:
        alt = m.group(1).lower()
        if "stark gefüllt" in alt or "very full" in alt:
            add(rec, "doubleness", "極重瓣")
        elif "halb" in alt:
            add(rec, "doubleness", "半重瓣")
        elif "einfach" in alt or alt.strip() == "single":
            add(rec, "doubleness", "單瓣")
        elif "gefüllt" in alt or "double" in alt:
            add(rec, "doubleness", "重瓣")
    m = re.search(r'properties-label">\s*(?:Duft|Fragrance)\s*</td>\s*<td class="properties-value">\s*<img[^>]+alt="([^"]+)"', html)
    if m:
        alt = m.group(1).lower()
        if alt in ("zart", "leicht", "light", "mild"):
            add(rec, "fragrance", "淡香")
        elif alt in ("mittel", "medium"):
            add(rec, "fragrance", "中香")
        elif alt in ("stark", "strong", "intensiv"):
            add(rec, "fragrance", "強香")
        elif "ohne" in alt or alt == "none":
            add(rec, "fragrance", "無")
    return rec if rec["fields"] else None


def parse_tantau(html: str, url: str) -> dict | None:
    text = textify(html)
    m = re.search(r"<h1[^>]*>(.*?)</h1>", html, re.S)
    title = textify(m.group(1)) if m else ""
    title = re.sub(r"®", "", title).strip()
    title = re.sub(r"\s+online kaufen.*", "", title, flags=re.I).strip()
    if not title or len(title) > 80:
        m = re.search(r"<title>(.*?)</title>", html, re.S)
        title = textify(m.group(1)) if m else ""
        title = re.split(r"[|–-]", title)[0]
        title = re.sub(r"®", "", title).strip()
    if not title:
        return None
    rec = {"name": title, "url": url, "src": "Tantau 商品頁", "fields": {}}
    m = re.search(r"Blütendurchmesser[^:]{0,20}:\s*(\d+)\s*-\s*(\d+)\s*cm", text)
    if m:
        add(rec, "flower_dia_cm", cm_range(m.group(1), m.group(2)))
    else:
        m = re.search(r"Blütendurchmesser[^:]{0,20}:\s*(\d+)\s*cm", text)
        if m:
            add(rec, "flower_dia_cm", m.group(1))
    m = re.search(r"Wuchshöhe:\s*(\d+)\s*-\s*(\d+)\s*cm", text)
    if m:
        add(rec, "height_cm", cm_range(m.group(1), m.group(2)))
    m = re.search(r"Wuchsbreite:\s*(\d+)\s*-\s*(\d+)\s*cm", text)
    if m:
        add(rec, "width_cm", cm_range(m.group(1), m.group(2)))
    m = re.search(r"Einführungsjahr:\s*((?:19|20)\d{2})", text)
    if m:
        add(rec, "year", m.group(1))
    m = re.search(r"Blattgesundheit Mehltau:\s*(\d+)\s*Stars", text)
    if m:
        add(rec, "disease_pm", m.group(1))
    m = re.search(r"Blattgesundheit Sternrußtau:\s*(\d+)\s*Stars", text)
    if m:
        add(rec, "disease_bs", m.group(1))
    if "öfter blühend" in text:
        add(rec, "bloom_habit", "反覆開")
    elif "einmalblühend" in text:
        add(rec, "bloom_habit", "一季開")
    m = re.search(r"Duftbeschreibung:\s*([^.]{0,80})", text)
    if m:
        desc = m.group(1)
        if "intensiv" in desc or "stark" in desc:
            add(rec, "fragrance", "強香")
        if "fruchtig" in desc:
            add(rec, "scent_type", "果香")
    if "Tantau" in text and "Züchter" not in text:
        # The shop sells many breeders. Only name Tantau when the page says so.
        pass
    m = re.search(r"Züchter:\s*([^|]{2,40})", text)
    if m:
        who = m.group(1).strip(" .")
        add(rec, "breeder", who)
        if "Tantau" in who:
            add(rec, "breeder_country", "德國")
    return rec if rec["fields"] else None


def parse_meilland(html: str, url: str) -> dict | None:
    raw = unescape(unescape(html))
    text = textify(raw)
    slug = url.rstrip("/").split("/")[-1]
    name = slug.split("-mei")[0].replace("-", " ").strip()
    name = re.sub(r"\s+", " ", name).title()
    if not name:
        return None
    rec = {"name": name, "url": url, "src": "Meilland 目錄", "fields": {}}
    m = re.search(r"mei[a-z0-9]{4,}", slug, re.I)
    if m:
        code = m.group(0)[0:3].upper() + m.group(0)[3:]
        add(rec, "reg_name", code)
    m = re.search(r"(?:Diamètre|Diameter)\s*:\s*(\d+(?:[.,]\d+)?)\s*cm", text, re.I)
    if m:
        add(rec, "flower_dia_cm", m.group(1).replace(",", "."))
    m = re.search(r"(?:Nombre de pétales|Number of petals)\s*:\s*(\d+)", text, re.I)
    if m:
        window = text[m.start(): m.start() + 40]
        add(rec, "petals", ("約" + m.group(1)) if re.search(r"environ|about|approx", window, re.I) else m.group(1))
    m = re.search(r"(?:Hauteur|Height)\s*:\s*(\d+)\s*/\s*(\d+)\s*cm", text, re.I)
    if m:
        add(rec, "height_cm", cm_range(m.group(1), m.group(2)))
    else:
        m = re.search(r"(?:Hauteur|Height)\s*:\s*(\d+)\s*cm", text, re.I)
        if m:
            add(rec, "height_cm", m.group(1))
    m = re.search(r"(?:Largeur|Width)\s*:\s*(\d+)\s*/\s*(\d+)\s*cm", text, re.I)
    if m:
        add(rec, "width_cm", cm_range(m.group(1), m.group(2)))
    m = re.search(r"(?:Parfum|Fragrance)\s*:\s*([^.]{0,60})", text)
    if m:
        frag = m.group(1).lower()
        if "puissant" in frag or "strong" in frag:
            add(rec, "fragrance", "強香")
        elif "léger" in frag or "light" in frag or "leger" in frag:
            add(rec, "fragrance", "淡香")
        elif "moyen" in frag or "medium" in frag:
            add(rec, "fragrance", "中香")
        if "rose de mai" in frag or "damask" in frag:
            add(rec, "scent_type", "大馬士革")
    if re.search(r"Fleur Solitaire|solitary", text, re.I):
        add(rec, "inflorescence", "單生")
    m = re.search(r"((?:19|20)\d{2})", text)
    # year is noisy on Meilland award lists; only take "Création" / introduction lines
    m = re.search(r"(?:Création|Introduction|Introduced)[^0-9]{0,20}((?:19|20)\d{2})", text)
    if m:
        add(rec, "year", m.group(1))
    if rec["fields"].get("reg_name", "").startswith("MEI"):
        add(rec, "breeder", "Meilland")
        add(rec, "breeder_country", "法國")
    return rec if rec["fields"] else None


def parse_kawamoto_post(post: dict) -> dict | None:
    title = textify(post.get("title", {}).get("rendered", ""))
    body = unescape(post.get("content", {}).get("rendered", ""))
    text = textify(body)
    m = re.search(r"品種名\s*([^\s]{1,20})\s*([A-Za-z][A-Za-z .'-]{2,40})?", text)
    name = ""
    if m:
        name = m.group(2).strip() if m.group(2) else m.group(1)
        zh = m.group(1)
    else:
        zh = re.sub(r"^\\NEW/\s*", "", title).strip()
        name = zh
    if not name:
        return None
    rec = {"name": name, "zh": zh, "url": post.get("link") or "", "src": "河本玫瑰園", "fields": {}}
    m = re.search(r"花\s*径\s*(\d+(?:\.\d+)?)\s*(?:～|~|-|－)\s*(\d+(?:\.\d+)?)\s*cm", text)
    if m:
        add(rec, "flower_dia_cm", cm_range(m.group(1).split(".")[0], m.group(2).split(".")[0]))
    elif re.search(r"花\s*径\s*(\d+)\s*cm", text):
        add(rec, "flower_dia_cm", re.search(r"花\s*径\s*(\d+)\s*cm", text).group(1))
    size = re.search(r"花\s*径\s*(小輪|中輪|大輪|巨大輪|中大輪)", text)
    if size and size.group(1) in ("小輪", "中輪", "大輪", "巨大輪"):
        add(rec, "flower_size", size.group(1))
    form = re.search(r"花\s*形\s*(\S{1,12})", text)
    forms = {
        "半剣弁咲き": "半劍瓣", "半剣弁": "半劍瓣", "剣弁高心": "劍瓣高心",
        "カップ咲き": "杯狀", "カップ": "杯狀", "ロゼット": "蓮座",
        "平咲き": "平開", "一重": "單瓣",
    }
    if form and form.group(1) in forms:
        add(rec, "form", forms[form.group(1)])
    m = re.search(r"樹\s*高\s*(\d+(?:\.\d+)?)\s*[mｍ]\s*(?:～|~|-|－)\s*(\d+(?:\.\d+)?)\s*[mｍ]", text)
    if m:
        add(rec, "height_cm", cm_range(str(int(round(float(m.group(1)) * 100))), str(int(round(float(m.group(2)) * 100)))))
    elif re.search(r"樹\s*高\s*(\d+)\s*cm", text):
        add(rec, "height_cm", re.search(r"樹\s*高\s*(\d+)\s*cm", text).group(1))
    frag = re.search(r"香\s*り\s*(微香|弱香|中香|強香|無香)", text)
    if frag:
        add(rec, "fragrance", "無" if frag.group(1) == "無香" else frag.group(1))
    if "四季咲き" in text:
        add(rec, "bloom_habit", "四季開花")
    elif "一季咲き" in text:
        add(rec, "bloom_habit", "一季開")
    elif "繰り返し" in text:
        add(rec, "bloom_habit", "反覆開")
    m = re.search(r"作出年\s*((?:19|20)\d{2})", text)
    if m:
        add(rec, "year", m.group(1))
    m = re.search(r"作出者\s*([^（(]{1,20})", text)
    if m:
        add(rec, "breeder", m.group(1).strip())
        if "日本" in text[m.start(): m.start() + 40] or "河本" in m.group(1):
            add(rec, "breeder_country", "日本")
    return rec if rec["fields"] else None


def parse_tnb(html: str) -> list[dict]:
    raw = unescape(unescape(html))
    out = []
    parts = re.split(r"【品種特性】", raw)
    for part in parts[1:]:
        block = part[:1200]
        text = textify(block)
        cap = re.search(r'data-image-caption="\s*<p>\s*([^\s<]{1,16})\s+([A-Za-z][^<]{1,40})', part)
        # caption often follows the stats; search a bit further
        if not cap:
            cap = re.search(r">\s*([\u4e00-\u9fff]{1,12})\s+([A-Za-z][A-Za-z .'’-]+)\s+", block)
        zh = cap.group(1) if cap else ""
        en = cap.group(2).strip() if cap else ""
        rec = {"name": en or zh, "zh": zh, "url": "https://tnb-planning.com/rosa-orientis/roses/", "src": "羅莎歐麗品種頁", "fields": {}}
        size = re.search(r"花徑・\s*(小輪|中輪|大輪|巨大輪)", text)
        if size:
            add(rec, "flower_size", size.group(1))
        m = re.search(r"花徑・\s*(\d+(?:\.\d+)?)\s*(?:～|-|~)\s*(\d+(?:\.\d+)?)\s*(?:cm|公分)", text)
        if m:
            add(rec, "flower_dia_cm", cm_range(m.group(1).split(".")[0], m.group(2).split(".")[0]))
        form = re.search(r"花型・\s*([^\s／]{1,12})", text)
        form_map = {"杯狀": "杯狀", "劍瓣高心": "劍瓣高心", "半劍瓣": "半劍瓣", "蓮座": "蓮座", "四分": "四分", "平開": "平開", "單瓣": "單瓣", "淺杯": "淺杯", "絨球": "絨球", "寶珠瓣": "寶珠瓣"}
        if form and form.group(1) in form_map:
            add(rec, "form", form_map[form.group(1)])
        frag = re.search(r"香・\s*(無香|微香|淡香|中香|強香)", text)
        if frag:
            add(rec, "fragrance", "無" if frag.group(1) == "無香" else frag.group(1))
        scent = re.search(r"香味・\s*([^／<\s]{1,12})", text)
        scent_map = {"茶香": "茶香", "大馬士革": "大馬士革", "果香": "果香", "沒藥": "沒藥", "麝香": "麝香"}
        if scent and scent.group(1) in scent_map:
            add(rec, "scent_type", scent_map[scent.group(1)])
        if "四季開花" in text:
            add(rec, "bloom_habit", "四季開花")
        elif "一季" in text:
            add(rec, "bloom_habit", "一季開")
        elif "反覆" in text:
            add(rec, "bloom_habit", "反覆開")
        m = re.search(r"樹高・\s*(\d+(?:\.\d+)?)\s*m", text)
        if m:
            add(rec, "height_cm", str(int(round(float(m.group(1)) * 100))))
        m = re.search(r"樹寬・\s*(\d+(?:\.\d+)?)\s*m", text)
        if m:
            add(rec, "width_cm", str(int(round(float(m.group(1)) * 100))))
        if "耐暑" in text and "強" in text:
            add(rec, "heat_flag", "1")
        if rec["fields"] and rec["name"]:
            out.append(rec)
    return out


def parse_hmf_summary(block_html: str) -> dict:
    title_m = re.search(r'class="bld"\s*>(.*?)</a>', block_html, re.S)
    title = textify(title_m.group(1)) if title_m else ""
    id_m = re.search(r'href="/rose/l\.php\?l=(2\.\d+)"', block_html)
    para = ""
    pm = re.search(r'<div class="t">\s*<p>(.*?)</p>', block_html, re.S)
    if pm:
        para = textify(pm.group(1))
    primary = re.split(r"\s*\(", title, maxsplit=1)[0].strip()
    fields: dict[str, str] = {}
    m = re.search(r"\((\d+\+|\d+\s*-\s*\d+)\s+petals\)", para)
    if m:
        fields["petals"] = m.group(1).replace(" ", "").replace("-", "–")
    def stated_cm(label: str) -> str:
        m = re.search(rf"{label}(?::| of)\s*[^(]{{0,80}}\((\d+)\s+to\s+(\d+)\s*cm\)", para, re.I)
        if m:
            return cm_range(m.group(1), m.group(2))
        m = re.search(rf"{label}(?::| of)\s*[^(]{{0,80}}\(up to (\d+)\s*cm\)", para, re.I)
        if m:
            return "≤" + m.group(1)
        m = re.search(rf"{label}(?::| of)\s*[^(]{{0,80}}\((\d+)\s*cm\)", para, re.I)
        if m:
            return m.group(1)
        return ""

    height = stated_cm("Height")
    if height:
        fields["height_cm"] = height
    width = stated_cm("Width")
    if width:
        fields["width_cm"] = width
    frag = ""
    if re.search(r"None to mild|mild to moderate|moderate to strong", para, re.I):
        frag = ""
    elif re.search(r"\bStrong\b", para) and "fragrance" in para.lower():
        frag = "強香"
    elif re.search(r"\bModerate\b", para) and "fragrance" in para.lower():
        frag = "中香"
    elif (re.search(r"\bMild\b", para) or re.search(r"\bLight\b", para)) and "fragrance" in para.lower():
        frag = "淡香"
    elif re.search(r"\bNone\b.*fragrance|no fragrance", para, re.I):
        frag = "無"
    if frag:
        fields["fragrance"] = frag
    low = para.lower()
    if "tea fragrance" in low or "tea," in low:
        fields["scent_type"] = "茶香"
    elif "damask" in low or "old rose" in low:
        fields["scent_type"] = "大馬士革"
    elif "myrrh" in low:
        fields["scent_type"] = "沒藥"
    elif "musk" in low:
        fields["scent_type"] = "麝香"
    elif "fruit" in low or "apple" in low:
        fields["scent_type"] = "果香"
    elif "citrus" in low:
        fields["scent_type"] = "柑橘"
    forms = []
    for token, label in (
        ("high-centered", "劍瓣高心"),
        ("cupped", "杯狀"),
        ("rosette", "蓮座"),
        ("quartered", "四分"),
        ("pompon", "絨球"),
        ("flat", "平開"),
    ):
        if token in low and label not in forms:
            forms.append(label)
    if "single" in low and "bloom form" in low and "petals" in low:
        forms.append("單瓣")
    if forms:
        fields["form"] = "、".join(forms)
    if "mostly solitary" in low or "borne solitary" in low:
        fields["inflorescence"] = "單生"
    elif "large cluster" in low:
        fields["inflorescence"] = "大簇"
    elif "cluster" in low:
        fields["inflorescence"] = "少量成簇"
    if "once" in low and "bloom" in low and "flushes" not in low:
        fields["bloom_habit"] = "一季開"
    elif "flushes throughout" in low or "repeat" in low:
        fields["bloom_habit"] = "反覆開"
    elif "continuous" in low:
        fields["bloom_habit"] = "四季開花"
    m = re.search(r"\((?:19|20)\d{2}\s*[-–]\s*(?:19|20)?\d{0,4}\)\s*\(((?:19|20)\d{2})\)", para)
    if not m:
        m = re.search(r"\(((?:19|20)\d{2})\)\.\s*$", para)
    if m:
        fields["year"] = m.group(1)
    m = re.search(r"^(?:Hybrid Tea|Floribunda|Shrub|Climber|Miniature|Tea|China|Bourbon|Polyantha|Grandiflora|Rambler|Species)\.\s+", para)
    bred = re.search(r"([A-Z][A-Za-z .'-]{2,40}?)\s*\((?:19|20)\d{2}", para)
    if bred:
        who = bred.group(1).strip(" .")
        if who.lower() not in ("height", "width", "usda"):
            fields["breeder"] = who
    if re.search(r"\(Germany", para):
        fields["breeder_country"] = "德國"
    elif re.search(r"\(France", para):
        fields["breeder_country"] = "法國"
    elif re.search(r"\(United Kingdom|\(England|\(U\.K", para):
        fields["breeder_country"] = "英國"
    elif re.search(r"\(Japan", para):
        fields["breeder_country"] = "日本"
    elif re.search(r"\(United States|\(U\.S\.A", para):
        fields["breeder_country"] = "美國"
    m = re.search(r"Average diameter\s+([0-9.]+)\s*(?:to\s+([0-9.]+)\s*)?(?:in|\"|inches)", para, re.I)
    if m:
        def inch(v: str) -> str:
            cm = float(v) * 2.54
            return str(int(cm)) if abs(cm - round(cm)) < 0.05 else f"{cm:.1f}"
        fields["flower_dia_cm"] = inch(m.group(1)) if not m.group(2) else f"{inch(m.group(1))}–{inch(m.group(2))}"
    return {"title": title, "primary": primary, "id": id_m.group(1) if id_m else "", "fields": fields, "para": para}


def hmf_search(name: str) -> list[dict]:
    q = urllib.parse.quote(name)
    url = f"https://www.helpmefind.com/rose/plants.php?searchNm={q}&searchNmTyp=2&tab=1"
    try:
        html = fetch(url).decode("utf-8", "replace")
    except Exception:
        return []
    blocks = re.findall(r'<div class="bg bgA plant">([\s\S]*?)<div class="cl">', html)
    return [parse_hmf_summary(b) for b in blocks]


def hmf_diameter(plant_id: str) -> str:
    if not plant_id:
        return ""
    url = f"https://www.helpmefind.com/rose/l.php?l={plant_id}"
    try:
        html = fetch(url).decode("utf-8", "replace")
    except Exception:
        return ""
    text = textify(html)
    m = re.search(r"Average diameter\s+([0-9.]+)\s*(?:to\s+([0-9.]+)\s*)?(?:in(?:ches)?|\")", text, re.I)
    if not m:
        m = re.search(r"diameter\s+([0-9.]+)\s*cm", text, re.I)
        return m.group(1) if m else ""

    def inch(v: str) -> str:
        cm = float(v) * 2.54
        return str(int(cm)) if abs(cm - round(cm)) < 0.05 else f"{cm:.1f}"

    if m.group(2):
        return f"{inch(m.group(1))}–{inch(m.group(2))}"
    return inch(m.group(1))


def map_workers(fn, items, workers=6):
    out = []
    with ThreadPoolExecutor(max_workers=workers) as pool:
        futs = [pool.submit(fn, it) for it in items]
        done = 0
        for fut in as_completed(futs):
            done += 1
            try:
                out.append(fut.result())
            except Exception:
                out.append(None)
            if done % 40 == 0:
                print(f"  {done}/{len(items)}", flush=True)
    return out


def load_kordes() -> list[dict]:
    print("kordes sitemap", flush=True)
    raw = fetch("https://www.rosen.de/sitemap/salesChannel-f5659efa1c5045ca8b1147b654791f6c-2fbb5fe2e29a4d70aa5854ce7ce3e20b/f5659efa1c5045ca8b1147b654791f6c-311b96d9b3874925b3efff8b9eb24a97-sitemap-www-rosen-de-1.xml.gz")
    xml = gzip.decompress(raw).decode("utf-8", "replace") if raw[:2] == b"\x1f\x8b" else raw.decode("utf-8", "replace")
    locs = re.findall(r"<loc>(.*?)</loc>", xml)
    urls = [u for u in locs if "/gartenrosen/rosentypen/" in u and u.rstrip("/").count("/") >= 5]
    print("kordes urls", len(urls), flush=True)

    def one(url: str):
        html = fetch(url).decode("utf-8", "replace")
        return parse_kordes(html, url)

    return [r for r in map_workers(one, urls) if r]


def load_tantau() -> list[dict]:
    print("tantau sitemap", flush=True)
    raw = fetch("https://www.rosen-tantau.com/sitemap/salesChannel-019831f85eae710bb875787740dd1f6c-2fbb5fe2e29a4d70aa5854ce7ce3e20b/019831f85eae710bb875787740dd1f6c-019836315a837ecca8cb40f497195a1c-sitemap-www-rosen-tantau-com-1.xml.gz")
    xml = gzip.decompress(raw).decode("utf-8", "replace") if raw[:2] == b"\x1f\x8b" else raw.decode("utf-8", "replace")
    locs = re.findall(r"<loc>(.*?)</loc>", xml)
    urls = [u for u in locs if u.count("/") == 3 and not u.endswith("/")]
    print("tantau urls", len(urls), flush=True)

    def one(url: str):
        html = fetch(url).decode("utf-8", "replace")
        return parse_tantau(html, url)

    return [r for r in map_workers(one, urls, workers=8) if r]


def load_meilland() -> list[dict]:
    print("meilland sitemap", flush=True)
    xml = fetch("https://meilland.com/portfolio-sitemap.xml").decode("utf-8", "replace")
    locs = re.findall(r"<loc>(.*?)</loc>", xml)
    seen = set()
    urls = []
    for u in locs:
        if "/catalogue/" not in u:
            continue
        slug = u.rstrip("/").split("/")[-1]
        if slug in seen:
            continue
        seen.add(slug)
        urls.append(u if "/en/" in u else u)
    print("meilland urls", len(urls), flush=True)

    def one(url: str):
        html = fetch(url).decode("utf-8", "replace")
        return parse_meilland(html, url)

    return [r for r in map_workers(one, urls, workers=6) if r]


def load_kawamoto() -> list[dict]:
    print("kawamoto", flush=True)
    out = []
    page = 1
    while page <= 8:
        url = f"https://www.kawamotorosegarden.com/wp-json/wp/v2/posts?per_page=50&page={page}&_fields=link,title,content"
        try:
            data = json.loads(fetch(url).decode("utf-8", "replace"))
        except Exception:
            break
        if not isinstance(data, list) or not data:
            break
        for post in data:
            rec = parse_kawamoto_post(post)
            if rec:
                out.append(rec)
        if len(data) < 50:
            break
        page += 1
    print("kawamoto records", len(out), flush=True)
    return out


def load_tnb() -> list[dict]:
    html = fetch("https://tnb-planning.com/rosa-orientis/roses/").decode("utf-8", "replace")
    recs = parse_tnb(html)
    print("rosa orientis", len(recs), flush=True)
    return recs


def load_rose_collection() -> list[dict]:
    """Series pages. Keep a record only when a variety name and a stated cm are on the same page."""
    try:
        raw = fetch("https://www.rose-collection.com/kawamoto-heaven.html")
    except Exception as exc:
        print("rose-collection failed", exc, flush=True)
        return []
    html = raw.decode("cp932", "replace")
    text = textify(html)
    # The series page names the line, not one variety's diameter. Do not spread one number across the line.
    print("rose-collection page length", len(text), "cm hits", len(re.findall(r"\d+\s*cm", text)), flush=True)
    return []


CLASS_WORD = {
    "hybrid tea": "HT",
    "floribunda": "FL",
    "polyantha": "FL",
    "grandiflora": "HT",
    "shrub": "SHR",
    "climber": "CL",
    "large-flowered climber": "CL",
    "miniature": "MIN",
    "miniflora": "MIN",
    "rambler": "RA",
    "groundcover": "GC",
    "ground cover": "GC",
    "tea": "OGR",
    "china": "OGR",
    "bourbon": "OGR",
    "species": "SP",
}


def class_of_title(title: str) -> str:
    low = title.lower()
    m = re.search(r"\(([^)]+)\)", low)
    hint = m.group(1) if m else low
    for word, code in CLASS_WORD.items():
        if word in hint:
            return code
    return ""


def dedupe_records(records: list[dict]) -> list[dict]:
    """Tantau and Meilland publish the same rose at several pot-size URLs."""
    grouped: dict[str, list[dict]] = {}
    for rec in records:
        key = norm(rec.get("name") or "")
        if len(key) < 2:
            continue
        grouped.setdefault(key, []).append(rec)
    out = []
    for group in grouped.values():
        merged: dict[str, str] = {}
        bad: set[str] = set()
        for rec in group:
            for field, value in rec.get("fields", {}).items():
                if field in bad:
                    continue
                if field not in merged:
                    merged[field] = value
                elif merged[field] != value:
                    bad.add(field)
                    merged.pop(field, None)
        if not merged:
            continue
        out.append({
            "name": group[0]["name"],
            "zh": group[0].get("zh") or "",
            "url": group[0].get("url") or "",
            "src": group[0]["src"],
            "fields": merged,
        })
    return out


def index_records(records: list[dict]) -> dict[str, list[dict]]:
    idx: dict[str, list[dict]] = {}
    for rec in records:
        keys = {norm(rec.get("name") or "")}
        if rec.get("zh"):
            keys.add(norm(rec["zh"]))
        for part in re.split(r"\s*/\s*", rec.get("name") or ""):
            keys.add(norm(part))
        for k in keys:
            if len(k) >= 2:
                idx.setdefault(k, []).append(rec)
    return idx


def lookup(idx: dict, *names: str) -> dict | None:
    hits = []
    seen = set()
    for name in names:
        key = norm(name)
        if len(key) < 3:
            continue
        for rec in idx.get(key, []):
            marker = rec.get("url") or rec.get("name")
            if marker not in seen:
                seen.add(marker)
                hits.append(rec)
    if len(hits) == 1:
        return hits[0]
    return None


def variety_names(var: dict) -> list[str]:
    names = [var.get("name_zh") or ""]
    eng = english_name(var.get("name_orig") or "")
    if eng:
        names.append(eng)
    aliases = var.get("aliases") or ""
    if aliases and aliases != PENDING:
        for part in re.split(r"[、,;/]", aliases):
            names.append(part.strip())
            eng_a = english_name(part)
            if eng_a:
                names.append(eng_a)
    return [n for n in names if n]


def can_write(field: str, cur: dict, src: str, identity_from_breeder: bool) -> bool:
    val = (cur or {}).get("v")
    if val in (None, "", PENDING):
        return True
    if not identity_from_breeder:
        return False
    if field not in ("year", "breeder", "breeder_country", "petals", "reg_name"):
        return False
    old = (cur or {}).get("src") or ""
    return any(token in old for token in ("冬冬", "尚實", "玫瑰天地", "四季", "J&K"))


def apply_record(var: dict, rec: dict, identity_from_breeder: bool) -> int:
    n = 0
    fields = var.setdefault("fields", {})
    for field, value in rec.get("fields", {}).items():
        cur = fields.get(field) or {"v": PENDING, "src": "", "url": ""}
        if not can_write(field, cur, rec["src"], identity_from_breeder):
            continue
        old = cur.get("v") or ""
        if field == "breeder" and old not in ("", PENDING) and not breeders_compatible(old, value):
            continue
        if field == "breeder_country" and old not in ("", PENDING):
            continue
        if field == "year" and re.fullmatch(r"(?:19|20)\d{2}", old) and re.fullmatch(r"(?:19|20)\d{2}", value):
            if abs(int(old) - int(value)) > 2:
                continue
        fields[field] = {"v": value, "src": rec["src"], "url": rec.get("url") or ""}
        n += 1
    dia_meta = fields.get("flower_dia_cm") or {}
    dia = dia_meta.get("v")
    size_meta = fields.get("flower_size") or {}
    size = size_meta.get("v")
    band = flower_size_from_cm(dia) if dia and dia != PENDING else None
    if band and size in (None, "", PENDING):
        fields["flower_size"] = {"v": band, "src": "由花徑換算", "url": dia_meta.get("url") or ""}
        n += 1
    elif band and size in ("小輪", "中輪", "大輪", "巨大輪") and size != band and size_meta.get("src") != dia_meta.get("src"):
        fields["flower_size"] = {"v": band, "src": "由花徑換算", "url": dia_meta.get("url") or ""}
        n += 1
    petals_meta = fields.get("petals") or {}
    petals = petals_meta.get("v")
    doub_meta = fields.get("doubleness") or {}
    doub = doub_meta.get("v")
    if petals and petals != PENDING and doub in (None, "", PENDING):
        band = doubleness_from_petals(petals)
        if band:
            fields["doubleness"] = {"v": band, "src": petals_meta.get("src") or "", "url": petals_meta.get("url") or ""}
            n += 1
    elif petals and petals != PENDING and doub not in (None, "", PENDING) and doub_meta.get("src") != petals_meta.get("src"):
        band = doubleness_from_petals(petals)
        if band and band != doub:
            fields["doubleness"] = {"v": band, "src": petals_meta.get("src") or "", "url": petals_meta.get("url") or ""}
            n += 1
        elif petals.startswith("約"):
            nums = [int(x) for x in re.findall(r"\d+", petals)]
            ranges = {"單瓣": (1, 8), "半重瓣": (9, 16), "重瓣": (17, 40), "極重瓣": (41, 999)}
            if nums and doub in ranges and not (ranges[doub][0] <= nums[0] <= ranges[doub][1]):
                fields["doubleness"] = {"v": PENDING, "src": "", "url": ""}
    return n


def apply_source(lib: dict, records: list[dict], identity_from_breeder: bool) -> int:
    idx = index_records(records)
    filled = 0
    used = 0
    for var in lib["varieties"]:
        rec = lookup(idx, *variety_names(var))
        if not rec:
            continue
        got = apply_record(var, rec, identity_from_breeder)
        if got:
            used += 1
            filled += got
    print(f"  matched varieties {used}, fields {filled}", flush=True)
    return filled


def color_clause(para: str) -> str:
    m = re.search(r"\.\s+(.*?)(?:\bfragrance\b|\bbloom form\b|\bpetals\b|Height of|Height:|USDA)", para, re.I)
    clause = m.group(1) if m else para[:160]
    clause = re.sub(r"yellow stamens", " ", clause, flags=re.I)
    clause = re.sub(r"old rose", " ", clause, flags=re.I)
    return clause.lower()


COLOR_WORDS = {
    "杏": ["apricot", "peach", "buff", "salmon", "amber", "copper"],
    "橘": ["orange", "apricot", "peach", "copper", "salmon", "amber"],
    "黃": ["yellow", "gold", "lemon", "amber", "buff"],
    "粉": ["pink", "blush", "salmon", "cerise"],
    "紅": ["red", "crimson", "scarlet", "burgundy", "cerise", "ruby"],
    "白": ["white", "cream"],
    "紫": ["purple", "mauve", "lavender", "lilac", "violet"],
    "綠": ["green"],
}
# Only drop a namesake when the stated color is on the other side of the wheel.
COLOR_OPPOSE = {
    "杏": {"紫", "紅"},
    "橘": {"紫", "白"},
    "黃": {"紫", "紅"},
    "白": {"紫", "紅"},
    "綠": {"紫", "紅"},
    "紅": {"黃", "白", "綠"},
    "紫": {"黃", "杏", "橘", "白", "綠"},
    "粉": {"綠"},
}
BREEDER_GROUPS = (
    ("羅莎歐麗", "kimura", "takunori"),
    ("河本", "kawamoto", "junko"),
    ("kordes", "sohne", "reimer"),
    ("tantau", "evers"),
    ("meilland", "mouchotte"),
    ("austin", "david"),
)


def breeders_compatible(left: str, right: str) -> bool:
    a, b = norm(left), norm(right)
    if not a or not b or a in b or b in a:
        return True
    if a.split(" ")[0] == b.split(" ")[0]:
        return True
    for group in BREEDER_GROUPS:
        if any(tok in a for tok in group) and any(tok in b for tok in group):
            return True
    return False


def hmf_reject(var: dict, hit: dict) -> str:
    """Skip a same-name HelpMeFind row when a fact already on the variety disagrees."""
    fields = var.get("fields") or {}
    clause = color_clause(hit.get("para") or "")
    color = (fields.get("color_group") or {}).get("v") or ""
    if color in COLOR_OPPOSE:
        own = COLOR_WORDS[color]
        if not any(word in clause for word in own):
            for other in COLOR_OPPOSE[color]:
                if any(word in clause for word in COLOR_WORDS[other]):
                    return "color"
    hy = (hit.get("fields") or {}).get("year") or ""
    ymeta = fields.get("year") or {}
    year = ymeta.get("v") or ""
    if (
        ymeta.get("src") != "HelpMeFind"
        and re.fullmatch(r"(?:19|20)\d{2}", year)
        and re.fullmatch(r"(?:19|20)\d{2}", hy)
        and abs(int(year) - int(hy)) >= 4
    ):
        return "year"
    bmeta = fields.get("breeder") or {}
    hb = (hit.get("fields") or {}).get("breeder") or ""
    breeder = bmeta.get("v") or ""
    if (
        bmeta.get("src") != "HelpMeFind"
        and breeder not in ("", PENDING)
        and hb
        and not breeders_compatible(breeder, hb)
        and re.fullmatch(r"(?:19|20)\d{2}", year)
        and re.fullmatch(r"(?:19|20)\d{2}", hy)
        and abs(int(year) - int(hy)) >= 2
    ):
        return "breeder-year"
    return ""


def hmf_fill(lib: dict) -> tuple[int, dict]:
    targets = []
    for var in lib["varieties"]:
        fields = var.get("fields") or {}
        missing = [f for f in ("flower_dia_cm", "petals", "year", "breeder", "height_cm", "width_cm", "form", "fragrance") if (fields.get(f) or {}).get("v", PENDING) in ("", PENDING)]
        eng = english_name(var.get("name_orig") or "")
        if missing and len(eng) >= 4:
            targets.append((var, eng))
    print("hmf targets", len(targets), flush=True)
    filled = 0
    ambiguous = 0
    none = 0
    rejected = {"color": 0, "year": 0, "breeder-year": 0}

    def one(item):
        var, eng = item
        try:
            hits = hmf_search(eng)
        except Exception as exc:
            print("hmf err", eng, exc, flush=True)
            return var["key"], None, 0
        exact = [h for h in hits if norm(h.get("primary") or "") == norm(eng)]
        if len(exact) > 1:
            breeder = (var.get("fields") or {}).get("breeder", {}).get("v") or ""
            klass = (var.get("fields") or {}).get("class_main", {}).get("v") or ""
            narrowed = exact
            if breeder and breeder != PENDING:
                token = norm(breeder).split(" ")[0]
                got = []
                for hit in narrowed:
                    who = (hit.get("fields") or {}).get("breeder") or ""
                    blob = norm((hit.get("title") or "") + " " + who)
                    if token and token in blob:
                        got.append(hit)
                if len(got) >= 1:
                    narrowed = got
            if len(narrowed) > 1 and klass and klass != PENDING:
                got = [h for h in narrowed if class_of_title(h.get("title") or "") == klass]
                if len(got) == 1:
                    narrowed = got
            exact = narrowed
        if len(exact) != 1:
            return var["key"], None, len(exact)
        hit = exact[0]
        reason = hmf_reject(var, hit)
        if reason:
            return var["key"], None, reason
        try:
            if "flower_dia_cm" not in hit["fields"]:
                dia = hmf_diameter(hit.get("id") or "")
                if dia:
                    hit["fields"]["flower_dia_cm"] = dia
        except Exception as exc:
            print("hmf dia err", eng, exc, flush=True)
        return var["key"], hit, 1

    found = {}
    with ThreadPoolExecutor(max_workers=4) as pool:
        futs = [pool.submit(one, t) for t in targets]
        done = 0
        for fut in as_completed(futs):
            done += 1
            key, hit, n = fut.result()
            if hit:
                found[key] = hit
            elif n == 0:
                none += 1
            elif isinstance(n, str):
                rejected[n] = rejected.get(n, 0) + 1
            else:
                ambiguous += 1
            if done % 50 == 0:
                print(f"  hmf {done}/{len(targets)} hits {len(found)}", flush=True)
            time.sleep(0.05)
    print(f"hmf unique {len(found)} none {none} ambiguous {ambiguous} rejected {rejected}", flush=True)
    by_key = {var["key"]: var for var in lib["varieties"]}
    for key, hit in found.items():
        rec = {
            "name": hit["primary"],
            "url": f"https://www.helpmefind.com/rose/l.php?l={hit['id']}" if hit.get("id") else "https://www.helpmefind.com/rose/plants.php",
            "src": "HelpMeFind",
            "fields": hit["fields"],
        }
        filled += apply_record(by_key[key], rec, False)
    print("hmf fields", filled, flush=True)
    return filled, rejected


def find_chiffon_photo(lib: dict) -> str:
    media = ROOT / "public/media/roses/1357.jpg"
    # HelpMeFind search for the variety named on the product page.
    variety = next((v for v in lib["varieties"] if v.get("name_zh") == "薄紗"), None)
    hits = hmf_search("Chiffon")
    exact = [h for h in hits if norm(h.get("primary") or "") == "chiffon"]
    photo_url = ""
    source = ""
    for hit in exact:
        if variety and hmf_reject(variety, hit):
            continue
        pid = hit.get("id") or ""
        if not pid:
            continue
        html = fetch(f"https://www.helpmefind.com/rose/l.php?l={pid}").decode("utf-8", "replace")
        text = textify(html).lower()
        if "hibiscus" in text or "rose of sharon" in text:
            continue
        imgs = re.findall(r'src="(/gardening/[^"]+\.(?:jpg|jpeg|png))"', html, re.I)
        if imgs:
            photo_url = "https://www.helpmefind.com" + imgs[0]
            source = f"https://www.helpmefind.com/rose/l.php?l={pid}"
            break
    if not photo_url:
        api = "https://commons.wikimedia.org/w/api.php?action=query&format=json&generator=search&gsrsearch=Rosa%20Chiffon%20rose&gsrnamespace=6&gsrlimit=8&prop=imageinfo&iiprop=url|mime|size"
        try:
            data = json.loads(fetch(api).decode("utf-8", "replace"))
        except Exception:
            data = {}
        pages = (data.get("query") or {}).get("pages") or {}
        for page in pages.values():
            title = page.get("title") or ""
            if re.search(r"hibiscus|sharon|althea", title, re.I):
                continue
            if "rose" not in title.lower() and "rosa" not in title.lower():
                continue
            info = (page.get("imageinfo") or [{}])[0]
            if info.get("mime") not in ("image/jpeg", "image/png"):
                continue
            if (info.get("size") or 0) < 8000:
                continue
            photo_url = info.get("url") or ""
            source = "https://commons.wikimedia.org/wiki/" + title.replace(" ", "_")
            if photo_url:
                break
    if not photo_url:
        return ""
    data = fetch(photo_url)
    if len(data) < 800:
        return ""
    media.write_bytes(data)
    rel = "media/roses/1357.jpg"
    for var in lib["varieties"]:
        if var.get("name_zh") != "薄紗":
            continue
        for offer in var.get("offers") or []:
            if str(offer.get("supplier_sku")) == "1357":
                offer["photo"] = rel
    return source


def field_counts(lib: dict) -> dict:
    n = len(lib["varieties"])
    out = {"n": n}
    keys = FOCUS + ["flower_size", "doubleness", "scent_type", "bloom_habit", "inflorescence"]
    for key in keys:
        c = 0
        for var in lib["varieties"]:
            if key in ("name_zh",):
                continue
            val = (var.get("fields") or {}).get(key, {}).get("v", PENDING)
            if key == "heat_flag":
                if val == "1":
                    c += 1
                continue
            if val not in ("", PENDING, None):
                c += 1
        out[key] = c
    return out


def main() -> None:
    lib = json.loads(LIB.read_text())
    before = field_counts(lib)
    print("before", json.dumps(before, ensure_ascii=False), flush=True)
    sources = {
        "kordes": load_kordes(),
        "meilland": load_meilland(),
        "tantau": load_tantau(),
        "kawamoto": load_kawamoto(),
        "rosa_orientis": load_tnb(),
        "rose_collection": load_rose_collection(),
    }
    # Application order: Taiwan shops first (none readable), then breeders, then HelpMeFind gaps.
    order = [
        ("Kordes", sources["kordes"], True),
        ("Meilland", sources["meilland"], True),
        ("Tantau", sources["tantau"], True),
        ("河本", sources["kawamoto"], True),
        ("rose-collection", sources["rose_collection"], True),
        ("羅莎歐麗", sources["rosa_orientis"], True),
    ]
    filled = {}
    for label, recs, ident in order:
        recs = dedupe_records(recs)
        print(label, "records", len(recs), flush=True)
        filled[label] = apply_source(lib, recs, ident)
    filled["HelpMeFind"], hmf_rejected = hmf_fill(lib)
    photo_src = find_chiffon_photo(lib)
    after = field_counts(lib)
    log = {
        "before": before,
        "after": after,
        "filled_fields": filled,
        "source_records": {k: len(v) for k, v in sources.items()},
        "chiffon_photo": photo_src,
        "hmf_rejected": hmf_rejected,
        "chiffon_photo_note": "冬冬商品頁沒有照片。HelpMeFind 唯一同名 Chiffon 是 1940 年淺粉雜交茶，和這株已標的杏色衝突，沒有拿它的資料或照片。VIP Roses 的 Chiffon 頁面寫 pale pink，照片也是淺粉，沒有當成這株的圖。",
        "shops_blocked": {
            "J&K": "蝦皮賣場，這個環境開啟商品頁會被要求登入，沒有讀到品種數據",
            "玫瑰天地": "蝦皮賣場 shop 563805315，同樣被擋",
            "四季有花": "蝦皮賣場 shop 5257351，同樣被擋",
        },
    }
    LOG.write_text(json.dumps(log, ensure_ascii=False, indent=2), encoding="utf-8")
    LIB.write_text(json.dumps(lib, ensure_ascii=False), encoding="utf-8")
    print("after", json.dumps(after, ensure_ascii=False), flush=True)
    print("photo", photo_src or "MISSING", flush=True)


if __name__ == "__main__":
    main()
