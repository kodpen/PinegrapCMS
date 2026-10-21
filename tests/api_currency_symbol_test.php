<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the currency symbol the external API gives out
 * (api_currency_symbol_text(), includes/api/resources/store.php): GET /meta
 * and GET /store/currencies write the symbol the currency screen keeps as HTML
 * out as plain text.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!defined('PG_API_PANEL')) {
	define('PG_API_PANEL', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/api/resources/store.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/resources/meta.php');

// The symbols the starter sites keep, as the currency screen writes them.
function test_api_currency_symbol_text_writes_the_html_out()
{
	pg_assert_same("\u{20BA}", api_currency_symbol_text('&#8378;'), 'TRY');
	pg_assert_same("\u{20AC}", api_currency_symbol_text('&euro;'), 'EUR');
	pg_assert_same("\u{00A3}", api_currency_symbol_text('&pound;'), 'GBP');
	pg_assert_same("\u{00A5}", api_currency_symbol_text('&yen;'), 'JPY');
}

// A symbol typed as text stays as it is.
function test_api_currency_symbol_text_keeps_plain_text()
{
	pg_assert_same('$', api_currency_symbol_text('$'), 'dollar');
	pg_assert_same('kr', api_currency_symbol_text('kr'), 'letters');
	pg_assert_same("\u{20BA}", api_currency_symbol_text("\u{20BA}"), 'already a character');
	pg_assert_same('', api_currency_symbol_text(null), 'none');
}

// GET /meta goes through the same rule as GET /store/currencies, rather than
// handing out the constant init.php reads from the currencies table.
function test_api_meta_currency_symbol_is_plain_text()
{
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/includes/api/resources/meta.php');

	pg_assert_contains("api_currency_symbol_text(BASE_CURRENCY_SYMBOL)", $source, 'meta writes the symbol out');
	pg_assert_true(strpos($source, "? BASE_CURRENCY_SYMBOL :") === false, 'the HTML constant is not handed out');
}
