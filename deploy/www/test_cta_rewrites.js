const assert = require("assert");
const target = require("./pm_cta_target.js");
const { FUNNEL, shouldRewrite } = require("./pm_funnel_cta.js");

assert.strictEqual(target.url, "https://seasideresort.com.tw/booking/pm_front/funnel.html");
assert.strictEqual(target.PRIMARY_LABEL, "了解是否適合長住／月租會員");
assert.strictEqual(FUNNEL, target.url);
assert.ok(shouldRewrite("https://seasideresort.com.tw/booking/pm_front/"));
assert.ok(shouldRewrite("#experience-application"));
assert.ok(shouldRewrite("https://seasideresort.com.tw/match/"));
assert.ok(!shouldRewrite(FUNNEL));
assert.ok(
  !shouldRewrite("https://seasideresort.com.tw/booking/pm_roomboard/pm_member_center_v17.php"),
);
assert.ok(!shouldRewrite("https://seasideresort.com.tw/booking/pm_roomboard/admin/"));
assert.ok(!shouldRewrite("https://seasideresort.com.tw/booking/"));
console.log("ok: locked funnel target + rewrite rules");
