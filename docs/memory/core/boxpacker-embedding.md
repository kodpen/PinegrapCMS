---
name: boxpacker-embedding
description: The bundled BoxPacker (includes/boxpacker, 1.x): which code loads it, and the local patch that keeps it quiet on PHP 8.5 (#10323)
---

## Which code loads includes/boxpacker?
- Only the real-time shipping rates in `shipping.php`: when an order's items need containers, it adds each container as a `TestBox` and each item as a `TestItem` (kept flat) and calls `Packer::pack()`; each packed box becomes a package with the box's outer size and `getWeight()`.
- A packing error is caught, written to the Site Log ("Container packer error") with `print_r()` of the packer, and no real-time rate is shown.

## What did it log before the patch?
- On PHP 8.4/8.5, six deprecations when the files were compiled, then ten for every container and seven for every item added (8.2 dynamic properties): 40 lines in the PHP error log for two containers and two items.

## Why a local patch instead of a newer release?
- BoxPacker 2.x and 3.x change the API (typed interfaces, other constructors) and 3.x asks for a newer PHP than 7.1; moving would rewrite the shipping code and could change which container is picked.

## What does the PHP 8.5 patch change?
- `TestBox` and `TestItem` declare their fields as public properties, in the order the constructor sets them, so the `print_r()` dump in the Site Log reads as before.
- `VolumePacker::findBestOrientation()` and `findPossibleOrientations()` take `?OrientatedItem $prevItem` (and `?Item $nextItem`) without the `= null` default: the default sat before required parameters, so PHP never used it, and 8.4 reports the implicit nullable type. Every caller passes all arguments.
- `#[\ReturnTypeWillChange]` on `compare()` of `BoxList`, `ItemList` and `PackedBoxList` (SplHeap); before PHP 8.0 the line is a comment. `compare()` may still return a float (a weight difference); PHP turns it into an integer without a notice.

## What keeps it?
- `tests/boxpacker_library_php85_test.php` packs items the way `shipping.php` does (one box, the weight spread over two boxes, an item too large) in its own PHP process that reports every error, and checks boxes, weights, items and the dumps of a `TestBox` and a `TestItem`. Items of one size differ in weight by whole units, so the result does not depend on how a PHP version breaks heap ties.
- `tests/bundled_library_php85_test.php` reads `includes/boxpacker` for non-canonical casts and implicitly nullable parameters.
- Checked 2026-11-19 on PHP 8.5: 60 random orders over three container sizes pack the same and dump the same `print_r()` length as with the unpatched library. A library update re-applies the patch.
