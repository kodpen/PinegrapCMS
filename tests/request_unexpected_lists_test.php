<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for pg_request_unexpected_lists() (includes/fn/core.php): the posted
 * fields that carry a list where their form draws one value, or a deeper list
 * than it draws. The settings saves, SMTP Settings, Private Label,
 * Application Access, Marketplaces and the Workspace settings refuse such a
 * request rather than write "Array" or an empty value over a setting.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// A field that holds one value is named when it arrives as a list, empty or
// not; strings, numbers and null fit.
function test_request_unexpected_lists_single_values()
{
	pg_assert_same(array(), pg_request_unexpected_lists(array('hostname' => 'example.com', 'debug' => '', 'port' => 25, 'x' => null)), 'single values fit');
	pg_assert_same(array('hostname', 'debug'), pg_request_unexpected_lists(array('hostname' => array('a'), 'title' => 'x', 'debug' => array())), 'lists named in request order');
	pg_assert_same(array(), pg_request_unexpected_lists('hostname'), 'a request that is not a list names nothing');
}

// A field drawn as a list fits as deep as the form draws it and no deeper.
function test_request_unexpected_lists_levels()
{
	$lists = array('job_dispatch_job' => 1, 'languages' => 2, 'erp_fx_currencies' => 1);

	pg_assert_same(array(), pg_request_unexpected_lists(array(
		'job_dispatch_job'  => array('auto_backup' => 'on', 'push_job' => 'on'),
		'languages'         => array('de' => array('label' => 'Deutsch', 'enabled' => '1')),
		'erp_fx_currencies' => array('USD', 'EUR'),
	), $lists), 'lists as drawn fit');

	pg_assert_same(array('job_dispatch_job'), pg_request_unexpected_lists(array('job_dispatch_job' => array('auto_backup' => array('on'))), $lists), 'one level too deep');
	pg_assert_same(array('languages'), pg_request_unexpected_lists(array('languages' => array('de' => array('label' => array('x')))), $lists), 'two levels too deep');
	pg_assert_same(array('erp_fx_currencies'), pg_request_unexpected_lists(array('erp_fx_currencies' => array(array('USD'))), $lists), 'a list inside a list of codes');
	pg_assert_same(array(), pg_request_unexpected_lists(array('languages' => array('de' => '1'), 'job_dispatch_job' => 'on'), $lists), 'a single value fits a list field');
}

// Every field a settings card draws as a list is named under its category's
// 'lists' in the registry. One that is not makes every save of that category
// refused, because its own field reads as a list where none is expected.
function test_settings_list_fields_are_registered()
{
	$settings = dirname(__DIR__) . '/pinegrap/includes/settings';
	$registry = file_get_contents($settings . '/registry.php');

	$registered = array();

	preg_match_all("/'lists'\s*=>\s*array\((.*?)\)/s", $registry, $blocks);

	foreach ($blocks[1] as $block) {
		preg_match_all("/'([a-z][a-z0-9_]*)'\s*=>\s*[1-9]/", $block, $names);

		foreach ($names[1] as $name) {
			$registered[$name] = true;
		}
	}

	// The page path's decoy field, accepted for every category.
	pg_assert_contains("array('fake_user' => 1)", $registry, 'the decoy field is accepted');
	$registered['fake_user'] = true;

	pg_assert_true(count($registered) > 1, 'the registry names its list fields');

	foreach (glob($settings . '/*.php') as $file) {
		preg_match_all("/(?:name=\"|= ')([a-z][a-z0-9_]*)\[/", file_get_contents($file), $drawn);

		foreach (array_unique($drawn[1]) as $name) {
			pg_assert_true(isset($registered[$name]), basename($file) . ' draws ' . $name . '[...] and the registry names it under lists');
		}
	}
}

// Application Access, Marketplaces and the Workspace settings refuse a list
// before the screen reads its action, and name every field their forms draw
// as a list. A drawn list the call leaves out makes every save of that form
// refused, so a new one has to be added there too.
function test_request_unexpected_lists_screens()
{
	$pinegrap = dirname(__DIR__) . '/pinegrap';

	// screen => the field its action is read from
	$screens = array(
		'api_settings.php'         => 'api_action',
		'marketplace_settings.php' => 'mp_action',
	);

	foreach ($screens as $screen => $action) {
		$source = file_get_contents($pinegrap . '/' . $screen);

		pg_assert_true(preg_match('/pg_request_unexpected_lists\(\$_POST, array\((.*?)\)\);/s', $source, $call, PREG_OFFSET_CAPTURE) === 1, $screen . ' checks the request for lists');

		$token = strpos($source, 'validate_token_field();');
		$read = strpos($source, "\$_POST['" . $action . "']");

		pg_assert_true(($token !== false) && ($token < $call[0][1]), $screen . ' checks after the token');
		pg_assert_true(($read !== false) && ($call[0][1] < $read), $screen . ' checks before ' . $action . ' is read');

		preg_match_all("/'([a-z][a-z0-9_]*)'\s*=>\s*[1-9]/", $call[1][0], $named);
		$named = array_flip($named[1]);

		$drawn = $source;

		preg_match_all('/name="([a-z][a-z0-9_]*)\[/', $drawn, $fields);
		// The helpers that add the brackets themselves: the weekday toggles
		// and the permission rows.
		preg_match_all("/(?:ws_settings_days_html|api_settings_permission_rows)\('([a-z][a-z0-9_]*)/", $drawn, $helpers);

		$fields = array_unique(array_merge($fields[1], $helpers[1]));

		pg_assert_true(count($fields) > 0, $screen . ' draws list fields');

		foreach ($fields as $name) {
			pg_assert_true(isset($named[$name]), $screen . ' draws ' . $name . '[...] and its check names it');
		}
	}
}

