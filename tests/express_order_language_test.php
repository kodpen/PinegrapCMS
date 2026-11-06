<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the address the express order page's scripts post to on a page
 * in another site language (pg_express_order_api_url(),
 * includes/fn/widgets_express_order.php): the installment, live tax and
 * shipping method requests use fetch(), which the jQuery prefilter of
 * frontend.src.js never sees, so the address itself carries pg_lang.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/widgets_express_order.php');

function test_express_order_api_url_source_language()
{
	pg_assert_same('/shop/software/api.php', pg_express_order_api_url('/shop/', 'software', ''), 'the source language adds nothing');
}

function test_express_order_api_url_other_language()
{
	pg_assert_same('/shop/software/api.php?pg_lang=de', pg_express_order_api_url('/shop/', 'software', 'de'));
	pg_assert_same('/software/api.php?pg_lang=pt-BR', pg_express_order_api_url('/', 'software', 'pt-BR'), 'a region code as the language row writes it');
}

// Every fetch() of the express order scripts posts to the address the helper
// builds: none is left on a bare api.php path.
function test_express_order_scripts_post_to_the_language_address()
{
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/widgets_express_order.php');
	pg_assert_same(1, preg_match_all("~\\. '/?api\\.php'~", $source), 'only the helper builds an api.php address');
	pg_assert_same(3, substr_count($source, '= pg_express_order_api_url('), 'the installment fetchers and the widget script');
}
