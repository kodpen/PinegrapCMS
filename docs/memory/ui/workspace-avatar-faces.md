---
name: workspace-avatar-faces
description: A face from avatar() in the Workspace scripts keeps Bootstrap's .avatar (classList.add, never className =) and is sized by --bs-avatar-size; why a check-in answer's photo once covered the channel
---

## How does a Workspace screen give a face of avatar() its own look?
- `face.classList.add('ws-…-face')` beside `.avatar`, and size it with `--bs-avatar-size` (the `--ws-avatar*` tokens), not `width`/`height`.
- `.avatar > .avatar-img` (Bootstrap 6) is the only rule that holds the photo to the face: `width: 100%; height: 100%; object-fit: cover`. Without `.avatar` the `<img>` draws at the picture's own size.

## What went wrong before? (2026-11-03)
- `workspace_status.js` set `face.className = 'ws-checkin-face'` on a check-in answer. A person whose photo was a large picture (an image-editor canvas, 1080 px) had it drawn at full size over the check-in card and the messages below it, on desktop and phone. Letters lost their tone class too.
- Fixed by adding the class; `.ws-checkin-face` sets `--bs-avatar-size: var(--ws-avatar-sm)`.

## What keeps it?
- `tests/workspace_avatar_face_test.php`: no `workspace*.js` writes `className` over a variable made by `avatar()`, and the check-in face is sized by the variable.
