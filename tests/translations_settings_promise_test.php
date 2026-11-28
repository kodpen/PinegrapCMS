<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for what the translation settings promise in
 * includes/translate/jobs.php: which engine takes the texts an update failed
 * on (pg_tr_fallback_engine_for()) and the owners of the scopes a product,
 * product group, offer or form save opens (pg_tr_scope_owners()).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/translate/jobs.php');

function test_translations_fallback_engine_for()
{
	$server = array('kind' => 'server', 'ready' => true);
	$queue = array('kind' => 'queue', 'ready' => true);

	pg_assert_same('google', pg_tr_fallback_engine_for('ai', 'google', $server), 'a ready server engine takes the failed texts');
	pg_assert_same('claude', pg_tr_fallback_engine_for('google', 'claude', $queue), 'Claude\'s queue takes them');
	pg_assert_same('', pg_tr_fallback_engine_for('google', '', null), 'no fallback set');
	pg_assert_same('', pg_tr_fallback_engine_for('google', 'google', $server), 'an update on the fallback never falls back again');
	pg_assert_same('', pg_tr_fallback_engine_for('ai', 'google', array('kind' => 'server', 'ready' => false)), 'a fallback not ready');
	pg_assert_same('', pg_tr_fallback_engine_for('ai', 'chrome', array('kind' => 'browser', 'ready' => true)), 'a browser engine needs the screen');
	pg_assert_same('', pg_tr_fallback_engine_for('ai', 'manual', array('kind' => 'panel', 'ready' => true)), 'by hand sends nothing');
	pg_assert_same('', pg_tr_fallback_engine_for('ai', 'deepl', null), 'an engine not known');
}

function test_translations_saved_owner_scopes()
{
	pg_assert_same(array(array('product', 7)), pg_tr_scope_owners('product:7'), 'a product');
	pg_assert_same(array(array('product_group', 3)), pg_tr_scope_owners('product_group:3'), 'a product group');
	pg_assert_same(array(array('offer', 2)), pg_tr_scope_owners('offer:2'), 'an offer');
	pg_assert_same(array(array('form', 14)), pg_tr_scope_owners('form:14'), 'a form');
	pg_assert_same(array(array('form_records', 14)), pg_tr_scope_owners('records:14'), 'a form\'s records stay their own scope');
	pg_assert_same(array(), pg_tr_scope_owners('product:x'), 'no id is the whole site');
}
