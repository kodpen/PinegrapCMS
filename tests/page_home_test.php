<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the site's one home page (includes/fn/page_home.php): the value
 * a save writes is 'yes' or 'no', never '' (page_home is ENUM('no','yes')),
 * and both writers, Edit Page and the visual designer, take the flag from
 * the other pages when they set it.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/page_home.php');

function test_page_home_value_is_yes_or_no()
{
	pg_assert_same('yes', pg_page_home_value('yes'), 'the switch on');

	foreach (array('' => 'empty', '0' => 'zero', '1' => 'one', 'no' => 'no', 'YES' => 'upper case') as $posted => $label) {
		pg_assert_same('no', pg_page_home_value((string) $posted), $label);
	}

	pg_assert_same('no', pg_page_home_value(null), 'not posted');
	pg_assert_same('no', pg_page_home_value(array('yes')), 'an array');
}

function test_page_home_claim_sql_clears_the_others_with_no()
{
	pg_assert_same(
		"UPDATE page SET page_home = 'no' WHERE page_home = 'yes' AND page_id <> '42'",
		pg_page_home_claim_sql('42abc')
	);
}

function test_page_home_writers_keep_one_home_page()
{
	$edit_page = file_get_contents(PG_FUNCTIONS_DIR . '/edit_page.php');
	pg_assert_true(strpos($edit_page, 'pg_page_home_claim(') !== false, 'Edit Page takes the flag from the other pages');
	pg_assert_true(strpos($edit_page, 'pg_page_home_value(') !== false, 'Edit Page writes yes or no');

	$designer = file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/designer.php');
	pg_assert_true(substr_count($designer, 'pg_page_home_claim(') >= 2, 'the designer page save and the template apply both take the flag');
	pg_assert_true(preg_match("/page_home\\s*=\\s*''/", $designer) === 0, 'the designer never writes an empty page_home');
	pg_assert_true(strpos($designer, "'yes' : ''") === false, 'the designer writes no for a page that is not the home page');
}
