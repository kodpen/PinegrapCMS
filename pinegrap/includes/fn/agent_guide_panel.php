<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The guide's panel topic (includes/fn/agent_guide.php), for an AI agent
 * working in the person's signed-in browser: the screens this viewer may
 * open, by English label and address, from the list the panel search box
 * offers (pg_panel_search_screens() in includes/fn/panel_screens.php) under the
 * same gates (pg_panel_search_viewer_gates()); how a change is kept; how the
 * Visual Page Editor is worked.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/panel_screens.php');

function pg_agent_guide_panel_sections() {

	$folder = (defined('PATH') && defined('SOFTWARE_DIRECTORY')) ? PATH . SOFTWARE_DIRECTORY . '/' : '';

	return pg_agent_guide_panel_build(pg_panel_search_screens(), pg_agent_guide_panel_gate(), $folder);

}

// The viewer's chip gates, read once per request. $gate sets them instead:
// the tests give a viewer this way.
function pg_agent_guide_panel_gate($gate = null) {

	static $current = null;

	if ($gate !== null) {

		$current = $gate;

	}

	if ($current === null) {

		$user = pg_agent_guide_panel_viewer();

		$current = ($user === null) ? array() : pg_panel_search_viewer_gates($user);

	}

	return $current;

}

// The person the guide is read for, in validate_user()'s shape, or null: the
// owner of the API or MCP connection, else the panel's signed-in user.
function pg_agent_guide_panel_viewer() {

	$id = 0;

	if (function_exists('api_current_app')) {

		$app = api_current_app();

		if (is_array($app) && isset($app['owner']['id'])) {

			$id = (int)$app['owner']['id'];

		}

	}

	if (($id <= 0) && defined('USER_ID')) {

		$id = (int)USER_ID;

	}

	if ($id <= 0) {

		return null;

	}

	$result = db_q("SELECT * FROM user WHERE user_id = ?" . pg_user_enabled_sql() . " LIMIT 1", array((string)$id));

	$row = $result ? mysqli_fetch_assoc($result) : null;

	return is_array($row) ? pg_agent_guide_panel_user($row) : null;

}

// A user row as the rights validate_user() gives the search box's gates:
// the yes/no columns and the integer ones as booleans.
function pg_agent_guide_panel_user($row) {

	$yes = function ($column) use ($row) {

		return isset($row[$column]) && ($row[$column] === 'yes');

	};

	return array(
		'id'                       => (int)$row['user_id'],
		'role'                     => (int)$row['user_role'],
		'create_pages'             => !empty($row['user_create_pages']),
		'manage_visitors'          => $yes('user_manage_visitors'),
		'manage_forms'             => $yes('user_manage_forms'),
		'manage_emails'            => $yes('user_manage_emails'),
		'manage_ecommerce'         => $yes('user_manage_ecommerce'),
		'manage_ecommerce_reports' => !empty($row['manage_ecommerce_reports']),
		'manage_contacts'          => $yes('user_manage_contacts'),
		'manage_calendars'         => $yes('user_manage_calendars')
	);

}

// The topic's sections for a viewer with the chip gates $gate, over the
// search box's $screens; $folder is the software folder's address ('' when
// not known). A screen whose gate is not the viewer's is left out, as the
// search box leaves out its chip.
function pg_agent_guide_panel_build($screens, $gate, $folder) {

	$groups = array();

	foreach ($screens as $screen) {

		if (empty($gate[$screen['gate']]) || (substr($screen['url'], 0, 1) === '#')) {

			continue;

		}

		$groups[$screen['group']][] = pg_panel_search_screen_label($screen) . ' (' . $screen['url'] . ')';

	}

	$screen_lines = array(($folder === '')
		? 'Addresses are under the software folder. A screen not listed here answers Access denied: do not open it.'
		: 'Addresses are under ' . $folder . '. A screen not listed here answers Access denied: do not open it.');

	foreach ($groups as $group => $labels) {

		$screen_lines[] = $group . ': ' . implode(', ', $labels) . '.';

	}

	$find_lines = array('Ctrl+K opens the panel search: type a screen\'s or a record\'s name (a page, a product, a contact) and open the result.');

	if (!empty($gate['manage'])) {

		$find_lines[] = 'The gear in the header opens the Site Settings dialog; its search box finds a setting by name. Other site tools: System tools (system_tools.php).';

	}

	if (!empty($gate['help'])) {

		$find_lines[] = 'The ? key lists the keyboard shortcuts.';

	}

	return array(

		array(
			'id'      => 'screens',
			'heading' => 'Screens this person may open',
			'lines'   => $screen_lines
		),

		array(
			'id'      => 'finding',
			'heading' => 'Finding a screen or a record',
			'lines'   => $find_lines
		),

		array(
			'id'      => 'saving',
			'heading' => 'Keeping a change',
			'lines'   => array(
				'A change is kept only by the screen\'s Save button; leaving the screen first drops it.',
				'Everything a screen does is in the toolbar under its heading.',
				'Tell the person what a save will change and wait for their yes.'
			)
		),

		array(
			'id'      => 'designer',
			'heading' => 'Visual Page Editor',
			'lines'   => array(
				'Change a page with the editor\'s Assistant box, or with the tools: {tool:design.pages.get}, then {tool:design.proposals.create}.',
				'Never drag or drop on the canvas.'
			)
		)

	);

}
