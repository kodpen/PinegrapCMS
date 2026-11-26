---
name: document-reader
description: The designer's document reader (assets/js/pg_document_reader.js): paragraphs with their styles in, sections with kinds, items and unsure marks out; pasted text to paragraphs; rules, test
---

## What does the reader take and give?
- `PGDocumentReader.read(paragraphs)`: each `{text, style, level, lead, rows, src, alt}`. `style` may be a document's own name: `Title`, `Heading 2`, `h3`, `List Paragraph`, `Quote`, `table` (with `rows`, arrays of cell texts), `picture` (with `src`); anything else is a paragraph. `lead` is the bold words the text starts with.
- Answers `{sections, skipped, cut}`: `skipped` the indexes of empty paragraphs, `cut` how many past the 2,000-paragraph limit were not read.
- A section: `id` (`p` + its first paragraph's index), `kind`, `heading`, `items`, `paragraphs` (indexes read), `words`, `long` (text over 300 words, for "Sort it for me"), `unsure`, `reason`, `options`.
- Kinds (`KINDS`): intro, text, cards, questions, figures, testimonial, table, prices, contact, picture, gallery. Labels are the rows' job (`_sdT()`), not the reader's.
- Words around the items (PR #11792): in a section of a list kind (not intro or text) the paragraphs not taken as items stay in `items` as text items `{type, text, lead}`, those before the first item first, the rest after the items, in order. A card past its 80-word limit is one, its `lead` the bold words. A text item's `type` (paragraph, item, quote, heading, table, picture) tells it from an item.
- Items: cards `{title, text}`, questions `{question, answer}`, figures `{value, label}`, testimonial `{quote, name, role}`, table and prices `{rows}`, contact `{type: email|phone|address, value}` (an address line is one item with all its words, its phone or e-mail not given again, PR #11769), picture and gallery `{src, alt}`, intro and text the paragraphs `{type, text, lead}`. The words are never changed: a card's title is its lead, its text what follows after a `:` or dash.
- `PGDocumentReader.paragraphs(text)`: a pasted plain or Markdown text to such paragraphs (`#` and underlined headings, `-`/`1.` items, `>` quotes, pipe tables, `![alt](src)`, `**bold**` leads). In a text with no marked heading, a short line opening a block that the next line does not continue is a heading, blank line under it or not (#11856). A line broken mid-sentence is joined.
- Pure, no DOM, in no bundle: the step that shows the form (FD3) loads it on first use.
- A .docx comes to it as paragraphs from the server, a .md or .txt as text for `paragraphs()`: `document-paragraphs.md`.

## Which rules read a document?
- The section level is the highest heading level after the first heading; such a heading opens a section, a deeper one stays inside it. What comes before the first heading is a section of its own.
- The first heading with up to two plain paragraphs is the intro; what follows them under it is read as another section.
- In a section: a table is a table, or prices when two cells hold a price (a currency mark or TL, USD, EUR); pictures alone a picture or gallery; two or more question-and-answer pairs questions.
- two or more lines of a number and a short label figures; a quotation with a name (a dash line, a short line after it, or `— Name` at its end) a testimonial; two or more items led by bold words, or short subheadings with text, cards; an e-mail, phone (7+ digits) or address (street words, `No:`) in a short section contact; else text.

## When is a section unsure?
- `mixed`: two readings fit nearly equally (within a tenth); a heading naming one of them decides and the section is sure. `words`: the heading names a kind (`WORDS`, English and Turkish, folded by `fold()`) the body does not show. The guess is `kind`, `options` the kinds to ask about.

## Where is it tested?
- `tests/document_reader_test.php` runs it in Node (skipped without Node). Timing (Node 22, 4 cores): a 1,560-paragraph, 38 KB text, `paragraphs()` 1.3 ms and `read()` 6 ms median (PR #11533).
