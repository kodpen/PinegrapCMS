<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * A filled text is cut to its field's length where it lands: the e-mail
 * subject and body, the task digest's subject and intro, the webhook text
 * and the summary title keep the length the action form gives the text as
 * written, whatever the placeholders put in.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/bootstrap.php');

function workspace_scheduled_fill_limits_source()
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/scheduled.php');
}

function test_workspace_scheduled_fill_limits_map()
{
	pg_assert_same(array(
		'email_subject'  => 250,
		'email_body'     => 20000,
		'digest_subject' => 250,
		'digest_intro'   => 2000,
		'webhook_text'   => 4000,
		'summary_title'  => 160,
	), ws_scheduled_text_limits(), 'limits');
}

function test_workspace_scheduled_fill_limits_webhook_text_of_ten_derived_values()
{
	$limits = ws_scheduled_text_limits();
	$value = str_repeat('x', 4000);
	$text = ws_scheduled_fill_cut(str_repeat($value . ' ', 10), $limits['webhook_text']);

	pg_assert_same(4000, mb_strlen($text), 'webhook text length');
}

function test_workspace_scheduled_fill_limits_email()
{
	$limits = ws_scheduled_text_limits();

	pg_assert_same(20000, mb_strlen(ws_scheduled_fill_cut(str_repeat('b', 50000), $limits['email_body'])), 'body');
	pg_assert_same(250, mb_strlen(ws_scheduled_fill_cut(str_repeat('s', 600), $limits['email_subject'])), 'subject');
}

function test_workspace_scheduled_fill_limits_multibyte_cut_by_character()
{
	$text = ws_scheduled_fill_cut(str_repeat('ğüşİ€😀', 100), 250);

	pg_assert_same(250, mb_strlen($text), 'characters');
	pg_assert_true(mb_check_encoding($text, 'UTF-8'), 'valid UTF-8');
	pg_assert_same(mb_substr(str_repeat('ğüşİ€😀', 100), 0, 250), $text, 'leading characters kept');
}

function test_workspace_scheduled_fill_limits_short_text_unchanged()
{
	pg_assert_same('Hello Ayşe', ws_scheduled_fill_cut('Hello Ayşe', 160), 'short');
	pg_assert_same('', ws_scheduled_fill_cut('', 160), 'empty');
	pg_assert_same(str_repeat('a', 160), ws_scheduled_fill_cut(str_repeat('a', 160), 160), 'exactly the limit');
}

// The action form cuts the written text with the same limits.
function test_workspace_scheduled_fill_limits_form_reads_the_same_limits()
{
	$source = workspace_scheduled_fill_limits_source();
	$start = strpos($source, 'function ws_scheduled_action_input(');
	$end = strpos($source, "\nfunction ", $start + 10);
	$form = substr($source, $start, $end - $start);

	foreach (array_keys(ws_scheduled_text_limits()) as $key) {
		pg_assert_true(strpos($form, "\$limits['" . $key . "']") !== false, 'form uses ' . $key);
	}

	foreach (array(', 0, 250)', ', 0, 20000)', ', 0, 2000)', ', 0, 4000)', ', 0, 160)') as $literal) {
		pg_assert_false(strpos($form, $literal) !== false, 'form has no literal ' . $literal);
	}
}

// Each landing cuts the filled text (ws_scheduled_do() sends mail, so it is
// checked in the source).
function test_workspace_scheduled_fill_limits_landings_cut()
{
	$source = workspace_scheduled_fill_limits_source();

	foreach (array(
		"\$subject = ws_scheduled_fill_cut(ws_scheduled_fill((string) \$action['subject'], \$context), \$limits['email_subject']);",
		"\$body = ws_scheduled_fill_cut(ws_scheduled_fill((string) \$action['body'], \$context), \$limits['email_body']);",
		"\$intro = ws_scheduled_fill_cut(ws_scheduled_fill((string) (\$action['intro'] ?? ''), \$context), \$limits['digest_intro']);",
		"\$text = ws_scheduled_fill_cut(ws_scheduled_fill((string) (\$action['text'] ?? ''), \$context), \$limits['webhook_text']);",
	) as $line) {
		pg_assert_true(strpos($source, $line) !== false, $line);
	}

	pg_assert_true((bool) preg_match("/\\\$subject = ws_scheduled_fill_cut\\(ws_scheduled_fill\\(.*\\\$limits\\['digest_subject'\\]\\);/", $source), 'digest subject');
	pg_assert_true((bool) preg_match("/\\\$title = ws_scheduled_fill_cut\\(ws_scheduled_fill\\(.*\\\$limits\\['summary_title'\\]\\);/", $source), 'summary title');
}
