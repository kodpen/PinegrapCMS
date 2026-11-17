---
name: boxpacker-embedding
description: The bundled BoxPacker (includes/boxpacker, 1.x): which code loads it, and the local patch that keeps it quiet on PHP 8.5
---

## Which code loads includes/boxpacker?
- Only the real-time shipping rates in `shipping.php`: when an order's items need containers, it adds each container as a `TestBox` and each item as a `TestItem` (kept flat) and calls `Packer::pack()`; each packed box becomes a package with the box's outer size and `getWeight()`.
- A packing error is caught and logged with `print_r()` of the packer, and no real-time rate is shown.

## Why a local patch instead of a newer release?
- BoxPacker 2.x and 3.x change the API (typed interfaces, other constructors) and 3.x asks for a newer PHP than 7.1; moving would rewrite the shipping code and could change which container is picked.

## What does the PHP 8.5 patch change?
- `TestBox` and `TestItem` declare their fields as public properties, in the order the constructor sets them, so `print_r()` in the error log reads as before (8.2 dynamic properties).
- `VolumePacker::findBestOrientation()` and `findPossibleOrientations()` take `?OrientatedItem $prevItem` (and `?Item $nextItem`) without the `= null` default: the default sat before required parameters, so PHP never used it, and 8.4 reports the implicit nullable type. Every caller passes all arguments.
- `#[\ReturnTypeWillChange]` on `compare()` of `BoxList`, `ItemList` and `PackedBoxList` (SplHeap); before PHP 8.0 the line is a comment.

## What keeps it?
- `tests/boxpacker_library_php85_test.php` packs items the way `shipping.php` does (one box, the weight spread over two boxes, an item too large) in its own PHP process that reports every error, and checks boxes, weights and items. `tests/bundled_library_php85_test.php` reads `includes/boxpacker` for non-canonical casts and implicitly nullable parameters.
- Checked 2026-11-18: packing results and the length of the logged `print_r()` are those of the unpatched library. A library update re-applies the patch.
