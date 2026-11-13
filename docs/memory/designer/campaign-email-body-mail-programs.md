---
name: campaign-email-body-mail-programs
description: MA4 (2026-11-13): what an HTML e-mail body must be for Gmail, Outlook and Apple Mail: width, tables, inline styles, head style, pictures, the 102 KB clip, the parts it keeps
---

## Which width and layout?
- Content 600 px wide, centred, fluid down to a phone (320 to 414 px). A 600 px frame fits every reading pane and the Gmail app.
- Classic Outlook for Windows draws with Word: it ignores `max-width`, flex, grid, floats, `position` and most margins. The frame and every row of columns are `role="presentation"` tables with `width` attributes; a column is a cell.
- Columns stack on a phone through a media query in a head `<style>`: Gmail, Apple Mail, iOS and Android read it; where it is dropped the two columns stay side by side, still readable at 600 px.

## Which styles hold?
- Every look a block needs is inline on its element: colour, background colour, font size and line height, padding, border, text alignment.
- One head `<style>` only for what cannot be inline: the phone query, dark mode (`@media (prefers-color-scheme: dark)` with the `color-scheme` and `supported-color-schemes` meta) and link resets. Gmail keeps a head style up to about 16 KB and drops all of it on one error, so it stays small and plain.
- No external stylesheet, no CSS variable without its value written out (Outlook and Gmail ignore `var()`), no background picture without a colour behind it, no web font without the system stack after it.

## What about pictures?
- Absolute https addresses (the e-mail render already makes them), `width` and `height` attributes, `max-width:100%` and `height:auto` inline, an `alt` text that reads in place of the picture.
- JPEG, PNG or GIF: Gmail and Outlook drop SVG, and WebP is not shown everywhere. An icon is a picture, not a font or an inline SVG.
- Many programs hold pictures back until the reader asks, so the message must read without them: text in text, not in a banner.

## How long, and which parts?
- The HTML stays under 102 KB: Gmail cuts a longer message behind "View entire message" and the unsubscribe footer goes with it. A full page render with its markup easily passes that.
- A preheader (a short hidden first text) gives the line the inbox shows beside the subject; without it the inbox shows the first words, often the web version link.
- The plain text part is made from the HTML at send (`convert_html_to_text()`), so the HTML's reading order is the text's order.

## What must every campaign body keep?
- The web version link, the organization's address and, for a commercial campaign, the preferences and unsubscribe link (today's fixed footer, `add_email_campaign.php`), beside the List-Unsubscribe headers `email()` adds.
- Merge fields as `^^field^^` and the `<reference_code>` and `<email_address_id>` tags the senders fill.
- No scripts, forms, iframes, video or audio (a picture that links to it), no `<button>` (a link drawn as a button), no `javascript:` link. Links absolute; on-site links take the tracking code.

Related: [[campaign-email-body-today]], [[campaign-email-body-way]]
