<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the express order screen's upsell offers (get_express_order.php):
 * the default action button label a custom layout receives goes through
 * lang(), as the system layout's does, so a Turkish site does not show the
 * English "More Info".
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_express_order_upsell_default_label_is_translated()
{
	$source = file_get_contents(dirname(__DIR__) . '/pinegrap/get_express_order.php');

	pg_assert_same(0, preg_match("/upsell_action_button_label'\\] = 'More Info';/", $source), 'no hard-coded English default label');
	pg_assert_contains("\$offer['upsell_action_button_label'] = lang('More Info');", $source, 'the custom layout default comes from lang()');
}
