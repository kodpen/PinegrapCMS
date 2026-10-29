<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a Site Settings save the server refuses: the fields the save
 * modules marked go up by name with their sentence
 * (pg_settings_marked_fields(), includes/settings/registry.php), and the
 * dialog marks them, brings its notice into view and focuses the first one.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!defined('PG_SETTINGS_MENU')) {
	define('PG_SETTINGS_MENU', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/settings/registry.php');

function test_settings_marked_fields_by_name_with_sentence()
{
	$state = array(
		'mailchimp_key'        => array('value' => 'x', 'error' => true, 'error_message' => 'Mailchimp refused the key.'),
		'organization'         => array('value' => 'Kodpen', 'error' => false, 'error_message' => ''),
		'backup_ftp_host'      => array('value' => '', 'error' => true, 'error_message' => ''),
		'languages'            => array('error' => true, 'error_message' => 'Sorry, the value you entered for languages is not valid.'),
		'_form_error_0'        => array('error' => true, 'error_message' => 'Something no field owns.'),
		'notices'              => array('Saved.'),
		'warnings'             => array('Careful.'),
		'audit_retention_days' => array('value' => '0'),
	);

	pg_assert_same(array(
		'mailchimp_key'   => 'Mailchimp refused the key.',
		'backup_ftp_host' => '',
		'languages'       => 'Sorry, the value you entered for languages is not valid.',
	), pg_settings_marked_fields($state), 'marked fields only, form-level errors and message lists skipped');

	pg_assert_same(array(), pg_settings_marked_fields(array()), 'nothing marked');
}

function test_settings_pane_invalid_answer_names_fields()
{
	$pane = (string) file_get_contents(PG_FUNCTIONS_DIR . '/settings_pane.php');
	$at = strpos($pane, "'status' => 'invalid'");

	pg_assert_true($at !== false, 'invalid answer found');
	pg_assert_contains('\'fields\' => $fields ? $fields : new stdClass()', substr($pane, (int) $at, 300), 'invalid answer carries the marked fields');
	pg_assert_contains('pg_settings_marked_fields(', $pane);
}

function test_settings_dialog_marks_refused_fields()
{
	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/backend.src.js');
	$at = strpos($js, "response.status === 'invalid'");

	pg_assert_true($at !== false, 'invalid branch found');
	pg_assert_contains('refused(response.fields', substr($js, (int) $at, 600), 'invalid branch marks the fields');

	$fn = strpos($js, 'function refused(fields)');
	pg_assert_true($fn !== false, 'refused() found');
	$body = substr($js, (int) $fn, 2000);
	pg_assert_contains('window.pgFieldInvalid(', $body, 'marks through the shared helper');
	pg_assert_contains('form.scrollTop = 0;', $body, 'notice brought into view');
	pg_assert_contains('.focus()', $body, 'first marked field focused');
	pg_assert_contains('CSS.escape', $js, 'names like languages[tr][prefix] escaped');
	pg_assert_contains("'[data-pg-sm-marked]'", $js, 'mark cleared on input');
}
