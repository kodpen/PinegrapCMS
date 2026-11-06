<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for replace_variables() (includes/fn/mail.php): a filled-in value is
 * never read again as a mail-merge field, so a value holding ^^field^^ text
 * neither takes another field's data nor leaves that field's place raw.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// A buyer-typed sender name holding ^^code^^ stays as typed; the code goes
// into its own place.
function test_mail_merge_value_naming_a_later_field_kept_as_written()
{
	$fields = array(
		array('name' => 'from_name', 'data' => '^^code^^', 'type' => ''),
		array('name' => 'code', 'data' => 'GC-1234', 'type' => ''));

	$out = replace_variables(array(
		'content' => 'From: ^^from_name^^ Code: ^^code^^',
		'fields' => $fields,
		'format' => 'plain_text'));

	pg_assert_same('From: ^^code^^ Code: GC-1234', $out, 'plain text');

	$out = replace_variables(array(
		'content' => '<p>From: ^^from_name^^</p><p>Code: ^^code^^</p>',
		'fields' => $fields,
		'format' => 'html'));

	pg_assert_same('<p>From: ^^code^^</p><p>Code: GC-1234</p>', $out, 'html');
}

// A value holding a field with a date format does not take the date either.
function test_mail_merge_value_naming_a_dated_field_kept_as_written()
{
	$out = replace_variables(array(
		'content' => 'Note: ^^note^^ Day: ^^day^^%%Y%%',
		'fields' => array(
			array('name' => 'note', 'data' => '^^day^^%%Y%%', 'type' => ''),
			array('name' => 'day', 'data' => '2026-11-05', 'type' => 'date')),
		'format' => 'plain_text'));

	pg_assert_same('Note: ^^day^^%%Y%% Day: 2026', $out, 'dated field');
}

// The same field twice, each with its own date format, and an unknown field
// left as written.
function test_mail_merge_repeated_field_and_unknown_field()
{
	$out = replace_variables(array(
		'content' => '^^day^^%%Y%% / ^^day^^%%m%% / ^^name^^ / ^^other^^ / $1',
		'fields' => array(
			array('name' => 'day', 'data' => '2026-11-05', 'type' => 'date'),
			array('name' => 'name', 'data' => 'A $1 \\ B', 'type' => '')),
		'format' => 'plain_text'));

	pg_assert_same('2026 / 11 / A $1 \\ B / ^^other^^ / $1', $out, 'each place filled once');
}
