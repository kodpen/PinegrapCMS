---
name: own-menu-dropdown-keydown
description: A right-click menu of a screen's own built on .dropdown-menu: Bootstrap's capture-phase keydown handler throws on it, so its keys are taken on the window; closing on scroll and a long press
---

## Why does an arrow key in my own menu throw "reading 'parentNode'"?
- Bootstrap's dropdown registers its data-api keydown on the document for `.dropdown-menu` with the delegated (capture) phase. ArrowUp, ArrowDown or Escape inside any `.dropdown-menu` makes it look for the toggle that opened the menu; a menu opened by a screen's own script has none, `getOrCreateInstance(undefined)` throws ("Cannot read properties of undefined (reading 'parentNode')" in `dataApiKeydownHandler`) and the key never reaches the menu's own listener (`stopPropagation()` there is too late). Found in PR #584.
- Fix: listen on `window` with capture (`addEventListener('keydown', fn, true)`), act only while the menu is open and the event is inside it, `stopPropagation()` first. Used by the Visual Page Editor home (`assets/js/designer_home.js`).

## What else does such a menu need?
- Focus: from the keyboard on the first entry, from the mouse on the menu itself (`tabindex="-1"`) so the arrows still work; Escape and Tab close it and give the focus back to what opened it.
- Close on a press outside (mousedown, capture), on resize, on window blur and on scroll, but not on a scroll in the first ~300 ms: a page still gliding from the wheel closed the menu the moment it opened.
- Touch: a 550 ms long press opens it; Android also fires `contextmenu`, iOS never does, so a timestamp keeps the two from opening it twice, and the lift that ends the press must not follow the link under it.
