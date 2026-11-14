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
	$design['buttons'] = array(array('type' => 'OTP', 'otp_type' => 'COPY_CODE'));
	pg_assert_false(pg_whatsapp_design_send_components($design)['ok'], 'a one-time-code button is not tested yet');

	$design['buttons'] = array(array('type' => 'COPY_CODE', 'example' => array('X25')));
	$send = pg_whatsapp_design_send_components($design);
	$last = end($send['components']);
	pg_assert_same('X25', $last['parameters'][0]['coupon_code'], 'a copy-code button from Meta sends its example');

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

function test_whatsapp_design_posted_header_and_buttons()
{
	$design = whatsapp_design_test_posted(array(
		'header_format' => 'image', 'header_file' => '12',
		'button_type'   => array('QUICK_REPLY', 'URL', 'PHONE_NUMBER', 'URL'),
		'button_text'   => array('More', 'Shop', 'Call us', ''),
		'button_value'  => array('', 'https://example.com/c?k={{1}}', '+90 (212) 555-01-02', ''),
		'button_sample' => array('', 'K7M2', '', ''),
	));
	pg_assert_same(array('type' => 'HEADER', 'format' => 'IMAGE'), $design['header'], 'media header');
	pg_assert_same(12, $design['header_file']['id'], 'the file chosen');
	pg_assert_same(3, count($design['buttons']), 'an empty row is no button');
	pg_assert_same(array('https://example.com/c?k=K7M2'), $design['buttons'][1]['example'], 'link example');
	pg_assert_same('+902125550102', $design['buttons'][2]['phone_number'], 'phone cleaned');

	$components = pg_whatsapp_design_components($design);
	$buttons = $components[count($components) - 1]['buttons'];
	pg_assert_same(array('URL', 'PHONE_NUMBER', 'QUICK_REPLY'), array_column($buttons, 'type'), 'quick replies grouped at the end');
	pg_assert_false(isset($buttons[0]['sample']), 'the designer\'s sample is not sent');

	$design = whatsapp_design_test_posted(array('header_format' => 'TEXT', 'header_text' => ' Your order ', 'button_type' => 'not a list'));
	pg_assert_same(array('type' => 'HEADER', 'format' => 'TEXT', 'text' => 'Your order'), $design['header'], 'text header');
	pg_assert_same(array(), $design['buttons'], 'buttons not posted as a list');

	$rejected = pg_whatsapp_design_new();
	$rejected['meta_id'] = '991';
	$rejected['status'] = 'REJECTED';
	$rejected['name'] = 'kept_name';
	$rejected['language'] = 'tr';
	$design = pg_whatsapp_design_posted(array('name' => 'other', 'language' => 'en', 'body' => 'Hi there'), $rejected);
	pg_assert_same(array('kept_name', 'tr'), array($design['name'], $design['language']), 'a template Meta knows keeps its name and language');
	pg_assert_true(pg_whatsapp_design_editable($design), 'a rejected template is edited');
	$rejected['status'] = 'PENDING';
	pg_assert_false(pg_whatsapp_design_editable($rejected), 'one in review is not');
}

function test_whatsapp_design_check_header()
{
	$design = pg_whatsapp_design_new();
	pg_assert_same(array(), pg_whatsapp_design_check_header($design), 'no header');

	$design['header'] = array('type' => 'HEADER', 'format' => 'TEXT', 'text' => '');
	pg_assert_true(isset(pg_whatsapp_design_check_header($design)['header_text']), 'empty text');
	$design['header']['text'] = str_repeat('a', 61);
	pg_assert_true(isset(pg_whatsapp_design_check_header($design)['header_text']), 'text over 60');
	$design['header']['text'] = 'Hi {{1}}';
	pg_assert_true(isset(pg_whatsapp_design_check_header($design)['header_text']), 'a variable');

	$design['header'] = array('type' => 'HEADER', 'format' => 'IMAGE');
	$design['header_file'] = array('id' => 4, 'name' => '', 'size' => 0);
	pg_assert_true(isset(pg_whatsapp_design_check_header($design)['header_file']), 'no file');
	$design['header_file'] = array('id' => 4, 'name' => 'promo.pdf', 'size' => 100);
	pg_assert_true(isset(pg_whatsapp_design_check_header($design)['header_file']), 'a PDF for a picture');
	$design['header_file'] = array('id' => 4, 'name' => 'promo.JPG', 'size' => 6000000);
	pg_assert_true(isset(pg_whatsapp_design_check_header($design)['header_file']), 'over 5 MB');
	$design['header_file']['size'] = 300000;
	pg_assert_same(array(), pg_whatsapp_design_check_header($design), 'a picture');
}

