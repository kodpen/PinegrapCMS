---
name: picture-text-auto-draw-steps
description: Plan picture-text-auto-draw, steps DA1 to DA5: rows and text jobs at save time, the waiting mark, the panel's background draw, the server's draw, the screens' words, close-out
---

## Which steps are there?
- DA1 (M) Rows, jobs and the mark; server only, nothing drawn yet. Done: #10409.
  - A page saved in the visual editor writes the waiting rows of the Design Canvas pictures it shows that have words, for every left-to-right target language, by the page pass's own address rule and one query, no document decoded. The first visit still writes them.
  - The texts' job: beside the page's translate-on-save job (`pg_tr_auto_update()`), the file texts of those pictures; a canvas save of a picture with a row opens one of its own. Same lock and open-job guard; never from a visit, never for a language without "translate on save".
  - The mark: set where a row starts to wait or becomes drawable (a waiting row, a save, a translation written), cleared when the queue's query finds nothing; kept where reading it costs no query.
  - The keeping part of `pg_picture_tr_draw_answer()` becomes a function of its own (the `files` row, the upsert, the page cache), called with a user id or 0.
  - Tests, red first: rows at a page save (words, no words, right to left, another host); the job's texts and guards; the mark's moves; the keep with 0.
- DA2 (M) after DA1: the panel draws in the background. Done: #10428.
  - New `assets/js/picture_auto.js`, standalone. The panel's footer prints its tag only while the mark is set and the user's queue (`pg_picture_tr_queue()`) is not empty.
  - It waits for an idle, visible tab, takes a lock so that one tab draws (`navigator.locks`, else a lease in `localStorage`), loads the Design Canvas bundle and draws the rows one at a time with the queue's drawing, moved out of `picture_queue.js` into a part both load (no copy). It stops when the tab is hidden; a failed row is not tried again in that page view.
  - The Design Canvas after a Save: the same draw for that picture at once.
  - Tests under Node: the lock, the order, the stop, a failed row; the footer's tag. A sandbox run (a unit test cannot reach the bundle's draw): a picture saved, translated and drawn with no press.
- DA3 (M) after DA1, beside DA2: the server draws with nobody there. Done: #10433.
  - `pg_picture_tr_draw_due($budget)` in `translation_job.php` after `pg_tr_jobs_run_due()`, only while the mark is set and `pg_image_draw_can()` passes shapes and text: queue rows of any folder not in the Recycle Bin, canvas documents only, under `pg_image_draw_guarded()` and inside the budget.
  - The language document and the fit in PHP (twins of `M.languageDoc()` and `M.languageFit()`: floor 0.7, `fit` 1 below it) with the server's own letter widths; then `pg_image_draw_plan()`'s verdict on that document. A row it cannot draw whole is left.
  - Kept through DA1's function with `drawn_by` 0. System Status shows the Canvas Drawing row also when the site has a target language.
  - Tests: the twins on fixtures shared with the script; a plain document drawn; a sticker, an effect, a missing font and a photo left; the budget; the mark cleared. A sandbox run with Imagick.
- DA4 (S) after DA2 and DA3: the screens' words. Done: #10448.
  - The Pictures group's note, the queue view's lead and the Languages list's note say that pictures are drawn by themselves, when, and that the buttons do it now.
  - Pictures to the architect: the three places at 1440 and 390, light and one dark, in Turkish.
- DA5 (S) close-out: every line of the plan checked against the code with its PR; `core/picture-text-overview.md` and its files corrected, with a file for the automatic draw; the changed `[NEW]` line named in the PR body; `workflow/user-docs-pages.md`; the plan's two files deleted.

## Which steps run side by side?
- DA2 and DA3 ran side by side once DA1 had landed (scripts and the panel's footer; the job run and `includes/fn/`); both have landed.
