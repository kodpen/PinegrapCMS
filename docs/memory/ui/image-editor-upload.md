---
name: image-editor-upload
description: Large files in the image editor (V8, V8b): video, sound and saved animation sent in parts and resumed, type by bytes, pg_image_editor_upload_limits(), parts in data/temp
module: image_editor
---

## Where is it?
- Part `upload`: `assets/js/pg_image_editor_upload.js` (bundled after `mix`), the model's `UPLOAD` section, labels `pg_image_editor_strings_upload()`, actions `upload_start`, `upload_part`, `upload_status`, `sound_upload`, its stylesheet section, `tests/image_editor_upload_test.php`. Added in V8 of `plans/image-editor-video-steps.md` (#8585).
- Hooks calling `editor.uploadSend(file, kind, head, opts)`: `clipUpload()`, `saveAnimationFile()` (Save to Files), the Add sound box's first button.

## How does a file go?
- Up to one part (config `uploadPart`): one request, the line then the bytes (`pg_image_editor_binary_body()`).
- Larger: `upload_start` {kind, size} → an id (32 hex); `upload_part` {upload, offset} + bytes for each part in order; then the kind's action (`video_upload`, `sound_upload`, `animation`) with `upload: id` and no bytes. `pg_image_editor_upload_take()` hands the action the first 64 KB (the type) and the size; `pg_image_editor_upload_put()` moves the file into Files (rename, never read into memory).
- A part that starts elsewhere than the server's count gets 409 `upload_offset` with `received`: the browser goes on from there, so a part sent twice is written once (counted under `flock`). No answer or a 5xx: the same part after 1, 2, 4, 8, 15 s, or when the browser is online again, six tries (`M.UPLOAD_TRIES`), then "The upload stopped. Pick the same file again…".
- The id is kept in `localStorage` under `M.uploadKey()` (kind, destination, file name, size, time). The same file picked again after a reload asks `upload_status` and goes on from its count ("Going on with {name} from {done}."). An export's Blob has no key: it resumes only while the dialog runs.
- Progress: a toast changed in place; in Save animation "{done} of {size} sent" under the bar.

## What is checked on the server?
- Start: the kind, a size above 0 and not over `max` (413), disk room for twice the size plus 64 MB (507). The destination is checked by the finishing action.
- Each part: the upload is this user's (another's: 403), touched within a day (else 410 `upload_gone` and removed), the offset and length (`pg_image_editor_upload_part_check()`); the first part tells the type by its bytes (`pg_image_editor_upload_format()`), a wrong type removes the upload.
- Finish: complete and of the same kind, else 409 `upload_open`.

## Where are the parts kept?
- `data/temp/image_editor_uploads/<id>.part` and `<id>.json` (user, kind, size): no table. Uploads untouched for a day go at the next start (`pg_image_editor_upload_sweep()`); Clean Up empties `data/temp` too.

## Which bounds apply?
- `pg_image_editor_upload_limits()` reads them all: `part` = one request (`pg_image_editor_animation_limit()`) less room for its line, 16 KB to 8 MB; `max` = the MB setting when above 0, else PHP's `upload_max_filesize`, never below one request. The config's `animationLimit` is `max`, so Save to Files takes files over one request.
- The largest file as a setting (V8b): `image-editor-upload-setting.md`.
- Signed in, parts skip the firewall's global rate limit (`waf_handle_rate_global()`).

## What does a sound from this computer do?
- From this computer, first in Sound from Files: a sound told by its bytes into the open file's or the canvas's folder (`pg_image_editor_upload_place()`, as `video_upload`), then `addSound()` at the playhead. Not shown for a callback save.

## What is left?
- Decoding still reads the whole video into memory (`image-editor-video-decode.md`).
- Not tried in a browser (#8585): the browser's offline switch (drops were scripted), the real file picker, Apache or nginx.