function test_whatsapp_design_check_buttons()
{
	$link = function ($url, $sample = null) {
		$button = array('type' => 'URL', 'text' => 'Shop', 'url' => $url);

		if ($sample !== null) {
			$button['sample'] = $sample;
		}

		return $button;
	};

	pg_assert_same(array(), pg_whatsapp_design_check_buttons(array($link('https://example.com/a'), array('type' => 'QUICK_REPLY', 'text' => 'More'))), 'valid');
	pg_assert_same(array(), pg_whatsapp_design_check_buttons(array($link('https://example.com/a?k={{1}}', 'K7')), 'a variable at the end with its sample'));
	pg_assert_true(isset(pg_whatsapp_design_check_buttons(array($link('https://example.com/{{1}}/a', 'x')))['button_0']), 'a variable not at the end');
	pg_assert_true(isset(pg_whatsapp_design_check_buttons(array($link('https://example.com/{{1}}', '')))['button_0']), 'no sample');
	pg_assert_true(isset(pg_whatsapp_design_check_buttons(array($link('example.com')))['button_0']), 'not a web address');
	pg_assert_true(isset(pg_whatsapp_design_check_buttons(array(array('type' => 'QUICK_REPLY', 'text' => str_repeat('a', 26))))['button_0']), 'text over 25');
	pg_assert_true(isset(pg_whatsapp_design_check_buttons(array(array('type' => 'OTP', 'text' => 'Copy')))['button_0']), 'a kind not offered');
	pg_assert_true(isset(pg_whatsapp_design_check_buttons(array(array('type' => 'COPY_CODE')))['button_0']), 'a copy-code button without a sample code');
	pg_assert_true(isset(pg_whatsapp_design_check_buttons(array(array('type' => 'PHONE_NUMBER', 'text' => 'Call', 'phone_number' => '12')))['button_0']), 'a short number');

	$phone = array('type' => 'PHONE_NUMBER', 'text' => 'Call', 'phone_number' => '+902125550102');
	pg_assert_true(isset(pg_whatsapp_design_check_buttons(array($phone, $phone))['button_1']), 'one phone button');
	pg_assert_true(isset(pg_whatsapp_design_check_buttons(array($link('https://a.com'), $link('https://b.com'), $link('https://c.com')))['button_2']), 'two link buttons');
	pg_assert_true(isset(pg_whatsapp_design_check_buttons(array_fill(0, 4, array('type' => 'QUICK_REPLY', 'text' => 'x')))['button_0']), 'at most three');
}

function test_whatsapp_design_submission_and_test_send()
{
	$design = whatsapp_design_test_posted(array('header_format' => 'VIDEO', 'header_file' => '3',
		'button_type' => array('URL'), 'button_text' => array('Track'), 'button_value' => array('https://example.com/t/{{1}}'), 'button_sample' => array('1042')));
	$template = pg_whatsapp_design_submission($design, '4::aW1h');
	pg_assert_same(array('header_handle' => array('4::aW1h')), $template['components'][0]['example'], 'the sample\'s handle');
	pg_assert_same(array('thank_you', 'tr', 'MARKETING'), array($template['name'], $template['language'], $template['category']), 'name, language, type');

	$design['status'] = 'APPROVED';
	pg_assert_false(pg_whatsapp_design_send_components($design)['ok'], 'a media header without a public file');

	$design['header_link'] = 'https://shop.example/clip.mp4';
	$send = pg_whatsapp_design_send_components($design);
	pg_assert_true($send['ok'], 'a public file');
	pg_assert_same(array('type' => 'header', 'parameters' => array(array('type' => 'video', 'video' => array('link' => 'https://shop.example/clip.mp4')))), $send['components'][0], 'video by link');
	pg_assert_same(array('type' => 'button', 'sub_type' => 'url', 'index' => '0', 'parameters' => array(array('type' => 'text', 'text' => '1042'))), $send['components'][2], 'the link\'s variable');

	pg_assert_same('K7', pg_whatsapp_design_url_sample(array('url' => 'https://a.com/c?k={{1}}', 'example' => array('https://a.com/c?k=K7'))), 'sample read from Meta\'s example');
	pg_assert_same('', pg_whatsapp_design_url_sample(array('url' => 'https://a.com/{{1}}', 'example' => array('https://b.com/K7'))), 'an example of another address');
}
