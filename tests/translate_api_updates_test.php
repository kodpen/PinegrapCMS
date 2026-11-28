<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests the translation API's updates (includes/translate/api.php): which
 * engines an update may be started with, the routes and their rehearsals,
 * the glossary delete with its POST twin, the groups of the strings listing,
 * and the translation.update_finished event of every move that ends an
 * update. Needs no database.
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

require_once(PG_FUNCTIONS_DIR . '/includes/translate/store.php');
require_once(PG_FUNCTIONS_DIR . '/includes/translate/engines.php');
require_once(PG_FUNCTIONS_DIR . '/includes/translate/jobs.php');
require_once(PG_FUNCTIONS_DIR . '/includes/translate/api.php');

// The function's source with its comments taken out.
function translate_api_updates_test_source($function_name)
{
	$function = new ReflectionFunction($function_name);
	$lines = file($function->getFileName());

	return preg_replace('~^\s*(//|\*).*$~m', '', implode('', array_slice($lines, $function->getStartLine() - 1, $function->getEndLine() - $function->getStartLine() + 1)));
}

function translate_api_updates_test_routes()
{
	$routes = array();

	foreach (translate_api_routes() as $route) {
		$routes[$route['id']] = $route;
	}

	return $routes;
}

// A server engine or Claude's queue that is ready starts an update; "By
// hand", the CSV import, a browser engine and an engine not ready do not.
function test_translate_api_update_refusal()
{
	pg_assert_same(null, translate_api_update_refusal('google', array('kind' => 'server', 'ready' => true)));
	pg_assert_same(null, translate_api_update_refusal('claude', array('kind' => 'queue', 'ready' => true)));

	foreach (array('manual', 'import') as $engine) {
		$refusal = translate_api_update_refusal($engine, array('kind' => 'panel', 'ready' => true));
		pg_assert_same('translated_by_hand', $refusal[0], $engine);
	}

	foreach (array('chrome', 'chrome_ai') as $engine) {
		$refusal = translate_api_update_refusal($engine, array('kind' => 'browser', 'ready' => true));
		pg_assert_same('engine_in_browser', $refusal[0], $engine);
	}

	$refusal = translate_api_update_refusal('google', array('kind' => 'server', 'ready' => false, 'reason' => 'No key.'));
	pg_assert_same(array('engine_not_ready', 'No key.'), $refusal);

	$refusal = translate_api_update_refusal('gone', null);
	pg_assert_same('engine_not_ready', $refusal[0], 'an engine not known');
}

// The update routes: read with translations:read, started with
// translations:write and a rehearsal that stops after every check.
function test_translate_api_update_routes()
{
	$routes = translate_api_updates_test_routes();

	pg_assert_same('translations:read', $routes['translations.updates.list']['scope']);
	pg_assert_same('translations:read', $routes['translations.updates.get']['scope']);
	pg_assert_same('translations:write', $routes['translations.updates.start']['scope']);
	pg_assert_true(!empty($routes['translations.updates.start']['dry_run']), 'a start can be rehearsed');

	$code = translate_api_updates_test_source('translate_api_updates_start');
	$stop = strpos($code, 'api_dry_run_stop(');

	pg_assert_true($stop !== false, 'the start stops a rehearsal');
	pg_assert_true(strpos($code, 'translate_api_update_refusal(') < $stop, 'the engine is checked before the stop');
	pg_assert_true(strpos($code, "'update_running'") < $stop, 'an open update of the scope is checked before the stop');
	pg_assert_true($stop < strpos($code, 'pg_tr_update('), 'nothing is read off the pages or sent in a rehearsal');
}

// The glossary delete: DELETE with its POST twin on one handler, which stops
// a rehearsal after finding the term and before the DELETE.
function test_translate_api_glossary_delete()
{
	$routes = translate_api_updates_test_routes();

	pg_assert_same('DELETE', $routes['translations.glossary.delete']['method']);
	pg_assert_same('/translations/glossary/{id}/delete', $routes['translations.glossary.delete_post']['path']);
	pg_assert_same($routes['translations.glossary.delete']['handler'], $routes['translations.glossary.delete_post']['handler']);
	pg_assert_true(!empty($routes['translations.glossary.delete_post']['dry_run']));

	$code = translate_api_updates_test_source('translate_api_glossary_delete');

	pg_assert_true(strpos($code, 'api_fail_not_found(') < strpos($code, 'api_dry_run_stop('), 'a missing term is 404 in a rehearsal too');
	pg_assert_true(strpos($code, 'api_dry_run_stop(') < strpos($code, 'DELETE FROM translation_glossary'), 'the rehearsal stops before the delete');
}

// The strings listing and the start take every group of the Translations
// screen, Images (pictures) and Cookie window (ui) included.
function test_translate_api_groups_follow_the_screen()
{
	$routes = translate_api_updates_test_routes();

	foreach (array('translations.strings.list', 'translations.updates.start') as $id) {
		foreach ($routes[$id]['params'] as $param) {
			if ($param['name'] === 'group') {
				pg_assert_same(array('catalog', 'forms', 'pictures', 'ui'), $param['values'], $id);
			}
		}
	}
}

// Every move that ends an update announces it, once, from the move that
// changed the row.
function test_translate_update_finished_is_announced()
{
	pg_assert_contains("pg_announce('translation.update_finished'", translate_api_updates_test_source('pg_tr_job_announce_finished'));

	foreach (array('pg_tr_job_close_if_complete', 'pg_tr_job_fail', 'pg_tr_job_cancel') as $function_name) {
		$code = translate_api_updates_test_source($function_name);

		pg_assert_contains('pg_tr_job_announce_finished(', $code, $function_name);
		pg_assert_true(strpos($code, 'mysqli_affected_rows(') < strpos($code, 'pg_tr_job_announce_finished('), $function_name . ' announces only the move that closed it');
	}

	$events = translate_webhook_events();
	$scopes = translate_webhook_event_scopes();

	pg_assert_true(isset($events['translation.update_finished']));
	pg_assert_same('translations:read', $scopes['translation.update_finished']);
}
