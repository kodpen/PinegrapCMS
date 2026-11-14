<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the WhatsApp template designer: a row read into a design, a
 * posted form, the draft check, the components and samples kept, the preview
 * formatting and the parameters a test send fills. No database and no call
 * to Meta.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/whatsapp_design.php');

function whatsapp_design_test_posted($overrides = array())
{
	return pg_whatsapp_design_posted(array_merge(array(
		'name' => 'thank_you', 'language' => 'tr', 'category' => 'MARKETING',
		'body' => "Hello {{1}}, thanks for order {{2}}!\r\nSee you.", 'footer' => 'Kupa',
		'label_1' => 'Customer\'s name', 'sample_1' => 'Ayşe', 'label_2' => 'Order number', 'sample_2' => '1042',
	), $overrides), pg_whatsapp_design_new());
}

function test_whatsapp_design_variables()
{
	pg_assert_same(array('1', '2'), pg_whatsapp_design_variables('A {{1}} b {{ 2 }} c {{1}}'), 'in order, once each');
	pg_assert_same(array('first_name'), pg_whatsapp_design_variables('Hi {{first_name}}'), 'named');
	pg_assert_same(array(), pg_whatsapp_design_variables('No {variable} here {{}}'), 'none');
}

function test_whatsapp_design_posted()
{
	$design = whatsapp_design_test_posted(array('name' => ' Thank_You ', 'category' => 'marketing', 'sample_3' => 'ignored', 'body' => array('list')));
	pg_assert_same('thank_you', $design['name'], 'name trimmed and lower-cased');
	pg_assert_same('MARKETING', $design['category'], 'category upper-cased');
	pg_assert_same('', $design['body'], 'a list counts as not given');
	pg_assert_same(array(), $design['samples'], 'no variables, no samples');

	$design = whatsapp_design_test_posted();
	pg_assert_same("Hello {{1}}, thanks for order {{2}}!\nSee you.", $design['body'], 'line breaks as \\n');
	pg_assert_same(array('1' => 'Ayşe', '2' => '1042'), $design['samples'], 'samples by variable');
	pg_assert_same('Order number', $design['labels']['2'], 'labels by variable');
}

function test_whatsapp_design_check()
{
	pg_assert_same(array(), pg_whatsapp_design_check(whatsapp_design_test_posted()), 'a valid draft');

	$errors = pg_whatsapp_design_check(whatsapp_design_test_posted(array('name' => 'thank you!', 'language' => 'xx', 'category' => 'OTHER')));
	pg_assert_true(isset($errors['name'], $errors['language'], $errors['category']), 'name, language and type refused');

	$errors = pg_whatsapp_design_check(whatsapp_design_test_posted(array('body' => '')));
	pg_assert_true(isset($errors['body']), 'an empty message');

	$errors = pg_whatsapp_design_check(whatsapp_design_test_posted(array('body' => str_repeat('a', 1025))));
	pg_assert_true(isset($errors['body']), 'over 1024 characters');
	pg_assert_same(array(), pg_whatsapp_design_check(whatsapp_design_test_posted(array('body' => str_repeat('ş', 1024)))), '1024 multibyte characters fit');

	$errors = pg_whatsapp_design_check(whatsapp_design_test_posted(array('body' => 'Hello {{1}} and {{3}} here', 'sample_3' => 'x')));
	pg_assert_true(isset($errors['body']), 'a gap in the numbering');

	$errors = pg_whatsapp_design_check(whatsapp_design_test_posted(array('body' => 'Hello {{name}} here')));
	pg_assert_true(isset($errors['body']), 'a named variable in a draft');

	$errors = pg_whatsapp_design_check(whatsapp_design_test_posted(array('body' => 'Hello {{1} here')));
	pg_assert_true(isset($errors['body']), 'a broken variable');

	$errors = pg_whatsapp_design_check(whatsapp_design_test_posted(array('body' => '{{1}}, hello there')));
	pg_assert_true(isset($errors['body']), 'starts with a variable');

	$errors = pg_whatsapp_design_check(whatsapp_design_test_posted(array('body' => 'Hello there {{1}}')));
	pg_assert_true(isset($errors['body']), 'ends with a variable');

	$errors = pg_whatsapp_design_check(whatsapp_design_test_posted(array('sample_2' => '')));
	pg_assert_true(isset($errors['sample_2']) && !isset($errors['sample_1']), 'a missing sample is marked on its field');

	$errors = pg_whatsapp_design_check(whatsapp_design_test_posted(array('footer' => str_repeat('a', 61))));
	pg_assert_true(isset($errors['footer']), 'footer over 60');

	$errors = pg_whatsapp_design_check(whatsapp_design_test_posted(array('footer' => 'Hi {{1}}')));
	pg_assert_true(isset($errors['footer']), 'a variable in the footer');
}

