<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the name rule of the regions a page style places by a tag of
 * their name (includes/forms/tag_name_rule.php), used by the menu and the
 * ad region forms.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/forms/tag_name_rule.php');

function test_tag_name_rule_on_create()
{
	$message = lang('Please only enter letters, numbers, and dashes for the name.');

	pg_assert_same('', pg_tag_name_error('Main-Menu-2', null), 'letters, numbers and dashes pass');
	pg_assert_same($message, pg_tag_name_error('main_menu', null), 'an underscore is refused');
	pg_assert_same($message, pg_tag_name_error('main menu', null), 'a space is refused');
	pg_assert_same($message, pg_tag_name_error('menü', null), 'a non-ASCII letter is refused');
}

function test_tag_name_rule_on_edit()
{
	$plain = lang('Please only enter letters, numbers, or dashes for the name.');
	$underscore = lang('Please only enter letters, numbers, dashes, and underscores for the name.');

	pg_assert_same('', pg_tag_name_error('top-menu', 'top-menu'), 'a valid name passes');
	pg_assert_same($plain, pg_tag_name_error('top_menu', 'top-menu'), 'no underscore when the stored name had none');
	pg_assert_same('', pg_tag_name_error('top_menu_2', 'top_menu'), 'underscores stay when the stored name had one');
	pg_assert_same($underscore, pg_tag_name_error('top menu', 'top_menu'), 'the underscore rule still refuses a space');
	pg_assert_same($plain, pg_tag_name_error('a.b', ''), 'a missing stored name uses the plain rule');
}

function test_tag_name_rule_used_by_both_forms()
{
	foreach (array('menu_form.php', 'ad_region_form.php') as $file) {
		$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/forms/' . $file);

		pg_assert_contains("includes/forms/tag_name_rule.php", $source, $file . ' loads the rule');
		pg_assert_contains('pg_tag_name_error(', $source, $file . ' applies the rule');
		pg_assert_false(strpos($source, "preg_match('/[^A-Za-z0-9") !== false, $file . ' keeps no copy of the rule');
	}
}
