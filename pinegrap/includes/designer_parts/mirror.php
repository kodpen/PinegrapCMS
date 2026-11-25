<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Designer part 'mirror', read by pg_designer_part_files()
 * (includes/fn/output.php).
 *
 * The canvas mirrors: the draft at another width beside the canvas, drawn
 * again when the hand rests. When the mirrors are drawn again
 * (window.PGMirrorSchedule), then the part that fills their frames and lays
 * out "Side by side". Handed to every editor; it runs the first time the
 * View menu's switch "Side by side" is on.
 *
 * Memory: docs/memory/designer/designer-canvas-mirror.md
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
		'assets/js/pg_mirror_schedule.js',
		'assets/js/style_designer_mirror.js',
	),
	'when'    => 'always',
);
