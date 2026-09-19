/**
 * Product lock 2026-09-19 (owner confirmed).
 *
 * ACTIVE guest questionnaire is ONLY:
 *   https://seasideresort.com.tw/booking/pm_front/funnel.html
 *
 * match-v3.1.html / /match/ / /800plus/match-v3.1.html are archived.
 * Do not send guests there. See deploy/www/archive/match/README.txt.
 *
 * Primary CTA label: 「了解是否適合長住／月租會員」
 * After pass: experience-type or monthly-type booking (interim pm_front OK).
 * After fail: soft land, no booking calendar, no Prime /booking/ hotel UI.
 */
(function (root) {
  var url = "https://seasideresort.com.tw/booking/pm_front/funnel.html";
  var PRIMARY_LABEL = "了解是否適合長住／月租會員";
  root.PM_GUEST_FUNNEL_URL = url;
  root.PM_CTA_PRIMARY_LABEL = PRIMARY_LABEL;
  if (typeof module !== "undefined") {
    module.exports = {
      url: url,
      PRIMARY_LABEL: PRIMARY_LABEL,
    };
  }
})(typeof globalThis !== "undefined" ? globalThis : this);
