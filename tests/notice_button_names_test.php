<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for notices that point at a control: Site Log's delete confirmations
 * survive an apostrophe in any language, and the texts of Pages, Storage
 * Engine, Repair Software, the offer editor, the e-invoice inbox and
 * Translations name the button they mean instead of "OK", "the button", a
 * colour or a direction.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function notice_button_names_test_source($file)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
}

function test_notice_button_names_view_log_confirms_are_json()
{
	$source = notice_button_names_test_source('view_log.php');

	pg_assert_true(strpos($source, "pgConfirm({title:\\'") === false, 'no confirm option is a hand-quoted translated string');
	pg_assert_same(2, substr_count($source, "pgConfirm(' . json_encode("), 'both delete confirmations pass their options as JSON');
	pg_assert_true((bool) preg_match('/name="delete_all_site_logs"[^>]*onclick="\' \. h\(/', $source), 'the site logs handler is escaped with h()');
	pg_assert_true((bool) preg_match('/name="delete_all_error_logs"[^>]*onclick="\' \. h\(/', $source), 'the error logs handler is escaped with h()');
	pg_assert_true(strpos($source, 'please click OK') === false, 'the dialogs do not ask for an OK button they do not have');
}

function test_notice_button_names_texts_name_their_button()
{
	$cases = array(
		'view_pages.php' => array(
			'gone' => array('click the button again'),
			'named' => array('click Refresh SEO Scores again', 'click Analyze HTML Structure again'),
		),
		'database_engine.php' => array(
			'gone' => array('the button below'),
			'named' => array('"Clean up visitor records now"'),
		),
		'software_repair.php' => array(
			'gone' => array('is red on System Status', 'before you press the button'),
			'named' => array('the Files check on System Status shows Problem', 'press "Run the steps again"'),
		),
		'edit_offer.php' => array(
			'gone' => array('on the right'),
			'named' => array('Use Add condition', 'Use Add result'),
		),
		'erp_inbox_document.php' => array(
			'gone' => array('details on the left'),
			'named' => array('the supplier details in this card'),
		),
		'translations.php' => array(
			'gone' => array('Press "Update translations"', ': "Update translations" scans', 'the next "Update translations"'),
			'named' => array('"{var:1}"'),
		),
	);

	foreach ($cases as $file => $case) {
		$source = notice_button_names_test_source($file);

		foreach ($case['gone'] as $text) {
			pg_assert_true(strpos($source, $text) === false, $file . ' no longer says: ' . $text);
		}

		foreach ($case['named'] as $text) {
			pg_assert_contains($text, $source, $file . ' names its button: ' . $text);
		}
	}
}

function test_notice_button_names_translations_label_is_computed_once()
{
	$source = notice_button_names_test_source('translations.php');

	pg_assert_same(1, substr_count($source, "lang('Update this page')"), 'the Update button label is chosen in one place');
	pg_assert_same(5, substr_count($source, '$update_button_label'), 'the label is set, drawn on the button and named by the three notes');
}
