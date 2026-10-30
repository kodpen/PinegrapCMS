<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the SMS marketing box beside the e-mail one: when it is drawn
 * (includes/fn/privacy.php, auth.php), how it starts, which answer a post
 * records as a consent_log row on the sms channel, and that each screen that
 * draws it draws it beside the e-mail box with the same start.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// A designer control: a semantic node with its HTML attributes and, when
// given, the address-book column it is bound to.
function sms_consent_test_control($tag, $attrs, $contact_field = '')
{
	$list = array();

	foreach ($attrs as $name => $value) {
		$list[] = array('name' => $name, 'value' => $value);
	}

	$props = array('tag' => $tag, '_attrs' => $list);

	if ($contact_field !== '') {
		$props['_cf'] = array('contact_field' => $contact_field);
	}

	return array('type' => 'semantic', 'props' => $props, 'children' => array());
}

function sms_consent_test_tree($controls)
{
	return array('type' => 'semantic', 'props' => array('tag' => 'form'), 'children' => $controls);
}

// The box shows only while SMS is on, and only with a number to send to.
function test_sms_consent_offered_needs_sms_and_number()
{
	pg_assert_true(pg_consent_sms_offered(true, true), 'SMS on, number');
	pg_assert_false(pg_consent_sms_offered(true, false), 'SMS off: running sites change nothing');
	pg_assert_false(pg_consent_sms_offered(false, true), 'no number, no box');
	pg_assert_false(pg_consent_sms_offered(false, false), 'neither');
}

// A number counts only when an SMS could go to it.
function test_sms_consent_number_known()
{
	pg_assert_true(pg_consent_sms_number_known('0532 123 45 67'), 'Turkish mobile');
	pg_assert_true(pg_consent_sms_number_known('+44 7700 900123'), 'with a country code');
	pg_assert_false(pg_consent_sms_number_known(''), 'empty');
	pg_assert_false(pg_consent_sms_number_known('0212 123 45 67'), 'Turkish landline');
	pg_assert_false(pg_consent_sms_number_known(array('0532 123 45 67')), 'not a scalar');
}

// The box starts ticked, as the e-mail box does, unless the person refused.
function test_sms_consent_start_state()
{
	pg_assert_true(pg_consent_sms_start(array()), 'no answer yet');
	pg_assert_true(pg_consent_sms_start(array('sms:marketing' => array('status' => 'granted'))), 'granted');
	pg_assert_true(pg_consent_sms_start(array('sms:marketing' => array('status' => 'pending'))), 'pending');
	pg_assert_false(pg_consent_sms_start(array('sms:marketing' => array('status' => 'refused'))), 'refused');
	pg_assert_true(pg_consent_sms_start(array('email:marketing' => array('status' => 'refused'))), 'the e-mail refusal is another answer');
}

// The answer of a post: only when the box was drawn and a number is known.
function test_sms_consent_answers()
{
	pg_assert_same(array('sms:marketing' => true), pg_consent_sms_answers(true, true, '1'), 'ticked');
	pg_assert_same(array('sms:marketing' => false), pg_consent_sms_answers(true, true, ''), 'unticked');
	pg_assert_same(array('sms:marketing' => false), pg_consent_sms_answers(true, true, array('1')), 'not a scalar');
	pg_assert_same(array(), pg_consent_sms_answers(false, true, '1'), 'box not drawn');
	pg_assert_same(array(), pg_consent_sms_answers(true, false, '1'), 'no number');
}

// The writer's rule: an sms row only for an answer that changed.
function test_sms_consent_changes_only_for_a_changed_answer()
{
	$granted = array('sms:marketing' => array('channel' => 'sms', 'purpose' => 'marketing', 'status' => 'granted'));

	pg_assert_same(
		array(array('channel' => 'sms', 'purpose' => 'marketing', 'status' => 'granted')),
		pg_consent_log_changes(array(), array('sms:marketing' => true)),
		'first answer');
	pg_assert_same(array(), pg_consent_log_changes($granted, array('sms:marketing' => true)), 'same answer again');
	pg_assert_same(
		array(
			array('channel' => 'email', 'purpose' => 'marketing', 'status' => 'refused'),
			array('channel' => 'sms', 'purpose' => 'marketing', 'status' => 'refused')),
		pg_consent_log_changes($granted, array('email:marketing' => false, 'sms:marketing' => false)),
		'each channel against its own last answer');
	pg_assert_same(array(), pg_consent_log_changes(array(), array('sms:group:5' => true)), 'lists are e-mail only');
}

