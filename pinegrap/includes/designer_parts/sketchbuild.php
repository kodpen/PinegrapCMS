<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Designer part 'sketchbuild', read by pg_designer_part_files()
 * (includes/fn/output.php).
 *
 * "Build the page" and "Build again" of a sketch page in the Prototype
 * view: the sketch reader and builder (window.PGSketchReader,
 * window.PGSketchBuilder), then the part that asks about the unsure boxes,
 * builds the draft page and keeps the sketch with it. Loaded when the
 * Sketch switch first goes on.
 *
 * Memory: docs/memory/designer/sketch-build.md
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
	exit;
}

return array(
	'sources' => array(
		'assets/js/pg_sketch_reader.js',
		'assets/js/pg_sketch_builder.js',
		'assets/js/style_designer_sketchbuild.js',
	),
	'when'    => 'always',
);
