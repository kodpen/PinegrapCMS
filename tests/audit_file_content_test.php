<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the audit trail of a file's content (includes/fn/audit.php) and
 * the name a settings line is shown under (includes/fn/audit_view.php).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// A content change of the same size still differs; the digest is kept in clear.
function test_audit_file_content_same_size_differs()
{
	$row = array('id' => '7', 'name' => 'note.txt', 'size' => '16');

	$before = pg_audit_with_content($row, sha1('pg-test line one'));
	$after = pg_audit_with_content($row, sha1('pg-test line two'));

	list($old, $new) = pg_audit_diff(pg_audit_volatile('files', $before), pg_audit_volatile('files', $after));

	pg_assert_same(array('content_sha1'), array_keys($new), 'only the content differs');
	pg_assert_same(sha1('pg-test line two'), $new['content_sha1'], 'digest after');
	pg_assert_same(sha1('pg-test line one'), $old['content_sha1'], 'digest before');
	pg_assert_same(false, pg_audit_secret_key('content_sha1'), 'not masked as a secret');
}

// No row stays no row; an unreadable file adds no field.
function test_audit_file_content_leaves_missing_alone()
{
	pg_assert_same(array(), pg_audit_with_content(array(), sha1('x')), 'no row');
	pg_assert_same(array('id' => '7'), pg_audit_with_content(array('id' => '7'), ''), 'no digest');
	pg_assert_same('', pg_audit_file_sha1(PG_FUNCTIONS_DIR . '/no-such-file-' . mt_rand() . '.txt'), 'missing file');
	pg_assert_same(sha1_file(__FILE__), pg_audit_file_sha1(__FILE__), 'readable file');
}

// A settings line is shown under its category's name; other names stay.
function test_audit_view_record_name_settings_category()
{
	$names = array('api' => 'Integrations', 'maintenance' => 'Updates and maintenance', 'seo' => 'SEO');

	pg_assert_same('Integrations', pg_audit_view_record_name('settings', 'api', $names), 'api category');
	pg_assert_same('Updates and maintenance', pg_audit_view_record_name('settings', 'maintenance', $names), 'maintenance category');
	pg_assert_same('SMTP', pg_audit_view_record_name('settings', 'SMTP', $names), 'not a category key');
	pg_assert_same('commerce', pg_audit_view_record_name('settings', 'commerce', $names), 'a key the registry no longer has');
	pg_assert_same('api', pg_audit_view_record_name('file', 'api', $names), 'other types keep the label');
	pg_assert_same('', pg_audit_view_record_name('settings', '', $names), 'empty label');
}

// The registry names every category key a settings save stores.
function test_audit_view_settings_names_cover_the_save_modules()
{
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/includes/settings/registry.php');

	foreach (glob(PG_FUNCTIONS_DIR . '/includes/settings/*.save.php') as $module) {
		$key = basename($module, '.save.php');
		pg_assert_true(preg_match("/\n        '" . preg_quote($key, '/') . "' => array\(\n            'label' => /", $source) === 1, $key . ' has a label in the registry');
	}
}
