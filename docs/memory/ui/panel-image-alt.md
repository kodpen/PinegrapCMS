---
name: panel-image-alt
description: Panel pictures a screen reader must not read by file name: link and image picker thumbnails, contacts list and order view photos, the Paraşüt emblem's alt, the liveform warning icon
module: ecommerce, contact_list
---

## Which panel pictures carry an empty alt, and why?
- A lazy `<img>` with no alt is read by its file name: first `loading.gif`, then the real file. Where the text beside it already names the thing, the picture gets `alt=""`:
  - the link picker's file thumbnail (`editor_select_page_or_file.php`): the file name is the button in the next cell;
  - the image picker tile (`editor_select_image.php`): its alt was the file name, which the `.esi-caption` in the same button repeated ("photo.jpg Select photo.jpg");
  - the contacts list photo (`pg_contact_list_row()`, `includes/fn/contact_list.php`): the name is in the row;
  - the order view's Customer Information photo (`view_order.php`): the customer's name is in the card below it;
  - the liveform warning icon (`get_warnings()`, `liveform.class.php`): its alt was the English literal "Warning" before `lang('Warning')` / `lang('Warnings')`, so the heading read "Warning Uyarı:".

## How is the link picker's No Image placeholder named?
- Both svgs carry `role="img" aria-label="No Image"` (translated): a `role="img"` takes no name from its `<text>`, as in the products list (`list-row-names.md`).

## What does the Paraşüt emblem say?
- `pg_order_list_status_html()` (`includes/fn/ecommerce.php`, also sent back by the order quick edit) gives the emblem `alt` the same `$title` as its span's hover title: "An invoice was created for this order in Parasut" or "This order has been exported for upload to parasut.com". The span takes no focus, so the title alone reached only mouse users.

## What was not checked?
- Nothing was rendered in a browser. Test: `tests/panel_image_alt_test.php`.

Related: docs/memory/ui/list-row-names.md, docs/memory/ui/order-view-picker-links.md
