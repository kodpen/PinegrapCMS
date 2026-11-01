<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the hello design template (includes/design_templates/
 * hello-pinegrap.php) beyond its comment threads, which the shop templates'
 * table checks (design_template_shop_comments_test.php): the pages keep
 * their comment settings, the product page carries its reviews under the
 * product, the Google Reviews widget stands beside the sample words of the
 * home page, and the Favourites page and its links come only with the shop.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(__DIR__ . '/helpers/template_threads.php');

// Each comment page keeps its label (a singular noun, the conversation's the
// lower-case `reply`), signed-in writers and comments published at once; the
// product's reviews take stars and tell the staff.
function test_design_template_hello_comment_settings()
{
	$tpl = _tpl_threads_tpl('hello-pinegrap');
	$labels = array('blog_post' => lang('Comment'), 'conversation' => lang('reply'), 'product' => lang('Review'));
	foreach ($labels as $key => $label) {
		$page = _tpl_threads_page($tpl, $key);
		pg_assert_true(is_array($page) && isset($page['comments']), $key . ': takes comments');
		$c = is_array($page) && isset($page['comments']) ? $page['comments'] : array();
		pg_assert_same($label, isset($c['label']) ? $c['label'] : null, $key . ': its label');
		pg_assert_true(!empty($c['login']) && !empty($c['auto_publish']), $key . ': signed in, published at once');
	}
	$product = _tpl_threads_page($tpl, 'product');
	pg_assert_true(!empty($product['comments']['rating']), 'the reviews take stars');
	pg_assert_same('{{site_email}}', $product['comments']['notify_email'], 'and tell the staff');
	pg_assert_same('ecommerce', $product['requires'], 'the product page comes with the shop');
}

// The product page: the product, then its reviews in the reading column.
function test_design_template_hello_product_reviews()
{
	$tpl = _tpl_threads_tpl('hello-pinegrap');
	$order = _tpl_threads_order(_tpl_threads_page($tpl, 'product')['tree']);
	$at = array_search('product_reviews', $order, true);
	pg_assert_true($at !== false && $at > 0 && $order[$at - 1] === 'product_detail', 'the reviews right under the product');
	pg_assert_same(lang('reviews'), $tpl['widgets']['product_reviews']['slug'], 'named as the other shops name it');
}

// The sample testimonials stay; the Google Reviews widget follows them in
// their section and shows only once Google Reviews is set up.
function test_design_template_hello_google_reviews()
{
	_tpl_threads_check_google_reviews('hello-pinegrap', 'google_reviews', 'home', lang('Testimonials'));
	$tpl = _tpl_threads_tpl('hello-pinegrap');
	pg_assert_true(empty($tpl['widgets']['google_reviews']['requires']), 'also without the shop');
	$home = json_encode(_tpl_threads_page($tpl, 'home')['tree'], JSON_UNESCAPED_UNICODE);
	$sample = strpos($home, json_encode(lang('Put the words of a customer here: a sentence or two on what you did for them and how it went.'), JSON_UNESCAPED_UNICODE));
	$widget = strpos($home, '"templateWidget":"google_reviews"');
	pg_assert_true($sample !== false && $widget !== false && $sample < $widget, 'the sample words stay, the widget after them');
}

// The template as a site without the shop reads it (ECOMMERCE false), in a
// process of its own: the constant is already defined in this one. Null
// when PHP cannot be started again.
function _test_tpl_hello_without_shop()
{
	if (!function_exists('exec') || !defined('PHP_BINARY') || (PHP_BINARY === '')) {
		return null;
	}
	$file = @tempnam(sys_get_temp_dir(), 'pghello');
	if ($file === false) {
		return null;
	}
	file_put_contents($file, '<?php' . "\n"
		. "define('PG_TEST_RUNNER', true);\n"
		. "define('ECOMMERCE', false);\n"
		. 'require ' . var_export(PG_FUNCTIONS_DIR . '/functions.php', true) . ";\n"
		. '$tpl = _pg_tpl_include(' . var_export(PG_FUNCTIONS_DIR . '/includes/design_templates/hello-pinegrap.php', true) . ");\n"
		. "echo json_encode(\$tpl);\n");
	$out = array();
	$code = 1;
	@exec(escapeshellarg(PHP_BINARY) . ' -d display_errors=stderr -d error_reporting=-1 ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);
	$tpl = json_decode(implode("\n", $out), true);
	pg_assert_same(0, $code, 'the template loads without the shop: ' . implode("\n", $out));
	return is_array($tpl) ? $tpl : null;
}

// Favourites: a page and its links on a shop site only. The page is left
// out by its `requires`; the links in My Account and the footer, which every
// page shares, are not written at all without the shop.
function test_design_template_hello_favourites_need_the_shop()
{
	$tpl = _tpl_threads_tpl('hello-pinegrap');
	pg_assert_same('ecommerce', _tpl_threads_page($tpl, 'favourites')['requires'], 'the page comes with the shop');
	pg_assert_same('ecommerce', $tpl['widgets']['wishlist']['requires'], 'and its widget');

	$plain = _test_tpl_hello_without_shop();
	if ($plain === null) {
		return;
	}
	pg_assert_false(strpos(json_encode($plain['shared']['footer']['tree']), '{{page:favourites}}') !== false, 'no footer link without the shop');
	pg_assert_false(strpos(json_encode($plain['widgets']['my_account']['tree']), '__my_account_wishlist_url') !== false, 'no My Account card without the shop');
	pg_assert_contains('"href":"{{page:favourites}}"', json_encode($tpl['shared']['footer']['tree']), 'the footer link with the shop');
}
