---
name: decisions-made-product-53
description: Decisions made, product part 53: a team device gets the person's own plans scope; the Team devices ceiling gains four read scopes; M7 native push is moved forward with its one column
---

## Does a team device session get `workspace:personal_plans` without the operator ticking it? (owner, 2026-11-15, channel message 4105)
- Asked by Pinegrap Mobile as issue #9605: without the scope "My calendar" on a phone answers 403 unless the operator ticks "My plans" on the device application.
- Offered "now", "after the running plans" and "no, the operator ticks it", the owner chose: "Evet, şimdi yapılsın".
- So: `api_device_scopes()` adds `workspace:personal_plans` the way it adds `workspace:mail_send`, only within the person's own rights. A running site has it on the next request. As built: `api/api-device-sessions.md`.

## Does the Team devices switch's ceiling gain read scopes for bookings, files, comments and commissions? (owner, 2026-11-15, channel message 4105)
- Asked as issue #9606: the switch gave six scopes (#1640, #2612), so a phone lacked these four unless an operator added each one in Application Access.
- Offered "all four, now", "after the running plans" and "no, the operator adds them", he wrote: "Evet ve api kapıları da olsun."
- So: `api_device_switch_ceiling()` gains `bookings:read`, `files:read`, `comments:read` and `commissions:read`; no write scope. Each stays capped by the person's own right (`api_owner_scopes()`). A running site takes them when the switch is next saved while on. As built: `api/api-team-devices-switch.md`.
- The architect's reading of "api kapıları da olsun", told to him in the window: the routes these scopes open exist already (`GET /bookings`, `/files`, `/comments`, `/commissions` and their single rows), so none is added. A route found missing is a new request.

## Is M7, native push, moved ahead and in which shape? (owner, 2026-11-15, in the architect's window)
- Asked whether M7 (issue #9607) waits for the running plans as he set on 2026-11-14, he chose: "Öne al, 4. soruyu şimdi konuşalım".
- Shown the questions on the broker and the column, he wrote instead: "issue olarak açılan planları şimdi anladım […] soylediği şeyler benim sözümle açıldı bilgine iş olara konları da dağıtalım".
- The architect's reading, told to him in the window: the shape #9607 hands over stands as his request, its one column included (`push_subscriptions.provider`, a step of the open version); a broker endpoint in that table, not the plan's first table of raw tokens. Steps: `plans/mobile-api-shelved.md` (M7a, M7b).
- Not decided, and not needed by the site step: the broker's quota sizes and whether a site without a licence may use it. They are the broker's settings, his when it is built.

## Who opens the mobile issues, and on whose word? (owner, 2026-11-15, in the architect's window)
- The session that builds Pinegrap Mobile for a team member, on the owner's word (his sentence above). They are given out as work; what an issue marks as open for the owner is still asked of him or left out of the step.

## Why were these two taken before the running plans?
- On 2026-11-14 he placed what is left of the mobile API after the running plans (`decisions-made-product-52.md`). These two are not steps of that plan: each changes one function and adds no route, and he chose "now" for both.
