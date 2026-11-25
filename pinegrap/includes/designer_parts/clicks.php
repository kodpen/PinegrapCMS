<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Designer part 'clicks', read by pg_designer_part_files()
 * (includes/fn/output.php).
 *
 * The "Clicks" layer: badges and tenth lines over the canvas and its
 * panel, from the visitors' counts; handed only with sdDesign.clicks (who
 * may open Visitors).
 *
 * Memory: docs/memory/visitors/page-clicks-designer-layer.md
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
		'assets/js/style_designer_clicks.js',
	),
	'when'    => 'clicks',
);
