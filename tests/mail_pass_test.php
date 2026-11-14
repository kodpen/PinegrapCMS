<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the mail pass (includes/fn/mail_pass.php): what it takes out of
 * a designed e-mail body (stylesheet links, the page's CSS, scripts), the
 * var() fallbacks written as values, the one head style put in, the
 * design's own footer noticed and the size Gmail clips at.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function mail_pass_test_render()
{
	return "<!DOCTYPE html>\n<html lang=\"tr\"><head><meta charset=\"utf-8\">\n"
		. "<link rel=\"stylesheet\" href=\"https://fonts.example/css\">\n"
		. "<link rel=\"stylesheet\" href=\"/pinegrap/assets/lib/bootstrap/bootstrap.min.css\">\n"
		. "<style id=\"pg-page-css\">\n.card { color: red }\n</style>\n"
		. "<script>window.x = 1;</script>\n"
		. "</head><body class=\"page\">"
		. "<div class=\"pg-mail-frame\" style=\"max-width:600px;background:var(--bs-body-bg,#ffffff)\">"
		. "<a style=\"background:var(--bs-primary, #0d6efd);color:var(--pg-on, var(--bs-white, #fff))\" href=\"https://shop.example\">Shop</a>"
		. "<p style=\"color:var(--no-fallback)\">Hi ^^first_name^^</p>"
		. "</div></body></html>";
}

function test_mail_pass_takes_out_what_only_weighs()
{
	$out = pg_mail_pass(mail_pass_test_render());

	foreach (array('<link', 'fonts.example', 'bootstrap.min.css', 'pg-page-css', '.card', '<script', 'window.x') as $needle) {
		pg_assert_true(strpos($out['html'], $needle) === false, 'left out: ' . $needle);
	}

	pg_assert_contains('<html lang="tr">', $out['html'], 'the language stays');
	pg_assert_contains('Hi ^^first_name^^', $out['html'], 'merge fields stay');
	pg_assert_contains('href="https://shop.example"', $out['html'], 'links stay');
}

function test_mail_pass_writes_var_fallbacks()
{
	$out = pg_mail_pass(mail_pass_test_render());

	pg_assert_contains('background:#ffffff', $out['html'], 'a fallback becomes the value');
	pg_assert_contains('background:#0d6efd;color:#fff', $out['html'], 'nested fallbacks, the innermost first');
	pg_assert_contains('color:var(--no-fallback)', $out['html'], 'a var() without a fallback stays');
	pg_assert_same('rgba(0, 0, 0, .5) solid', pg_mail_pass_var_fallbacks('var(--line, rgba(0, 0, 0, .5)) solid'), 'a fallback with a function');
}

function test_mail_pass_puts_one_head_style_in()
{
	$out = pg_mail_pass(mail_pass_test_render());

	pg_assert_same(1, substr_count($out['html'], '<style>'), 'one style element');
	pg_assert_contains(pg_mail_pass_head_style() . '</head>', $out['html'], 'at the end of the head');
	pg_assert_contains('@media (max-width: 620px)', pg_mail_pass_head_style(), 'the phone rule');

	$bare = pg_mail_pass('<body><p>Hi</p></body>');
	pg_assert_contains('<head>' . pg_mail_pass_head_style() . '</head><body>', $bare['html'], 'a head is made before the body');

	$fragment = pg_mail_pass('<p>Hi</p>');
	pg_assert_true(strpos($fragment['html'], '<!DOCTYPE html><html><head><meta charset="utf-8">') === 0, 'a fragment becomes a document');
	pg_assert_contains('<body><p>Hi</p></body></html>', $fragment['html'], 'with the fragment as its body');
}

function test_mail_pass_notices_the_designs_footer()
{
	$without = pg_mail_pass(mail_pass_test_render());
	$with = pg_mail_pass(str_replace('</div></body>', '<div data-pg-mail-footer class="foot">Address</div></div></body>', mail_pass_test_render()));
	$valued = pg_mail_pass('<div data-pg-mail-footer="1">Address</div>');

	pg_assert_false($without['has_footer'], 'no footer block, the campaign adds its own');
	pg_assert_true($with['has_footer'], 'a footer block is noticed');
	pg_assert_true($valued['has_footer'], 'with a value too');
	pg_assert_false(pg_mail_pass('<p>data-pg-mail-footer</p>')['has_footer'], 'the words in the text are no block');
}

function test_mail_pass_size()
{
	$small = pg_mail_pass(mail_pass_test_render());
	$large = pg_mail_pass('<p>' . str_repeat('x', pg_mail_pass_clip_bytes()) . '</p>');

	pg_assert_same(strlen($small['html']), $small['bytes'], 'bytes is the length');
	pg_assert_false($small['clipped'], 'a small body is not clipped');
	pg_assert_true($large['clipped'], 'over 102 KB Gmail clips');
}
