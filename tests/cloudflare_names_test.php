<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source pins for accessible names on Cloudflare Tools and System Info: the
 * five setting switches and every Add DNS Record field have an id that their
 * label points at, both DNS dialogs are named by their title, and the PHP
 * information frame carries a title.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function cloudflare_names_source($path)
{
	return (string) file_get_contents(dirname(__DIR__) . '/pinegrap/' . $path);
}

function test_cloudflare_names_switches_have_labels()
{
	$source = cloudflare_names_source('cloudflare.php');

	// The label sits after the .switch box, so only for= binds it.
	foreach (array('cf_dev', 'cf_uam', 'cf_https', 'cf_brotli', 'cf_rocket') as $id) {
		pg_assert_true((bool) preg_match('/<input class="" type="checkbox" id="' . $id . '" onchange="this\.form\.submit\(\)"/', $source), $id . ' switch has the id');
		pg_assert_contains('<label class="" for="' . $id . '">', $source, $id . ' label');
	}

	pg_assert_true(!preg_match('/<input class="" type="checkbox" onchange=/', $source), 'no switch without an id');
}

function test_cloudflare_names_add_record_fields_have_labels()
{
	$source = cloudflare_names_source('cloudflare.php');
	$start = strpos($source, '<dialog class="dialog" id="addModal"');
	pg_assert_true($start !== false, 'add dialog found');
	$dialog = substr($source, $start, strpos($source, '</dialog>', $start) - $start);

	$ids = array('add_type', 'add_name', 'add_content', 'add_ttl', 'add_proxied', 'add_priority', 'add_service', 'add_proto', 'add_priority2', 'add_weight', 'add_port', 'add_target', 'add_flags', 'add_tag', 'add_value');
	foreach ($ids as $id) {
		pg_assert_true((bool) preg_match('/<(input|select)[^>]* id="' . $id . '"/', $dialog), $id . ' field has the id');
		pg_assert_true((bool) preg_match('/<label[^>]* for="' . $id . '"/', $dialog), $id . ' label points at it');
	}

	// Every visible field of the dialog is named by a label.
	preg_match_all('/<(?:input|select)\b(?![^>]*type="hidden")[^>]*>/', $dialog, $fields);
	foreach ($fields[0] as $field) {
		pg_assert_true((bool) preg_match('/ id="([^"]+)"/', $field, $m), 'field has an id: ' . $field);
		pg_assert_contains('for="' . $m[1] . '"', $dialog, $m[1] . ' label');
	}
}

function test_cloudflare_names_dialogs_named_by_their_title()
{
	$source = cloudflare_names_source('cloudflare.php');

	foreach (array('addModal', 'editModal') as $id) {
		pg_assert_contains('id="' . $id . '" aria-labelledby="' . $id . '_title"', $source, $id);
		pg_assert_true((bool) preg_match('/<h5 class="dialog-title" id="' . $id . '_title">/', $source), $id . ' title has the id');
	}
}

function test_cloudflare_names_system_info_frame_has_title()
{
	$source = cloudflare_names_source('includes/templates/si.php');
	pg_assert_true((bool) preg_match('/<iframe src="pi\.php" title="<\?=h\(lang\(\'PHP information\'\)\)\?>"/', $source), 'phpinfo frame has a title');
}
