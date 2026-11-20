<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the guide's panel topic (includes/fn/agent_guide_panel.php): the
 * screens come from the panel search box's list under the viewer's gates,
 * a user row is read with the rights validate_user() gives, the topic stays
 * within its bytes for an administrator with every module on, and it says
 * how a change is kept and how the Visual Page Editor is worked.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/agent_guide.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/agent_guide_panel.php');

// The chip gates of a role, every module on, no right beyond the role's.
function agent_guide_panel_test_gate($role, $over = array())
{
	$gate = pg_panel_search_chip_gates(array_merge(array(
		'role'                   => $role,
		'ecommerce_module'       => true,
		'calendars_module'       => true,
		'forms_module'           => true,
		'ads_module'             => true,
		'dynamic_regions_module' => true,
		'not_found_log_ready'    => ($role < 3)
	), $over));

	$gate['content_review'] = $gate['folders'];

	return $gate;
}

// The topic's text for a viewer with $gate.
function agent_guide_panel_test_text($gate, $folder = '/pinegrap/')
{
	$text = '';

	foreach (pg_agent_guide_panel_build(pg_panel_search_screens(), $gate, $folder) as $section) {
		$text .= pg_agent_guide_section_text($section, array('store', 'workspace', 'erp'));
	}

	return $text;
}

function test_agent_guide_panel_administrator_sees_every_screen()
{
	$text = agent_guide_panel_test_text(agent_guide_panel_test_gate(0));

	foreach (pg_panel_search_screens() as $screen) {
		if (substr($screen['url'], 0, 1) !== '#') {
			pg_assert_contains(pg_panel_search_screen_label($screen) . ' (' . $screen['url'] . ')', $text, $screen['url']);
		}
	}

	pg_assert_contains('Create Page (add_page.php)', $text, 'the noun filled in');
	pg_assert_contains('Addresses are under /pinegrap/.', $text);
	pg_assert_contains('Site Settings dialog', $text);
	pg_assert_false(strpos($text, '#keyboard-shortcuts') !== false, 'a dialog of the header is no address');
}

// A screen whose chip the search box leaves out for the viewer is left out
// of the topic too.
function test_agent_guide_panel_follows_the_viewers_gates()
{
	$manager = agent_guide_panel_test_text(agent_guide_panel_test_gate(2));

	pg_assert_contains('Orders (view_orders.php)', $manager);
	pg_assert_false(strpos($manager, 'view_menus.php') !== false, 'menus are for designers');
	pg_assert_false(strpos($manager, 'view_styles.php') !== false, 'page styles are for designers');

	$user = agent_guide_panel_test_text(agent_guide_panel_test_gate(3, array('folder_edit' => true)));

	pg_assert_contains('Pages (view_pages.php)', $user, 'an edit folder opens the pages');
	pg_assert_false(strpos($user, 'add_page.php') !== false, 'no create_pages, no Create Page');
	pg_assert_false(strpos($user, 'view_orders.php') !== false, 'no store right');
	pg_assert_false(strpos($user, 'view_users.php') !== false, 'users are the managers\'');
	pg_assert_false(strpos($user, 'Site Settings') !== false, 'settings are the managers\'');

	$shop = agent_guide_panel_test_text(agent_guide_panel_test_gate(3, array('manage_ecommerce' => true)));

	pg_assert_contains('Store: Orders (view_orders.php)', $shop, 'the store right opens the store');

	$store_off = agent_guide_panel_test_text(agent_guide_panel_test_gate(0, array('ecommerce_module' => false)));

	pg_assert_false(strpos($store_off, 'view_orders.php') !== false, 'store off, no store screens');

	$nobody = agent_guide_panel_test_text(array());

	pg_assert_false(strpos($nobody, '.php)') !== false, 'no viewer, no screen');
	pg_assert_contains('Keeping a change', $nobody, 'the rules stay');
}

function test_agent_guide_panel_user_row_reads_the_rights()
{
	$user = pg_agent_guide_panel_user(array(
		'user_id'                  => '7',
		'user_role'                => '3',
		'user_create_pages'        => '1',
		'user_manage_visitors'     => 'no',
		'user_manage_forms'        => 'yes',
		'user_manage_emails'       => 'no',
		'user_manage_ecommerce'    => 'yes',
		'manage_ecommerce_reports' => '0',
		'user_manage_contacts'     => 'no',
		'user_manage_calendars'    => 'yes'
	));

	pg_assert_same(7, $user['id']);
	pg_assert_same(3, $user['role']);
	pg_assert_true($user['create_pages']);
	pg_assert_false($user['manage_visitors'], '"no" is no right');
	pg_assert_true($user['manage_forms']);
	pg_assert_true($user['manage_ecommerce']);
	pg_assert_false($user['manage_ecommerce_reports']);
	pg_assert_true($user['manage_calendars']);
}

function test_agent_guide_panel_size_and_rules()
{
	$sizes = pg_agent_guide_sizes();

	pg_agent_guide_panel_gate(agent_guide_panel_test_gate(0));

	$text = pg_agent_guide_topic_text(pg_agent_guide_registry(), 'panel', array('store', 'workspace', 'erp'));

	pg_assert_true(strlen($text) <= $sizes['topic'], 'the panel topic is ' . strlen($text) . ' bytes for an administrator');

	foreach (array('screens', 'finding', 'saving', 'designer') as $section) {
		pg_assert_contains('(#' . $section . ')', $text, 'section ' . $section);
	}

	foreach (array('Save button', 'Ctrl+K', 'Assistant box', 'design_proposals_create', 'Never drag') as $needle) {
		pg_assert_contains($needle, $text, 'says ' . $needle);
	}

	pg_assert_true(in_array('panel', pg_agent_guide_listed(pg_agent_guide_registry(), array(), array()), true), 'listed to every caller');
}
