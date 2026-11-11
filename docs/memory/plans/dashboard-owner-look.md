---
name: dashboard-owner-look
description: Plan (owner 2026-11-11, #8672): what the owner asked for on the new dashboard's cards: the notes card as a My Notes shortcut, last online times, the world map without city names; steps DL1-DL3
---

## What is it for?
- The owner looked at the new dashboard on dev (2026-11-11) and asked for changes on three cards. His words: below, #8672, `workflow/decisions-made-panel-5.md`. A further finding of his on a dashboard card becomes a step here.

## Which steps are there?
- DL1 (M) The notes card becomes a My Notes shortcut. Done: PR #8693.
  - Owner: "bu widget notlarıma bir kısayol olabilir miş. en üstünde eklenen not ta yine notlarımdan erişilmesi lazımmış / not ekle daha kısa olsun input gibi tıklayınca genişlesin ve yazacak kadar alan olsun yeni not olarak eklensin. / altında diğer notlar sadece başlık ve tarihleri ile dursun tıklanınca notlarımı sayfasında açsın. bu şekilde güncellensin buraya özel bir not uygun olmamış".
  - Built as `ui/dashboard-v2-notes-card.md` describes.
  - The card: an "Add note" box one line high that grows when clicked; saving adds a note to My Notes (`workspace/workspace-notes.md`) through the notes' own save path and rights. Under it the person's latest notes, title and date only; a row opens that note on the My Notes screen. A locked note shows as the notes list shows it (`workspace/workspace-note-lock-readers.md`).
  - The card's text of before is moved once into My Notes as that person's note and only then cleared from the layout; an empty text makes no note. Data only.
  - The card is offered only to a person who can use My Notes (`ws_notes_ready()` and the notes' right).
  - Tests: the one-time move (not for an empty text, not twice), who is offered the card. Topics: `ui/dashboard-v2-personal-cards.md` corrected; `workspace/workspace-notes.md` names the card's path.
- DL2 (S) Online Engagement shows the last visits when nobody is on the site.
  - Owner: "bu widget ta en azından en son çevrim içi  olma zamanlarını gösterebilirdi."
  - Today one line, "Nobody is on the site right now." (`includes/dashboard/v2/cards/engagement.php`, `pg_dashboard_v2_card_engagement()`).
  - When nobody is on: the line stays as the heading; under it the last visits, each with its time (how long ago, then the clock in the site's time) and its landing page. The read is bounded from the newest ids, never a scan (`visitors/high-volume-counter-rules.md`).
  - Tests: the rows' shape and the bound. Topic: a new `ui/dashboard-v2-engagement-card.md`.
- DL3 (M) The Visitor map draws the world map without city names.
  - Owner: "bu widget ta eğer şehir isimlerini göremiyorsa dünya haritası üzerinde görünmeli".
  - Today a map only when the home country has a region map and a city matches; otherwise "Countries" bars and no map (`ui/dashboard-v2-visitmap-card.md`, `includes/dashboard/v2/cards/visitmap.php`).
  - Without a city match the card draws the world map (`assets/maps/world-countries.svg`) with the visitors' countries marked by their share, tips by `pgDash.chartTips()`; the "Countries" bars stay under it. With no country either, the card's empty text stays.
  - Tests: which map the card names in its three cases (region map, world map, none). Topic `ui/dashboard-v2-visitmap-card.md` corrected.

## What does every step keep?
- No schema. Texts through `lang()`, keys by `php tools/tr_add.php`; the dashboard's bundles rebuilt (`php tools/build_js.php`, `build_css.php`).
- Looked at in the sandbox (`ui/panel-screenshots.md`): wide and phone, light and dark, linked in the PR. The look on dev is the owner's.

## Who holds it, where does it start?
- Coordinators hand the steps out; claim as `[plan:dashboard-owner-look] <step> — <topic>`. The steps do not wait for each other, but DL2 and DL3 both edit `assets/js/pg_dash_cards_visitors.js`: the second starts when the first has landed. The last step to land deletes this file.