function test_whatsapp_design_components_and_samples()
{
	$design = whatsapp_design_test_posted();
	$components = pg_whatsapp_design_components($design);

	pg_assert_same(2, count($components), 'body and footer');
	pg_assert_same('BODY', $components[0]['type'], 'body first');
	pg_assert_same(array(array('Ayşe', '1042')), $components[0]['example']['body_text'], 'samples for Meta\'s review');
	pg_assert_same(array('type' => 'FOOTER', 'text' => 'Kupa'), $components[1], 'footer');

	$design['header'] = array('type' => 'HEADER', 'format' => 'IMAGE');
	$design['buttons'] = array(array('type' => 'QUICK_REPLY', 'text' => 'More'));
	$design['footer'] = '';
	$components = pg_whatsapp_design_components($design);
	pg_assert_same(array('HEADER', 'BODY', 'BUTTONS'), array_column($components, 'type'), 'header and buttons kept, empty footer left out');

	$json = pg_whatsapp_design_samples_json($design);
	pg_assert_same('{"body":[{"label":"Customer\'s name","value":"Ayşe"},{"label":"Order number","value":"1042"}]}', $json, 'samples column');

	$row = array(
		'id' => '7', 'meta_id' => null, 'name' => 'thank_you', 'language' => 'tr', 'category' => 'MARKETING', 'status' => 'DRAFT',
		'reason' => '', 'parameter_format' => 'POSITIONAL',
		'components' => json_encode(pg_whatsapp_design_components($design)), 'samples' => $json,
	);
	$read = pg_whatsapp_design_from_row($row);
	pg_assert_same(7, $read['id'], 'id');
	pg_assert_same($design['body'], $read['body'], 'body read back');
	pg_assert_same(array('1' => 'Ayşe', '2' => '1042'), $read['samples'], 'samples read back');
	pg_assert_same('Order number', $read['labels']['2'], 'labels read back');
	pg_assert_same('IMAGE', $read['header']['format'], 'header read back');
	pg_assert_true(pg_whatsapp_design_editable($read), 'a draft is editable');
}

function test_whatsapp_design_from_meta_row()
{
	$row = array(
		'id' => 3, 'meta_id' => '991', 'name' => 'order_ready', 'language' => 'en_US', 'category' => 'UTILITY', 'status' => 'APPROVED',
		'parameter_format' => 'POSITIONAL', 'samples' => '',
		'components' => json_encode(array(
			array('type' => 'HEADER', 'format' => 'TEXT', 'text' => 'Order {{1}}', 'example' => array('header_text' => array('#55'))),
			array('type' => 'BODY', 'text' => 'Hi {{1}}, it is {{2}} now.', 'example' => array('body_text' => array(array('Jo', 'ready')))),
			array('type' => 'BUTTONS', 'buttons' => array(array('type' => 'QUICK_REPLY', 'text' => 'Thanks'))),
		)),
	);
	$design = pg_whatsapp_design_from_row($row);
	pg_assert_same(array('1' => 'Jo', '2' => 'ready'), $design['samples'], 'Meta\'s examples as samples');
	pg_assert_false(pg_whatsapp_design_editable($design), 'a template Meta holds is not edited here');

	$send = pg_whatsapp_design_send_components($design);
	pg_assert_true($send['ok'], 'can be tested');
	pg_assert_same(array(
		array('type' => 'header', 'parameters' => array(array('type' => 'text', 'text' => '#55'))),
		array('type' => 'body', 'parameters' => array(array('type' => 'text', 'text' => 'Jo'), array('type' => 'text', 'text' => 'ready'))),
	), $send['components'], 'header and body filled with samples');

	$named = pg_whatsapp_design_from_row(array(
		'name' => 'n', 'language' => 'en', 'status' => 'APPROVED', 'parameter_format' => 'NAMED', 'samples' => '',
		'components' => json_encode(array(array('type' => 'BODY', 'text' => 'Hi {{first_name}}!', 'example' => array('body_text_named_params' => array(array('param_name' => 'first_name', 'example' => 'Jo')))))),
	));
	$send = pg_whatsapp_design_send_components($named);
	pg_assert_same(array(array('type' => 'body', 'parameters' => array(array('type' => 'text', 'text' => 'Jo', 'parameter_name' => 'first_name')))), $send['components'], 'named parameters');

	$design['header'] = array('type' => 'HEADER', 'format' => 'IMAGE');
	pg_assert_false(pg_whatsapp_design_send_components($design)['ok'], 'a picture header is not tested yet');

	$design['header'] = array();
	$design['buttons'] = array(array('type' => 'COPY_CODE', 'example' => 'X'));
	pg_assert_false(pg_whatsapp_design_send_components($design)['ok'], 'a button that takes a value is not tested yet');

	$design['buttons'] = array();
	$design['samples']['2'] = '';
	pg_assert_false(pg_whatsapp_design_send_components($design)['ok'], 'a variable without a sample');
}

