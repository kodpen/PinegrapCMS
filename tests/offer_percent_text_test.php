<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the percent figures of the offer texts (edit_offer_f.php,
 * offer_editor.js) and the image Optimize badges: every figure goes
 * through the '{var:1}%' key, so English reads "10%" and Turkish "%10";
 * a '%' glued to the number in code reads one way in every language.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/edit_offer_f.php');
require_once(__DIR__ . '/helpers/language_map.php');

function offer_percent_text_test_source($path)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $path);
}

function offer_percent_text_test_percent($value)
{
	return lang(array('string' => '{var:1}%', 'vars' => $value));
}

// The value of a percent result, the list chips and the sentences.
function test_offer_percent_value_follows_the_language()
{
	$order = array('type' => 'discount order', 'unit' => 'percent', 'value' => 10);
	$shipping = array('type' => 'discount shipping', 'unit' => 'percent', 'value' => 100, 'shipping_method_ids' => array(7));
	$methods = array(array('id' => 7, 'name' => 'Courier'));

	pg_assert_same(offer_percent_text_test_percent(10), _pg_offer_value_text($order));
	pg_assert_same(lang('Order') . ' ' . offer_percent_text_test_percent(10), _pg_offer_action_chip($order, array()));
	pg_assert_same(lang('Shipping') . ' ' . offer_percent_text_test_percent(100), _pg_offer_action_chip($shipping, array()));
	pg_assert_same(
		lang(array('string' => '{var:1} off shipping ({var:2})', 'vars' => array(offer_percent_text_test_percent(100), 'Courier'))),
		_pg_offer_action_text($shipping, array(), $methods)
	);
	pg_assert_true(strpos(_pg_offer_action_text($order, array(), array()), '%10') === false, 'English order text keeps the sign last');
}

// A tier rung takes the formatted percent, not a '%' written into the key.
function test_offer_percent_tier_rung_follows_the_language()
{
	$action = array('type' => 'discount order', 'unit' => 'percent', 'value' => 0, 'tiers' => array(array('min' => 100, 'percent' => 5)));
	$text = _pg_offer_action_text($action, array(), array());

	pg_assert_contains('+ → ' . offer_percent_text_test_percent(5), $text);
}

// The new keys are worded in Turkish with the sign first.
function test_offer_percent_turkish_keys()
{
	$tr = pg_test_language_map();

	pg_assert_same('%{var:1}', isset($tr['{var:1}%']) ? $tr['{var:1}%'] : null);
	pg_assert_same('{var:1}+ → {var:2}', isset($tr['{var:1}+ → {var:2}']) ? $tr['{var:1}+ → {var:2}'] : null);
	pg_assert_same('kargoya {var:1} indirim ({var:2})', isset($tr['{var:1} off shipping ({var:2})']) ? $tr['{var:1} off shipping ({var:2})'] : null);
}

// The editor's live summary builds its figures from the passed template.
function test_offer_editor_percent_uses_the_template()
{
	$js = offer_percent_text_test_source('assets/js/offer_editor.js');
	$php = offer_percent_text_test_source('edit_offer.php');

	pg_assert_true(strpos($js, "'%' + (parseInt(a.value") === false, 'valueText glues the sign');
	pg_assert_true(strpos($js, "'+ → %'") === false, 'tier rung glues the sign');
	pg_assert_contains('L.percent.replace(', $js);
	pg_assert_contains("'percent'", $php);
	pg_assert_contains("lang('{var:1} off shipping ({var:2})')", $php);
}

// The Optimize badges on Files, Design Files and Edit File.
function test_image_optimize_badges_follow_the_language()
{
	pg_assert_same(0, calculate_optimizable_percent('/nonexistent/' . md5('x') . '.png'));

	foreach (array('view_files.php', 'view_design_files.php', 'edit_file.php') as $path) {
		$source = offer_percent_text_test_source($path);
		pg_assert_true(strpos($source, "'{var:1}%'") !== false, $path . ' formats through the key');
		pg_assert_true(strpos($source, "optimization_percent'] . '%") === false, $path . ' glues the sign');
	}
}
