#!/usr/bin/env python3
"""Guard official homepage guest CTAs → funnel.html."""

from __future__ import annotations

import re
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parent
HTML = (ROOT / "index.html").read_text(encoding="utf-8")
FUNNEL = "https://seasideresort.com.tw/booking/pm_front/funnel.html"


def fail(msg: str) -> None:
    print(f"FAIL: {msg}", file=sys.stderr)
    raise SystemExit(1)


def hrefs(html: str) -> list[str]:
    return re.findall(r"""href=["']([^"']+)["']""", html)


def main() -> None:
    links = hrefs(HTML)

    old_scheme = [
        h
        for h in links
        if re.search(r"/booking/pm_front/?$", h) or h.endswith("/booking/pm_front/index.html")
    ]
    if old_scheme:
        fail(f"old pm_front index still linked: {old_scheme}")

    if "#experience-application" in links:
        fail("primary CTAs still open the leftover on-page form")

    hotel = [h for h in links if re.search(r"https://seasideresort\.com\.tw/booking/?$", h)]
    if hotel:
        fail(f"QloApps /booking/ hotel UI still linked: {hotel}")

    if FUNNEL not in links:
        fail("funnel.html is not a CTA target")

    required = [
        ("入住申請", FUNNEL),
        ("了解問卷｜申請入住", FUNNEL),
        ("了解問卷｜預約參觀", FUNNEL),
        ("了解問卷／預約申請", FUNNEL),
    ]
    for label, url in required:
        if label not in HTML:
            fail(f"missing CTA label: {label}")
        pattern = rf'href="{re.escape(url)}"[^>]*>{re.escape(label)}'
        if not re.search(pattern, HTML):
            fail(f"label {label!r} is not wired to {url}")

    if "pm_member_center_v17.php" not in HTML:
        fail("會員中心 link was removed")
    if "webreg.hwln.mohw.gov.tw" not in HTML:
        fail("hospital booking links were removed")
    if 'href="#experience"' not in HTML:
        fail("in-page 預約體驗 nav was removed")

    js = (ROOT / "pm_funnel_cta.js").read_text(encoding="utf-8")
    if "/booking/pm_front/index.html" not in js:
        fail("overlay JS no longer rewires the old scheme-page path")

    target = (ROOT / "pm_cta_target.js").read_text(encoding="utf-8")
    if FUNNEL not in target:
        fail("pm_cta_target.js is missing the live funnel URL")
    if 'ACTIVE = "live"' not in target:
        fail("ACTIVE default is not live (match-v3.1 is not uploaded)")
    if "/match/" not in target or "match-v3.1.html" not in target:
        fail("canonical B option is not documented")

    print("ok: homepage guest CTAs point at the documented live target")


if __name__ == "__main__":
    main()
