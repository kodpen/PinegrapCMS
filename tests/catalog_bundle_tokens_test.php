<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the bundle tokens and flags of the catalog widgets
 * (includes/fn/widgets_catalog.php): __is_bundle, the saving against the
 * components bought one by one, and the card a bundle row takes.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// A plain product is no bundle and saves nothing; a bundle's saving is read
// from the price paid and written with the sign; one that saves nothing
// has an empty saving.
function test_catalog_bundle_values()
{
	$contents = array(array('price' => 1000, 'quantity' => 2), array('price' => 500, 'quantity' => 1));

	$plain = _pg_catalog_bundle_values(false, $contents, 1000, '$');
	pg_assert_same(array('__is_bundle' => '0', '__bundle_saving_formatted' => ''), $plain);
	pg_assert_same(array('is_bundle' => false, 'has_bundle_saving' => false), _pg_catalog_bundle_flags($plain));

	$bundle = _pg_catalog_bundle_values(true, $contents, 2000, '$');
	pg_assert_same('1', $bundle['__is_bundle']);
	pg_assert_same(pg_visitor_money(5, '$'), $bundle['__bundle_saving_formatted'], 'five whole units saved');
	pg_assert_same(array('is_bundle' => true, 'has_bundle_saving' => true), _pg_catalog_bundle_flags($bundle));

	$dear = _pg_catalog_bundle_values(true, $contents, 2500, '$');
	pg_assert_same(array('is_bundle' => true, 'has_bundle_saving' => false), _pg_catalog_bundle_flags($dear), 'no saving at the full sum');
}

// A plain product's card draws no bundle element; a bundle's card draws
// is_bundle, and has_bundle_saving only with a saving, with the rating
// state it has; a group card draws none.
function test_catalog_bundle_card_states()
{
	$paragraph = function ($text, $flag) {
		$props = array('contentType' => 'paragraph', 'text' => $text);
		if ($flag !== '') $props['_bindings'] = array('eo_visible_if' => $flag);
		return array('type' => 'content', 'props' => $props, 'children' => array());
	};
	$cards = _pg_catalog_card_templates(array(
		$paragraph('Always', ''),
		$paragraph('Bundle', 'is_bundle'),
		$paragraph('You save', 'has_bundle_saving'),
		$paragraph('Stars', 'has_rating'),
	));

	foreach (array('rated', 'unrated', 'group') as $state) {
		pg_assert_false(strpos($cards[$state], 'Bundle') !== false, $state . ': no bundle element');
		pg_assert_false(strpos($cards[$state], 'You save') !== false, $state . ': no saving');
	}
	$plain = _pg_catalog_card_for($cards, true, array('is_bundle' => false, 'has_bundle_saving' => false));
	pg_assert_same($cards['rated'], $plain, 'a plain product takes the rated card');

	$saving = _pg_catalog_card_for($cards, false, array('is_bundle' => true, 'has_bundle_saving' => true));
	pg_assert_contains('<p>Bundle</p>', $saving);
	pg_assert_contains('<p>You save</p>', $saving);
	pg_assert_false(strpos($saving, 'Stars') !== false, 'the rating state is kept');
	pg_assert_false(strpos($saving, 'pg-vis') !== false, 'no marker is left');
	pg_assert_true(isset($cards['bundle_unrated_saving']), 'kept for the next row');

	$none = _pg_catalog_card_for($cards, true, array('is_bundle' => true, 'has_bundle_saving' => false));
	pg_assert_contains('<p>Bundle</p>', $none);
	pg_assert_contains('<p>Stars</p>', $none);
	pg_assert_false(strpos($none, 'You save') !== false, 'no saving element without a saving');
}
