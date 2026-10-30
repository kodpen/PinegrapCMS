---
name: voice-text-keyboard
description: Convert to text under a voice message from the keyboard (pg_voice_text.js): where focus goes while it works, after success and after a failure; the progress meter's name
---

## Where does focus go?
- The click hides the button, so when the button held the focus, the role=status line (`tabindex="-1"`) takes it first; otherwise focus fell to `<body>` and the next Tab started at the page top.
- Success: the written-out text (`tabindex="-1"`) takes the focus, so the screen reader reads it; before, the status was cleared and the text appeared silently.
- Failure: the button shows again and takes the focus back.
- Only when the focus is still on the status or was lost to `<body>` (`held()`): focus the person moved elsewhere while it worked stays there.

## How is the meter named?
- The `<progress>` carries `aria-label` from the existing texts: `voice_text_working` from the start and while converting, `voice_text_download` while the model downloads (`ui/progressbar-names.md`).

## How is it pinned?
- `tests/voice_text_test.php` (`test_voice_text_keyboard_focus_and_meter_name`): the Node stub gives `focus()` and `document.activeElement`. No browser run: the sandbox has no Prompt API.
