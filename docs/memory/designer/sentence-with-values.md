---
name: sentence-with-values
description: Words beside a value (W26): props._sentence draws one translatable sentence, its N-th child at {var:N}; twins, panel row, code view lock, comment stars' label, order view's withdrawal line
---

## What is it?
- A semantic element with `props._sentence` true: `props.text` is one sentence, `{var:N}` marks where its N-th child (the value, an element of its own: a span bound to a token, styled on its own) stands. Owner answer "Words beside a value?" in [[widget-designability-decisions-2]].
- `{var:N}` is the lang() placeholder: `pg_tr_tokens_of()` knows it, so the Translations screen, the engines, the API and the CSV import refuse a translation that drops or adds one ([[content-translation-result-check]]); the translator moves it where the language needs it. The sentence is the node's `text`, translated as any semantic text (format `text`, `pg_tr_node_fields()`).

## How is it drawn?
- `pg_sentence_layout($text, $count)` (`includes/fn/designer.php`) gives the pieces: words, and child places from 1. A child is drawn once, at its first placeholder; a placeholder without a child draws nothing; a child no placeholder names follows after a space. `pg_sentence_html()` writes them on one line, words escaped, each child rendered at indent 0 with its trailing newline cut. The semantic case of `_render_tree_node()` takes this branch after void and white-space tags, before the link/button label rule.
- Editor twins: `_sdIsSentence()`, `_sdSentenceLayout()`; the canvas (`buildNodeEl()`, no virtual label on a link or button sentence) and the exporter (`_sdEmitNode()`, code view ranges kept) use them.
- Without the prop nothing changes: words first, children on their own lines, `{var:1}` printed as written.

## How does the operator build one?
- Properties, Semantic Element section, "Value in the text" (`_sdSentenceRow()`): inside a system widget, on a semantic element with no children (or already a sentence) and unbound text. Picking a value adds a span bound to it (named after the value) and writes `{var:N}` at the end of the words (`_sdSentenceAdd()`); the operator moves it in the Text field. Removing the span leaves its placeholder drawing nothing.
- A sentence is not typed on the canvas (no in-place edit) and is half-locked in the code view (attribute values only, `_sdCodeLockOf()`, text kind `none`): the words and the value places are the panel's.

## Where is it used?
- The comment stars' `aria-label` (`pg_sw_part_comment_stars()`, `_sdPartCommentStars()`): the label's own words `Rated {var:1} out of 5` with `{{__comment_rating}}` in place of `{var:1}` (an attribute holds no element, so the value is the W15 attribute token, [[widget-attr-bindings]]). The output equals the old `__comment_rating_label`; that token stays filled for saved trees.

- The order view's right of withdrawal line (#PR): in the legal documents part (`pg_sw_part_order_legal_documents()`, `_sdPartOrderLegal()`) and the order view starter, three sentences with `__withdrawal_deadline` as the value, one per state under its flag: `withdrawal_deadline_firm` "Last day of the right of withdrawal: {var:1}", `withdrawal_deadline_estimated` the same with the estimate's note, `withdrawal_ended` "The right of withdrawal ended on {var:1}." The words are `pg_legal_withdrawal_text()`'s keys, so the part's output equals the old `__withdrawal_text` line; that token stays filled for saved trees. Helpers `pg_sw_part_sentence()` / `_sdPartSentence()`.

## What is left?
- No canvas preview inside an attribute; no drag of a value within the words (the placeholder is moved as text).

## Tests?
- `tests/designer_sentence_test.php`: layout vectors (server and editor under Node), inline render, nested value, unchanged tree without the prop, translation check.
