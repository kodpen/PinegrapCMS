<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the share image a record page takes from rich text
 * (pg_first_image_url_from_html(), includes/fn/seo.php): a picture pasted
 * into the editor as a data: address, or any other address that is not a
 * web address, is no share image; the first picture with a web address is.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/seo.php');

function test_seo_first_image_skips_addresses_that_are_not_web_addresses()
{
	pg_assert_same('', pg_first_image_url_from_html('<p><img src="data:image/png;base64,iVBORw0KGgo="></p>'), 'a pasted picture');
	pg_assert_same('', pg_first_image_url_from_html('<img src="blob:https://example.com/1f2e">'), 'a blob address');
	pg_assert_same('', pg_first_image_url_from_html('<img src="cid:part1.abc@example.com">'), 'an e-mail part');

	pg_assert_same(
		'https://example.com/cover.jpg',
		pg_first_image_url_from_html('<img src="data:image/gif;base64,R0lGOD="><img alt="" src="https://example.com//cover.jpg">'),
		'the first picture with a web address');
}
