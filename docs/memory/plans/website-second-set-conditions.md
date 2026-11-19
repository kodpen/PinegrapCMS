---
name: website-second-set-conditions
description: The architect's conditions for the eight website plans of poll 82, set before they were written: tables, no second engine, scheduled work, mail, money, personal data, cache, first steps
---

## Which plans, and what already binds them?
- Owner (2026-11-16, agent channel poll 82, message 6434): of the nine features the R&D agent researched he chose eight for a detailed plan: automatic newsletter from new posts, personal appointment link, map and store finder, customer value reports, referral programme, wholesale, event tickets, courses.
- `plans/website-features-rules.md` holds for them unchanged. The lines below were given before the plans were written (architect, 2026-11-16, agent channel message 6460); what each plan adds is in `plans/website-second-set-conditions-per-plan.md`.
- The architect reads the plans' draft PR before it lands. Each plan has "What is left out?" and "Whose look?"; a step is about 1,000 product lines.

## May these plans add tables?
- Not yet. Poll 65's yes covered the tables the nine earlier plans counted (architect, message 5763). The R&D agent asks the owner again, in one question, with the count per plan.
- After a yes each step's columns, types and indexes still come to the architect in the draft PR body before the migration is written.

## What must no plan build a second time?
- Each plan opens with "What exists today and is reused", by file and function. No second mail sender, ledger, calendar, QR generator or PDF library.
- Scheduled work hangs on the general job or on a job script that takes `pg_cron_claim()`. The run is claimed by one UPDATE before any mail leaves, so a job that runs twice sends once (`platform/job-script-claims.md`).
- Mail leaves through the queue or the campaign path, where the bounce gate and consent live (`core/mail-bounces.md`); never a direct `email()`.
- Money passes only through the order path: a product and an order line, amounts in integer minor units rounded with `round()`. A refund takes the granted right back.

## What protects people and running sites?
- A new table that holds a person's data joins the personal data export and `privacy_erase.php`, and names how long it is kept (`core/personal-data-erasure.md`).
- Output that differs by person or group (a wholesale price, a learner's progress) never enters the anonymous page cache; the plan says how (`platform/page-cache-decisions.md`).
- Capacity, stock and the use of a code are taken by one conditional UPDATE, never read and then written.

## Where does a plan start?
- Its first step is server and data only. `workspace.js` and `style_designer.src.js` are held by other open PRs: a step that enters them names the clash.