// A member widget asks the SMS question with a visible opt_in_sms control
// and a control bound to the contact's mobile number.
function test_sms_consent_member_tree_asks()
{
	$box = sms_consent_test_control('input', array('type' => 'checkbox', 'name' => 'opt_in_sms', 'value' => '1'));
	$mobile = sms_consent_test_control('input', array('type' => 'tel', 'name' => 'mobile'), 'mobile_phone');
	$email_box = sms_consent_test_control('input', array('type' => 'checkbox', 'name' => 'opt_in', 'value' => '1'));

	pg_assert_true(pg_member_tree_asks_opt_in(sms_consent_test_tree(array($box)), 'opt_in_sms'), 'the SMS box');
	pg_assert_false(pg_member_tree_asks_opt_in(sms_consent_test_tree(array($email_box)), 'opt_in_sms'), 'the e-mail box only');
	pg_assert_true(pg_member_tree_asks_opt_in(sms_consent_test_tree(array($email_box))), 'the e-mail box still asks the e-mail question');
	pg_assert_true(pg_member_tree_asks_mobile(sms_consent_test_tree(array($box, $mobile))), 'a mobile control');
	pg_assert_false(pg_member_tree_asks_mobile(sms_consent_test_tree(array($box))), 'no mobile control');
	pg_assert_false(pg_member_tree_asks_mobile(sms_consent_test_tree(array(
		sms_consent_test_control('input', array('type' => 'hidden', 'name' => 'mobile'), 'mobile_phone')))), 'hidden');
}

// Every place that draws the SMS box draws it next to the e-mail box, with
// the same start, and only behind the offered check.
function test_sms_consent_drawn_beside_the_email_box()
{
	$root = dirname(__DIR__) . '/pinegrap/';

	foreach (array('includes/templates/email_preferences.php', 'includes/templates/email_preferences_system.php') as $file) {
		$source = (string) file_get_contents($root . $file);
		$email = strpos($source, 'name="opt_in"');
		$sms = strpos($source, 'name="opt_in_sms"');

		pg_assert_true(($email !== false) && ($sms !== false) && ($sms > $email), $file . ': SMS box after the e-mail box');
		pg_assert_true(strpos($source, '<?php if ($sms_opt_in): ?>') !== false, $file . ': drawn only when offered');
		pg_assert_true(strpos($source, 'name="opt_in_sms_shown"') !== false, $file . ': marks the box as drawn');
	}

	$screen = (string) file_get_contents($root . 'get_email_preferences.php');
	pg_assert_true(strpos($screen, "\$form->set('opt_in_sms', 'value', 1)") !== false, 'preferences: starts from the log');

	$widgets = (string) file_get_contents($root . 'includes/fn/widgets.php');
	pg_assert_same(2, substr_count($widgets, "_pg_member_fill_control(\$tree, 'opt_in_sms', '1', true);"), 'registration and membership widgets start ticked');
	pg_assert_same(2, substr_count($widgets, "_pg_member_sms_box(\$tree, \$mode);"), 'registration and membership widgets drop it when not offered');

	$account = (string) file_get_contents($root . 'includes/fn/widgets_account.php');
	pg_assert_true(strpos($account, "_pg_member_fill_control(\$tree, 'opt_in_sms', '1', \$sms_on);") !== false, 'preferences widget starts from the log');
}

// A checkout post answers the SMS question only from a drawn box, never
// while ghosting, and only with a billing phone an SMS can go to.
function test_sms_consent_checkout_answers()
{
	pg_assert_same(array('sms:marketing' => true), pg_consent_checkout_sms_answers('1', false, '0532 123 45 67', '1'), 'ticked');
	pg_assert_same(array('sms:marketing' => false), pg_consent_checkout_sms_answers('1', false, '0532 123 45 67', ''), 'unticked');
	pg_assert_same(array(), pg_consent_checkout_sms_answers('', false, '0532 123 45 67', ''), 'a layout without the box: silence is no refusal');
	pg_assert_same(array(), pg_consent_checkout_sms_answers(null, false, '0532 123 45 67', '1'), 'nothing posted');
	pg_assert_same(array(), pg_consent_checkout_sms_answers(array('1'), false, '0532 123 45 67', '1'), 'not a scalar');
	pg_assert_same(array(), pg_consent_checkout_sms_answers('1', true, '0532 123 45 67', '1'), 'ghosting');
	pg_assert_same(array(), pg_consent_checkout_sms_answers('1', false, '0212 123 45 67', '1'), 'a landline');
	pg_assert_same(array(), pg_consent_checkout_sms_answers('1', false, '', '1'), 'no number');
}

// The checkout draws the box while it is offered and the buyer has not said
// yes, as the e-mail box is drawn only for a contact not opted in.
function test_sms_consent_checkout_ask()
{
	pg_assert_true(pg_consent_sms_checkout_ask(array(), true), 'no answer yet');
	pg_assert_true(pg_consent_sms_checkout_ask(array('sms:marketing' => array('status' => 'refused')), true), 'refused before');
	pg_assert_false(pg_consent_sms_checkout_ask(array('sms:marketing' => array('status' => 'granted')), true), 'said yes already');
	pg_assert_true(pg_consent_sms_checkout_ask(array('email:marketing' => array('status' => 'granted')), true), 'the e-mail yes is another answer');
	pg_assert_false(pg_consent_sms_checkout_ask(array(), false), 'not offered');
}

