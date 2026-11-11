<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the legacy design key (includes/fn/designer.php,
 * pg_design_frameworks()): the key a classic style moved into the Visual
 * Page Editor carries, the flags callers ask instead of the key text, the
 * theme recognition that reads it, and the Legacy badge on the Visual
 * Designs screen.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/legacy_design_theme.php');
require_once(PG_FUNCTIONS_DIR . '/includes/designer_ai.php');
require_once(PG_FUNCTIONS_DIR . '/includes/designer_screen.php');

function test_legacy_design_framework_is_a_known_key()
{
	pg_assert_same('legacy', pg_design_framework_key('legacy'));
	pg_assert_same('legacy', pg_design_framework_key(' Legacy '), 'case and spaces');
	pg_assert_same('bootstrap5', pg_design_framework_key('legacy2'), 'an unknown key reads as Bootstrap 5');
	$fw = pg_design_framework('legacy');
	pg_assert_same('legacy', $fw['key']);
	pg_assert_same('', $fw['css'], 'no framework stylesheet');
	pg_assert_same('', $fw['js'], 'no framework script');
}

function test_legacy_design_framework_every_entry_has_both_flags()
{
	foreach (pg_design_frameworks() as $key => $fw) {
		pg_assert_true(array_key_exists('bootstrap', $fw), $key . ': bootstrap flag');
		pg_assert_true(array_key_exists('legacy', $fw), $key . ': legacy flag');
	}
}

function test_legacy_design_framework_flags()
{
	pg_assert_true(pg_design_framework_has('bootstrap5', 'bootstrap'));
	pg_assert_false(pg_design_framework_has('custom', 'bootstrap'));
	pg_assert_false(pg_design_framework_has('legacy', 'bootstrap'));
	pg_assert_true(pg_design_framework_has('legacy', 'legacy'));
	pg_assert_false(pg_design_framework_has('custom', 'legacy'));
	pg_assert_false(pg_design_framework_has('bootstrap5', 'legacy'));
	pg_assert_true(pg_design_framework_has('', 'bootstrap'), 'an empty column is Bootstrap 5');
	pg_assert_false(pg_design_framework_has('legacy', 'no-such-flag'));
}

// LG1's theme loads for the legacy key alone; a custom design is not a
// moved classic style whatever its components.
function test_legacy_design_framework_theme_recognition_reads_the_flag()
{
	pg_assert_true(pg_legacy_design_from_classic(7, 'legacy'));
	pg_assert_false(pg_legacy_design_from_classic(7, 'custom'));
	pg_assert_false(pg_legacy_design_from_classic(7, 'bootstrap5'));
	pg_assert_false(pg_legacy_design_from_classic(0, 'legacy'), 'no style');
}

// The AI page writer treats a legacy design like a custom one: no Bootstrap.
function test_legacy_design_framework_ai_sees_no_bootstrap()
{
	pg_assert_false(pg_design_ai_known_class('d-flex', 'legacy'));
	pg_assert_true(pg_design_ai_known_class('d-flex', 'bootstrap5'));
	pg_assert_contains('without a CSS framework', pg_design_ai_framework_line(array('framework' => 'legacy')));
	pg_assert_contains('Bootstrap 5.3', pg_design_ai_framework_line(array('framework' => 'bootstrap5')));
}

function _test_legacy_design_framework_card_row($legacy)
{
	return array(
		'style_id' => 31, 'style_name' => 'Kurumsal', 'page_count' => 0, 'home_count' => 0,
		'draft_count' => 0, 'scheduled_count' => 0, 'last_modified_timestamp' => 0,
		'last_modified_username' => 'pg-test', 'template' => '', 'look' => '', 'palette' => '',
		'framework_label' => $legacy ? 'Legacy' : 'Custom', 'framework_bootstrap' => false,
		'framework_legacy' => $legacy,
	);
}

function test_legacy_design_framework_badge_beside_the_name()
{
	$ctx = array('featured' => false, 'folders' => 0, 'can_change' => true, 'can_draft' => false);
	$tile = pg_designer_home_design_card(_test_legacy_design_framework_card_row(true), array(), $ctx);
	pg_assert_same(1, preg_match('~<h3 class="sdh-name">.*?</a><span class="badge theme-secondary sdh-legacy">Legacy</span></h3>~s', $tile), 'the badge follows the name inside the heading');
	$featured = pg_designer_home_design_card(_test_legacy_design_framework_card_row(true), array(), array('featured' => true) + $ctx);
	pg_assert_contains('sdh-legacy', $featured, 'the featured card too');
	$custom = pg_designer_home_design_card(_test_legacy_design_framework_card_row(false), array(), $ctx);
	pg_assert_false(strpos($custom, 'sdh-legacy') !== false, 'a custom design has no badge');
}
