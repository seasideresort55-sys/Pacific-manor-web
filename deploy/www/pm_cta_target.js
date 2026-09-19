/**
 * Guest long-stay / experience CTA target.
 *
 * Product has two questionnaire candidates. Do not treat this as a final
 * merge until they pick one.
 *
 * A LIVE (default):  /booking/pm_front/funnel.html
 *     Already on the official host. Long-stay intent funnel.
 * B CANONICAL (not uploaded): match-v3.1.html
 *     Title:「800+｜找到適合我的退休生活」
 *     Suggested paths once uploaded: /match/ or /800plus/match-v3.1.html
 *
 * Flip ACTIVE to "canonical" after B is on the host. Until then, A avoids
 * sending guests to a 404 or the old short-stay scheme page.
 *
 * After a pass: experience-type or monthly-type booking (interim pm_front OK).
 * After a fail: soft land, no booking calendar, no Prime /booking/ hotel UI.
 */
(function (root) {
  var TARGETS = {
    live: "https://seasideresort.com.tw/booking/pm_front/funnel.html",
    canonical: "https://seasideresort.com.tw/match/",
  };
  var ACTIVE = "live";
  var url = TARGETS[ACTIVE] || TARGETS.live;
  root.PM_CTA_TARGETS = TARGETS;
  root.PM_CTA_ACTIVE = ACTIVE;
  root.PM_GUEST_FUNNEL_URL = url;
  if (typeof module !== "undefined") {
    module.exports = {
      TARGETS: TARGETS,
      ACTIVE: ACTIVE,
      url: url,
    };
  }
})(typeof globalThis !== "undefined" ? globalThis : this);
