---
name: own-menu-dropdown-keydown
description: A right-click menu of a screen's own built on .menu: Bootstrap's capture-phase keydown handler acts on it, so its keys are taken on the window; closing on scroll and a long press
---

## Why does my own menu take its keys on the window?
- Bootstrap's Menu registers its data-api keydown on the document for `[data-bs-toggle="menu"]` and for every `.menu`, in the delegated (capture) phase. Arrow keys, Home/End and Escape inside any `.menu` make it look for the toggle that opened the menu: an open instance, else a `[data-bs-toggle="menu"]` beside the menu or in its parent. A menu opened by a screen's own script has no toggle of its own, so Bootstrap either finds none or works on a stranger's toggle, and it reaches the key before the menu's own listener (`stopPropagation()` there is too late).
- Under Bootstrap 5 the same handler threw on such a `.dropdown-menu` ("Cannot read properties of undefined (reading 'parentNode')" in `dataApiKeydownHandler`) and the key never arrived. Found in PR #584.
- Fix: listen on `window` with capture (`addEventListener('keydown', fn, true)`), act only while the menu is open and the event is inside it, `stopPropagation()` first. Used by the Visual Page Editor home (`assets/js/designer_home.js`, `#sdh-menu.menu.sdh-menu` in `includes/designer_screen.php`, items `.menu-item`, separators `.menu-divider`).

## What else does such a menu need?
- Focus: from the keyboard on the first entry, from the mouse on the menu itself (`tabindex="-1"`) so the arrows still work; Escape and Tab close it and give the focus back to what opened it.
- Close on a press outside (mousedown, capture), on resize, on window blur and on scroll, but not on a scroll in the first ~300 ms: a page still gliding from the wheel closed the menu the moment it opened.
- Touch: a 550 ms long press opens it; Android also fires `contextmenu`, iOS never does, so a timestamp keeps the two from opening it twice, and the lift that ends the press must not follow the link under it.
