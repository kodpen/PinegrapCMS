<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Designer part 'overrides', read by pg_designer_part_files()
 * (includes/fn/output.php).
 *
 * "Can change per place": an element's open fields and the list of a
 * shared component's open fields in its settings. The per-place model of a
 * shared component (window.PGComponentOverrides), then the part.
 *
 * Memory: docs/memory/designer/component-overrides-fields.md
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
		'assets/js/pg_component_overrides.js',
		'assets/js/style_designer_overrides.js',
	),
	'when'    => 'always',
);