// Every checkout screen draws the SMS box beside the e-mail box, marks it,
// starts it ticked, and every handler writes its answer with the e-mail one.
function test_sms_consent_checkout_screens()
{
	$root = dirname(__DIR__) . '/pinegrap/';
	$read = function ($file) use ($root) {
		return (string) file_get_contents($root . $file);
	};
	$after = function ($source, $first, $second) {
		$a = strpos($source, $first);
		$b = strpos($source, $second);

		return ($a !== false) && ($b !== false) && ($b > $a);
	};

	foreach (array('get_billing_information.php', 'get_express_order.php') as $file) {
		$source = $read($file);
		pg_assert_true($after($source, "' . \$output_opt_in . '", "' . \$output_opt_in_sms . '"), $file . ': table row after the e-mail box');
		pg_assert_true(strpos($source, 'pg_consent_sms_checkout_row($form)') !== false, $file . ': table row with its mark');
		pg_assert_true(strpos($source, "'opt_in_sms' => \$opt_in_sms,") !== false, $file . ': layout told');
		pg_assert_true(strpos($source, "'opt_in_sms', '1')") !== false, $file . ': starts ticked');
	}

	foreach (array('includes/templates/billing_information.php', 'includes/templates/express_order.php') as $file) {
		$source = $read($file);
		pg_assert_true($after($source, 'name="opt_in"', 'name="opt_in_sms"'), $file . ': SMS box after the e-mail box');
		pg_assert_true(strpos($source, '<?php if ($opt_in_sms): ?>') !== false, $file . ': drawn only when offered');
		pg_assert_true(strpos($source, 'name="opt_in_sms_shown"') !== false, $file . ': marks the box as drawn');
	}

	pg_assert_true(strpos(pg_consent_sms_checkout_row(new sms_consent_test_form()), 'name="opt_in_sms_shown" value="1"') !== false, 'the table row marks itself');

	foreach (array('billing_information.php', 'express_order.php', 'submit_order.php') as $file) {
		$source = $read($file);
		pg_assert_true(strpos($source, "'opt_in_sms', 'opt_in_sms_shown'") !== false, $file . ': reads the box and its mark');
		pg_assert_true(strpos($source, "pg_consent_checkout_sms_answers(\$liveform->get_field_value('opt_in_sms_shown'), \$ghost, \$liveform->get_field_value('billing_phone_number'), \$liveform->get_field_value('opt_in_sms'))") !== false, $file . ': written with the e-mail answer');
	}

	$bi = $read('includes/fn/widgets_billing_information.php');
	pg_assert_true(strpos($bi, "'opt_in_sms'      => !\$bi_flags['shows_opt_in_sms'],") !== false, 'billing widget drops the box when not offered');
	pg_assert_true(strpos($bi, "_eo_tree_asks_opt_in(\$split['static_tree'], 'opt_in_sms')") !== false, 'billing widget marks a drawn box only');

	$cart = $read('includes/fn/widgets_cart.php');
	pg_assert_true(strpos($cart, "'shows_opt_in_sms'   => \$_eo_sms_box,") !== false, 'express order widget flag');
	pg_assert_true(strpos($cart, "\$_eo_sms_box && _eo_tree_asks_opt_in(\$tree_decoded_for_eo, 'opt_in_sms')") !== false, 'express order widget marks a drawn box only');
}

// The express order widget's default tree draws both boxes, the SMS one
// behind its flag; the designer's starter trees are its twins.
function test_sms_consent_default_trees()
{
	$tree = _eo_default_designer_tree();
	pg_assert_true(_eo_tree_asks_opt_in($tree), 'express order: e-mail box');
	pg_assert_true(_eo_tree_asks_opt_in($tree, 'opt_in_sms'), 'express order: SMS box');
	pg_assert_false(_eo_tree_asks_opt_in($tree, 'opt_in_sms_missing'), 'only the named box');

	$flags = array_fill_keys(array('has_shipping', 'has_no_shipping', 'has_single_recipient', 'has_terms'), true);
	$off = $tree;
	_eo_apply_visibility_bindings($off, $flags + array('shows_opt_in_sms' => false));
	pg_assert_false(_eo_tree_asks_opt_in($off, 'opt_in_sms'), 'express order: not offered, not drawn');
	pg_assert_true(_eo_tree_asks_opt_in($off), 'express order: the e-mail box stays');

	$js = (string) file_get_contents(dirname(__DIR__) . '/pinegrap/assets/js/style_designer.src.js');
	pg_assert_true(strpos($js, "checkRow('opt_in_sms', _sdT('I want to receive campaign and offer messages by SMS.'), 'pg-eo-opt-in-sms mb-3', 'shows_opt_in_sms')") !== false, 'express order starter twin');
	pg_assert_true(strpos($js, "biCheck('opt_in_sms', _sdT('I want to receive campaign and offer messages by SMS.'), '_optinsms', '', 'shows_opt_in_sms')") !== false, 'billing information starter');
	pg_assert_same(2, substr_count($js, "['shows_opt_in_sms',"), 'flag offered in both widgets');
}

// Enough of a liveform for pg_consent_sms_checkout_row().
class sms_consent_test_form
{
	public function output_field($attrs)
	{
		return '<input name="' . $attrs['name'] . '">';
	}
}
