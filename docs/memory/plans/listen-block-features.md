---
name: listen-block-features
description: Plan listen-block, features L1 to L13: the block and its mark, where the script comes from, what is read and in which language, when the button shows, its controls, the canvas and the Preview
---

## What is the block?
- (L1) A block "Listen" in the palette: a tree of plain nodes with Bootstrap classes, a button with a play icon and its label, and a pause and a stop that show while it speaks. Its root carries the mark `data-pg-listen`. No script, no `onclick` and no style of its own in the block.
- (L2) The script `assets/js/pg_listen.js` is added by the server only to a page whose markup carries the mark, and by the Preview's twin row; the existing test compares the two lists.
- (L3) No server call, no file, no table, no cookie, no setting. A site that never places the block changes in nothing.

## What is read, and in which language?
- (L4) The text of the nearest `article` around the block, else of the page's `main`, else nothing. Left out: navigation, header, footer and aside inside it, forms and their controls, buttons, hidden parts, scripts, and the block itself.
- (L5) Spoken piece by piece, a sentence or at most 200 characters each, in the page's order: a browser stops a long piece by itself.
- (L6) The language is the page's `<html lang>` with everything from `-x-` cut off; a part with its own `lang` is spoken in that one.
- (L7) One pure function picks the pieces and their languages and is tested under Node on fixtures: an article with a menu, a form, a hidden part and a span in another language.

## When does the button show, and what does it do?
- (L8) The block's root is `hidden` in the markup. The script shows it only where the browser has `speechSynthesis` and lists a voice for the page's language, also when the voices arrive late. Where it does not, nothing is seen: never a button that does nothing.
- (L9) "Listen" starts at the top; "Pause" and "Resume"; "Stop"; the end puts the button back. Leaving or hiding the page stops the voice. One block speaks at a time.
- (L10) Real buttons with their names, usable from the keyboard; the pause button's state is told to a screen reader.
- (L11) The labels reach the script in the page's language through `lang()`, as the in-browser summary's do; the Turkish through `tools/tr_add.php`.

## What do the canvas and the Preview show?
- (L12) On the canvas the block is drawn as it will look, visible, and does not speak; its texts in the designer come from `_sdT()`.
- (L13) The Preview behaves as the page does.
