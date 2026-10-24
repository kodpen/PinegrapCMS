<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * A liveform message is HTML: the values the messages of a custom form's
 * submit and of the membership entrance carry from stored records and site
 * settings (a field's label, the validation message written for it, the
 * member ID label) go in through h(). Each row is a file, one line of it and
 * how many times that line occurs, so an escape that is taken out again is
 * noticed.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_message_values_forms_are_escaped()
{
	$lines = array(
		array('custom_form.php', '$error_message = lang(array(\'string\'=>\'{var:1} is required.\',\'vars\'=>h(function_exists(\'pg_tr_label\') ? pg_tr_label($field[\'label\']) : $field[\'label\'])));', 2),
		array('custom_form.php', '$liveform->mark_error($field[\'id\'], lang(array(\'string\'=>\'Please enter a valid date for {var:1}\',\'vars\'=>h(function_exists(\'pg_tr_label\') ? pg_tr_label($field[\'label\']) : $field[\'label\']))) );', 1),
		array('custom_form.php', '$liveform->mark_error($field[\'id\'], lang(array(\'string\'=>\'Please enter a valid date & time for {var:1}\',\'vars\'=>h(function_exists(\'pg_tr_label\') ? pg_tr_label($field[\'label\']) : $field[\'label\']))) );', 1),
		array('custom_form.php', '$liveform->mark_error($field[\'id\'], lang(array(\'string\'=>\'Please enter a valid e-mail address for {var:1}\',\'vars\'=>h(function_exists(\'pg_tr_label\') ? pg_tr_label($field[\'label\']) : $field[\'label\']))) );', 1),
		array('custom_form.php', '$liveform->mark_error($field[\'id\'], lang(array(\'string\'=>\'Please enter a valid time for {var:1}\',\'vars\'=>h(function_exists(\'pg_tr_label\') ? pg_tr_label($field[\'label\']) : $field[\'label\']))) );', 1),
		array('custom_form.php', '$message = h(function_exists(\'pg_tr_label\') ? pg_tr_label($message) : $message);', 1),
		array('custom_form.php', '$message = lang(array(\'string\'=>\'Please enter a valid value for {var:1}\',\'vars\'=>h(function_exists(\'pg_tr_label\') ? pg_tr_label($field[\'label\']) : $field[\'label\'])));', 1),
		array('custom_form.php', '$liveform->mark_error($field[\'id\'], lang(array(\'string\'=>\'Sorry, that {var:1} is already in use. Can you please enter a different {var:1}?\',\'vars\'=>h(function_exists(\'pg_tr_label\') ? pg_tr_label($field[\'label\']) : $field[\'label\']))) );', 1),
		array('includes/fn/auth.php', '$form->validate_required_field(\'member_id\', lang(array(\'string\' => \'{var:1} is required.\', \'vars\' => h(MEMBER_ID_LABEL))));', 1),
		array('includes/fn/auth.php', '\'vars\'   => h(MEMBER_ID_LABEL))));', 1),
	);

	foreach ($lines as $line) {
		$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $line[0]);

		pg_assert_same($line[2], substr_count($source, $line[1]), $line[0] . ': ' . $line[1]);
	}
}
