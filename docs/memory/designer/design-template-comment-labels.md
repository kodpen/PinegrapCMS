---
name: design-template-comment-labels
description: Which comments labels the design templates set (singular nouns; `reply` on the forum topic and every conversation page, tested) and why not the verb key `Reply` (#3453, #4584).
---

## What label does a template give a page's comments?
- A singular noun: the comments code lowercases it into sentences, puts it after a count ("2 replies") and into "Add {var:1}".
- The forum topic page and every template's `conversation` page use `lang('reply')` (Turkish `yanıt`). Other labels: Feedback, Message, Note ([[design-template-forum]]).
- News, Magazine and Forum still had `Message` on `conversation` while its Comments widget says "Write a reply": the count heading read "1 Mesaj" over a reply form (#4553, PR #4584; versions 1.2.1, 1.2.1, 1.3.1). `tests/design_template_comment_labels_test.php` now checks every template's `conversation` label.

## Why not `lang('Reply')`?
- `Reply` is the Reply button's key, a verb in Turkish (`Yanıtla`). As a label it printed "Yanıtla ekle", "1 Yanıtla" (#3453, PR #3556). Keep `Reply` for buttons and links only; `tests/design_template_comment_labels_test.php` refuses it as a label.

## Do installed sites change?
- No. A page keeps its stored `page.comments_label`; only a new install from a template gets the noun. No migration.
