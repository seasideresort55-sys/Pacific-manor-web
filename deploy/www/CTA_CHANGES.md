# Official homepage CTA rewires

Guest long-stay / experience entry uses one constant (`PM_GUEST_FUNNEL_URL` in `pm_cta_target.js`, mirrored inline in `index.html`).

## Target A/B (product has not picked)

| Key | URL | Status |
| --- | --- | --- |
| `live` **(ACTIVE default)** | `https://seasideresort.com.tw/booking/pm_front/funnel.html` | Already on the host |
| `canonical` | `https://seasideresort.com.tw/match/` (or `/800plus/match-v3.1.html`) | `match-v3.1.html`「800+｜找到適合我的退休生活」— **not uploaded** (404) |

Do not merge as “funnel.html forever.” Flip `ACTIVE` to `canonical` after B is uploaded. Until then, A is the only working guest path (avoids 404 and the old short-stay scheme page).

After a questionnaire **pass**: experience-type or monthly-type booking (interim `pm_front` OK).  
After a **fail**: soft land, no booking calendar, no Prime `/booking/` hotel UI.

Old scheme page `https://seasideresort.com.tw/booking/pm_front/` and QloApps `/booking/` are **not** guest entry points.

## Strings / URLs changed

| Location | Before (label → URL) | After |
| --- | --- | --- |
| Header top action | 入住申請 → `/booking/pm_front/` | 入住申請 → active target (`funnel.html`) |
| FAQ #5 | 查看體驗價格／預約體驗 → `/booking/pm_front/` | 了解問卷／預約申請 → active target |
| `#experience` | 預約體驗｜開始申請 → `#experience-application` | 了解問卷｜申請入住 → active target |
| `#experience` visit card | 預約參觀｜填寫表單 → `#experience-application` | 了解問卷｜預約參觀 → active target |
| `#longstay` plans | *(no apply button)* | 了解問卷｜申請入住 → active target |
| Hidden stay-experience button | 申請住宿體驗 *(opens dead form)* | 了解問卷｜申請入住 → active target |
| `#experience-application` leftover form | 填寫住宿體驗申請 *(unconnected)* | Notice + 了解問卷｜申請入住 → active target |

Unchanged on purpose:

- 會員中心 → `pm_member_center_v17.php`
- Nav 預約體驗 → `#experience` (in-page section)
- Hospital 預約門診
- Phone / LINE / maps

Marked anchors use `data-pm-guest-cta` so a later A/B flip is one constant.
