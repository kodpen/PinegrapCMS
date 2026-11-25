---
name: document-sort
description: "Sort it for me" on the "From a document" rows (FD4): when it shows, what is sent, the proposals and their answers, limits, designer_document.php sort, includes/fn/document_sort.php, tests
module: document_sort
---

## When is "Sort it for me" there?
- In the rows' head of the "From a document" start (`document-start.md`), New design window and the designer's window alike, only while `pg_document_sort_on()`: Pinegrap AI writes on the site (`pg_ai_write_engine() === 'pinegrap'`, "Write with AI" on and Pinegrap AI set up). The texts carry it as `sortOn`; the hosts give `PGDocumentRows.mount()` a `sort()` only then. No bar button (`designer-light-and-simple.md`).
- The line under the rows says it before the first press: "“Sort it for me” sends this text to Pinegrap AI. Nothing is sent before it is pressed."

## What is sent, and what comes back?
- One press posts `sort` and `sections` to `designer_document.php`: the rows kept, each `{id, kind, fits, heading, text, unsure}` (`sortSections()`, `sectionText()`), and `pg_document_sort_request()` makes one `pg_ai_chat()` call (PR #11704).
- Limits of "Write with AI": its daily allowance (`pg_ai_write_usage_take()`), 6 presses a minute a person; 60 sections, 12,000 characters a section, 60,000 together. A passage cut by those gets no shorter text.
- Kept from the answer (`pg_document_sort_proposals()`): a kind only from the section's fits and not its own; a shorter text only for a text or intro over 300 words sent whole, at most four fifths of its words. An answer without `proposals` is "Pinegrap AI did not answer"; `off: true` (Pinegrap AI not set up any more) takes the button away.
- The prompt (`pg_document_sort_messages()`) ends with the shared writer rules and the answer's shape; the sections go as a `pg_ai_data_block()`.

## How are the proposals shown and taken?
- While it works the button reads "Sorting…" and the rows stay usable; a row whose kind or words changed since the press (`sig()`) gets no proposal (`takeProposals()`).
- A proposal is a tinted line under its row, the head's pill "N proposals" in place of "N to check". A kind: "Proposed: Prices instead of Cards", "Accept" (`choose()`, the mark gone) and "Leave". A shorter text: "A shorter text: 96 words in place of 420", "Show"/"Hide" (yours beside the proposed one), "Use the shorter text" (`shorten()`: its paragraphs become the row's), "Keep mine". No button takes all; choosing a kind from the list drops that row's kind proposal.
- Ends: "Nothing to propose", or "Pinegrap AI did not answer" with the reason and "Try again". A field being typed in keeps its focus when the answer redraws the rows.

## Where is it tested, and what did it cost?
- `tests/document_sort_test.php` (input, prompt, proposals, texts, the rows' pure parts in Node); `tests/ai_prompt_rules_test.php` lists the builder.
- Only the part's files grew (loaded on first use of the start): `pg_document_rows.js` 27.1 to 37.3 KB (gzip 7.2 to 9.4), its CSS 4.4 to 5.4 KB. Node 22, 61 rows: `sortSections()` 0.08 ms, `takeProposals()` 1.7 ms; PHP 8.3, 60 sections: input, prompt and proposals 2.1 ms. The New design window works out `sortOn` as it draws the start's pane.
