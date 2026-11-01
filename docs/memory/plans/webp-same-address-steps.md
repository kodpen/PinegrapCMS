---
name: webp-same-address-steps
description: Steps W1-W10 of plans/webp-same-address.md: negotiation, switches, encode cap, convert doors, Cloudflare rule, panel thumbnails, Workspace previews, upload cap; claim as [plan:webp-same-address] W<n>
---

## Which steps are there?
- S under 400 product lines, M about 1,000. W1 and W3 touch different files and may run side by side; W2 and W3 both change `includes/image_engine.php`, one after the other.

- W1 (M) Negotiation. Done (#4690).
  - New `includes/image_negotiation.php` (gate `PG_GET_FILE` or `PG_FUNCTIONS_DIR`, as `includes/http_range.php`), pure: `pg_image_accepts($accept, $type)` (q-values; `q=0` refuses; `image/*` and `*/*` never count), `pg_image_behind_proxy($server)`, `pg_image_negotiated_format()`. `tests/image_negotiation_test.php`.
  - `get_file.php`, after the access checks, for jpg/jpeg/jpe/png asked without `f`, not an attachment, ERP or workspace file: the switch through one function on `$router_config_row` (the router read `SELECT *`: no extra query; bit 4 unless question 3 says otherwise); the derivative as webp at the asked width (0 = own); `Vary: Accept` on every answer that could differ; download name `<stem>.webp`; `f=original` skips it. Nothing sets the switch before W2, so W1 alone changes no site.
  - Memory: `platform/image-derivatives.md` (plain-address copies); `page-cache-proxy-rules.md` (pictures and `Vary`).

- W2 (S) Switches and status. After W1 and questions 1 and 3.
  - Two switches in Settings › Features › Image Optimization (texts through `tr_add.php`), the clamp in `pg_image_delivery_settings()`, the default of question 1; a System Status line: on or off, WebP written or not, a CDN in front without confirmation.

- W3 (M) Encode cap and warm-up. `free`.
  - At most N encodes at once for every derivative (lock slots beside `data/cache/img/formats.json`, `busy` when all are taken), so a page of forty new pictures does not start forty encodes on a shared host; a warm-up pass in `job.php` beside the sweep: largest JPEG and PNG first, seconds a tick, a cursor file. Tests on the slots and the cursor.

- W4 (S) Convert doors, downloads, mails. After W1 and question 2.
  - `pg_explorer_handle_webp()` and Edit File's replace mode as question 2 decides; the file window shows the copy's size and saving; File Manager Download and the mail pass (`get_page_content()` with `$email`) ask for `f=original`.

- W5 (S) Cloudflare rule. After W2 and question 5.
  - Cloudflare Tools writes the Cache Rule (phase `http_request_cache_settings`; jpg, jpeg, png; Vary `accept` normalize, media type `image/webp`) when the token may, else shows the steps; a check fetches one picture through the edge with two `Accept` values and sets the confirmation only when each gets its own type.

- W7 (S) Panel thumbnails. After W3 and question 7.
  - 320 in `pg_image_derivative_widths()`; File Manager tiles and list, the product table and the image pickers ask for `?w=320&f=webp`; W3's warm-up makes the 320 copies of the newest files first (Imagick reads a JPEG at a reduced scale, `jpeg:size`).

- W8 (S) Workspace previews. After W7 and question 8.
  - `ws_file_preview()` (`includes/workspace/files.php`) answers from the derivative cache and widths; `data/temp/workspace_previews` and its sweep go; Workspace access checks stay in front.

- W9 (S) Upload size cap. After question 9.
  - "Shrink pictures larger than N px on upload" in the image card (same format, the engine's resize), the default of question 9; the heavy-images widget points to it.

- W10 (S) Close-out.
  - Lasting decisions to `platform/` (the extension rule as a key rule of `platform/README.md`); the three plan files deleted.
