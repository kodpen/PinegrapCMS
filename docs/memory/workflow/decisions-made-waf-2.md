---
name: decisions-made-waf-2
description: Decisions list, firewall part 2 (2026-11-13 on): the false-positive fixes ship with 2026.11.0, Monitor mode does not enforce automatic bans.
---

## Where are the rules and the earlier part?
- `decisions-made.md`; the first firewall part is `decisions-made-waf.md`. The generated `DECISIONS.md` lists every part's entries.

## How do the firewall fixes reach sites on 2026.4.10? (owner, 2026-11-13, #9125)
- With the open version: "2026.11.0 ile gelsin (Önerilen)". Not chosen: "Ara sürüm çıkar", a version in between carrying only the firewall fixes.
- Reason: a site on 2026.4.10 kept refusing its own administrator (the ban mirror enforced a ban past its end, the allowed list did not reach it, the sensitivity moved nothing); `development` had already fixed most of it after that release.
- Until then, on a site whose firewall shuts its operator out, "Automatic Temporary Bans" is switched off instead of the whole firewall (the option's text said so).
- The architect's, not the owner's words: the work is the plan `plans/firewall-false-positives.md`.

## Does Monitor mode enforce automatic bans? (owner, 2026-11-13, #9125)
- No: "Otomatik engel dursun (Önerilen)". Not chosen: "Bugünkü gibi kalsın" (automatic bans enforced in Monitor mode until they end).
- The option he read: "İzleme modunda güvenlik duvarının kendi koyduğu otomatik engeller uygulanmaz; elle yazdığınız engel listesi uygulanmaya devam eder. Yanlış engeli ararken güvenlik duvarını tamamen kapatmak gerekmez."
- Reason: an operator who switched to Monitor to find out what refused them stayed shut out until the ban ended, and had to switch the firewall off.
- A hand-written block entry is enforced in every mode, as before. Until step FP7 of the plan lands the code still enforces both (`waf/waf-kill-switch-and-monitor-mode.md`).
- The architect's, not the owner's words: the ban's row stays and applies again in Block mode; the ban mirror carries no automatic line while the mode is Monitor.
