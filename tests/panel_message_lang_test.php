<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source pins for panel messages that were English literals: the software
 * updater's step messages and the archive extraction failures it shows,
 * the barcode inventory screens' alerts and endpoint answers, the backup
 * download failures and the contacts list's opt-in texts.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function panel_message_lang_source($path)
{
	return (string) file_get_contents(dirname(__DIR__) . '/pinegrap/' . $path);
}

function test_panel_message_lang_software_update_steps()
{
	$source = panel_message_lang_source('includes/panel/software.php');
	preg_match_all("/'message' => '([^']*)'/", $source, $matches);

	// The step is validated before the switch, so these two never reach a screen.
	pg_assert_same(array('Invalid step.', 'Crashed.'), $matches[1], 'software.php: bare English step messages');
	pg_assert_contains("lang(array('string' => 'Error while get files from the update server.{var:1}', 'vars' => array(pg_curl_tls_hint(\$curl_errno, true))))", $source);

	$source = panel_message_lang_source('includes/fn/update.php');
	pg_assert_true(!preg_match("/\\\$failure\\['message'\\] = (?!lang\\()/", $source), 'update.php: an extraction failure message outside lang()');

	// A step's message may carry server file names: every step prints it as text.
	$source = panel_message_lang_source('software_update.php');
	pg_assert_same(1, substr_count($source, 'log_box.text(response.message);'), 'software_update.php: the step messages printed as text');
	pg_assert_true(strpos($source, '.html(response.message') === false, 'software_update.php: no step message printed as HTML');
}

function test_panel_message_lang_barcode_and_backups()
{
	foreach (array('barcode_increase_inventory.php', 'barcode_decrease_inventory.php') as $path) {
		$source = panel_message_lang_source($path);
		pg_assert_true(!preg_match("/'message' => '/", $source), $path . ': an endpoint message outside lang()');
		pg_assert_contains('alert(response.message);', $source, $path);
		pg_assert_contains("alert(\"' . escape_javascript(lang('No answer came, so the inventory quantity may or may not have changed. Check the inventory of the product before scanning again.')) . '\");", $source, $path);
	}

	$source = panel_message_lang_source('backups.php');
	// The one die() left runs after the zip headers, so its text is never shown.
	pg_assert_same(1, substr_count($source, 'die("'), 'backups.php: an English die() before the download headers');
}

function test_panel_message_lang_contacts_opt_in()
{
	$source = panel_message_lang_source('view_contacts.php');
	pg_assert_true(strpos($source, '>Opt-In</option>') === false && strpos($source, '>Opt-Out</option>') === false, 'view_contacts.php: literal opt-in options');
	pg_assert_contains("<input value=\"' . h(lang('Opt-In')) . '\" type=\"text\"", $source, 'My Subscribers shows the translated status in a box');
	pg_assert_contains("title=\"' . h(lang('Hide Contact Groups')) . '\"", $source);
	pg_assert_contains("title=\"' . h(lang('Show Contact Groups')) . '\"", $source);
}
