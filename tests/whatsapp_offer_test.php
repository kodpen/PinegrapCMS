<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a WhatsApp template's offer code: the offer settings read,
 * posted and checked, the key made for one recipient, the code filled into
 * the components, the copy-code button in the designer and in the senders'
 * slots, and the consent gate at the send. No database and no call to Meta.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/whatsapp_offer.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/whatsapp_design.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/whatsapp_messages.php');

function whatsapp_offer_test_design($overrides = array())
{
	return pg_whatsapp_design_posted(array_merge(array(
		'name' => 'thanks_code', 'language' => 'tr', 'category' => 'MARKETING',
		'body' => 'Hello {{1}}, your code is {{2}} for next time.',
		'label_1' => 'Customer\'s name', 'sample_1' => 'Ayşe', 'label_2' => 'Offer code', 'sample_2' => 'K7M29QXA',
		'button_type' => array('COPY_CODE'), 'button_text' => array(''), 'button_value' => array(''), 'button_sample' => array('K7M29QXA'),
		'offer_mode' => 'key', 'offer_code' => 'NOV10', 'offer_days' => '14', 'offer_variable' => '2',
	), $overrides), pg_whatsapp_design_new());
}

function test_whatsapp_offer_read()
{
	pg_assert_same(array('mode' => '', 'code' => '', 'days' => 0, 'variable' => ''), pg_whatsapp_offer_read(''), 'nothing stored');
	pg_assert_same(array('mode' => '', 'code' => '', 'days' => 0, 'variable' => ''), pg_whatsapp_offer_read('{"mode":"other","code":"X"}'), 'an unknown mode is no offer');

	$offer = pg_whatsapp_offer_read('{"mode":"key","code":"NOV10","days":"14","variable":"2"}');
	pg_assert_same(array('mode' => 'key', 'code' => 'NOV10', 'days' => 14, 'variable' => '2'), $offer, 'a key per recipient');
	pg_assert_same($offer, pg_whatsapp_offer_read(pg_whatsapp_offer_json($offer)), 'kept as it was read');
	pg_assert_same($offer, pg_whatsapp_offer_read($offer), 'an array is read too');

	$shared = pg_whatsapp_offer_read(array('mode' => 'shared', 'code' => 'NOV10', 'days' => 9, 'variable' => ''));
	pg_assert_same(0, $shared['days'], 'a shared code has no validity of its own');
}

function test_whatsapp_offer_posted()
{
	$design = whatsapp_offer_test_design();
	pg_assert_same(array('mode' => 'key', 'code' => 'NOV10', 'days' => 14, 'variable' => '2'), $design['offer'], 'posted with the design');
	pg_assert_same(array('type' => 'COPY_CODE', 'example' => 'K7M29QXA'), $design['buttons'][0], 'a copy-code button keeps its sample code');

	$design = whatsapp_offer_test_design(array('offer_mode' => ''));
	pg_assert_same('', $design['offer']['mode'], 'no offer');
	pg_assert_same('', $design['offer']['code'], 'None clears the offer');
}

function test_whatsapp_offer_check()
{
	pg_assert_same(array(), pg_whatsapp_design_check(whatsapp_offer_test_design()), 'a valid draft with a key per recipient');
	pg_assert_same(array(), pg_whatsapp_design_check(whatsapp_offer_test_design(array('offer_mode' => 'shared', 'offer_days' => ''))), 'a shared code');

	$errors = pg_whatsapp_design_check(whatsapp_offer_test_design(array('offer_mode' => 'key', 'offer_code' => '')));
	pg_assert_true(isset($errors['offer_code']), 'an offer must be chosen');

	foreach (array('0', '366', 'x') as $days) {
		$errors = pg_whatsapp_design_check(whatsapp_offer_test_design(array('offer_days' => $days)));
		pg_assert_true(isset($errors['offer_days']), 'validity ' . $days . ' refused');
	}

	$errors = pg_whatsapp_design_check(whatsapp_offer_test_design(array('label_2' => 'Code', 'offer_variable' => '3')));
	pg_assert_true(isset($errors['offer_variable']), 'a variable the message does not hold');

	$errors = pg_whatsapp_design_check(whatsapp_offer_test_design(array('offer_mode' => '')));
	pg_assert_true(isset($errors['button_0']), 'a copy-code button needs an offer');

	$errors = pg_whatsapp_design_check(whatsapp_offer_test_design(array('category' => 'UTILITY')));
	pg_assert_true(isset($errors['category']), 'an offer code makes a marketing template');

	$errors = pg_whatsapp_design_check(whatsapp_offer_test_design(array('label_2' => 'Code', 'offer_variable' => '', 'button_type' => array('QUICK_REPLY'), 'button_text' => array('More'))));
	pg_assert_true(isset($errors['offer_variable']), 'the code must go somewhere');

	$errors = pg_whatsapp_design_check(whatsapp_offer_test_design(array('button_sample' => array('has space'))));
	pg_assert_true(isset($errors['button_0']), 'a sample code without spaces');

	$errors = pg_whatsapp_design_check(whatsapp_offer_test_design(array(
		'button_type' => array('COPY_CODE', 'COPY_CODE'), 'button_text' => array('', ''), 'button_value' => array('', ''), 'button_sample' => array('A1', 'B2'))));
	pg_assert_true(isset($errors['button_1']), 'one copy-code button');
}

