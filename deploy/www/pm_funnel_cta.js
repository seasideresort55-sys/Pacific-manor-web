/**
 * Optional overlay if you cannot overwrite public_html/index.html.
 * Upload with pm_cta_target.js and add before </body>:
 *   <script src="pm_cta_target.js"></script>
 *   <script src="pm_funnel_cta.js"></script>
 *
 * Rewrites leftover guest entry links to the locked funnel URL.
 * Does not touch 會員中心, hospital 預約門診, footer admin, or /booking/ hotel UI.
 */
(function () {
  function targetUrl() {
    if (typeof PM_GUEST_FUNNEL_URL === "string" && PM_GUEST_FUNNEL_URL) {
      return PM_GUEST_FUNNEL_URL;
    }
    try {
      return require("./pm_cta_target.js").url;
    } catch (e) {
      return "https://seasideresort.com.tw/booking/pm_front/funnel.html";
    }
  }

  function shouldRewrite(href) {
    if (!href) return false;
    if (href === "#experience-application") return true;
    try {
      var url = new URL(href, "https://seasideresort.com.tw/");
      if (url.origin !== "https://seasideresort.com.tw") return false;
      var path = url.pathname.replace(/\/+$/, "") || "/";
      if (path.indexOf("/match") === 0 || path.indexOf("/800plus/match") === 0) {
        return true;
      }
      return path === "/booking/pm_front" || path === "/booking/pm_front/index.html";
    } catch (e) {
      return false;
    }
  }

  function apply(root) {
    var funnel = targetUrl();
    var scope = root || document;
    var nodes = scope.querySelectorAll("a[href], a[data-pm-guest-cta]");
    Array.prototype.forEach.call(nodes, function (el) {
      var marked = el.hasAttribute("data-pm-guest-cta");
      var href = el.getAttribute("href") || "";
      if (!marked && !shouldRewrite(href)) return;
      el.setAttribute("href", funnel);
      el.setAttribute("target", "_blank");
      el.setAttribute("rel", "noopener noreferrer");
    });
  }

  if (typeof document !== "undefined") {
    if (document.readyState === "loading") {
      document.addEventListener("DOMContentLoaded", function () {
        apply(document);
      });
    } else {
      apply(document);
    }
  }

  if (typeof module !== "undefined") {
    module.exports = {
      get FUNNEL() {
        return targetUrl();
      },
      shouldRewrite: shouldRewrite,
      apply: apply,
    };
  }
})();
