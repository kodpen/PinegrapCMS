---
name: connections-hub
description: Plan (owner 2026-11-17): one list in code of every outside service and one Connections screen drawn from it, the redraw of Connected accounts; features H1 to H13, steps CN1 to CN7
priority: 4
---

## What is asked?
- Owner (2026-11-17, agent channel message 7359): "claude ai de bağdaştırıcılar gibi seçenekler var bunlar oldukça güzel ama şu anda github vs. bağlamalarda ayarlarda karışık durumda sonuç olarak hem onları hemde yazılımdaki ayarlar sayfasını güncellemek gerekecek."
- Owner (2026-11-17, typed to the architect, message 7562; `workflow/decisions-made-product-69.md`): "entegrasyonlar ile ilgili olumlu düşünüyorum … bugün engel olmayanları 4/10 öncelikle onaylarız."
- The study behind it: `integrations-census.md`, the screens of today in `integrations-census-screens.md`, how other products lay connections out in `integrations-census-settings.md`.

## What is wrong today?
- 79 outside services are in the code and no screen lists them (`integrations-census-today.md`).
- A Connect button for an outside account is on at least ten screens, and the application it needs is set up on another one.
- GitHub is set up in five places, AI keys in six, mail in four; "Connections" names four things.
- Nothing shows in one look what is connected and what stopped working.

## What is decided about the way? (architect, 2026-11-17, agent channel message 7524)
- Not a second home for any field: one registry in code, one screen drawn from it; a row's drawer shows state, use and the last error and opens the setting's one place.
- Where: the System tools group "Connections", as the redraw of the Connected accounts screen, not a list inside the Site Settings window. The Connected accounts step of `panel-old-screens.md` moves here, so the screen is drawn once.
- No table: a state that cannot be read from what is stored is left out and named.
- The public figure is the registry's count, kept by a test, never typed.
- Each merge of scattered entries that moves a setting is asked of the owner one by one; the first steps move nothing.
- A new screen: drawn first at 1440, 1024 and 390 with the longest names, approved by the owner before any screen step.

## What is built, and by which steps?
- The features: `connections-hub-features.md`. CN1 to CN7, their order and what every step keeps: `connections-hub-steps.md`.
- Claim as `[plan:connections-hub] <step> — <topic>`.

## What waits for the release of 2026.11.0?
- Nothing: no step of this plan needs an account, a demo site or another repository. CN1 and CN2 go now; the screen steps wait only for the owner's yes to CN2's sheet.
- Connect with one click through a broker is not this plan: it waits for the owner's decision on Google's verification (`reverse-oauth-leftovers.md`).

## Whose look?
- Every screen step: the architect's, the screen beside CN2's sheet (agent channel message 2645).
- The owner's: CN2's sheet before CN3; each merge of CN6 as its own question; the wording of the public figure when the registry can count.

## What is left out?
- A second home for a field, a copy of Application Access, a table of states or a history of them.
- A call to an outside service while the list is drawn.
- A catalogue of add-ons to install (`addon-hook-system.md`).
- The broker (above).
