<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the AI Visibility section of the Visitors overview
 * (includes/visitors/ai.php): the redirect of the former screen with its
 * period kept, the ready check and the search anchor.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!defined('PG_VISITORS_SCREEN')) {
	define('PG_VISITORS_SCREEN', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/visitors/shell.php');
require_once(PG_FUNCTIONS_DIR . '/includes/visitors/ai.php');

function test_visitors_ai_old_link_keeps_period()
{
	pg_assert_same(array('p' => '90d'), pg_visitor_insight_overview_query(array('p' => '90d', 'device' => 'mobile')), 'no filter is carried over unless named');
	pg_assert_same(array('p' => '30d'), pg_visitor_insight_overview_query(array()));
}

function test_visitors_ai_screen_redirects_and_anchors()
{
	pg_assert_true(function_exists('pg_visitors_section_ai'));

	$screen = (string) file_get_contents(PG_FUNCTIONS_DIR . '/visitor_ai.php');
	pg_assert_contains('validate_visitors_access($user);', $screen);
	pg_assert_contains("pg_visitor_insight_overview_query(\$_GET)) . '#ai', true, 301);", $screen);

	$search = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/panel/search.php');
	pg_assert_contains("\$base_url . '/visitors.php#ai'", $search);

	$section = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/visitors/ai.php');
	pg_assert_contains("if (!pg_ai_visibility_ready()) {\n        return '';", $section, 'nothing before the table exists');
	pg_assert_contains('pg_visitor_ai_section($period, $user)', $section, 'the overview\'s period is the window');
}