function test_whatsapp_offer_template_check()
{
	// A template WhatsApp holds: only the offer is checked, its category is Meta's.
	$components = array(
		array('type' => 'BODY', 'text' => 'Code {{1}} for you.'),
		array('type' => 'BUTTONS', 'buttons' => array(array('type' => 'COPY_CODE', 'example' => 'A1'))),
	);
	pg_assert_same(array(), pg_whatsapp_offer_check(array('mode' => 'shared', 'code' => 'NOV10', 'days' => 0, 'variable' => '1'), $components), 'shared, variable and button');
	pg_assert_same(array(), pg_whatsapp_offer_check(array('mode' => '', 'code' => '', 'days' => 0, 'variable' => ''), array($components[0])), 'no offer, nothing to fill');
	pg_assert_true(isset(pg_whatsapp_offer_check(array('mode' => '', 'code' => '', 'days' => 0, 'variable' => ''), $components)['offer_mode']), 'a copy-code button without an offer');
}

function test_whatsapp_offer_key_code()
{
	$seen = array();

	for ($i = 0; $i < 50; $i++) {
		$code = pg_whatsapp_offer_key_code();
		pg_assert_true((bool) preg_match('/^[ABCDEFGHJKLMNPQRSTUVWXYZ23456789]{10}$/D', $code), 'ten letters and digits without 0, O, 1 and I: ' . $code);
		$seen[$code] = true;
	}

	pg_assert_true(count($seen) > 45, 'random');
}

function test_whatsapp_offer_expiration()
{
	$now = mktime(12, 0, 0, 11, 14, 2026);
	pg_assert_same('2026-11-28', pg_whatsapp_offer_expiration(14, $now), 'fourteen days from the day it is queued');
	pg_assert_same('0000-00-00', pg_whatsapp_offer_expiration(0, $now), 'no validity, no end');
}

function test_whatsapp_offer_slots()
{
	$components = array(
		array('type' => 'BODY', 'text' => 'Hello {{1}}, code {{2}}, see {{3}}.'),
		array('type' => 'BUTTONS', 'buttons' => array(array('type' => 'COPY_CODE', 'example' => 'A1'), array('type' => 'QUICK_REPLY', 'text' => 'Stop'))),
	);
	$offer = array('mode' => 'key', 'code' => 'NOV10', 'days' => 14, 'variable' => '2');

	$read = pg_whatsapp_message_slots($components);
	pg_assert_true($read['unsupported'] !== '', 'a copy-code button without an offer cannot be filled');

	$read = pg_whatsapp_message_slots($components, pg_whatsapp_offer_json($offer));
	pg_assert_same('', $read['unsupported'], 'with an offer it can');
	pg_assert_same(array('body:1', 'body:3'), array_column($read['slots'], 'key'), 'the offer code is no sender\'s choice');

	$template = array('status' => 'APPROVED', 'parameter_format' => 'POSITIONAL', 'components' => $components, 'offer' => pg_whatsapp_offer_json($offer));
	$filled = pg_whatsapp_message_components($template, array('body:1' => 'name', 'body:3' => 'link'), array('name' => 'Ayşe', 'link' => 'x.com'));
	pg_assert_true($filled['ok'], 'filled');
	pg_assert_same(array('Ayşe', PG_WHATSAPP_OFFER_PLACEHOLDER, 'x.com'), array_column($filled['components'][0]['parameters'], 'text'), 'the code\'s place kept in order');
	pg_assert_same(
		array('type' => 'button', 'sub_type' => 'COPY_CODE', 'index' => '0', 'parameters' => array(array('type' => 'coupon_code', 'coupon_code' => PG_WHATSAPP_OFFER_PLACEHOLDER))),
		$filled['components'][1], 'the copy-code button');
	pg_assert_true(pg_whatsapp_offer_needed($filled['components']), 'the send needs a code');

	$code = pg_whatsapp_offer_fill($filled['components'], 'K7M29QXAB2');
	pg_assert_same('K7M29QXAB2', $code[0]['parameters'][1]['text'], 'the body variable');
	pg_assert_same('K7M29QXAB2', $code[1]['parameters'][0]['coupon_code'], 'the button');
	pg_assert_true(!pg_whatsapp_offer_needed($code), 'nothing left to fill');
}

function test_whatsapp_offer_send_gate()
{
	pg_assert_true(pg_whatsapp_queue_marketing('MARKETING', 'UTILITY'), 'queued as marketing');
	pg_assert_true(pg_whatsapp_queue_marketing('UTILITY', 'MARKETING'), 'moved to marketing by Meta since');
	pg_assert_true(!pg_whatsapp_queue_marketing('UTILITY', ''), 'a utility template without a row');
	pg_assert_true(!pg_whatsapp_queue_marketing('UTILITY', 'UTILITY'), 'still a utility template');
}

function test_whatsapp_offer_design_test_send()
{
	$design = whatsapp_offer_test_design();
	$design['status'] = 'APPROVED';
	$design['components'] = pg_whatsapp_design_components($design);
	$sent = pg_whatsapp_design_send_components($design);
	pg_assert_true($sent['ok'], 'a copy-code button can be tested');
	$last = end($sent['components']);
	pg_assert_same('COPY_CODE', $last['sub_type'], 'with its sample code');
	pg_assert_same('K7M29QXA', $last['parameters'][0]['coupon_code'], 'the sample');
}