// The bracketed field names a source draws: name="x[", name=\"x[ inside
// JavaScript, and liveform's 'name' => 'x[.
function request_unexpected_lists_test_drawn($source)
{
	preg_match_all('/(?:name=\\\\?["\']|\'name\'\s*=>\s*\')([a-z][a-z0-9_]*)\[/', $source, $fields);

	return array_values(array_unique($fields[1]));
}

// Edit Page, Edit File, Edit Theme File and the product screens refuse a
// field posted as a list after the token and before the save reads the
// first posted field. Before, trim(), escape() or mb_strrpos() of such a
// field stopped the save with an error page.
function test_request_unexpected_lists_record_screens()
{
	$pinegrap = dirname(__DIR__) . '/pinegrap';

	// screen => array(the check, what the save reads first after it)
	$screens = array(
		'edit_page.php'       => array('pg_request_unexpected_lists($_POST);', "\$name = trim(\$_POST['name']);"),
		'edit_file.php'       => array('pg_request_unexpected_lists($_POST);', "\$name = prepare_file_name(\$_POST['name']);"),
		'edit_theme_file.php' => array('pg_request_unexpected_lists($_POST);', '$liveform->add_fields_to_session();'),
		'add_product.php'     => array('pg_request_unexpected_lists($_POST, pg_pb_list_fields());', "if (trim(\$_POST['name']) === '')"),
		'edit_product.php'    => array('pg_request_unexpected_lists($_POST, pg_pb_list_fields());', "\$_POST['merchant_push']"),
	);

	foreach ($screens as $screen => $rule) {
		$source = (string) file_get_contents($pinegrap . '/' . $screen);
		$call = strpos($source, $rule[0]);
		$token = strpos($source, 'validate_token_field();');
		$read = strpos($source, $rule[1]);

		pg_assert_true($call !== false, $screen . ' checks the request for lists');
		pg_assert_true(($token !== false) && ($token < $call), $screen . ' checks after the token');
		pg_assert_true(($read !== false) && ($call < $read), $screen . ' checks before the save reads ' . $rule[1]);
		pg_assert_contains("'Sorry, the value you entered for {var:1} is not valid.'", substr($source, (int) $call, 600), $screen . ' names the fields with the settings screens\' words');
	}

	// The page, file and theme file forms draw no list: one drawn later is
	// refused on every save until the check names it.
	$plain = array('edit_page.php', 'includes/forms/page_form.php', 'includes/fn/page_type_pane.php', 'seo.php', 'seo_structure.php', 'edit_file.php', 'edit_theme_file.php', 'includes/forms/theme_file_form.php', 'includes/forms/design_file_form.php');

	foreach ($plain as $file) {
		pg_assert_same(array(), request_unexpected_lists_test_drawn((string) file_get_contents($pinegrap . '/' . $file)), $file . ' draws no list field');
	}

	// Every list the product form draws, the image picker's included, is
	// named in pg_pb_list_fields(), and every name there is drawn.
	require_once($pinegrap . '/product_builder.php');
	$named = pg_pb_list_fields();
	$drawn = array();

	foreach (array('product_builder.php', 'add_product.php', 'edit_product.php', 'includes/fn/product_bins.php') as $file) {
		$drawn = array_merge($drawn, request_unexpected_lists_test_drawn((string) file_get_contents($pinegrap . '/' . $file)));
	}

	$js = (string) file_get_contents($pinegrap . '/assets/js/backend.src.js');
	pg_assert_contains('name="selected_images[]"', $js, 'the image picker adds picked images as a list');
	$drawn = array_values(array_unique(array_merge($drawn, array('selected_images'))));
	sort($drawn);

	foreach ($drawn as $name) {
		pg_assert_same(1, $named[$name] ?? null, 'the product form draws ' . $name . '[...] one level deep and pg_pb_list_fields() names it');
	}

	$names = array_keys($named);
	sort($names);
	pg_assert_same($drawn, $names, 'pg_pb_list_fields() names only fields the product form draws');

	// The lists as the form posts them fit; a single value posted as one,
	// or a list inside a list, is named.
	pg_assert_same(array(), pg_request_unexpected_lists(array('name' => 'SKU-1', 'allowed_zones' => array('1', '2'), 'product_bin' => array(0 => 'A-03-2'), 'stock_location' => array(3 => '5')), $named), 'as drawn');
	pg_assert_same(array('code', 'catalog_group_ids'), pg_request_unexpected_lists(array('code' => array('x'), 'catalog_group_ids' => array(array('1'))), $named), 'a list where one value is drawn, and one level too deep');

	// An id sent as a list opens no product; (int) read it as product 1.
	pg_assert_contains("\$product_id = is_scalar(\$_REQUEST['id'] ?? null) ? (int) \$_REQUEST['id'] : 0;", (string) file_get_contents($pinegrap . '/edit_product.php'), 'edit_product.php reads one id');
}
