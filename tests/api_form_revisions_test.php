<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the revisions of a collection record over the external API
 * (GET /forms/{id}/submissions/{submission_id}/revisions, one revision and
 * its restore): the rows a restore writes back, the revision object, the
 * field-by-field comparison with the restore status, the declared shapes
 * and the routes' refusals.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!defined('PG_API_PANEL')) {
	define('PG_API_PANEL', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/api/resources/forms.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/schema.php');

// The fields of a collection form as pg_record_revision_form_fields() reads them.
function api_form_revisions_test_fields()
{
	return array(
		array('id' => '3', 'label' => 'Title', 'name' => 'title', 'type' => 'text box', 'wysiwyg' => '0'),
		array('id' => '4', 'label' => 'Body', 'name' => 'body', 'type' => 'text area', 'wysiwyg' => '1'),
		array('id' => '5', 'label' => 'Tags', 'name' => 'tags', 'type' => 'check box', 'wysiwyg' => '0'),
		array('id' => '6', 'label' => 'Day', 'name' => 'day', 'type' => 'date', 'wysiwyg' => '0'),
		array('id' => '7', 'label' => 'Picture', 'name' => 'picture', 'type' => 'file upload', 'wysiwyg' => '0'),
		array('id' => '8', 'label' => 'Signed', 'name' => 'signed', 'type' => 'signature', 'wysiwyg' => '0'),
	);
}

// One row per value, typed as the edit screen types it; an empty field
// gets one empty row; file and signature fields are left to the file step.
function test_api_form_revisions_restore_rows()
{
	$fields   = api_form_revisions_test_fields();
	$revision = array(
		'3' => array('Old title'),
		'4' => array('<p>Old</p>'),
		'5' => array('a', 'b'),
		'7' => array(array('file_id' => 41)),
	);
	$plan = pg_record_revision_restore_plan($fields, $revision, array('7' => array(array('file_id' => 42))), array(41));

	$rows = pg_record_revision_restore_rows($fields, $revision, $plan);

	pg_assert_same(array('3', '4', '5', '6'), array_map('strval', array_keys($rows)), 'value fields only');
	pg_assert_same(array(array('data' => 'Old title', 'type' => 'standard', 'name' => 'title')), $rows['3']);
	pg_assert_same('html', $rows['4'][0]['type'], 'rich text');
	pg_assert_same(array('a', 'b'), array_column($rows['5'], 'data'), 'one row per choice');
	pg_assert_same(array(array('data' => '', 'type' => 'date', 'name' => 'day')), $rows['6'], 'emptied, keeps its type');
	pg_assert_same('file', $plan['7'], 'the file is attached again by the file step');
}

// The revision object: user and address name null when missing, current
// by the hash, a stored source outside the list read as edit.
function test_api_form_revisions_present()
{
	$row = array(
		'revision_no'   => '4',
		'record_json'   => '{"address_name":"old-title","complete":1}',
		'values_hash'   => 'abc',
		'user_id'       => '9',
		'source'        => 'restore',
		'created_at'    => '1700000000',
		'user_username' => 'editor',
	);

	$out = api_form_revision_present($row, 'abc');

	pg_assert_same(4, $out['revision_no']);
	pg_assert_same('restore', $out['source']);
	pg_assert_same(9, $out['user_id']);
	pg_assert_same('editor', $out['user']);
	pg_assert_same('old-title', $out['address_name']);
	pg_assert_true($out['complete'], 'complete');
	pg_assert_true($out['current'], 'the record holds it');
	pg_assert_same(array_keys(api_form_revision_schema()), array_keys($out), 'declared shape');

	$gone = api_form_revision_present(array('record_json' => 'broken', 'user_id' => '0', 'user_username' => null, 'source' => 'x') + $row, 'def');

	pg_assert_same(null, $gone['user_id']);
	pg_assert_same(null, $gone['user']);
	pg_assert_same(null, $gone['address_name']);
	pg_assert_false($gone['complete'], 'broken record JSON');
	pg_assert_false($gone['current'], 'another hash');
	pg_assert_same('edit', $gone['source']);
}

// Each field beside the current one, with what a restore does to it.
function test_api_form_revisions_values_present()
{
	$revision = array(
		'3' => array('Old title'),
		'5' => array('a'),
		'7' => array(array('file_id' => 41)),
		'8' => array(array('file_id' => 50)),
	);
	$current = array(
		'3' => array('New title'),
		'5' => array('a'),
		'7' => array(array('file_id' => 42)),
		'8' => array(array('file_id' => 51)),
	);

	$out = array();

	foreach (api_form_revision_values_present(api_form_revisions_test_fields(), $revision, $current, array()) as $row) {
		$out[$row['name']] = $row;
	}

	pg_assert_same(array('title', 'body', 'tags', 'day', 'picture', 'signed'), array_keys($out), 'form order');
	pg_assert_same('value', $out['title']['restore']);
	pg_assert_same(array('Old title'), $out['title']['values']);
	pg_assert_same(array('New title'), $out['title']['current_values']);
	pg_assert_true($out['title']['changed'], 'changed');
	pg_assert_same('unchanged', $out['tags']['restore'], 'same answer');
	pg_assert_same('unchanged', $out['body']['restore'], 'empty on both sides');
	pg_assert_same('gone', $out['picture']['restore'], 'its file was deleted since');
	pg_assert_same(41, $out['picture']['file_id']);
	pg_assert_same(42, $out['picture']['current_file_id']);
	pg_assert_same(array(), $out['picture']['values'], 'a file is no text value');
	pg_assert_same('kept', $out['signed']['restore'], 'a signature is never redrawn');

	$detail = api_form_revision_detail_schema();

	pg_assert_same(array_keys($detail['values'][0]), array_keys($out['title']), 'declared value shape');
}

// The three routes: the record loaded through the folder rule, the
// collection check before anything else, the restore rehearsable with the
// stop above its first write and recorded as a restore.
function test_api_form_revisions_routes()
{
	$routes = array();

	foreach (api_schema() as $route) {
		$routes[$route['id']] = $route;
	}

	pg_assert_same('/forms/{id}/submissions/{submission_id}/revisions', $routes['forms.submission_revisions']['path']);
	pg_assert_same('GET', $routes['forms.submission_revision_get']['method']);
	pg_assert_same('POST', $routes['forms.submission_revision_restore']['method']);
	pg_assert_true(!empty($routes['forms.submission_revision_restore']['dry_run']), 'restore takes dry_run');

	foreach (array('api_form_submission_revisions', 'api_form_submission_revision_get', 'api_form_submission_revision_restore') as $handler) {
		$function = new ReflectionFunction($handler);
		$source   = implode('', array_slice(file($function->getFileName()), $function->getStartLine() - 1, $function->getEndLine() - $function->getStartLine() + 1));
		$owned    = strpos($source, 'api_form_submission_owned(');
		$ready    = strpos($source, 'api_form_revisions_ready(');

		pg_assert_true($owned !== false && $ready !== false && $owned < $ready, $handler . ': folder rule, then the collection check');
	}

	$function = new ReflectionFunction('api_form_submission_revision_restore');
	$source   = implode('', array_slice(file($function->getFileName()), $function->getStartLine() - 1, $function->getEndLine() - $function->getStartLine() + 1));
	$stop     = strpos($source, 'api_dry_run_stop(');

	pg_assert_true($stop !== false && $stop < strpos($source, 'pg_record_revision_restore_values('), 'the stop above the write');
	pg_assert_contains("'restore'", substr($source, strpos($source, 'pg_record_revision_after('), 120), 'recorded as a restore');

	$ready = new ReflectionFunction('api_form_revisions_ready');
	$body  = implode('', array_slice(file($ready->getFileName()), $ready->getStartLine() - 1, $ready->getEndLine() - $ready->getStartLine() + 1));

	pg_assert_contains("'not_a_collection'", $body, 'a record of another form');
	pg_assert_contains('pg_record_revision_history_enabled(', $body, 'the History section\'s own condition');
}
