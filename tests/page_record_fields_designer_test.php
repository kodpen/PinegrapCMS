<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a form record's fields by name in the visual designer's
 * page-record pickers: the field types the editor offers equal those the
 * server reads (pg_sw_entity_field_types()), and the option builder
 * (_sdPageRecordFieldOptions(), run under Node) names the tokens
 * pg_sw_entity_field_values() publishes, with a choice field's options.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function _prf_js()
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
}

// The editor's field types are the server's, in the same order.
function test_page_record_fields_types_twin()
{
	pg_assert_true(preg_match("/var SD_PAGE_RECORD_FIELD_TYPES = \\[(.*?)\\];/", _prf_js(), $m) === 1, 'SD_PAGE_RECORD_FIELD_TYPES is found');
	preg_match_all("/'([a-z ]+)'/", $m[1], $types);
	pg_assert_same(pg_sw_entity_field_types(), $types[1]);
}

// The builder offers the tokens the server publishes for the same fields.
function test_page_record_fields_builder()
{
	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);
	if ($code !== 0) {
		return;
	}

	$js = _prf_js();
	$start = strpos($js, "\n    var SD_PAGE_RECORD_FIELD_TYPES = ");
	$fn = strpos($js, "\n    function _sdPageRecordFieldOptions(", (int) $start);
	$end = ($fn === false) ? false : strpos($js, "\n    }\n", $fn);
	if (!pg_assert_true($start !== false && $end !== false, 'the builder is found')) {
		return;
	}

	$fields = array(
		array('id' => 1, 'name' => 'Title', 'label' => 'Title:', 'type' => 'text box', 'wysiwyg' => '0', 'office_use_only' => '0'),
		array('id' => 2, 'name' => 'price-2', 'label' => '', 'type' => 'text box', 'wysiwyg' => '0', 'office_use_only' => '0'),
		array('id' => 3, 'name' => 'body', 'label' => 'Body', 'type' => 'text area', 'wysiwyg' => '1', 'office_use_only' => '0'),
		array('id' => 4, 'name' => 'colour', 'label' => 'Colour', 'type' => 'pick list', 'wysiwyg' => '0', 'office_use_only' => '0',
			'options' => array(array('label' => '--choose--', 'value' => ''), array('label' => 'Sky blue', 'value' => 'blue'))),
		array('id' => 5, 'name' => 'day', 'label' => 'Day', 'type' => 'date', 'wysiwyg' => '0', 'office_use_only' => '0'),
		array('id' => 6, 'name' => 'cv', 'label' => 'CV', 'type' => 'file upload', 'wysiwyg' => '0', 'office_use_only' => '0'),
		array('id' => 7, 'name' => 'mail', 'label' => 'Mail', 'type' => 'email address', 'wysiwyg' => '0', 'office_use_only' => '0'),
		array('id' => 8, 'name' => 'internal', 'label' => 'Internal', 'type' => 'text box', 'wysiwyg' => '0', 'office_use_only' => '1'),
		array('id' => 9, 'name' => 'Başlık', 'label' => 'First', 'type' => 'text box', 'wysiwyg' => '0', 'office_use_only' => '0'),
		array('id' => 10, 'name' => 'BALK', 'label' => 'Second', 'type' => 'text box', 'wysiwyg' => '0', 'office_use_only' => '0'),
		array('id' => 11, 'name' => '???', 'label' => 'Nameless', 'type' => 'text box', 'wysiwyg' => '0', 'office_use_only' => '0'),
	);

	$script = "function _sdT(s, v) { return String(s).replace('{var:1}', v ? v[0] : ''); }\n"
		. substr($js, $start, $end - $start + 6) . "\n"
		. 'console.log(JSON.stringify(_sdPageRecordFieldOptions(' . json_encode($fields) . ')));';
	$file = tempnam(sys_get_temp_dir(), 'pgprf');
	file_put_contents($file, $script);
	$out = array();
	exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	unlink($file);
	pg_assert_same(0, $code, 'the Node script runs: ' . implode("\n", array_slice($out, 0, 8)));
	$res = json_decode(implode("\n", $out), true);
	if (!pg_assert_true(is_array($res), 'the script answers')) {
		return;
	}

	$tokens = array();
	foreach ($res as $o) {
		$tokens[$o['token']] = $o;
	}

	// The server's tokens for the same fields, under __record.
	$rows = array();
	foreach ($fields as $f) {
		$rows[] = array('field_name' => $f['name'], 'field_type' => $f['type'], 'data' => 'x', 'file_name' => '',
			'field_wysiwyg' => $f['wysiwyg'], 'field_office_use' => $f['office_use_only']);
	}
	$server = array();
	foreach (array_keys(pg_sw_entity_field_values($rows, array(), function () { return 'Y-m-d'; })) as $token) {
		$server[] = '__record' . $token;
	}
	pg_assert_same($server, array_keys($tokens), 'the editor offers exactly the server tokens');

	pg_assert_same('Record field: Title', $tokens['__record__field_title']['label'], 'labelled with the field label, colon dropped');
	pg_assert_same('Record field: price-2', $tokens['__record__field_price2']['label'], 'no label: the name');
	pg_assert_same('Record field: First', $tokens['__record__field_balk']['label'], 'two names stripped alike: the first one');
	pg_assert_same(array(array('label' => 'Sky blue', 'value' => 'blue')), $tokens['__record__field_colour']['choices'], 'a pick list offers its options, the empty one left out');
	pg_assert_false(isset($tokens['__record__field_title']['choices']), 'a text box has no choices');
}
