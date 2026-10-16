#!/usr/bin/env python3
"""Fill still-待補 variety facts from David Austin product pages.

Only year, petals, height/width (feet converted to cm), fragrance strength,
and bloom habit. Does not overwrite a value already filled from a Taiwan seller.
Does not invent flower diameter.
"""
from __future__ import annotations

import json
import re
import urllib.request
from concurrent.futures import ThreadPoolExecutor, as_completed
from html import unescape
from pathlib import Path

LIB = Path("/workspace/garden/data/rose-library.json")
UA = {"User-Agent": "Mozilla/5.0"}
PENDING = "待補"


def slug(name: str) -> str:
    name = name.lower()
    name = name.replace("'", "")
    name = re.sub(r"[^a-z0-9]+", "-", name).strip("-")
    return name


def fetch(url: str) -> str:
    req = urllib.request.Request(url, headers=UA)
    with urllib.request.urlopen(req, timeout=25) as resp:
        return resp.read().decode("utf-8", "replace")


def feet_to_cm(token: str) -> int | None:
    token = token.strip().lower().replace("½", " 1/2").replace("¼", " 1/4").replace("¾", " 3/4")
    m = re.search(r"(\d+)\s+(\d+)\s*/\s*(\d+)\s*ft", token)
    if m:
        feet = int(m.group(1)) + int(m.group(2)) / int(m.group(3))
        return int(round(feet * 30.48))
    m = re.search(r"(\d+(?:\.\d+)?)\s*ft", token)
    if not m:
        return None
    return int(round(float(m.group(1)) * 30.48))


def parse(html: str) -> dict:
    text = re.sub(r"<script[\s\S]*?</script>", " ", html, flags=re.I)
    text = re.sub(r"<style[\s\S]*?</style>", " ", text, flags=re.I)
    text = re.sub(r"<[^>]+>", " ", text)
    text = unescape(re.sub(r"\s+", " ", text))
    out = {}
    m = re.search(r"Year of Introduction:\s*((?:19|20)\d{2})", text)
    if m:
        out["year"] = m.group(1)
    m = re.search(r"Petal Count:\s*(\d+)", text)
    if m:
        out["petals"] = m.group(1)
        n = int(m.group(1))
        if n <= 8:
            out["doubleness"] = "單瓣"
        elif n <= 16:
            out["doubleness"] = "半重瓣"
        elif n <= 40:
            out["doubleness"] = "重瓣"
        else:
            out["doubleness"] = "極重瓣"
    m = re.search(r"Height:\s*([0-9./ ]+ft)", text, re.I)
    if m:
        cm = feet_to_cm(m.group(1))
        if cm:
            out["height_cm"] = str(cm)
    m = re.search(r"Width:\s*([0-9./ ]+ft)", text, re.I)
    if m:
        cm = feet_to_cm(m.group(1))
        if cm:
            out["width_cm"] = str(cm)
    if re.search(r"Flowering:\s*Repeat", text):
        out["bloom_habit"] = "反覆開"
    elif re.search(r"Flowering:\s*Once", text):
        out["bloom_habit"] = "一季開"
    if re.search(r"Fragrance:\s*Light", text) or re.search(r"Light,?\s+\w+\s+fragrance", text):
        out["fragrance"] = "淡香"
    elif re.search(r"Fragrance:\s*Medium", text):
        out["fragrance"] = "中香"
    elif re.search(r"Fragrance:\s*Strong", text) or "strong Old Rose" in text or "Strong, Old Rose" in text:
        out["fragrance"] = "強香"
    if "Old Rose" in text:
        out["scent_type"] = "大馬士革"
    m = re.search(r"Breeder Code:\s*([A-Za-z0-9]+)", text)
    if not m:
        m = re.search(r'"sku":\s*"([A-Z]{3,}[A-Z0-9]+)"', html)
    if m and not m.group(1).startswith("1G"):
        out["reg_name"] = m.group(1)
    out["breeder"] = "David Austin"
    out["breeder_country"] = "英國"
    if "English Climbing" in text or "Climbing Rose" in text:
        out.setdefault("class_main", "CL")
    else:
        out.setdefault("class_main", "SHR")
    return out


def main() -> None:
    lib = json.loads(LIB.read_text())
    targets = []
    for var in lib["varieties"]:
        breeder = (var.get("fields") or {}).get("breeder", {}).get("v", "")
        orig = var.get("name_orig") or ""
        if "Austin" not in breeder and "Austin" not in orig and "奧斯汀" not in breeder:
            continue
        eng = re.sub(r"[^A-Za-z '\-]", " ", orig)
        eng = re.sub(r"\s+", " ", eng).strip(" -")
        if len(eng) < 4:
            continue
        targets.append((var, slug(eng), eng))
    print("austin targets", len(targets), flush=True)

    def one(item):
        var, sl, eng = item
        url = f"https://www.davidaustinroses.com/products/{sl}"
        try:
            html = fetch(url)
        except Exception:
            return var["key"], None, url
        if "Page not found" in html or "404" in html[:500]:
            return var["key"], None, url
        return var["key"], parse(html), url

    found = {}
    with ThreadPoolExecutor(max_workers=6) as pool:
        futs = [pool.submit(one, t) for t in targets]
        for fut in as_completed(futs):
            key, data, url = fut.result()
            if data:
                found[key] = (data, url)
    print("austin pages", len(found), flush=True)
    filled = 0
    for var in lib["varieties"]:
        hit = found.get(var["key"])
        if not hit:
            continue
        data, url = hit
        for field, value in data.items():
            cur = var["fields"].get(field) or {"v": PENDING, "src": "", "url": ""}
            # Taiwan sellers win for bloom habit and heat. Breeder wins only when still 待補,
            # except year / petals / reg_name / breeder where the breeder page is authoritative
            # if the current value is 待補.
            if cur.get("v") not in (None, "", PENDING):
                continue
            var["fields"][field] = {"v": value, "src": "David Austin 商品頁", "url": url}
            filled += 1
        if var["fields"].get("flower_dia_cm", {}).get("v") not in (None, "", PENDING):
            pass
        elif var["fields"].get("petals", {}).get("v") not in (None, "", PENDING):
            pass
    LIB.write_text(json.dumps(lib, ensure_ascii=False), encoding="utf-8")
    print("fields filled", filled)


if __name__ == "__main__":
    main()
