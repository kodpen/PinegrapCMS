<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Private Label branding is a card of Site Settings › Appearance: drawn and
 * saved for administrators only, the same fields, the same config row and
 * data/config.php writer as the screen it came from, and private_label.php
 * sends old links to it.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!defined('PG_SETTINGS_MENU')) {
	define('PG_SETTINGS_MENU', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/settings/registry.php');

function settings_private_label_test_source($file)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
}

function settings_private_label_test_category($key)
{
	$source = settings_private_label_test_source('includes/settings/registry.php');
	$start = strpos($source, "\n        '" . $key . "' => array(");
	$end = strpos($source, "\n        ),\n", $start);

	return substr($source, $start, $end - $start);
}

function test_settings_private_label_is_an_appearance_section_for_administrators()
{
	$appearance = settings_private_label_test_category('appearance');
	$registry = settings_private_label_test_source('includes/settings/registry.php');

	pg_assert_true(strpos($appearance, "'pgset-editor'") < strpos($appearance, "'pgset-private-label'"), 'after the Rich-text Editor');
	pg_assert_contains("'pgset-private-label' => pg_settings_keywords(", $appearance, 'with its search words');
	pg_assert_contains("unset(\$categories['appearance']['sections']['pgset-private-label'], \$categories['appearance']['keywords']['pgset-private-label']);", $registry, 'left out of the list for anyone else');
	pg_assert_contains("if (!defined('USER_ROLE') || ((int) USER_ROLE !== 0)) {", $registry, 'the administrator\'s alone');
}

function test_settings_private_label_screen_is_no_longer_a_category_screen()
{
	pg_assert_same(array('languages'), array_keys(pg_settings_category_screens(array('role' => 0))), 'the menu lists the section instead of the screen');
}

function test_settings_private_label_card_is_drawn_for_the_administrator()
{
	$cards = settings_private_label_test_source('includes/settings/appearance.php');

	pg_assert_contains("if ((int) \$user['role'] === 0) {", $cards, 'only for an administrator');
	pg_assert_contains('id="pgset-private-label"', $cards, 'the card');
	pg_assert_contains('<input type="hidden" name="private_label_card" value="1"', $cards, 'a marker the save reads');
	pg_assert_contains('data-bs-target="#private_label_fields"', $cards, 'the switch opens the fields');

	foreach (array('private_label', 'logo_url', 'control_panel_stylesheet_url') as $field) {
		pg_assert_contains('name="' . $field . '"', $cards, $field . ' posted');
	}

	// The five footer link pairs, drawn in a loop.
	pg_assert_contains('name="footer_link_\' . $private_label_link . \'_label"', $cards, 'the footer link labels');
	pg_assert_contains('name="footer_link_\' . $private_label_link . \'_url"', $cards, 'the footer link addresses');
	pg_assert_contains('$private_label_link <= 5', $cards, 'all five');
}

function test_settings_private_label_save_follows_the_same_gate()
{
	$save = settings_private_label_test_source('includes/settings/appearance.save.php');

	pg_assert_contains("\$private_label_save = ((int) \$user['role'] === 0) && (post_value('private_label_card') !== null);", $save, 'administrator and the card posted');
	pg_assert_contains('UPDATE config SET private_label = ?', $save, 'the config row');
	pg_assert_contains('pg_private_label_config_content(', $save, 'data/config.php through the same writer');
	pg_assert_contains('pg_write_config_file(', $save, 'written through a temp file');

	$refuse = strpos($save, 'pg_request_unexpected_lists(');
	pg_assert_true(($refuse !== false) && ($refuse < strpos($save, 'db("UPDATE config')), 'a field posted as a list refuses the save before anything is written');
}

function test_settings_private_label_old_screen_sends_to_the_card()
{
	$screen = settings_private_label_test_source('private_label.php');

	pg_assert_contains("validate_area_access(\$user, 'administrator');", $screen, 'still the administrator\'s door');
	pg_assert_contains("pg_settings_url('appearance') . '#pgset-private-label'", $screen, 'to the card');
	pg_assert_same(false, strpos($screen, 'UPDATE config'), 'it writes nothing any more');
}

function test_settings_private_label_has_no_menu_case_of_its_own()
{
	$output = settings_private_label_test_source('includes/fn/output.php');

	pg_assert_true(strpos($output, "case 'private_label.php':") === false, 'the address only redirects, so output_menu() never sees it');
}
