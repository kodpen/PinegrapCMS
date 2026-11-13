<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the designer canvas's sample answer to a form field
 * (_sdFormFieldSample() in style_designer.js, run under Node): a value of
 * the field's kind, one of its own choices, its information text, and a
 * text box read by its default and the words of its label.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The sample of each field in $fields, as Node prints them; null without Node.
function _canvas_form_samples(array $fields)
{
	if (!function_exists('exec')) return null;
	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);
	if ($code !== 0) return null;
	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
	$a = strpos($js, "\n    var _SD_FIELD_WORD_SAMPLES = [");
	pg_assert_true($a !== false, '_SD_FIELD_WORD_SAMPLES is found');
	$b = strpos($js, "\n    function _sdFormFieldSample(", $a);
	$b = strpos($js, "\n    }\n", $b) + 6;
	$stubs = "function _sdT(s, v) { return v === undefined ? s : String(s).replace('{var:1}', v); }\n"
		. "function _sdDatePlaceholder() { return 'DATE'; }\n";
	$script = 'console.log(JSON.stringify(' . json_encode($fields) . ".map(_sdFormFieldSample)));\n";
	$file = @tempnam(sys_get_temp_dir(), 'pgffs');
	if ($file === false) return null;
	file_put_contents($file, $stubs . substr($js, $a, $b - $a) . $script);
	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);
	pg_assert_same(0, $code, implode("\n", $out));
	return json_decode(implode("\n", $out), true);
}

// Each kind of field draws a value of its kind; no two fields of the
// contact form draw the same text.
function test_canvas_form_field_samples_by_kind()
{
	$got = _canvas_form_samples(array(
		array('type' => 'date', 'label' => 'Date'),
		array('type' => 'time', 'label' => 'Time'),
		array('type' => 'email address', 'label' => 'E-mail'),
		array('type' => 'pick list', 'label' => 'Topic', 'options' => array(array('label' => 'Orders', 'value' => 'orders'), array('label' => 'Returns', 'value' => 'returns'))),
		array('type' => 'radio button', 'label' => 'Size', 'options' => array()),
		array('type' => 'check box', 'label' => 'Newsletter'),
		array('type' => 'file upload', 'label' => 'Attachment'),
		array('type' => 'information', 'label' => 'Note', 'information' => '<p>We answer <b>within a day</b>.</p>'),
		array('type' => 'text area', 'label' => 'Message'),
		array('type' => 'text area', 'label' => 'About your work'),
	));
	if ($got === null) return;
	pg_assert_same(array('DATE', '14:30', 'sample@example.com', 'Orders', 'First choice', 'Yes', 'document.pdf', 'We answer within a day.',
		'Hello, I would like to know when the order can be delivered. I am at home on weekdays after five, and the parcel can be left with the neighbour.',
		'Two or three sentences in my own words: what I need, by when, and anything else that helps.'), $got);
}

// A text box: its default first, then its label's words (Turkish too), its
// RSS title role, else the label as a sample.
function test_canvas_form_field_samples_text_box()
{
	$got = _canvas_form_samples(array(
		array('type' => 'text box', 'label' => 'Your name'),
		array('type' => 'text box', 'label' => 'Adınız Soyadınız'),
		array('type' => 'text box', 'label' => 'Telefon'),
		array('type' => 'text box', 'label' => 'E-posta adresi'),
		array('type' => 'text box', 'label' => 'Adres'),
		array('type' => 'text box', 'label' => 'Company'),
		array('type' => 'text box', 'label' => 'Headline', 'rss_field' => 'title'),
		array('type' => 'text box', 'label' => 'Country', 'default_value' => 'Türkiye'),
		array('type' => 'text box', 'label' => 'Favourite colour:'),
	));
	if ($got === null) return;
	pg_assert_same(array('Alex Morgan', 'Alex Morgan', '+90 555 000 00 00', 'sample@example.com', 'Sample Street 1, Springfield',
		'Example Ltd.', 'How we restored a 1920s bookshelf', 'Türkiye', 'Favourite colour sample'), $got);
}
