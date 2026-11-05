<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for waf_log_text() (waf.php): a request value as the firewall log
 * stores it, cut to the column's bytes and always valid UTF-8, so the INSERT
 * into the utf8mb4 waf_log table is never refused by a strict sql_mode.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/waf.php');

// Plain and valid multibyte text passes unchanged.
function test_waf_log_text_valid_unchanged()
{
	pg_assert_same("' OR 1=1--", waf_log_text("' OR 1=1--", 250));
	pg_assert_same("çay şeker 🍵", waf_log_text("çay şeker 🍵", 250));
	pg_assert_same('', waf_log_text('', 250));
}

// An invalid byte (a GBK-style %bf%27 payload PHP already decoded, a raw
// high byte in the path) becomes '?' instead of failing the INSERT.
function test_waf_log_text_invalid_bytes_replaced()
{
	pg_assert_same("id=?' OR 1=1", waf_log_text("id=\xbf' OR 1=1", 250));
	pg_assert_same('/??/x', waf_log_text("/\xc0\xae/x", 250));
	pg_assert_same('a???b', waf_log_text("a\xed\xa0\x80b", 250));
	pg_assert_true((bool) preg_match('//u', waf_log_text("\xff\xfe\x80abc", 250)), 'always valid UTF-8');
}

// The cut never splits a character: a trailing partial character is dropped.
function test_waf_log_text_cut_on_character_boundary()
{
	pg_assert_same('ab', waf_log_text('abç', 3));
	pg_assert_same('abç', waf_log_text('abçd', 4));
	pg_assert_same('a', waf_log_text('a🍵', 4));
	pg_assert_same('abc', waf_log_text('abcdef', 3));
	pg_assert_true(strlen(waf_log_text(str_repeat('ş', 200), 250)) <= 250, 'within the byte limit');
	pg_assert_true((bool) preg_match('//u', waf_log_text(str_repeat('ş', 200), 250)), 'valid after the cut');
}
