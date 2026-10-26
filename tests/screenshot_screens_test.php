<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Checks the screen list of tools/screenshots/shoot.js
 * (tools/screenshots/screens.json): every entry names a panel file that
 * exists, names are unique and safe as file names, the optional fields
 * are well formed, placeholders are ids seed.php prints, and the screens kept as they are stay on the list.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function screenshot_test_screens()
{
	$json = file_get_contents(dirname(__DIR__) . '/tools/screenshots/screens.json');
	$data = json_decode($json, true);
	pg_assert_true(is_array($data) && isset($data['screens']) && is_array($data['screens']), 'screens.json parses');
	return is_array($data) && isset($data['screens']) ? $data['screens'] : array();
}

function test_screenshot_screens_point_at_panel_files()
{
	$panel = dirname(__DIR__) . '/pinegrap/';
	$names = array();

	foreach (screenshot_test_screens() as $screen) {
		pg_assert_true(isset($screen['name'], $screen['path']), 'name and path');
		$name = (string) $screen['name'];
		pg_assert_true((bool) preg_match('/^[a-z0-9_-]+$/', $name), 'file-safe name: ' . $name);
		pg_assert_false(isset($names[$name]), 'unique name: ' . $name);
		$names[$name] = true;

		$file = preg_replace('/\?.*$/', '', (string) $screen['path']);
		pg_assert_true(is_file($panel . $file), 'panel file exists: ' . $file);

		if (isset($screen['viewports'])) {
			pg_assert_same(array(), array_diff($screen['viewports'], array('wide', 'tablet', 'phone')), 'viewports are wide, tablet or phone: ' . $name);
		}

		if (isset($screen['click']) && is_array($screen['click'])) {
			pg_assert_same(array(), array_diff(array_keys($screen['click']), array('wide', 'tablet', 'phone')), 'click keyed by viewport: ' . $name);
		}

		if (isset($screen['cookies'])) {
			pg_assert_true(is_array($screen['cookies']) && $screen['cookies'] !== array(), 'cookies is a name => value map: ' . $name);
		}

		if (isset($screen['crop'])) {
			pg_assert_true((bool) preg_match('/^[#.][A-Za-z][\w-]*$/', $screen['crop']), 'crop is one id or class: ' . $name);
		}
	}
}

function test_screenshot_screens_placeholders_are_seeded()
{
	$seed = file_get_contents(dirname(__DIR__) . '/tools/screenshots/seed.php');

	foreach (screenshot_test_screens() as $screen) {
		preg_match_all('/\{([a-z_]+)\}/', (string) $screen['path'], $matches);
		foreach ($matches[1] as $key) {
			pg_assert_contains("'" . $key . "'", $seed, 'seed.php prints ' . $key);
		}
	}
}

// The screens the owner wants kept as they are through the Bootstrap 6 move.
function test_screenshot_screens_keep_owner_list()
{
	$kept = array();
	foreach (screenshot_test_screens() as $screen) {
		if (!empty($screen['kept'])) {
			$kept[] = $screen['name'];
		}
	}

	foreach (array('workspace', 'view_files', 'product_builder', 'edit_user', 'welcome', 'view_product_groups', 'view_offers', 'edit_offer', 'header', 'footer', 'main-menu') as $name) {
		pg_assert_true(in_array($name, $kept, true), 'kept screen listed: ' . $name);
	}
}
