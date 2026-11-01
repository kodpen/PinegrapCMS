<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Visitor texts of the classic store pages (shopping cart, express order,
 * order receipt) and of the placeholder image speak the site's language.
 * The classic pages printed headings, labels and sentences as fixed English
 * markup, so a Turkish site showed English beside Turkish on the same page;
 * the placeholder picture drew a fixed English caption under the icon.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

/**
 * The visible text written as markup inside the string literals of a PHP
 * file: the text between tags of a literal that holds a tag. Text that goes
 * through lang() is a call, not a literal, so it never shows here. Script
 * fragments (assignments, calls) are left out.
 *
 * @param string $file
 * @return string[] "line: text"
 */
function pg_test_site_language_literal_texts($file)
{
	$found = array();

	foreach (token_get_all(file_get_contents($file)) as $token) {
		if (!is_array($token) || !in_array($token[0], array(T_CONSTANT_ENCAPSED_STRING, T_ENCAPSED_AND_WHITESPACE, T_INLINE_HTML), true)) {
			continue;
		}

		$string = $token[1];

		if ($token[0] === T_CONSTANT_ENCAPSED_STRING) {
			$string = substr($string, 1, -1);
		}

		$string = preg_replace('~<(script|style)\b.*?</\1>~is', '', $string);
		$parts = preg_split('~<[^<>]*>~', $string);
		$count = count($parts);

		// A literal without a tag is a value, not markup: not judged here.
		if ($count === 1) {
			continue;
		}

		foreach ($parts as $index => $part) {
			// The first piece may close a tag opened in the literal before
			// (an attribute value joined in), the last may open one: only the
			// text outside the tag counts.
			if ($index === 0 && preg_match('~["=]~', $part)) {
				if (!preg_match('~>([^>]*)$~', $part, $match)) {
					continue;
				}

				$part = $match[1];
			}

			if ($index === $count - 1 && strpos($part, '<') !== false) {
				$part = substr($part, 0, strpos($part, '<'));
			}

			$text = trim(html_entity_decode(str_replace('&nbsp;', ' ', $part)));

			if (!preg_match('~[A-Za-z]{2,}~', $text) || preg_match('~[;={}]|software\.|ORDER BY~', $text)) {
				continue;
			}

			$found[] = $token[2] . ': ' . $text;
		}
	}

	return $found;
}

// No fixed English heading, label or sentence is left in the markup.
function test_site_language_classic_store_pages_markup()
{
	foreach (array('get_shopping_cart.php', 'get_express_order.php', 'get_order_receipt.php') as $file) {
		pg_assert_same(array(), pg_test_site_language_literal_texts(PG_FUNCTIONS_DIR . '/' . $file), $file);
	}
}

// Button values, error messages and headings kept in variables go through lang() too.
function test_site_language_classic_store_pages_values()
{
	$cart = file_get_contents(PG_FUNCTIONS_DIR . '/get_shopping_cart.php');
	$express = file_get_contents(PG_FUNCTIONS_DIR . '/get_express_order.php');

	foreach (array('get_shopping_cart.php' => $cart, 'get_express_order.php' => $express) as $file => $source) {
		pg_assert_same(0, substr_count($source, 'value="Add"'), $file . ' Add button');
		pg_assert_same(0, preg_match_all("~_heading = '(Qty|Price)';~", $source), $file . ' quantity and price headings');
	}

	pg_assert_same(0, substr_count($express, 'value="Apply"'), 'Apply button');
	pg_assert_same(0, substr_count($express, "mark_error('payment_method', 'Sorry"), 'no payment method error');
	pg_assert_same(0, substr_count($express, ' (Remaining Balance: '), 'gift card remaining balance');
}

// The placeholder picture carries no words: the card names the item through the image's alt text.
function test_site_language_no_image_placeholder_has_no_words()
{
	$svg = file_get_contents(PG_FUNCTIONS_DIR . '/assets/images/no-image.svg');

	pg_assert_same(0, preg_match('~<text\b~', $svg), 'text element');
	pg_assert_same(0, preg_match('~aria-label=~', $svg), 'aria-label');
	pg_assert_true(preg_match('~<svg\b[^>]*\baria-hidden="true"~', $svg) === 1, 'hidden from assistive technology');
}
