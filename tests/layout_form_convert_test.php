<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the classic custom form layout converter
 * (includes/fn/layout_convert.php): a layout's PHP view template and the
 * form's field rows become the design elements of a custom_form widget,
 * controls named after their fields and tied to their rows, choice fields
 * as fieldsets, the CAPTCHA block as the CAPTCHA element, the parts a
 * designed form has no place for reported, and no PHP left in the tree.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/layout_convert.php');

// Every node of a tree, depth first, the root left out.
function _test_lfc_nodes($nodes)
{
	$out = array();
	foreach ($nodes as $n) {
		$out[] = $n;
		if (!empty($n['children'])) {
			$out = array_merge($out, _test_lfc_nodes($n['children']));
		}
	}
	return $out;
}

// The semantic nodes of one tag.
function _test_lfc_tag($tree, $tag)
{
	$out = array();
	foreach (_test_lfc_nodes($tree['children']) as $n) {
		if ($n['type'] === 'semantic' && strtolower($n['props']['tag']) === $tag) $out[] = $n;
	}
	return $out;
}

function _test_lfc_attr($node, $name)
{
	foreach ((array)($node['props']['_attrs'] ?? array()) as $a) {
		if ($a['name'] === $name) return $a['value'];
	}
	return null;
}

// A field row shaped like pg_cf_load_page_fields() returns it.
function _test_lfc_field($id, $name, $label, $type, $extra = array())
{
	return array_merge(array(
		'id' => $id, 'name' => $name, 'label' => $label, 'type' => $type,
		'required' => false, 'multiple' => false, 'office_use_only' => false,
		'wysiwyg' => false, 'translate' => true, 'rss_field' => '', 'contact_field' => '',
		'default_value' => '', 'folder_default' => false, 'size' => 0, 'maxlength' => 0,
		'rows' => 0, 'cols' => 0, 'upload_folder' => 0, 'quiz_question' => false,
		'quiz_answer' => '', 'information' => '', 'regex' => '', 'regex_message' => '',
		'options' => array(),
	), $extra);
}

function _test_lfc_option($value, $label = null, $default = false)
{
	return array('label' => ($label === null ? $value : $label), 'value' => $value, 'default' => $default);
}

// The starter site's layouts, kept in the repository.
function _test_lfc_starter($id)
{
	return (string)file_get_contents(PG_FUNCTIONS_DIR . '/data/backups/english_default/layouts/' . $id . '.php');
}

// Field rows read back from a layout's own markup, for the layouts whose rows
// the test does not spell out: every control name and every printed label
// or information is a row.
function _test_lfc_rows_of($code)
{
	$rows = array();
	preg_match_all('/\$field\[\'(\d+)\'\]\[\'(label|information)\'\]/', $code, $m, PREG_SET_ORDER);
	foreach ($m as $x) {
		$id = (int)$x[1];
		if (!isset($rows[$id])) $rows[$id] = _test_lfc_field($id, 'f' . $id, 'Label ' . $id, 'text box');
		if ($x[2] === 'information') {
			$rows[$id]['type'] = 'information';
			$rows[$id]['information'] = '<p>Info ' . $id . '</p>';
		}
	}
	preg_match_all('/<(input|select|textarea)\b[^>]*\bname="(\d+)(\[\])?"[^>]*>/', $code, $m, PREG_SET_ORDER);
	foreach ($m as $x) {
		$id = (int)$x[2];
		if (!isset($rows[$id])) $rows[$id] = _test_lfc_field($id, 'f' . $id, 'Label ' . $id, 'text box');
		$type = 'text box';
		if ($x[1] === 'textarea') $type = 'text area';
		elseif ($x[1] === 'select') $type = 'pick list';
		elseif (preg_match('/type="(radio|checkbox|email)"/', $x[0], $t)) {
			$type = array('radio' => 'radio button', 'checkbox' => 'check box', 'email' => 'email address')[$t[1]];
			if (preg_match('/value="([^"]*)"/', $x[0], $v) && $t[1] !== 'email') {
				$rows[$id]['options'][] = _test_lfc_option($v[1]);
			}
		}
		$rows[$id]['type'] = $type;
	}
	return array_values($rows);
}

function _test_lfc_1023_fields()
{
	return array(
		_test_lfc_field(314, 'subject', 'Subject', 'text box', array('required' => true, 'rss_field' => 'title')),
		_test_lfc_field(315, 'details', 'Details', 'text area', array('rows' => 6)),
		_test_lfc_field(316, 'information_01', '', 'information', array('information' => '<p>Tell us about you.</p>')),
		_test_lfc_field(317, 'first_name', 'First Name', 'text box', array('contact_field' => 'first_name')),
		_test_lfc_field(318, 'last_name', 'Last Name', 'text box'),
		_test_lfc_field(319, 'e-mail', 'E-mail', 'email address', array('required' => true)),
		_test_lfc_field(320, 'credits', 'Credits', 'text box', array('office_use_only' => true)),
	);
}

