---
name: image-optimize-messages
description: What Optimize and Resize say when they leave a picture alone, per reason (pg_optimize_failure_message()); a .jpg that is no picture is unreadable, not a download
---

## Where is the sentence chosen?
- `optimize_image()` (`optimize_image.php`, used by the File Manager's Optimize and Resize, Edit Files and `optimize.php`) passes the report's `reason` to `pg_optimize_failure_message($reason, $name)` (`includes/fn/image.php`, beside `pg_optimize_unsupported_message()`), writes the sentence to the activity log and answers it.
- One sentence per reason, each naming the file: `unsupported` ("could not be read as a picture … not the type its name says"), `animated`, `orientation_unreadable`, `color_profile`, `too_large_to_process`, `no_library` (no file name), `missing`, `failed` (the image library could not process it), `write_failed`. Any other reason (`no_<format>`, `no_color`, a new one) gets "Sorry, {file} could not be optimized, so it was left alone."
- Before (#9022, PR #9090): every reason without a branch, `unsupported` and `failed` among them, said "Sorry, we could not download the optimized image from the image optimization service ({file})". The picture is processed on this server; nothing is downloaded. Prepare WebP already had a fitting answer.

## Where do the reasons come from?
- `pg_process_image_file()` (`includes/image_engine.php`, `platform/image-processing-engine.md`): `unsupported` when `getimagesize()` cannot read the file or its type is not one the engine takes.

Related: tests/optimize_image_types_test.php
