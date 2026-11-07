---
name: design-template-sportsclub-design
description: Design of plans/design-template-sportsclub.md: shipped features it reuses for squads, fixtures, registrations, membership and kit, what it does not build, its look and words
---

## Which existing features does it reuse? (no new schema, module or behaviour)
- Teams are fixed data drawn as cards and one page each (a club's teams change once a season): sport, age group or league as words, training days and times as words, the coach, the squad and fixtures links filtered on the team.
- Players are a collection (the Gym's Trainers pattern, [[collection-fields]]): team and position pick lists, shirt number, a `photo` image kind, a short profile, office field `featured`; the list `squad` filters `?team=` ([[collection-lists]]). Youth players are never named or pictured in the sample: the youth teams' squads are left to the club.
- Matches are a collection (the Music template's Shows pattern): team, competition and home or away pick lists, opponent, a date kind written from today on opening (`$match_on()`), kick-off time as words, venue, score as words after the match ("2–1"), an outcome pick list (win, draw, loss), a match report as long text. `fixtures` lists the matches from today on, `results` those before today ([[collection-lists]]); both filter `?team=`.
- An academy registration is a request, never an enrolment: a record of the Academy Registrations form (age group by `?team=` through `_cf.url_param`, the child's name and birth date, the parent's name, phone, medical notes, consent linking `privacy`), office-only status, messages on the record page's Comments widget, `submitter_only` view, My Registrations, the staff list. A trial request is a smaller form of the same kind (`?team=`, `?position=`).
- Club events are a collection (the School's Events pattern): a kind pick list, date as words, place, summary, details.
- A sponsorship enquiry is a request record (package by `?package=`), staff list only; sponsors themselves are fixed data by tier.
- With the shop: membership products (`membership_days` renew the buyer's membership; a `members` folder with access `membership` holds the members' area), season tickets (`shippable => false`, as the Events template's tickets) and kit (shirts, scarf, training top, shippable). Only shop pages carry `requires => ecommerce`; shared parts and record pages never link them.
- Club News is the standard blog; the newsletter page adds to its contact group.
- Not built (new behaviour): league tables computed from results, live scores, player statistics, ticket seat maps, match-day ticket sales per fixture, team selection or availability polls, payments of academy fees.

## Look and names
- Prefix `psp-` for classes (`psp-brand`, `psp-display`, `psp-eyebrow`, `psp-hero`, `psp-lift`, `psp-score`, `psp-crest`), `sp-` for field ids. Two fonts: Barlow Condensed headings, Inter body. Look `bold`, palette `forest`, order 99, icon `bi-trophy`. No `opacity-*` on text ([[design-template-text-contrast]]). No fixed date, year or month in the sample: matches are dated from the day the template opens; the club's founding and honours read as words ("Founded by railway workers", "League champions three times").
- Turkish: "kulüp" for the club, "takım" for a team, "kadro" for a squad, "oyuncu" for a player, "fikstür" for fixtures, "maç" for a match, "sonuç" for a result, "altyapı" for the youth academy, "seçme" for a trial, "kombine bilet" for a season ticket, "forma" for kit, "üyelik" for a membership, "sponsor", "Kulüp Haberleri" for Club News; check every shared key in tr.json before reuse.

Related: docs/memory/plans/design-template-sportsclub.md