// The starter site's support form: every control named after its field and
// tied to its row, labels kept, the information printed, no PHP left.
function test_layout_form_convert_starter_support_form()
{
	$res = pg_layout_form_convert(_test_lfc_starter(1023), _test_lfc_1023_fields(), array('page_id' => 1023, 'submit_label' => 'Send'));
	pg_assert_true($res['ok']);
	$json = json_encode($res['tree']);
	pg_assert_false(strpos($json, '<?') !== false, 'no PHP');
	pg_assert_false(strpos($json, 'pg-captcha') !== false, 'no marker');
	pg_assert_same(array(), _test_lfc_tag($res['tree'], 'form'), 'the widget draws the form');

	$controls = array();
	foreach (array('input', 'textarea', 'select') as $tag) {
		foreach (_test_lfc_tag($res['tree'], $tag) as $n) $controls[_test_lfc_attr($n, 'name')] = $n;
	}
	pg_assert_same(array('subject', 'first_name', 'last_name', 'e-mail', 'credits', 'details'), array_keys($controls));
	pg_assert_same(array('1023' => 314), $controls['subject']['props']['_cf']['ids']);
	pg_assert_same('', _test_lfc_attr($controls['subject'], 'required'), 'required from the row');
	pg_assert_same('title', $controls['subject']['props']['_cf']['rss_field']);
	pg_assert_same('Subject', $controls['subject']['props']['_cf']['label'], 'no label element: the row label');
	pg_assert_same('6', _test_lfc_attr($controls['details'], 'rows'));
	pg_assert_same('first_name', $controls['first_name']['props']['_cf']['contact_field']);
	pg_assert_false(isset($controls['first_name']['props']['_cf']['label']), 'a label element names it');
	pg_assert_true($controls['credits']['props']['_cf']['office_use_only']);
	pg_assert_same('email', _test_lfc_attr($controls['e-mail'], 'type'));

	// The label follows the control's new id.
	$for = array();
	foreach (_test_lfc_tag($res['tree'], 'label') as $l) $for[_test_lfc_attr($l, 'for')] = $l['props']['text'] ?? '';
	pg_assert_same('First Name', $for[$controls['first_name']['props']['id']]);
	pg_assert_same('first_name', $controls['first_name']['props']['id']);

	pg_assert_contains('Tell us about you.', $json, 'information drawn');
	$buttons = _test_lfc_tag($res['tree'], 'button');
	pg_assert_same(1, count($buttons));
	pg_assert_same('Send', $buttons[0]['props']['text']);

	$captcha = array();
	foreach (_test_lfc_nodes($res['tree']['children']) as $n) {
		if (($n['props']['_bindings']['section'] ?? '') === 'captcha') $captcha[] = $n;
	}
	pg_assert_same(1, count($captcha), 'the CAPTCHA block is the CAPTCHA element');
	$in_column = false;
	foreach ($res['tree']['children'][0]['children'] as $col) {
		if (($col['children'][0]['props']['_bindings']['section'] ?? '') === 'captcha') $in_column = ($col['props']['cssClass'] === 'col-sm-12');
	}
	pg_assert_true($in_column, 'it stays in its column');
	pg_assert_false(strpos($json, 'To prevent spam') !== false, 'its classic words go with it');
	pg_assert_same(array('watcher'), $res['dropped']);
	pg_assert_same(array(), $res['missing']);
	pg_assert_same('loop_area', end($res['tree']['children'])['type']);
}

