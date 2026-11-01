<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the one place that says which pages each background pass renders
 * (pg_render_pass_scope() and pg_render_pass_covers() in
 * includes/fn/background_render.php): the search index keeps private folders,
 * the structure and accessibility audits only public ones, and the audit
 * buttons render as a visitor who is not signed in, as the jobs do.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/background_render.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/auth.php');
require_once(PG_FUNCTIONS_DIR . '/seo_structure.php');

function _render_pass_page($type, $public = true, $archived = false, $search = true)
{
	return array(
		'page_type'       => $type,
		'folder_public'   => $public,
		'folder_archived' => $archived ? '1' : '0',
		'page_search'     => $search ? '1' : '0',
	);
}

function test_render_pass_index_types_follow_the_features()
{
	$plain = pg_render_pass_scope('index', array('calendars' => false, 'ecommerce' => false));
	$full = pg_render_pass_scope('index', array('calendars' => true, 'ecommerce' => true));

	pg_assert_true(in_array('standard', $plain['types'], true), 'standard pages');
	pg_assert_true(in_array('my account', $plain['types'], true), 'account screens are searchable');
	pg_assert_false(in_array('shopping cart', $plain['types'], true), 'no cart without e-commerce');
	pg_assert_false(in_array('calendar view', $plain['types'], true), 'no calendar view without calendars');
	pg_assert_true(in_array('shopping cart', $full['types'], true), 'cart with e-commerce');
	pg_assert_true(in_array('catalog', $full['types'], true), 'catalog with e-commerce');
	pg_assert_true(in_array('calendar view', $full['types'], true), 'calendar view with calendars');
	pg_assert_true($plain['private_folders'], 'the index keeps private folders');
	pg_assert_true($plain['search_flag'], 'the index asks the page search switch');
}

function test_render_pass_audits_share_one_scope()
{
	$structure = pg_render_pass_scope('structure');
	$a11y = pg_render_pass_scope('a11y');

	pg_assert_same($structure, $a11y, 'both audits cover the same pages');
	pg_assert_same(pg_seo_renderable_page_types(), $structure['types'], 'the renderable types');
	pg_assert_false($structure['private_folders'], 'audits leave private folders out');
	pg_assert_false($structure['search_flag'], 'audits do not ask the page search switch');
}

function test_render_pass_covers_folders_and_types()
{
	$index = pg_render_pass_scope('index', array('calendars' => false, 'ecommerce' => false));
	$audit = pg_render_pass_scope('a11y');

	pg_assert_true(pg_render_pass_covers($index, _render_pass_page('standard')), 'index: public page');
	pg_assert_true(pg_render_pass_covers($index, _render_pass_page('standard', false)), 'index: private page');
	pg_assert_false(pg_render_pass_covers($index, _render_pass_page('standard', true, true)), 'index: archived folder');
	pg_assert_false(pg_render_pass_covers($index, _render_pass_page('standard', true, false, false)), 'index: page search off');
	pg_assert_false(pg_render_pass_covers($index, _render_pass_page('order receipt')), 'index: type not listed');

	pg_assert_true(pg_render_pass_covers($audit, _render_pass_page('standard')), 'audit: public page');
	pg_assert_true(pg_render_pass_covers($audit, _render_pass_page('standard', true, false, false)), 'audit: page search off still audited');
	pg_assert_false(pg_render_pass_covers($audit, _render_pass_page('standard', false)), 'audit: private page');
	pg_assert_false(pg_render_pass_covers($audit, _render_pass_page('standard', true, true)), 'audit: archived folder');
	pg_assert_false(pg_render_pass_covers($audit, _render_pass_page('my account')), 'audit: account screen');
}

function test_render_pass_unknown_pass_covers_nothing()
{
	$scope = pg_render_pass_scope('nonsense');

	pg_assert_same(array(), $scope['types'], 'no types');
	pg_assert_false(pg_render_pass_covers($scope, _render_pass_page('standard')), 'nothing covered');
}

function test_render_pass_anonymous_run_drops_the_session_user()
{
	pg_assert_false(pg_auth_session_user_applies(5, true), 'an anonymous run renders without the session user');
	pg_assert_true(pg_auth_session_user_applies(5, false), 'an ordinary request keeps it');

	$auth = file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/auth.php');
	pg_assert_contains("pg_auth_session_user_applies(\$_SESSION['sessionuserid'] ?? null, pg_auth_anonymous_run())", $auth);
	pg_assert_contains("defined('PG_ANONYMOUS_RENDER')", $auth);
}

function test_render_pass_audit_buttons_run_anonymously()
{
	$run = file_get_contents(PG_FUNCTIONS_DIR . '/audit_run.php');
	pg_assert_true(strpos($run, "define('PG_ANONYMOUS_RENDER', true);") < strpos($run, "include('init.php');"), 'flag set before init.php');
	pg_assert_true(strpos($run, 'session_write_close();') < strpos($run, 'pg_seo_analyze_batch('), 'session closed before the structure pass');
	pg_assert_true(strpos($run, 'session_write_close();') < strpos($run, 'pg_a11y_run('), 'session closed before the accessibility pass');
	pg_assert_contains("AND (user_role < '3')", $run);
	pg_assert_contains('validate_token_field();', $run);

	// The screens send their buttons there instead of rendering in their own
	// signed-in request.
	foreach (array('view_pages.php', 'view_accessibility.php') as $screen) {
		$src = file_get_contents(PG_FUNCTIONS_DIR . '/' . $screen);
		pg_assert_false(strpos($src, 'pg_seo_analyze_batch(') !== false, $screen . ': no structure run in the signed-in request');
		pg_assert_false(strpos($src, 'pg_a11y_run(') !== false, $screen . ': no accessibility run in the signed-in request');
	}
}

function test_render_pass_passes_read_the_scope()
{
	pg_assert_contains("pg_render_pass_scope('index'", file_get_contents(PG_FUNCTIONS_DIR . '/update_search_index.php'));
	pg_assert_contains("pg_render_pass_scope('structure')", file_get_contents(PG_FUNCTIONS_DIR . '/seo_structure.php'));
	pg_assert_contains("pg_render_pass_scope('a11y')", file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/a11y.php'));
}
