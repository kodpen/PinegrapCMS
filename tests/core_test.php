<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for input and output helpers in includes/fn/core.php and the tax
 * rate parser in includes/fn/ecommerce.php.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// A javascript: URL is not one of the accepted forms and is refused.
function test_core_escape_url_refuses_javascript()
{
	pg_assert_false(escape_url('javascript:alert(1)'));
}

// An absolute https URL is accepted and returned unchanged.
function test_core_escape_url_keeps_https()
{
	pg_assert_same('https://example.com/a', escape_url('https://example.com/a'));
}

// Anything other than asc/desc in an ORDER BY direction falls back to asc.
function test_core_sql_order_direction_whitelists()
{
	pg_assert_same('asc', sql_order_direction('DROP'));
}

// A comma decimal separator is read and the rate is stored with three places.
function test_core_parse_tax_rate_reads_comma()
{
	pg_assert_same('18.500', parse_tax_rate('18,5'));
}
