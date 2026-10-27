<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the offers' texts in the site translation: which fields of an
 * offer become segments, that the catalog group carries the owner type,
 * and that the extraction and the translator's context name it.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/translate/store.php');
require_once(PG_FUNCTIONS_DIR . '/includes/translate/extract.php');
require_once(PG_FUNCTIONS_DIR . '/includes/translate/content.php');

// An offer's message, its suggestion and its button label are one segment
// each, in order; empty ones and the code give none.
function test_translate_offer_segments()
{
	$row = array('id' => 3, 'code' => 'WELCOME', 'description' => '10% off your first order',
		'upsell_message' => '  ', 'upsell_action_button_label' => 'See the offer');
	$segments = pg_tr_offer_segments($row, pg_tr_offer_fields());
	pg_assert_same(array('description', 'upsell_action_button_label'), array_column($segments, 'field'));
	pg_assert_same(array('10% off your first order', 'See the offer'), array_column($segments, 'text'));
	pg_assert_same(array(), pg_tr_offer_segments(array('description' => '', 'code' => 'X'), pg_tr_offer_fields()), 'nothing to translate');
	pg_assert_same(array('description'), array_column(pg_tr_offer_segments($row, array('description')), 'field'), 'only the columns the table has');
}

// The offers go with the catalog group, the extraction stores them under
// their own owner type and the translator sees which offer a text is from.
function test_translate_offer_wiring()
{
	$groups = pg_tr_owner_groups();
	pg_assert_true(in_array('offer', $groups['catalog']['types'], true), 'the catalog group carries offers');
	$content = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/translate/content.php');
	pg_assert_contains("pg_tr_store_owner_type('offer', \$by_owner, 'content')", $content);
	pg_assert_contains("FROM offers WHERE status = 'enabled' ORDER BY id", $content);
	$context = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/translate/context.php');
	pg_assert_contains("'offer'         => array(\"SELECT id, code AS label FROM offers WHERE id IN (%s)\", lang('Offer')),", $context);
	pg_assert_same(2, substr_count($context, "'attribute' => array(), 'offer' => array(),"), 'owners and names know the type');
}
