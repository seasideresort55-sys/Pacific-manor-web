#!/usr/bin/env python3
"""Guard product-lock homepage: funnel-only CTAs, pricing, hidden apply form."""

from __future__ import annotations

import re
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parent
HTML = (ROOT / "index.html").read_text(encoding="utf-8")
FUNNEL = "https://seasideresort.com.tw/booking/pm_front/funnel.html"
PRIMARY = "了解是否適合長住／月租會員"
ADMIN = "https://seasideresort.com.tw/booking/pm_roomboard/admin/"


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

    hotel = [h for h in links if re.search(r"https://seasideresort\.com\.tw/booking/?$", h)]
    if hotel:
        fail(f"QloApps /booking/ hotel UI still linked: {hotel}")

    if any("/match" in h or "match-v3" in h for h in links):
        fail("archived match pages are still linked")

    if FUNNEL not in links:
        fail("funnel.html is not a CTA target")

    if PRIMARY not in HTML:
        fail(f"missing primary CTA label: {PRIMARY}")
    if not re.search(rf'href="{re.escape(FUNNEL)}"[^>]*>\s*{re.escape(PRIMARY)}', HTML):
        fail("primary label is not wired to funnel.html")

    if "首月 NT$18,000" not in HTML or "NT$59,800" not in HTML:
        fail("monthly price lock 18000 then 59800 is missing")
    if "一生僅此一次・海邊住一個月" in HTML:
        fail("short/one-month experience product card is still visible")
    if "體驗是月租會員權益，不是可單賣的停留商品。" not in HTML:
        fail("member-benefit one-liner is missing")

    section = re.search(
        r'<section id="experience-application"[^>]*>',
        HTML,
    )
    if not section:
        fail("experience-application section missing")
    tag = section.group(0)
    if "hidden" not in tag or "display:none" not in tag.replace(" ", ""):
        fail("experience-application primary path is not hidden")

    if ADMIN not in links:
        fail("footer admin URL missing")
    if "pm_member_center_v17.php" not in HTML:
        fail("會員中心 link was removed")

    target = (ROOT / "pm_cta_target.js").read_text(encoding="utf-8")
    if FUNNEL not in target:
        fail("pm_cta_target.js missing locked funnel URL")
    if "canonical" in target or "/match/" in target and "archived" not in target.lower():
        if "var ACTIVE" in target or "TARGETS" in target:
            fail("A/B target constant was not removed")
    if PRIMARY not in target:
        fail("primary label missing from pm_cta_target.js")

    print("ok: product-lock homepage CTAs, pricing, and footer")


if __name__ == "__main__":
    main()
