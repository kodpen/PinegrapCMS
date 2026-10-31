<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The dashboard and the Users list leave out modules switched off in Site
 * Settings (CALENDARS, FORMS, ECOMMERCE), and the greeting's feature lines
 * link only to screens the reader can open.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/user_list.php');

function dashboard_module_gates_source($path)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $path);
}

// The condition of the nearest four-space `if (` before $needle, provided the
// block has not closed between the two; '' when the needle is not inside one.
function dashboard_module_gates_block_condition($source, $needle)
{
	$at = strpos($source, $needle);
	pg_assert_true($at !== false, $needle . ' found');
	$before = substr($source, 0, (int) $at);
	$if = strrpos($before, "\n    if (");

	if ($if === false || strpos(substr($before, $if), "\n    }") !== false) {
		return '';
	}

	return substr($before, $if, strpos($before, "{", $if) - $if);
}

function test_dashboard_calendars_card_needs_the_module()
{
	$welcome = dashboard_module_gates_source('welcome.php');
	pg_assert_true(preg_match("/\\\$widget_registry\[20\] = array\(.*?'show'  => \(\(CALENDARS === true\) && \(validate_calendars_access\(\\\$user, true\) != false\)\)\);/s", $welcome) === 1, 'widget 20 shows only with Calendars on');
}

function test_dashboard_activity_forms_needs_the_module()
{
	$widget = dashboard_module_gates_source('includes/dashboard/widgets/widget_7.php');
	pg_assert_true(strpos($widget, "if ((FORMS === true) && (((\$user['role'] < 3) || (\$user['manage_forms'] == true)))) {\n        \$form_where") !== false, 'Activity Forms kind needs Forms on');
}

function test_dashboard_brief_forms_signal_needs_the_module()
{
	$brief = dashboard_module_gates_source('includes/dashboard/brief.php');
	pg_assert_true(strpos(dashboard_module_gates_block_condition($brief, '$greeting_forms = db_item('), 'FORMS === true') !== false, 'forms signal needs Forms on');
}

function test_dashboard_brief_feature_links_follow_their_screens()
{
	$brief = dashboard_module_gates_source('includes/dashboard/brief.php');
	$commerce = "(ECOMMERCE === true) && ((\$user['role'] < 3) || (\$user['manage_ecommerce'] == true))";
	$folders = "(\$user['role'] < 3) || no_acl_check(\$user['id'])";
	$gates = array(
		'add_product.php' => $commerce,
		'add_offer.php' => $commerce,
		'view_product_groups.php' => $commerce,
		'view_folders.php' => $folders,
		'view_pages.php' => $folders,
		'view_comments.php' => $folders,
		'settings_security.php#pgset-device' => "\$user['role'] <= 2",
	);

	foreach ($gates as $href => $condition) {
		$gate = dashboard_module_gates_block_condition($brief, "\$greeting_ask('" . $href . "'");
		pg_assert_true(strpos($gate, $condition) !== false, $href . ' line is gated like its screen');
	}

	// The one line with no link stays ungated, so the pool is never empty.
	pg_assert_same('', dashboard_module_gates_block_condition($brief, "h(lang('You can start a deep database check from the system status widget whenever you like.'))"), 'database check line has no gate');
}

function test_user_list_calendar_column_needs_the_module()
{
	$list = dashboard_module_gates_source('includes/fn/user_list.php');
	pg_assert_true(strpos($list, "if (CALENDARS === true) {\n            \$columns['manage_calendars']") !== false, 'Calendar Manager column needs Calendars on');
}

function test_user_list_filters_follow_the_modules()
{
	$on = pg_user_list_filters(2, array('calendars' => true, 'forms' => true, 'ecommerce' => true));
	pg_assert_true(isset($on['my_calendar_managers'], $on['my_submitted_forms_managers'], $on['my_commerce_managers']));

	$off = pg_user_list_filters(2, array('calendars' => false, 'forms' => false, 'ecommerce' => false));
	pg_assert_false(isset($off['my_calendar_managers']));
	pg_assert_false(isset($off['my_submitted_forms_managers']));
	pg_assert_false(isset($off['my_commerce_managers']));
	pg_assert_true(isset($off['my_contact_managers']), 'other filters stay');

	// A switched-off module's filter name is still accepted from the address.
	pg_assert_same('my_calendar_managers', pg_user_list_filter('my_calendar_managers'));

	// Without the constants (CLI) every module counts as on.
	pg_assert_true(isset(pg_user_list_filters(2)['my_commerce_managers']));
}