// A radio or check box field is a fieldset whose legend is the field label,
// one box per option of the row, each with its own label.
function test_layout_form_convert_choice_fieldset()
{
	$code = '<form <?=$attributes?>>' . "\n"
		. '<div><?=$field[\'137\'][\'label\']?></div>' . "\n"
		. '<div style="margin: 1em 0"><div><div class="radio-option"><input type="radio" name="137" value="True"><span>True</span></div></div>'
		. '<div><div class="radio-option"><input type="radio" name="137" value="False"><span>False</span></div></div></div>' . "\n"
		. '<div class="col-sm-6"><div><?=$field[\'118\'][\'label\']?></div>'
		. '<div class="checkbox"><label><input type="checkbox" name="118[]" value="Planning"> Planning</label></div>'
		. '<div class="checkbox"><label><input type="checkbox" name="118[]" value="Building"> Building</label></div></div>' . "\n"
		. '<?=$system?></form>';
	$fields = array(
		_test_lfc_field(137, 'burnt_orange', 'Burnt orange?', 'radio button', array('required' => true,
			'options' => array(_test_lfc_option('True'), _test_lfc_option('False', 'False', true)))),
		_test_lfc_field(118, 'services', 'Services', 'check box', array('multiple' => true,
			'options' => array(_test_lfc_option('Planning'), _test_lfc_option('Building')))),
	);
	$res = pg_layout_form_convert($code, $fields, array('page_id' => 9));
	pg_assert_true($res['ok']);

	$sets = _test_lfc_tag($res['tree'], 'fieldset');
	pg_assert_same(2, count($sets));
	pg_assert_same('Burnt orange?', $sets[0]['children'][0]['props']['text']);
	pg_assert_same('legend', $sets[0]['children'][0]['props']['tag']);
	pg_assert_same(3, count($sets[0]['children']), 'legend and two answers');
	pg_assert_same('Services', $sets[1]['children'][0]['props']['text']);

	// The check box group stays in its column; the label line is gone.
	pg_assert_same('col-sm-6', $res['tree']['children'][1]['props']['cssClass']);
	pg_assert_same('fieldset', $res['tree']['children'][1]['children'][0]['props']['tag']);
	pg_assert_same(2, count($res['tree']['children']) - 1, 'the radio field and the column, then the loop area');

	$boxes = _test_lfc_tag($res['tree'], 'input');
	pg_assert_same(4, count($boxes));
	pg_assert_same('burnt_orange', _test_lfc_attr($boxes[0], 'name'));
	pg_assert_same('', _test_lfc_attr($boxes[0], 'required'), 'a radio is required on every box');
	pg_assert_same('', _test_lfc_attr($boxes[1], 'checked'), 'the default answer');
	pg_assert_same('services', _test_lfc_attr($boxes[2], 'name'), 'no [] in the name');
	pg_assert_same(array('9' => 118), $boxes[3]['props']['_cf']['ids']);

	$labels = array();
	foreach (_test_lfc_tag($res['tree'], 'label') as $l) $labels[_test_lfc_attr($l, 'for')] = $l['props']['text'];
	pg_assert_same('False', $labels[$boxes[1]['props']['id']]);
	pg_assert_same('Building', $labels[$boxes[3]['props']['id']]);

	// The field list the save derives is the form's own.
	$derived = _pg_cf_controls($res['tree'], 9);
	pg_assert_same(array('burnt_orange', 'services'), array_keys($derived));
	pg_assert_same(118, $derived['services']['id']);
	pg_assert_same('check box', $derived['services']['type']);
	pg_assert_same(2, count($derived['services']['options']));
	pg_assert_same('Burnt orange?', $derived['burnt_orange']['label']);
}

// An empty pick list gets the row's options; a date field becomes a date
// control; a field the layout leaves out is reported.
function test_layout_form_convert_select_date_and_missing()
{
	$code = '<div class="form-group"><label for="119"><?=$field[\'119\'][\'label\']?></label><select name="119" id="119"></select></div>'
		. '<div class="form-group"><label for="123"><?=$field[\'123\'][\'label\']?></label><input type="text" name="123" id="123" class="form-control"></div>';
	$fields = array(
		_test_lfc_field(119, 'salutation', 'Salutation', 'pick list', array('options' => array(
			_test_lfc_option('Mr'), _test_lfc_option('Ms', 'Ms', true)))),
		_test_lfc_field(123, 'game_date', 'Game date', 'date', array('default_value' => '2026-10-27')),
		_test_lfc_field(124, 'winner', 'Winner', 'text box'),
		_test_lfc_field(125, 'note', '', 'information', array('information' => 'x')),
	);
	$res = pg_layout_form_convert($code, $fields, array('page_id' => 5));
	$sel = _test_lfc_tag($res['tree'], 'select')[0];
	pg_assert_same(2, count($sel['children']));
	pg_assert_same('Ms', $sel['children'][1]['props']['text']);
	pg_assert_same('', _test_lfc_attr($sel['children'][1], 'selected'));
	$date = _test_lfc_tag($res['tree'], 'input')[0];
	pg_assert_same('date', _test_lfc_attr($date, 'type'));
	pg_assert_same('2026-10-27', _test_lfc_attr($date, 'value'));
	pg_assert_same(array(124, 125), $res['missing'], 'fields the layout does not draw');
	$names = array_keys(_pg_cf_controls($res['tree'], 5));
	pg_assert_same(array('salutation', 'game_date', 'winner'), $names, 'added after the drawn ones');
	pg_assert_contains('"x"', json_encode($res['tree']), 'the information too');
}

