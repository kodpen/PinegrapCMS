<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Café, Clinic and Salon form fixes: the required message of a
 * lone check box (pg_cf_required_message()), the inline error of a consent
 * box placed after the whole box row (pg_form_mark_invalid_controls()), the
 * date format of a template's notification subject
 * (pg_cf_subject_date_format()), the <html lang> of an e-mail body
 * (pg_tr_mail_html_lang()) and the template links that carry the chosen
 * item into the booking forms.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// A semantic node with the given tag, class, attributes, id and children.
function _test_ccf_node($tag, $class = '', $attrs = array(), $id = '', $children = array())
{
	$list = array();
	foreach ($attrs as $name => $value) {
		$list[] = array('name' => $name, 'value' => $value);
	}
	$props = array('tag' => $tag, '_attrs' => $list);
	if ($class !== '') {
		$props['cssClass'] = $class;
	}
	if ($id !== '') {
		$props['id'] = $id;
	}
	return array('type' => 'semantic', 'props' => $props, 'children' => $children);
}

// A lone check box says "tick it", never "<the whole sentence> is required.".
function test_ccf_required_message_lone_check_box()
{
	$sentence = 'I agree that the café keeps these details to manage my booking, as its privacy notice explains.';
	pg_assert_same('Check this box to continue.', pg_cf_required_message('check box', $sentence, 1));
	pg_assert_same('Check this box to continue.', pg_cf_required_message('check box', $sentence, 0));
	pg_assert_same('Topics is required.', pg_cf_required_message('check box', 'Topics', 3), 'a group keeps its label');
	pg_assert_same('Name is required.', pg_cf_required_message('text box', 'Name', 0));
	pg_assert_same('', pg_cf_required_message('text box', '', 0), 'no label, no text');
	pg_assert_same('A &amp; B is required.', pg_cf_required_message('pick list', 'A & B', 2), 'label escaped');
}

// The inline error of a consent box goes after the privacy link, so the
// sentence and its link stay together.
function test_ccf_consent_feedback_after_whole_row()
{
	$tree = _test_ccf_node('form', '', array(), '', array(
		_test_ccf_node('div', 'form-check small mb-3', array(), '', array(
			_test_ccf_node('input', 'form-check-input', array('type' => 'checkbox', 'name' => '757', 'value' => 'yes'), 'cf-res-consent'),
			_test_ccf_node('label', 'form-check-label', array('for' => 'cf-res-consent')),
			_test_ccf_node('a', 'ms-1', array('href' => '/privacy')),
		)),
		_test_ccf_node('button', '', array('type' => 'submit')),
	));

	pg_assert_same(1, pg_form_mark_invalid_controls($tree, array(757 => 'Check this box to continue.')));

	$row = $tree['children'][0]['children'];
	pg_assert_same(4, count($row));
	pg_assert_same('label', $row[1]['props']['tag']);
	pg_assert_same('a', $row[2]['props']['tag'], 'link stays right after the label');
	pg_assert_same('cf-res-consent-feedback', $row[3]['props']['id'], 'error text last in the row');
	pg_assert_same('button', $tree['children'][1]['props']['tag'], 'nothing added outside the row');
}

// A text control outside a form-check keeps its error right after it.
function test_ccf_text_feedback_unchanged()
{
	$tree = _test_ccf_node('div', 'mb-3', array(), '', array(
		_test_ccf_node('input', 'form-control', array('type' => 'text', 'name' => '12'), 'cf-name'),
		_test_ccf_node('div', 'form-text', array(), 'hint'),
	));
	pg_form_mark_invalid_controls($tree, array(12 => 'Name is required.'));
	pg_assert_same('cf-name-feedback', $tree['children'][1]['props']['id']);
	pg_assert_same('hint', $tree['children'][2]['props']['id']);
}