function test_whatsapp_design_format_html()
{
	pg_assert_same('Hi <mark>Ayşe</mark>, <b>10%</b> off<br><i>soon</i> <s>old</s> <code>a*b*</code>',
		pg_whatsapp_design_format_html("Hi {{1}}, *10%* off\n_soon_ ~old~ ```a*b*```", array('1' => 'Ayşe')), 'formatting and a sample');
	pg_assert_same('<mark>{{2}}</mark> &lt;b&gt;x&lt;/b&gt;', pg_whatsapp_design_format_html('{{2}} <b>x</b>', array()), 'escaped; a variable without a sample shows itself');
	pg_assert_same('<mark>&lt;i&gt;</mark>', pg_whatsapp_design_format_html('{{1}}', array('1' => '<i>')), 'a sample is escaped');
	pg_assert_same('snake_case_name and 2*3*4', pg_whatsapp_design_format_html('snake_case_name and 2*3*4', array()), 'markers inside words are left');
	pg_assert_same('<b>Hi <mark>Jo</mark></b>', pg_whatsapp_design_format_html('*Hi {{1}}*', array('1' => 'Jo')), 'bold around a variable');
}

function test_whatsapp_design_lists()
{
	pg_assert_same(array('UTILITY', 'MARKETING', 'AUTHENTICATION'), array_keys(pg_whatsapp_design_categories()), 'types');
	pg_assert_true(isset(pg_whatsapp_design_languages()['tr'], pg_whatsapp_design_languages()['en_US']), 'languages');

	foreach (array_keys(pg_whatsapp_design_languages()) as $code) {
		pg_assert_true((bool) preg_match('/^[A-Za-z0-9_]{2,16}$/D', $code), 'a code the templates table takes: ' . $code);
	}
}

function test_whatsapp_design_chip_labels()
{
	$design = pg_whatsapp_design_new();
	$design['body'] = 'Hi {{1}}, order {{2}} on {{3}} and {{4}}.';
	$design['labels'] = array('1' => 'Customer', '2' => '', '3' => 'Customer', '4' => ' Date ');

	pg_assert_same(array('1' => 'Customer', '2' => 'Value 2', '3' => 'Customer (3)', '4' => 'Date'), pg_whatsapp_design_chip_labels($design), 'labels, a fallback and a shared label made apart');
}

function test_var_chip_html()
{
	pg_assert_same('<span class="pg-var-chip" data-pg-var-label="A &amp; &lt;b&gt;"><span class="pg-var-chip-text">A &amp; &lt;b&gt;</span></span>', pg_var_chip_html('A & <b>'), 'plain chip, escaped');
	pg_assert_contains('<button type="button" class="pg-var-chip" draggable="true" data-pg-var-label="Date">', pg_var_chip_html('Date', true), 'palette chip');
}
