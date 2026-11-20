---
name: product-feed-google-xml
description: The fourth product feed format, google_xml (PR #10133): Google Merchant Center's RSS 2.0 file from the google_tsv rows, one XML escape helper, streamed like the others, content type, card switch
---

## What is it?
- `google_xml` in `pg_product_feed_formats()` (`includes/fn/product_feeds.php`): Google's product data as RSS 2.0, file `data/feeds/google_xml.xml`, served by `feed.php` as `application/xml; charset=utf-8` (`products.xml`). Added in PR #10133 for price sites and integrators that take a shop's catalogue as XML (plan `market-table-stakes.md`, TS1).
- Followed: Google Merchant Center Help, "RSS 2.0 specification" (support.google.com/merchants/answer/160589) and "Additional image link" (answer/6324370). No field Google does not define.

## Where do its values come from?
- The same rows as `google_tsv`: `pg_product_feed_rows()` gives every non-OpenAI format `pg_product_feed_google_row()`, and `pg_product_feed_columns('google_xml')` is the TSV's column list. No second set of rules: a field rule changed for Google changes both files.
- `tests/product_feeds_test.php` parses the XML and checks each item equals the TSV line field by field.

## How is the file written?
- `<rss version="2.0" xmlns:g="http://base.google.com/ns/1.0">`, one `<channel>` with title and description (the seller name, else "Products") and link (the seller website, else the site address), then one `<item>` per row.
- Every field is a `g:` element in the column order, as Google's example writes `g:title`, `g:link`, `g:description`. A list (`additional_image_link`) is one element per image, as Google asks for a repeated attribute; an empty field is not written.
- Every value goes through `pg_product_feed_xml_text()`: broken UTF-8 replaced, control bytes XML 1.0 cannot carry dropped (tab and line breaks kept), `& < > " '` as entities, so `]]>` is never raw. No CDATA.
- Streamed like JSON Lines: the writer writes the head at open, items per chunk, the foot at close; the work file is the feed and is renamed over the old one (`product-feed-streaming.md`). A failed build writes no foot and keeps the old file.

## Where is it switched on?
- Settings › Ecommerce › Product Feeds: a fourth switch "Google product feed (XML)" with its own address, from `pg_product_feed_format_texts()`. State in `config.product_feeds.formats.google_xml` like the others; no schema change.
- `pg_product_feed_formats_to_build()` names the formats a build writes (switched on, limited to the "Build now" list); a format that is off is not built and loses its file.

## What is still open?
- `GET /store/feeds` lists it as a fourth row (content type included); the endpoint's description in `includes/api/schema.php` names google_xml after google_tsv (#10527).
- Whether Akakçe and Cimri take this file as it is: TS2.
