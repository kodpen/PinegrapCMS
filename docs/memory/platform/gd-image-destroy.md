---
name: gd-image-destroy
description: Freeing a GD image: pg_image_destroy() calls imagedestroy() only below PHP 8.0, since it does nothing from 8.0 on and PHP 8.5 deprecates it (#1669)
---

## How is a GD image freed?
- With `pg_image_destroy($image)` (`includes/image_engine.php`), never `imagedestroy()`. Below PHP 8.0 a GD image is a resource that keeps its memory until `imagedestroy()` or the end of the request, so the helper calls it there. From 8.0 on the image is a `GdImage` object freed with its last reference, `imagedestroy()` does nothing, and PHP 8.5 raises E_DEPRECATED for it, so the helper skips the call.
- It sits in the image engine because `get_file.php` loads that file without `functions.php` (derivatives); everything behind `functions.php` and the tests have it too.

## What did it fix, and what keeps it fixed?
- Before #1669 the product called `imagedestroy()` 12 times (`image_engine.php` 7, `includes/fn/contacts.php`, `includes/fn/signature.php`, `manifest_icon.php`): one deprecation notice per image operation in a PHP 8.5 site's error log, and 18 unit tests that ERROR on PHP 8.5, because `tools/test.php` turns a deprecation into an exception. CI runs an older PHP and did not see it.
- The helper's one call carries a `phpcs:ignore` (guarded by `PHP_VERSION_ID`), and `tools/phpcs-baseline.json` holds no `imagedestroyDeprecated` entry, so a new direct call fails CI's PHPCompatibility step (`workflow/static-analysis.md`).
- The vendored dompdf calls `imagedestroy()` itself; the test runner drops deprecations raised inside vendored folders.
- Test: `test_image_destroy_frees_only_below_php_8` (`tests/image_gd_gif_test.php`).

Related: docs/memory/platform/image-processing-engine.md, docs/memory/workflow/static-analysis.md
