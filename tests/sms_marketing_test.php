<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the marketing SMS kind (includes/fn/sms.php): the sender identity
 * and the type label appended to a marketing message and never to a
 * transactional one, the refusal of a message without the identity, the
 * consent rule for a number, and the settings card's decision. No database
 * and no provider.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function sms_marketing_test_settings($overrides = array())
{
	return $overrides + array(
		'trade_name' => 'Örnek Ticaret A.Ş.',
		'mersis'     => '0123456789012345',
		'label'      => '',
	);
}

function sms_marketing_test_post($overrides = array())
{
	return $overrides + array(
		'sms_msg_card'          => '1',
		'sms_mkt_trade_name'    => '',
		'sms_mkt_mersis'        => '',
		'sms_mkt_label'         => '',
	);
}

// The identity line and the label go after the text, the label being the
// built-in one while the setting is empty.
function test_sms_marketing_text_appends_identity_and_label()
{
	$text = pg_sms_marketing_text('Yaz indirimi başladı', sms_marketing_test_settings());

	pg_assert_same(
		"Yaz indirimi başladı\nÖrnek Ticaret A.Ş. " . lang(array('string' => 'MERSİS No: {var:1}', 'vars' => array('0123456789012345'))) . "\n" . pg_sms_marketing_label_default(),
		$text);

	$own = pg_sms_marketing_text('Kampanya', sms_marketing_test_settings(array('mersis' => '', 'label' => 'Ticari ileti')));
	pg_assert_same("Kampanya\nÖrnek Ticaret A.Ş.\nTicari ileti", $own);
}

// A text that already ends with the footer (a queued body checked again at
// send time) does not get it twice.
function test_sms_marketing_text_is_appended_once()
{
	$settings = sms_marketing_test_settings();
	$once = pg_sms_marketing_text('Kampanya', $settings);

	pg_assert_same($once, pg_sms_marketing_text($once, $settings));
}

// A queued body already carries the footer it was queued with. Checked again
// when it is sent after the identity changed, it goes out as it was queued,
// not with a second footer below the first.
function test_sms_check_queued_body_keeps_its_footer()
{
	$queued = pg_sms_check('0532 123 45 67', 'Kampanya', 'marketing', sms_marketing_test_settings());
	$changed = sms_marketing_test_settings(array('trade_name' => 'Yeni Unvan Ltd.'));

	$again = pg_sms_check(pg_sms_dial($queued['number']), $queued['text'], 'marketing', $changed, true);

	pg_assert_true($again['ok']);
	pg_assert_same($queued['text'], $again['text']);

	// A new message takes the new identity.
	$fresh = pg_sms_check('0532 123 45 67', 'Kampanya', 'marketing', $changed);
	pg_assert_same(pg_sms_marketing_text('Kampanya', $changed), $fresh['text']);
}

function test_sms_marketing_identity_filled()
{
	pg_assert_true(pg_sms_marketing_identity_filled(sms_marketing_test_settings()));
	pg_assert_true(pg_sms_marketing_identity_filled(sms_marketing_test_settings(array('trade_name' => ''))));
	pg_assert_true(pg_sms_marketing_identity_filled(sms_marketing_test_settings(array('mersis' => ''))));
	pg_assert_false(pg_sms_marketing_identity_filled(sms_marketing_test_settings(array('trade_name' => '', 'mersis' => ''))));
	pg_assert_false(pg_sms_marketing_identity_filled(pg_sms_marketing_normalize(null)));
}

// The check appends the footer to a marketing message and refuses one while
// the identity is empty.
function test_sms_check_marketing_needs_the_identity()
{
	$check = pg_sms_check('0532 123 45 67', 'Kampanya', 'marketing', sms_marketing_test_settings());

	pg_assert_true($check['ok']);
	pg_assert_same('905321234567', $check['number']);
	pg_assert_same(pg_sms_marketing_text('Kampanya', sms_marketing_test_settings()), $check['text']);

	$refused = pg_sms_check('0532 123 45 67', 'Kampanya', 'marketing', sms_marketing_test_settings(array('trade_name' => '', 'mersis' => '')));
	pg_assert_false($refused['ok']);
	pg_assert_same(pg_sms_marketing_identity_missing(), $refused['error']);

	// No settings passed and no database: the identity reads empty.
	$unset = pg_sms_check('0532 123 45 67', 'Kampanya', 'marketing');
	pg_assert_false($unset['ok']);
	pg_assert_same(pg_sms_marketing_identity_missing(), $unset['error']);
}

// Transactional kinds never carry the identity or the label, whatever the
// settings hold.
function test_sms_check_transactional_kinds_get_no_footer()
{
	foreach (pg_sms_kinds() as $kind => $commercial) {
		if ($commercial) {
			continue;
		}

		$check = pg_sms_check('0532 123 45 67', 'Siparişiniz alındı', $kind, sms_marketing_test_settings());

		pg_assert_true($check['ok'], $kind);
		pg_assert_same('Siparişiniz alındı', $check['text'], $kind);
	}
}

