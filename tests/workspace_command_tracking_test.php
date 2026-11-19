<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the /tracking (/kargo) channel command
 * (ws_command_tracking() in includes/workspace/commands.php): the card
 * shows the order's numbers beside the new ones, Save writes only over the
 * numbers the card showed, no mail goes to the customer, and only a person
 * the order screen lets write is offered it.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(__DIR__ . '/helpers/source_reader.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/changes.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/quick_edit.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/commands.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/command_registry.php');

// The order's tracking_numbers field as ws_change_types() defines it (that
// function probes the schema, which a test without a database cannot run).
function pg_test_tracking_field()
{
	$field = array('lines', array(20, 100), lang('Tracking Numbers'), '__tracking_numbers');
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/changes.php');

	pg_assert_contains("'tracking_numbers'    => array('lines', array(20, 100), lang('Tracking Numbers'), '__tracking_numbers'),", $source, 'the field is still the one written here');

	return $field;
}

function test_workspace_command_tracking_card_shows_the_old_numbers_beside_the_new()
{
	$field = pg_test_tracking_field();
	$card = ws_command_tracking_preview('<span>chip</span>', $field, array('AA111', 'BB222'), array('CC333'));

	pg_assert_same('record_preview', $card['type'], 'drawn as the /price card is');
	pg_assert_same('<span>chip</span>', $card['preview']['record_html'], 'the order\'s chip');
	pg_assert_same((string) $field[2], $card['preview']['label'], 'the field\'s label');
	pg_assert_same('AA111, BB222', $card['preview']['from'], 'the numbers the order holds now');
	pg_assert_same('CC333', $card['preview']['to'], 'the new numbers');
	pg_assert_same(lang('The customer is not e-mailed about these numbers.'), $card['preview']['note'], 'the card says the customer is not mailed');
	pg_assert_same("AA111\nBB222", $card['from'], 'Save sends back what the card showed, one number a line');

	$first = ws_command_tracking_preview('', $field, array(), array('CC333', 'DD444'));

	pg_assert_same(lang('(empty)'), $first['preview']['from'], 'an order without numbers says so');
	pg_assert_same('CC333, DD444', $first['preview']['to']);
	pg_assert_same('', $first['from']);
}

function test_workspace_command_tracking_reads_the_order_and_numbers()
{
	$field = pg_test_tracking_field();

	pg_assert_same(array(42, '1Z999AA1, 2B'), ws_command_record_ref('<#order:42> 1Z999AA1, 2B', 'order'), 'the order\'s tag, then the numbers');
	pg_assert_same(array(0, '<#product:7> 1Z999AA1'), ws_command_record_ref('<#product:7> 1Z999AA1', 'order'), 'a product is not an order');
	pg_assert_same(array('1Z999AA1', '2B'), ws_command_tracking_numbers($field, ' 1Z999AA1 , 2B, 1Z999AA1 '), 'commas part them, a repeat is kept once');
	pg_assert_same(null, ws_command_tracking_numbers($field, ''), 'no number');
	pg_assert_same(null, ws_command_tracking_numbers($field, ' , '), 'only commas');
	pg_assert_same(null, ws_command_tracking_numbers($field, str_repeat('9', 101)), 'longer than the field keeps');
}

function test_workspace_command_tracking_saves_only_over_what_the_card_showed()
{
	$field = pg_test_tracking_field();

	// ws_quick_edit_save() lays the card's value against the order as it is
	// when Save is pressed.
	pg_assert_false(ws_quick_edit_moved($field, "AA111\nBB222", array('AA111', 'BB222')), 'unchanged: written');
	pg_assert_true(ws_quick_edit_moved($field, "AA111\nBB222", array('AA111')), 'a number taken away meanwhile: refused');
	pg_assert_true(ws_quick_edit_moved($field, "AA111", array('AA111', 'ZZ999')), 'a number added meanwhile: refused');
	pg_assert_false(ws_quick_edit_moved($field, '', array()), 'still empty: written');
	pg_assert_true(ws_quick_edit_moved($field, '', array('ZZ999')), 'numbered meanwhile: refused');

	$body = pg_test_function_source('ws_command_tracking');

	pg_assert_contains("if (!isset(\$options['from']) || !is_scalar(\$options['from']))", $body, 'a Save without the card\'s value writes nothing');
	pg_assert_contains("ws_quick_edit_save(\$viewer, 'order', \$id, array('tracking_numbers' => \$numbers), array('tracking_numbers' => (string) \$options['from'])", $body, 'only tracking_numbers, checked against the card\'s value');
	pg_assert_true(strpos($body, 'ws_quick_edit_save(') > strpos($body, "if (empty(\$options['confirm']))"), 'nothing is written before Save');
}

function test_workspace_command_tracking_sends_no_mail()
{
	$body = pg_test_function_source('ws_command_tracking');

	foreach (array('email(', 'create_auto_email_campaigns', 'send_email', 'mail(') as $call) {
		pg_assert_true(strpos($body, $call) === false, 'the command calls no ' . $call);
	}

	// The order's writer hands the numbers to the order screen's save with
	// shipped off, the switch that keeps the shipping mail back.
	$writer = pg_test_function_source('ws_change_write_order');

	pg_assert_true((bool) preg_match("/'shipped'\\s*=>\\s*false,/", $writer), 'written with shipped off');
	pg_assert_true(strpos($writer, 'create_auto_email_campaigns') === false, 'the writer mails nobody');
}

function test_workspace_command_tracking_needs_the_order_right()
{
	pg_assert_true(ws_command_offered(null, 'tracking'), 'listed when nobody is named');

	// Offered, and let through, only by the order kind's right: the
	// e-commerce right the order screen asks for (role 2 or below, or
	// user_manage_ecommerce). The save checks it again (ws_quick_edit_check()).
	pg_assert_contains("ws_change_allowed(\$viewer, 'order', 0)", pg_test_function_source('ws_command_gate_order'), 'the gate asks the order kind\'s right');
	pg_assert_contains("ws_quick_edit_check(\$viewer, 'order', \$id)", pg_test_function_source('ws_command_tracking'), 'the run asks it for the order named');

	$changes = file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/changes.php');
	$order = substr($changes, strpos($changes, "'order' => array("), 200);

	pg_assert_contains("'right'   => 'ecommerce',", $order, 'the order kind needs the e-commerce right');

	$registry = ws_command_registry();

	pg_assert_same('ws_command_gate_order', $registry['tracking']['gate']);
	pg_assert_same(array('kargo', 'tracking'), $registry['tracking']['names']);
	pg_assert_same('tracking', ws_command_table()['kargo']);
}