// The subject's bare date tokens take the format the e-mail body uses.
function test_ccf_subject_date_format()
{
	pg_assert_same(
		'New table booking: ^^cafe^^, ^^reservation_date^^%%d.m.Y%% ^^reservation_time^^',
		pg_cf_subject_date_format('New table booking: ^^cafe^^, ^^reservation_date^^ ^^reservation_time^^', array('reservation_date'), 'd.m.Y'));
	pg_assert_same('^^d^^%%Y%%', pg_cf_subject_date_format('^^d^^%%Y%%', array('d'), 'd.m.Y'), 'a given format stands');
	pg_assert_same('^^d^^', pg_cf_subject_date_format('^^d^^', array('d'), ''), 'no format, no change');
	pg_assert_same('^^dx^^ ^^d^^%%j/n/Y%%', pg_cf_subject_date_format('^^dx^^ ^^d^^', array('d'), 'j/n/Y'), 'only the named fields');
	pg_assert_same('^^d^^%%j/n/Y%%, ^^d^^%%j/n/Y%%', pg_cf_subject_date_format('^^d^^, ^^d^^', array('d'), 'j/n/Y'), 'every occurrence');
}

// An e-mail body carries the language it is written in.
function test_ccf_mail_html_lang()
{
	$html = '<!DOCTYPE html><html lang="en"><head></head><body></body></html>';
	pg_assert_same('<!DOCTYPE html><html lang="tr"><head></head><body></body></html>', pg_tr_mail_html_lang($html, 'tr'));
	pg_assert_same($html, pg_tr_mail_html_lang($html, ''), 'no language, unchanged');
	pg_assert_same($html, pg_tr_mail_html_lang($html, 'en'));
	pg_assert_same('<html lang="de">', pg_tr_mail_html_lang('<html lang="de">', 'tr'), 'a hand-written language stands');
	pg_assert_same('plain text', pg_tr_mail_html_lang('plain text', 'tr'));
}

// The template buttons carry the chosen item, and the form fields read it.
function test_ccf_template_links_carry_the_item()
{
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/';
	$clinic = file_get_contents($dir . 'pinegrap-clinic.php');
	$cafe = file_get_contents($dir . 'pinegrap-cafe.php');

	pg_assert_contains("'url_param' => 'department'", $clinic);
	pg_assert_contains("'url_param' => 'doctor'", $clinic);
	pg_assert_contains("'url_param' => 'reason'", $clinic);
	pg_assert_contains("{{page:appointment}}?' . \$appointment_query(", $clinic);
	pg_assert_contains("'url_param' => 'cafe'", $cafe);
	pg_assert_contains("'url_param' => 'day'", $cafe);
	pg_assert_contains("\$page_link('reservation', array('cafe' => 'cafe', 'day' => 'event_date'))", $cafe);

	foreach (array('department', 'doctor', 'reason', 'cafe', 'day') as $name) {
		pg_assert_same($name, pg_sw_url_param_name($name), $name . ' is a usable address name');
	}
}

// The Services sections' "Book this service" and the café cards' "Book a
// Table" open the form with that department or café chosen (#4806).
function test_ccf_services_and_cafe_cards_carry_the_item()
{
	$dir = PG_FUNCTIONS_DIR . '/includes/design_templates/';
	$clinic = file_get_contents($dir . 'pinegrap-clinic.php');
	$cafe = file_get_contents($dir . 'pinegrap-cafe.php');

	pg_assert_contains("\$link(lang('Book this service'), '{{page:appointment}}?' . \$appointment_query(array('department' => \$s['title'])),", $clinic);
	pg_assert_false(strpos($clinic, "\$link(lang('Book this service'), '{{page:appointment}}',") !== false, 'no Book this service link without the department');
	pg_assert_contains("\$link(lang('Book a Table'), '{{page:reservation}}?' . \$reservation_query(array('cafe' => \$c['name'])), 'btn btn-primary px-4')", $cafe);
	pg_assert_same('cafe=Old%20Town', http_build_query(array('cafe' => 'Old Town'), '', '&', PHP_QUERY_RFC3986), 'a space is %20, as the clinic links write it');
	pg_assert_contains("'version'     => '1.1.2'", $clinic);
	pg_assert_contains("'version'     => '1.1.2'", $cafe);
}
