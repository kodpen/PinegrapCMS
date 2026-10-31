<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The İYS card of Settings › Communication (includes/iys/settings.php)
 * describes what the daily run does: one switch carries both the e-mail and
 * the SMS consents, and a consent İYS does not know stays valid; only a
 * refusal held in İYS stops sending. These checks pin the card's texts.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function iys_card_text_source()
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/iys/settings.php');
}

// Returns the lang() literals of the card's markup function.
function iys_card_text_strings()
{
	$source = iys_card_text_source();
	$start = strpos($source, 'function pg_iys_settings_fields(');
	$end = ($start === false) ? false : strpos($source, "\nfunction ", $start + 1);
	$body = ($start === false) ? '' : (($end === false) ? substr($source, $start) : substr($source, $start, $end - $start));

	preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $body, $matches);

	return array_map('stripslashes', $matches[1]);
}

function test_iys_card_text_names_sms()
{
	$texts = implode("\n", iys_card_text_strings());

	pg_assert_contains('SMS', $texts, 'the card names the SMS channel');
	pg_assert_true(strpos(iys_card_text_source(), "lang('E-mail and SMS')") !== false, 'the switch label covers SMS');
	pg_assert_false(in_array('E-mail', iys_card_text_strings(), true), 'the switch is no longer labelled e-mail alone');
}

function test_iys_card_text_unknown_consent_stays_valid()
{
	$texts = implode("\n", iys_card_text_strings());

	pg_assert_false(strpos($texts, 'counts as not given') !== false, 'a consent missing from İYS is not called not given');
	pg_assert_contains('stays valid', $texts, 'a consent İYS does not know stays valid');
}

function test_iys_card_text_keeps_switch_key()
{
	pg_assert_contains('name="iys_email" id="iys_email"', iys_card_text_source(), 'the stored switch key stays iys_email');
}
