<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Designer part 'versions', read by pg_designer_part_files()
 * (includes/fn/output.php).
 *
 * A live page's new version, the door and the tab: "Save as a new
 * version" or "Open the new version" from a live tab, the draft's tab
 * opened, and "Discard this version". Handed where the Publish sheet's
 * check is: full designer access, drafts ready, not a component alone.
 *
 * Memory: docs/memory/designer/designer-new-version-tab.md
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
		'assets/js/style_designer_versions.js',
	),
	'when'    => 'publishcheck',
);
