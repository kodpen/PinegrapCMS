<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the no-image fallback of a designer image: the placeholder's
 * address is in data-pg-fallback and the onerror code carries no
 * address and no firewall signature (pg_img_fallback_handler() and the image
 * case of _render_content_html()). Crawlers request a path-like handler
 * value as a relative URL; with "onerror=" in it the firewall blocked them.
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

function designer_image_fallback_test_img($props)
{
	$node = array('type' => 'content', 'props' => array_merge(array(
		'contentType' => 'image', 'alt' => 'A',
	), $props));
	$html = _render_tree_node($node, 0);
	preg_match('/<img\b[^>]*>/', $html, $m);

	return isset($m[0]) ? $m[0] : '';
}

function designer_image_fallback_test_attr($tag, $name)
{
	if (!preg_match('/\s' . preg_quote($name, '/') . '="([^"]*)"/', $tag, $m)) {
		return null;
	}

	return html_entity_decode($m[1], ENT_QUOTES, 'UTF-8');
}

function test_img_fallback_handler_has_no_address_and_no_waf_signature()
{
	foreach (array(false, true) as $responsive) {
		$code = pg_img_fallback_handler($responsive);
		pg_assert_false(strpos($code, '/') !== false, 'no path in the code' . ($responsive ? ' (responsive)' : ''));
		pg_assert_false(stripos($code, 'no-image') !== false, 'no placeholder name in the code');
		pg_assert_true(strpos($code, "this.removeAttribute('data-pg-fallback')") !== false, 'the attribute is used once');
		foreach (waf_rules() as $rule) {
			pg_assert_same(0, preg_match($rule['re'], $code), $rule['id'] . ' does not match the handler');
			pg_assert_same(0, preg_match($rule['re'], '/' . $code), $rule['id'] . ' does not match the handler requested as a path');
		}
	}
	pg_assert_true(strpos(pg_img_fallback_handler(true), "removeAttribute('srcset')") !== false, 'the responsive code drops srcset');
	pg_assert_false(strpos(pg_img_fallback_handler(false), 'srcset') !== false, 'the plain code leaves srcset alone');
}

function test_designer_image_fallback_markup()
{
	foreach (array(
		'external file' => array('src' => 'https://example.com/a.jpg'),
		'bound image'   => array('src' => '^^image_url^^'),
		'empty src'     => array('src' => ''),
	) as $label => $props) {
		$tag = designer_image_fallback_test_img($props);
		$fallback = designer_image_fallback_test_attr($tag, 'data-pg-fallback');
		$onerror  = designer_image_fallback_test_attr($tag, 'onerror');
		pg_assert_true($fallback !== null && substr($fallback, -strlen('/assets/images/no-image.svg')) === '/assets/images/no-image.svg', $label . ': the placeholder is in data-pg-fallback');
		pg_assert_true($onerror !== null && strpos($onerror, '/') === false, $label . ': the handler carries no address');
		pg_assert_false((bool) preg_match('/\bonerror\s*=/i', $onerror), $label . ': the handler never writes onerror=');
	}

	$bound = designer_image_fallback_test_attr(designer_image_fallback_test_img(array('src' => '^^image_url^^')), 'onerror');
	pg_assert_same(pg_img_fallback_handler(true), $bound, 'a bound image drops srcset before the swap');
}

function test_designer_image_fallback_leaves_the_real_src_to_greedy_rewriters()
{
	// The e-mail rewriters and get_page.php take the last "src=" of an <img>.
	$tag = designer_image_fallback_test_img(array('src' => '/files/a.jpg'));
	pg_assert_same(1, preg_match('/<\s*img\s+[^>]*src\s*=\s*["\']([^"\']*)/is', $tag, $m), 'an address is found');
	pg_assert_same('/files/a.jpg', $m[1], 'the real src, not the fallback');
}