// The footer counts towards the longest message.
function test_sms_check_marketing_length_includes_the_footer()
{
	$settings = sms_marketing_test_settings();
	$room = pg_sms_max_length() - mb_strlen(pg_sms_marketing_text('', $settings), 'UTF-8') - 1;

	pg_assert_true(pg_sms_check('905321234567', str_repeat('a', $room), 'marketing', $settings)['ok']);
	pg_assert_false(pg_sms_check('905321234567', str_repeat('a', $room + 1), 'marketing', $settings)['ok']);
}

// The latest answer of each contact, whatever the order of the rows.
function test_sms_marketing_answers_latest_per_contact()
{
	$answers = pg_sms_marketing_answers(array(
		array('id' => 3, 'created_at' => 200, 'contact_id' => 7, 'channel' => 'sms', 'purpose' => 'marketing', 'status' => 'refused'),
		array('id' => 1, 'created_at' => 100, 'contact_id' => 7, 'channel' => 'sms', 'purpose' => 'marketing', 'status' => 'granted'),
		array('id' => 2, 'created_at' => 150, 'contact_id' => 9, 'channel' => 'sms', 'purpose' => 'marketing', 'status' => 'granted'),
	));

	pg_assert_same(array(7 => 'refused', 9 => 'granted'), $answers);
}

// Consent given here, no refusal here or in İYS: sent. Each other case has
// its own reason.
function test_sms_marketing_refusal_rule()
{
	pg_assert_same('', pg_sms_marketing_refusal(array(7 => 'granted'), array(7 => '')));
	pg_assert_same('', pg_sms_marketing_refusal(array(7 => 'granted'), array(7 => 'approved')));

	$none = lang('The recipient has not consented to marketing messages by SMS.');
	pg_assert_same($none, pg_sms_marketing_refusal(array(), array()));
	pg_assert_same($none, pg_sms_marketing_refusal(array(), array(7 => 'approved')), 'İYS alone is no consent here');

	pg_assert_same(lang('The recipient refused marketing messages by SMS.'), pg_sms_marketing_refusal(array(7 => 'granted', 9 => 'refused'), array()));
	pg_assert_same(lang('The recipient refused marketing messages by SMS.'), pg_sms_marketing_refusal(array(7 => 'refused'), array()));

	pg_assert_same(lang('İYS records that the recipient refused marketing messages by SMS.'), pg_sms_marketing_refusal(array(7 => 'granted'), array(7 => 'refused')));
	pg_assert_same(lang('İYS records that the recipient refused marketing messages by SMS.'), pg_sms_marketing_refusal(array(7 => 'granted'), array(7 => '', 8 => 'refused')));
}

// The stored forms one query looks a number up by; each reads back as it.
function test_sms_number_variants()
{
	foreach (pg_sms_number_variants('905321234567') as $variant) {
		pg_assert_same('905321234567', pg_sms_number($variant), $variant);
	}

	pg_assert_same(array('+4915112345678', '004915112345678'), pg_sms_number_variants('4915112345678'));
	pg_assert_same(array(), pg_sms_number_variants('abc'));
	pg_assert_same(pg_sms_number_variants('905321234567'), pg_iys_number_variants('905321234567'));
	pg_assert_same(array(), pg_iys_number_variants('4915112345678'));
}

// The card: trimmed values, a MERSİS number of 16 digits, the built-in label
// sent back unchanged stored empty.
function test_sms_marketing_settings_decide()
{
	$before = pg_sms_marketing_normalize(null);

	pg_assert_same(null, pg_sms_marketing_settings_decide(array(), $before));

	$unchanged = pg_sms_marketing_settings_decide(sms_marketing_test_post(array('sms_mkt_label' => pg_sms_marketing_label_default())), $before);
	pg_assert_false($unchanged['changed']);
	pg_assert_same(array(), $unchanged['marks']);

	$filled = pg_sms_marketing_settings_decide(sms_marketing_test_post(array(
		'sms_mkt_trade_name' => '  Örnek Ticaret A.Ş. ',
		'sms_mkt_mersis'     => '0123 4567 8901 2345',
	)), $before);
	pg_assert_true($filled['changed']);
	pg_assert_same(array('trade_name' => 'Örnek Ticaret A.Ş.', 'mersis' => '0123456789012345', 'label' => ''), $filled['marketing']);
	pg_assert_same(array(), $filled['marks']);

	$wrong = pg_sms_marketing_settings_decide(sms_marketing_test_post(array('sms_mkt_mersis' => '12345')), $before);
	pg_assert_true(isset($wrong['marks']['sms_mkt_mersis']));
}
