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
		'workspace_settings.php'   => 'ws_action',
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

		// What the screen draws, and for the Workspace the cards its settings
		// functions draw from includes/workspace/.
		$drawn = $source;

		if ($screen === 'workspace_settings.php') {
			foreach (glob($pinegrap . '/includes/workspace/*.php') as $file) {
				foreach (preg_split('/^function /m', file_get_contents($file)) as $function) {
					if (preg_match('/^ws_[a-z_]*settings_[a-z_]*\(/', $function)) {
						$drawn .= $function;
					}
				}
			}
		}

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
