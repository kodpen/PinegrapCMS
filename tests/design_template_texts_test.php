<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for visitor-facing words of the design templates: the Boutique
 * founder named by the shared sample person, one cart term in the Boutique,
 * a translatable percentage, the playground's own about address, the
 * password reset e-mail page address and the product page's payment table
 * head.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The source of a template file.
function _test_tpl_texts_source($id)
{
	return (string)file_get_contents(PG_FUNCTIONS_DIR . '/includes/design_templates/' . $id . '.php');
}

// The Turkish value of $key from its shard, or null.
function _test_tpl_texts_tr($key)
{
	foreach (glob(PG_FUNCTIONS_DIR . '/includes/local/tr/*.json') as $file) {
		$map = json_decode((string)file_get_contents($file), true);
		if (is_array($map) && array_key_exists($key, $map)) return $map[$key];
	}
	return null;
}

function test_design_template_boutique_founder_is_the_sample_person()
{
	$src = _test_tpl_texts_source('pinegrap-boutique');
	pg_assert_false(strpos($src, "lang('Jane Cooper, founder") !== false, 'no name inside a longer key');
	pg_assert_contains("lang(array('string' => '{var:1}, founder of the boutique', 'vars' => array(lang('Jane Cooper'))))", $src, 'the home quote');
	pg_assert_contains("lang(array('string' => '{var:1}, founder', 'vars' => array(lang('Jane Cooper'))))", $src, 'the about page');
	pg_assert_same('{var:1}, butiğin kurucusu', _test_tpl_texts_tr('{var:1}, founder of the boutique'), 'Turkish home quote');
	pg_assert_same('{var:1}, kurucu', _test_tpl_texts_tr('{var:1}, founder'), 'Turkish about line');
}

function test_design_template_boutique_one_cart_term()
{
	$src = _test_tpl_texts_source('pinegrap-boutique');
	foreach (array("lang('Shopping bag')", "lang('Add to bag')", "lang('Your shopping bag')", "lang('All entries')") as $old) {
		pg_assert_false(strpos($src, $old) !== false, 'gone: ' . $old);
	}
	pg_assert_contains("'title'    => lang('Shopping Cart'),", $src, 'the cart page title is the cart widget heading');
	pg_assert_contains("\$button(lang('Add to cart'),", $src, 'the card button names the cart');
	pg_assert_contains("\$link(lang('All posts'), '{{page:blog}}',", $src, 'the blog post back link');
}

function test_design_template_hello_percentage_is_translated()
{
	$src = _test_tpl_texts_source('hello-pinegrap');
	pg_assert_false(strpos($src, "\$stat('98%'") !== false, 'no fixed percentage');
	pg_assert_contains("lang(array('string' => '{var:1}%', 'vars' => array('98')))", $src, 'percentage through the shared key');
	pg_assert_same('%{var:1}', _test_tpl_texts_tr('{var:1}%'), 'Turkish puts the sign first');
}

function test_design_template_page_addresses()
{
	$src = _test_tpl_texts_source('pinegrap-playground');
	pg_assert_contains("    'key'              => 'about',\n    'name'             => lang('about-me'),", $src, 'the playground about page is about me');
	pg_assert_same('hakkimda', _test_tpl_texts_tr('about-me'), 'Turkish about-me address');
	pg_assert_same('e-posta-parola-sifirlama', _test_tpl_texts_tr('email-password-reset'), 'password reset e-mail page says parola');
}

function test_catalog_item_view_payment_table_head()
{
	$js = (string)file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
	pg_assert_contains("_ciTh(_sdT('Payment method')),", $js, 'payment table head names a payment method');
	pg_assert_false(strpos($js, "_ciTh(_sdT('Method')),") !== false, 'not the bare Method');
}
