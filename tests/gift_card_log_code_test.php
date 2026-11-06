<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for pg_gift_card_log_code() (includes/fn/core.php): a gift card's
 * code as a Site Log line names it, only its last four characters readable.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// A 16-character code is spendable by whoever reads it; the log keeps only
// the last group, in the grouping output_gift_card_code() shows.
function test_gift_card_log_code_hides_all_but_last_four()
{
	pg_assert_same('****-****-****-WXYZ', pg_gift_card_log_code('ABCDEFGHJKLMWXYZ'));
}

// The masked form never carries the readable part of the code.
function test_gift_card_log_code_does_not_contain_the_code()
{
	$code = 'ABCDEFGHJKLMWXYZ';

	pg_assert_same(false, strpos(pg_gift_card_log_code($code), 'ABCD'));
	pg_assert_same(false, strpos(pg_gift_card_log_code($code), output_gift_card_code($code)));
}

// A short or empty code shows no characters at all.
function test_gift_card_log_code_short_code()
{
	pg_assert_same('****', pg_gift_card_log_code('ABCD'));
	pg_assert_same('****', pg_gift_card_log_code(''));
}
