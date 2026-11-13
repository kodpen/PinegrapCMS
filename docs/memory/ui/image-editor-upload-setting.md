---
name: image-editor-upload-setting
description: The image editor's largest upload as a setting (V8b): the config_text row image_editor_upload_max_mb in MB, 0 keeps upload_max_filesize, 10240 at most, its field on Settings, Features
module: image_editor
---

## What does it bound?
- The `config_text` row `image_editor_upload_max_mb` (V8b, #8635; owner, 2026-11-11, #8587: "A: tek ayar, varsayılan 0."), in MB. Not a `config` column: the row is full (#8637, `data/config-table-no-varchar.md`), so there is no schema step. One bound for an uploaded video or sound and a saved animation, never below one request; the part size does not follow it. 0, an empty value or no row keeps PHP's `upload_max_filesize`, the bound before V8b, so an upgrade changes nothing. It may stand above `upload_max_filesize`: each part is a raw request body, which that ini value does not bound.

## How is it read and saved?
- Read by `pg_image_editor_upload_max_mb()` through `pg_config_text()`, saved by `pg_image_editor_upload_max_mb_save()` through `pg_config_text_save()`, both held by `pg_image_editor_upload_max_mb_clamp()` from 0 to 10240 (`pg_image_editor_upload_max_mb_most()`: 10 GB, above what a tab decodes, while an extra digit is refused).

## Where is it set?
- Field "Largest Video Or Sound In The Design Canvas (MB)" in Settings, Features, Image Optimization, after "Shrink Pictures On Upload Above" (`platform/image-upload-cap.md`), always shown; its help names PHP's own limit. Saved by `features.save.php`; an empty box is 0.

## How was it tried?
- Tests: `test_image_editor_upload_setting()` in `tests/image_editor_upload_test.php` (0, above and below `upload_max_filesize`, under one request, the clamp).

Related: image-editor-upload.md, platform/image-upload-cap.md
