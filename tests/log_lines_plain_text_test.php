<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests that Site Log lines and the Catalog Manager's toast messages are
 * stored and sent as plain text. log_activity() keeps the sentence as it is
 * given, and every reader escapes it once (Site Log, the dashboard Activity
 * card, Edit User's activity list); the File/Catalog Manager toast sets its
 * message with textContent. A name escaped inside the sentence's vars would
 * show "Çay &amp; Kahve" on all of them.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function log_lines_plain_text_test_files()
{
	return array(
		'includes/explorer/handle_catalog.php',
		'includes/explorer/handle_catalog_edit.php',
		'includes/explorer/recycle_pages_styles.php',
		'includes/workspace/changes.php',
		'edit_product_group.php',
		'variant_set_action.php',
		'edit_short_link.php',
		'includes/explorer/handle_short_links.php',
		'includes/explorer/handle_recycle.php',
		'includes/explorer/handle_upload.php',
		'image_editor_save.php',
		'includes/panel/software.php',
	);
}

function log_lines_plain_text_test_source($relative)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $relative);
}

// Every call that starts with $opening in $source, up to its balanced
// closing parenthesis. Strings are not parsed: the sentences these files
// pass contain no unbalanced parentheses.
function log_lines_plain_text_test_calls($source, $opening)
{
	$calls = array();
	$offset = 0;

	while (($start = strpos($source, $opening, $offset)) !== false) {
		$depth = 0;
		$length = strlen($source);

		for ($i = $start + strlen($opening) - 1; $i < $length; $i++) {
			if ($source[$i] === '(') {
				$depth++;
			} elseif ($source[$i] === ')') {
				$depth--;

				if ($depth === 0) {
					break;
				}
			}
		}

		$calls[] = substr($source, $start, $i - $start + 1);
		$offset = $start + strlen($opening);
	}

	return $calls;
}

function test_log_lines_plain_text_log_calls_carry_no_escaped_vars()
{
	foreach (log_lines_plain_text_test_files() as $file) {
		$source = log_lines_plain_text_test_source($file);

		pg_assert_true($source !== '', $file . ' is readable');

		foreach (array('log_activity(', 'ws_change_log(') as $opening) {
			foreach (log_lines_plain_text_test_calls($source, $opening) as $call) {
				pg_assert_false(
					(bool) preg_match('/(?<![\w$>])h\(/', $call),
					$file . ': ' . substr(preg_replace('/\s+/', ' ', $call), 0, 120));
			}
		}
	}
}

function test_log_lines_plain_text_explorer_messages_carry_no_escaped_vars()
{
	foreach (array('includes/explorer/handle_catalog.php', 'includes/explorer/handle_upload.php') as $file) {
		$calls = log_lines_plain_text_test_calls(log_lines_plain_text_test_source($file), "'message' => lang(");

		pg_assert_true(count($calls) > 0, $file . ' has messages');

		foreach ($calls as $call) {
			pg_assert_false(
				(bool) preg_match('/(?<![\w$>])h\(/', $call),
				$file . ': ' . substr(preg_replace('/\s+/', ' ', $call), 0, 120));
		}
	}
}

function test_log_lines_plain_text_quick_edit_sentence_uses_raw_name()
{
	$calls = log_lines_plain_text_test_calls(
		log_lines_plain_text_test_source('includes/explorer/handle_catalog_edit.php'),
		'$quick_message = lang(');

	pg_assert_same(1, count($calls), 'one quick-edit sentence');
	pg_assert_contains("'vars' => array(\$quick_row['name'], \$quick_note)", $calls[0], 'raw product name');
}

// The backup screen appends the JSON message as HTML, so only the log line
// loses its escape.
function test_log_lines_plain_text_backup_error_json_stays_escaped()
{
	pg_assert_contains(
		"'message' => h(\$backups_error_message)",
		log_lines_plain_text_test_source('includes/panel/software.php'),
		'backup error JSON message');
}
