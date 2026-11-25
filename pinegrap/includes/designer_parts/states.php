<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Designer part 'states', read by pg_designer_part_files()
 * (includes/fn/output.php).
 *
 * The Style tab's states (Hover, Focus, Pressed): the panel under a state,
 * its rule in User Styles and the state on the canvas. The state rule model
 * (window.PGOptionStates), then the part.
 *
 * Memory: docs/memory/designer/designer-options-states.md
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
		'assets/js/pg_option_states.js',
		'assets/js/style_designer_states.js',
	),
	'when'    => 'always',
);
