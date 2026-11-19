<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Workspace values on GET /meta
 * (includes/api/resources/meta.php): the task estimate the board counts and
 * the working day, with init.php's fallbacks.
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

require_once(PG_FUNCTIONS_DIR . '/includes/api/resources/meta.php');

function test_api_meta_workspace_values()
{
	pg_assert_same(array('default_task_minutes' => 45, 'day_minutes' => 450), api_meta_workspace_values(45, '450'), 'the site\'s values as integers');
	pg_assert_same(array('default_task_minutes' => 60, 'day_minutes' => 480), api_meta_workspace_values(null, null), 'init.php\'s fallbacks');
}

function test_api_meta_declares_the_workspace_values()
{
	$schema = api_meta_schema();

	pg_assert_same('MetaWorkspace?', $schema['workspace']);
	pg_assert_same('boolean', $schema['features']['quiet_hours']);
	pg_assert_same(array('default_task_minutes' => 'integer', 'day_minutes' => 'integer'), api_meta_workspace_schema());
}
