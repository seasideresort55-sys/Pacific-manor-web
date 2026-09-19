const assert = require("assert");
const target = require("./pm_cta_target.js");
const { FUNNEL, shouldRewrite } = require("./pm_funnel_cta.js");

assert.strictEqual(target.ACTIVE, "live");
assert.strictEqual(target.url, "https://seasideresort.com.tw/booking/pm_front/funnel.html");
assert.strictEqual(target.TARGETS.canonical, "https://seasideresort.com.tw/match/");
assert.strictEqual(FUNNEL, target.url);
assert.ok(shouldRewrite("https://seasideresort.com.tw/booking/pm_front/"));
assert.ok(shouldRewrite("https://seasideresort.com.tw/booking/pm_front"));
assert.ok(shouldRewrite("/booking/pm_front/"));
assert.ok(shouldRewrite("#experience-application"));
assert.ok(!shouldRewrite(FUNNEL));
assert.ok(
  !shouldRewrite("https://seasideresort.com.tw/booking/pm_roomboard/pm_member_center_v17.php"),
);
assert.ok(!shouldRewrite("https://seasideresort.com.tw/booking/"));
assert.ok(!shouldRewrite("https://webreg.hwln.mohw.gov.tw/OINetReg.WebRwd/"));
console.log("ok: overlay rewrite rules + A/B constant");
