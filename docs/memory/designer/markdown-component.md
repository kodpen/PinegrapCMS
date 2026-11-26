---
name: markdown-component
description: The Markdown palette component (DO26): the reader pg_markdown_html() and its twin PGMarkdown, what it draws, the safe addresses, the part 'markdown', where the text is edited and translated
module: markdown
---

## What is the Markdown component?
- A Bootstrap palette item "Markdown" (`COMPONENTS.markdown`, componentType `markdown`, icon `bi-markdown`); its one prop `markdown` holds the text, edited in a 12-line box under Options. A new one lands with a sample: a heading, a line of inline marks, a list and a table.
- On the site `_render_component_html()` draws it with `pg_markdown_component_html()` (`includes/fn/markdown.php`): a `<div>` with the class, inline style and attributes, the drawn text inside, the whole after the indent (a code block keeps its lines). An empty text is `<div></div>`.
- Its text is translatable: `pg_tr_node_fields()` lists `markdown` as plain text, so a translator gets the Markdown source.

## Which reader draws it?
- `pg_markdown_html($text)` on the site and `window.PGMarkdown.html(text)` (`assets/js/pg_markdown.js`) in the browser, the same rules line for line. `tests/markdown_test.php` runs the twin in Node on 27 texts (hostile ones included) and asserts the same markup; a rule changes in both files.
- No reader existed before: element notes (`props._notes`) are shown as escaped plain text in the Notes tab and the note window, and still are. Their hint "Markdown is supported" is unchanged.
- The canvas loads the twin as the part `markdown` (`includes/designer_parts/markdown.php`, `always`) the first time a Markdown element is drawn; until it has run the element is an empty div, then the canvas redraws (`_sdMarkdownMarkup()`).

## What does it draw?
- Blocks: `#` to `######` headings, paragraphs (two spaces or a backslash at a line's end break it), `-`/`*`/`+` and `1.` lists (nested by indenting, a first number kept as `start`), `>` quotes (`blockquote.blockquote`), ``` and `~~~` code (`language-x` class from the info word), rules, tables.
- A table is a header row with a pipe, a row of dashes with as many cells (`:` for alignment: `text-start`, `text-center`, `text-end`), then rows up to a blank line; drawn as `div.table-responsive > table.table`, `th scope="col"`, a short row filled with empty cells, `\|` a pipe inside a cell.
- Inline: `**`/`__` bold, `*`/`_` italic (an underscore inside a word stays), `~~` struck, `` `code` ``, `[words](address "title")`, `![alt](address "title")` as `img.img-fluid` with `loading="lazy"`, `<https://…>` and `<mailto:…>`, backslash escapes. Lists are tight: an item never holds `<p>`.

## How is it kept safe?
- Every piece of the author's text is escaped (`& < > " '`) before marks are added; code, escapes, links and pictures are put aside as `\x01n\x02` first. Control characters `\x00`-`\x08`, `\x0B`, `\x0C`, `\x0E`-`\x1F`, `\x7F` are dropped on the way in.
- `pg_markdown_safe_url()` / `PGMarkdown.safeUrl()`: spaces and control characters inside an address go first (`java\tscript:`), then a scheme must be http, https, mailto or tel for a link, http or https for a picture; no scheme (a page or `#anchor`) passes; a colon before the first `/ ? #` naming no scheme is refused. A refused link keeps its words, a refused picture its alt text.
- An entity in an address stays text (`&amp;#106;avascript:`), which the browser reads as a page name.

## What was measured?
- PR #11797: the reader is 610 PHP lines and 276 JS lines in their own files; `style_designer.src.js` grew by 43 lines (the component, its markup helper, the Options box, the palette card and drawing). Built: `style_designer.min.js` 3,113,836 to 3,115,234 bytes (+1,398); the new part bundle `style_designer_markdown.min.js` is 7,150 bytes, fetched only when a Markdown element is drawn.