// A row the layout leaves out comes before the CAPTCHA and the button,
// inside the row that holds them; a signature is left to its row.
function test_layout_form_convert_missing_before_the_end()
{
	$code = '<div class="row"><div class="col-sm-12"><label for="1"><?=$field[\'1\'][\'label\']?></label><input type="text" name="1" id="1"></div>'
		. '<?php if ($captcha_question): ?><div class="col-sm-12"><input type="number" name="captcha_submitted_answer"></div><?php endif ?>'
		. '<div class="col-sm-12"><button type="submit"><?=h($submit_button_label)?></button></div></div>';
	$fields = array(
		_test_lfc_field(1, 'name', 'Name', 'text box'),
		_test_lfc_field(2, 'topic', 'Topic', 'radio button', array('options' => array(_test_lfc_option('A'), _test_lfc_option('B')))),
		_test_lfc_field(3, 'sign', 'Sign here', 'signature'),
		_test_lfc_field(4, 'notes', 'Notes', 'text area'),
	);
	$res = pg_layout_form_convert($code, $fields, array('page_id' => 8));
	pg_assert_same(array(2, 4), $res['missing']);
	pg_assert_same(array('signature'), $res['dropped']);
	$row = $res['tree']['children'][0]['children'];
	pg_assert_same(5, count($row), 'name, topic, notes, CAPTCHA, button');
	pg_assert_same('fieldset', $row[1]['children'][0]['props']['tag']);
	pg_assert_same('Topic', $row[1]['children'][0]['children'][0]['props']['text']);
	pg_assert_same('notes', _test_lfc_attr($row[2]['children'][1], 'name'));
	pg_assert_same('captcha', $row[3]['children'][0]['props']['_bindings']['section']);
	$derived = _pg_cf_controls($res['tree'], 8);
	pg_assert_same(array('name', 'topic', 'notes'), array_keys($derived));
	pg_assert_same('Notes', $derived['notes']['label']);
	pg_assert_same(2, count($derived['topic']['options']));
}

// The generated part of an unmodified layout is converted from the text the
// caller passes; PHP the converter does not know is left out and reported,
// the markup of a rule it cannot keep with it.
function test_layout_form_convert_generated_and_unknown_php()
{
	$code = '<?=$messages?><form <?=$attributes?>><?=eval(\'?>\' . generate_form_layout_content(array(\'page_id\' => $page_id)))?>'
		. '<?php if ($save): ?><button type="submit" name="save_button">Save for Later</button><?php endif ?>'
		. '<?php if (USER_LOGGED_IN): ?><p>Hello</p><?php else: ?><p>Sign in</p><?php endif ?>'
		. '<p><?=date(\'Y\')?></p>'
		. '<button type="submit" name="submit_button" class="btn btn-primary"><?=h($submit_button_label)?></button><?=$system?></form>';
	$generated = '<div class="form-group"><label for="7"><?=$field[\'7\'][\'label\']?></label><input type="text" name="7" id="7" class="form-control"></div>';
	$res = pg_layout_form_convert($code, array(_test_lfc_field(7, 'city', 'City', 'text box')),
		array('page_id' => 3, 'generated' => $generated));
	pg_assert_true($res['ok']);
	$json = json_encode($res['tree']);
	pg_assert_contains('"city"', $json);
	pg_assert_false(strpos($json, 'Save for Later') !== false);
	pg_assert_false(strpos($json, 'Hello') !== false);
	pg_assert_false(strpos($json, 'Sign in') !== false);
	pg_assert_same(array('save', 'condition', 'php'), $res['dropped']);
	pg_assert_same('Submit', _test_lfc_tag($res['tree'], 'button')[0]['props']['text'], 'the default label');

	$bad = pg_layout_form_convert('<?php foreach ($x as $y) { ?><p>a</p>', array(), array());
	pg_assert_same(array('php'), $bad['dropped']);
}

// Every custom form layout of the starter site converts with no PHP left and
// every control on a field of the form.
function test_layout_form_convert_every_starter_layout()
{
	foreach (array(1023, 183, 288, 290, 291, 395, 408, 417, 472, 584, 1059) as $id) {
		$code = _test_lfc_starter($id);
		$rows = _test_lfc_rows_of($code);
		$res = pg_layout_form_convert($code, $rows, array('page_id' => $id));
		pg_assert_true($res['ok'], "layout $id");
		$json = json_encode($res['tree']);
		pg_assert_false(strpos($json, '<?') !== false || strpos($json, '?>') !== false, "layout $id: no PHP");
		$names = array();
		foreach ($rows as $r) $names[] = $r['name'];
		foreach (array_keys(_pg_cf_controls($res['tree'], $id)) as $key) {
			pg_assert_true(in_array($key, $names, true), "layout $id: $key is a field");
		}
		pg_assert_same(array(), $res['missing'], "layout $id: every field drawn");
	}
}
