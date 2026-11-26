<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Site Settings > Ecommerce, Gift cards, Rewards and Reference price opened:
 * the radio rows helper, and the three cards without popovers or old
 * switches, every field under its old name.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/commerce_settings_fold.php');

// The part of commerce.php from one card's id to the next card or the next
// part's heading comment.
function settings_commerce_gift_test_card($id)
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/settings/commerce.php');
	$open = '<div id="' . $id . '" class="pg-set-card">';
	$start = strpos($source, $open);

	if ($start === false) {
		return '';
	}

	$ends = array(strlen($source));

	foreach (array('class="pg-set-card">', "\n// ── ") as $mark) {
		$end = strpos($source, $mark, $start + strlen($open));

		if ($end !== false) {
			$ends[] = $end;
		}
	}

	return substr($source, $start, min($ends) - $start);
}

// No popover, no old switch, nothing opened by a switch.
function settings_commerce_gift_test_plain($html, $what)
{
	pg_assert_true($html !== '', $what . ' is drawn');
	pg_assert_false(strpos($html, 'popover') !== false, $what . ' has no popover');
	pg_assert_false(strpos($html, 'class="switch') !== false, $what . ' has no old switch');
	pg_assert_false(strpos($html, 'collapse-switcher') !== false, $what . ' opens nothing by a switch');
}

function test_settings_commerce_gift_choice()
{
	$html = pg_commerce_settings_choice('rule', 'Price & more', array(
		array('value' => 'a', 'label' => 'First', 'line' => 'One <line>', 'on' => false),
		array('value' => 'b', 'label' => 'Second', 'line' => '', 'on' => true),
	), 'Under the rows');

	pg_assert_contains('<legend class="form-label pg-field-label">Price &amp; more</legend>', $html, 'the legend escaped');
	pg_assert_contains('<input value="a" class="radio" type="radio" id="rule_a" name="rule" aria-describedby="rule_a_help"/>', $html, 'a radio row keeps the id name_value and names its line');
	pg_assert_contains('<div class="form-text mb-3" id="rule_a_help">One &lt;line&gt;</div>', $html, 'its line after the field, escaped');
	pg_assert_contains('id="rule_b" name="rule" checked="checked"/>', $html, 'the chosen one is checked');
	pg_assert_false(strpos($html, 'rule_b_help') !== false, 'no line, no help id');
	pg_assert_contains('<fieldset aria-describedby="rule_help">', $html, 'the fieldset names its own line');
	pg_assert_contains('<p class="pg-field-help pg-text-meta fg-secondary mb-0" id="rule_help">Under the rows</p>', $html, 'the choice\'s line under it');

	$bare = pg_commerce_settings_choice('x', 'L', array(array('value' => 'v', 'label' => 'V', 'line' => '', 'on' => false)));
	pg_assert_contains('<fieldset><legend', $bare, 'no line, a plain fieldset');
}

function test_settings_commerce_gift_cards_card()
{
	$html = settings_commerce_gift_test_card('pgset-giftcards');
	settings_commerce_gift_test_plain($html, 'Gift cards');

	foreach (array('ecommerce_gift_card_validity_days', 'credit_refund_bonus') as $name) {
		pg_assert_contains('name="' . $name . '"', $html, $name . ' keeps its name');
	}

	pg_assert_contains("pg_commerce_settings_switch('ecommerce_gift_card'", $html, 'Accept gift cards is a pg-switch');
	pg_assert_contains("pg_commerce_settings_group_switch('ecommerce_givex'", $html, 'Givex is a group headed by its switch');
	pg_assert_contains("pg_payment_method_connection_html('givex'", $html, 'Givex shows its connection');
}

function test_settings_commerce_gift_rewards_card()
{
	$html = settings_commerce_gift_test_card('pgset-rewards');
	settings_commerce_gift_test_plain($html, 'Rewards');

	foreach (array('ecommerce_reward_program_points', 'credit_points_per_unit', 'credit_points_smallest', 'ecommerce_reward_program_membership_days', 'ecommerce_reward_program_email_bcc_email_address', 'ecommerce_reward_program_email_subject') as $name) {
		pg_assert_contains('name="' . $name . '"', $html, $name . ' keeps its name');
	}

	pg_assert_contains("pg_commerce_settings_switch('ecommerce_reward_program'", $html, 'the programme is a pg-switch');
	pg_assert_contains("pg_commerce_settings_switch('ecommerce_reward_program_membership'", $html, 'Grant membership is a pg-switch');
	pg_assert_contains("pg_commerce_settings_switch('ecommerce_reward_program_email'", $html, 'Send e-mail is a pg-switch');
	pg_assert_contains("pg_commerce_settings_group(lang('When the goal is reached'))", $html, 'what the goal grants is its own group');
	pg_assert_contains("'ecommerce_reward_program_email_page_id'", $html, 'the e-mail page keeps its name');
}

function test_settings_commerce_gift_reference_card()
{
	$html = settings_commerce_gift_test_card('pgset-reference-price');
	settings_commerce_gift_test_plain($html, 'Reference price');

	pg_assert_contains("pg_commerce_settings_choice('ecommerce_reference_price_rule'", $html, 'the three rules are radio rows with their lines');
	pg_assert_false(strpos($html, 'class="radio"') !== false, 'no radio written by hand');
}
