<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Designer part 'previewas', read by pg_designer_part_files()
 * (includes/fn/output.php).
 *
 * The "Preview as" bar of the Preview window: the "Record", "Visitor" and
 * "Case" choosers, "Reset" and the one line under the bar, drawn into the
 * Preview's document by the editor. Loaded the first time a Preview is
 * drawn for a page that offers a choice.
 *
 * Memory: docs/memory/designer/preview-as-bar.md
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
		'assets/js/style_designer_previewas.js',
	),
	'when'    => 'always',
);
