<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Designer part 'recordedit', read by pg_designer_part_files()
 * (includes/fn/output.php).
 *
 * "Edit content here" in the Preview: the switch of the "Preview as" bar,
 * the frames around a collection item's words and pictures, each field's
 * control in place, the foot bar with "Save to the record" and a field
 * changed meanwhile. Loaded the first time a Preview draws a collection
 * item: the pure list of spots (window.PGRecordEditSpots), then the part.
 *
 * Memory: docs/memory/designer/record-edit-in-place-preview.md
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
		'assets/js/pg_record_edit_spots.js',
		'assets/js/style_designer_recordedit.js',
	),
	'when'    => 'always',
);
