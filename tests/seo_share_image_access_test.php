<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the share image and the feed enclosure of a submitted form's
 * media upload: pg_og_image_from_open_file() and pg_file_open_to_everyone()
 * in includes/fn/seo.php give nothing for an upload a visitor who is not
 * signed in cannot open, so og:image falls back to the default share image
 * and the classic feed (get_page.php) writes no enclosure, instead of either
 * carrying an address that answers with the sign-in screen.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Without a database the file's row cannot be read, and a file that cannot
// be read is closed: no share image, nothing reaches the files query.
function test_seo_share_image_closed_upload_gives_nothing()
{
	pg_assert_same(null, pg_og_image_from_open_file(301));
	pg_assert_same(null, pg_og_image_from_open_file(0));
	pg_assert_same(null, pg_og_image_from_open_file('301'));
	pg_assert_false(pg_file_open_to_everyone(301));
	pg_assert_false(pg_file_open_to_everyone(0));
}

// The resolver hands an uploaded file to the open-file check and never to
// the file alone; the check loads includes/record_files.php and asks it.
function test_seo_share_image_resolver_asks_first()
{
	$seo = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/seo.php');

	$start = strpos($seo, 'function pg_resolve_submitted_form_og_image(');
	$body  = substr($seo, $start, strpos($seo, "\nfunction ", $start + 10) - $start);
	pg_assert_contains("return pg_og_image_from_open_file(\$media['file_id']);", $body);
	pg_assert_same(false, strpos($body, 'pg_og_image_from_file_id('), 'never the file alone');

	$start = strpos($seo, 'function pg_og_image_from_open_file(');
	$body  = substr($seo, $start, strpos($seo, "\nfunction ", $start + 10) - $start);
	$ask   = strpos($body, 'if (!pg_file_open_to_everyone($file_id)) {');
	$image = strpos($body, 'return pg_og_image_from_file_id($file_id);');
	pg_assert_true($ask !== false && $image !== false && $ask < $image, 'the check before the image');

	$start = strpos($seo, 'function pg_file_open_to_everyone(');
	$body  = substr($seo, $start, strpos($seo, "\nfunction ", $start + 10) - $start);
	pg_assert_contains("require_once(PG_FUNCTIONS_DIR . '/includes/record_files.php');", $body);
	pg_assert_contains('return pg_record_file_anonymous_access($file_id);', $body);
}

// The classic feed writes the media upload's enclosure only inside the
// open-file check; a typed address is written as before.
function test_seo_share_image_classic_feed_asks_first()
{
	$page = (string) file_get_contents(PG_FUNCTIONS_DIR . '/get_page.php');

	$ask       = strpos($page, "if (pg_file_open_to_everyone(\$submitted_form['media_file'])) {");
	$enclosure = strpos($page, '<enclosure url="\' . URL_SCHEME . HOSTNAME_SETTING . PATH');
	$typed     = strpos($page, '<enclosure url="\' . h($submitted_form[\'media_text\'])');
	pg_assert_true($ask !== false && $enclosure !== false && $typed !== false);
	pg_assert_true($ask < $enclosure && $enclosure < $typed, 'the upload inside the check, the typed address after it');
	pg_assert_same(1, substr_count($page, 'pg_file_open_to_everyone('));
}
