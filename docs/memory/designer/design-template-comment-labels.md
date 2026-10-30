---
name: design-template-comment-labels
description: Which comments labels the design templates set (singular nouns; `reply` on the forum topic and every conversation page) and why not the verb key `Reply` (#3453).
---

## What label does a template give a page's comments?
- A singular noun: the comments code lowercases it into sentences, puts it after a count ("2 replies") and into "Add {var:1}".
- The forum topic page and every template's `conversation` page use `lang('reply')` (Turkish `yanıt`). Other labels: Feedback, Message, Note ([[design-template-forum]]).

## Why not `lang('Reply')`?
- `Reply` is the Reply button's key, a verb in Turkish (`Yanıtla`). As a label it printed "Yanıtla ekle", "1 Yanıtla" (#3453, PR #3556). Keep `Reply` for buttons and links only; `tests/design_template_comment_labels_test.php` refuses it as a label.

## Do installed sites change?
- No. A page keeps its stored `page.comments_label`; only a new install from a template gets the noun. No migration.
