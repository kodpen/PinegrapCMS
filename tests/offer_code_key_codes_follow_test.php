<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests that a saved offer's key codes follow a change of its code: the save
 * moves them when no other offer keeps the old code, the editor warns while
 * the code is being changed, and All Key Codes names a key code whose offer
 * code matches no offer.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function offer_code_follow_source($file)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
}

function test_key_code_with_no_offer_says_so()
{
	if (!defined('DATE_FORMAT')) {
		define('DATE_FORMAT', 'day_month');
	}

	$today = '2026-10-31';
	// The list reads the offer through a LEFT JOIN: no offer, every offer column null.
	$key_code = array(
		'code' => 'ORPHAN1', 'enabled' => '1', 'expiration_date' => '0000-00-00',
		'offer_status' => null, 'offer_start_date' => null, 'offer_end_date' => null,
		'last_modified_timestamp' => time(), 'last_modified_username' => '',
	);
	$cells = pg_key_code_list_cells($key_code, $today);
	pg_assert_contains(pg_list_status_word(lang('No offer has this code')), $cells['code']['html'], 'a code whose offer is gone says so');
	pg_assert_true(strpos($cells['code']['html'], lang('Offer not running')) === false, 'not the wrong reason');

	$key_code['enabled'] = '0';
	$cells = pg_key_code_list_cells($key_code, $today);
	pg_assert_contains(pg_list_status_word(lang('Disabled')), $cells['code']['html'], 'the code\'s own state still comes first');
}

function test_offer_save_moves_key_codes_to_the_new_code()
{
	$src = offer_code_follow_source('edit_offer_f.php');
	$start = strpos($src, 'function _pg_offer_save(');
	pg_assert_true($start !== false, '_pg_offer_save exists');
	$body = substr($src, $start, strpos($src, "\nfunction ", $start + 10) - $start);

	pg_assert_contains("\$old_code = \$is_new ? '' : (string) \$existing['code'];", $body, 'the stored code is read before the write');
	pg_assert_contains("SELECT COUNT(*) FROM offers WHERE code = '\" . e(\$old_code) . \"'", $body, 'another offer keeping the old code keeps its key codes');
	pg_assert_contains("UPDATE key_codes SET offer_code = '\" . e(\$offer['code']) . \"' WHERE offer_code = '\" . e(\$old_code) . \"'", $body, 'the key codes move to the new code');
	pg_assert_true(strpos($body, 'UPDATE key_codes') > strpos($body, 'UPDATE offers SET'), 'they move after the offer row has the new code');
	pg_assert_true(strpos($body, 'special_offer_code') === false, 'past orders keep the code they used');
}

function test_editor_warns_while_the_code_changes()
{
	$page = offer_code_follow_source('edit_offer.php');
	pg_assert_contains("'keyCodesFollow'", $page, 'the editor gets the warning text');
	pg_assert_contains("'savedCode'", offer_code_follow_source('edit_offer_f.php'), 'the editor knows the stored code');

	$tpl = offer_code_follow_source('includes/templates/edit_offer.php');
	pg_assert_contains('id="pg-if-code-follow"', $tpl, 'a line under the code field holds the warning');

	$js = offer_code_follow_source('assets/js/offer_editor.js');
	pg_assert_contains('function renderCodeFollow()', $js, 'the editor draws the warning');
	pg_assert_contains('S.offer.code !== C.savedCode', $js, 'only while the typed code differs from the stored one');
	pg_assert_contains('C.key_code_count > 0', substr($js, strpos($js, 'function renderCodeFollow()'), 600), 'and only when key codes exist');
	pg_assert_contains('!(C.same_code_count > 0)', substr($js, strpos($js, 'function renderCodeFollow()'), 600), 'not while another offer keeps the saved code and its key codes');
}
