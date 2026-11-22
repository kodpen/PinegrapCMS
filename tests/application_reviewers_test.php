<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for an application programme's reviewers that need no database
 * (includes/fn/application_reviewers.php): the pool a reviewer serves and
 * the assignment run worked out per category, with kept, dropped and
 * conflicting reviews.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/application_reviewers.php');

function test_application_reviewers_pool_and_categories()
{
	pg_assert_same(null, pg_application_reviewer_categories(null));
	pg_assert_same(null, pg_application_reviewer_categories('not json'));
	pg_assert_same(array('Music', 'Dance'), pg_application_reviewer_categories('["Music","Dance","Music",""]'));

	// No categories on the reviewer: every pool; none on the programme:
	// one pool for everyone.
	pg_assert_true(pg_application_reviewer_in_pool(null, 'Music', true));
	pg_assert_true(pg_application_reviewer_in_pool(array('Music'), 'Music', true));
	pg_assert_false(pg_application_reviewer_in_pool(array('Music'), 'Dance', true));
	pg_assert_true(pg_application_reviewer_in_pool(array('Music'), 'Dance', false));
}

function test_application_reviewers_plan_gives_each_category_its_pool()
{
	$entries = array(array('id' => 1, 'category' => 'Music'), array('id' => 2, 'category' => 'Dance'), array('id' => 3, 'category' => 'Music'));
	$reviewers = array(
		array('id' => 10, 'categories' => array('Music'), 'revoked_at' => 0),
		array('id' => 11, 'categories' => array('Dance'), 'revoked_at' => 0),
		array('id' => 12, 'categories' => null, 'revoked_at' => 0),
		array('id' => 13, 'categories' => null, 'revoked_at' => 99),
	);

	$plan = pg_application_assignment_plan($entries, $reviewers, array(), 2, true, false);

	// Dance first (categories in name order), then Music; the removed
	// reviewer 13 is in no pool.
	pg_assert_same(array(array(2, 11), array(2, 12), array(1, 10), array(1, 12), array(3, 10), array(3, 12)), $plan['inserts']);
	pg_assert_same(array(), $plan['deletes']);
	pg_assert_same(array(), $plan['short']);
	pg_assert_same(3, $plan['entries']);
	pg_assert_same(3, $plan['reviewers']);
}

function test_application_reviewers_plan_rerun_keeps_and_drops()
{
	$entries = array(array('id' => 1, 'category' => ''), array('id' => 2, 'category' => ''));
	$reviewers = array(
		array('id' => 10, 'categories' => null, 'revoked_at' => 0),
		array('id' => 11, 'categories' => null, 'revoked_at' => 0),
		array('id' => 12, 'categories' => null, 'revoked_at' => 5),
	);
	$reviews = array(
		// Entry 1: 10 has sent, 12 (since removed) has not started.
		array('id' => 100, 'entry_id' => 1, 'reviewer_id' => 10, 'status' => 2),
		array('id' => 101, 'entry_id' => 1, 'reviewer_id' => 12, 'status' => 0),
		// Entry 2: 10 declared a conflict; 11 started.
		array('id' => 102, 'entry_id' => 2, 'reviewer_id' => 10, 'status' => 3),
		array('id' => 103, 'entry_id' => 2, 'reviewer_id' => 11, 'status' => 1),
		// A review of an entry no longer given out is left alone.
		array('id' => 104, 'entry_id' => 9, 'reviewer_id' => 11, 'status' => 0),
	);

	$plan = pg_application_assignment_plan($entries, $reviewers, $reviews, 2, false, false);

	// Entry 1 keeps 10 and gets 11 in place of the removed 12; entry 2 keeps
	// 11 and lacks one, as 10 is in conflict and 12 is gone.
	pg_assert_same(array(array(1, 11)), $plan['inserts']);
	pg_assert_same(array(101), $plan['deletes']);
	pg_assert_same(array(2 => 1), $plan['short']);
	pg_assert_same(1, $plan['entries']);
	pg_assert_same(2, $plan['reviewers']);

	// A second run over the result changes nothing.
	$after = array(
		array('id' => 100, 'entry_id' => 1, 'reviewer_id' => 10, 'status' => 2),
		array('id' => 105, 'entry_id' => 1, 'reviewer_id' => 11, 'status' => 0),
		array('id' => 102, 'entry_id' => 2, 'reviewer_id' => 10, 'status' => 3),
		array('id' => 103, 'entry_id' => 2, 'reviewer_id' => 11, 'status' => 1),
	);
	$again = pg_application_assignment_plan($entries, $reviewers, $after, 2, false, false);
	pg_assert_same(array(), $again['inserts']);
	pg_assert_same(array(), $again['deletes']);
	pg_assert_same(0, $again['entries']);
}

function test_application_reviewers_plan_drops_a_reviewer_moved_out_of_the_pool()
{
	$entries = array(array('id' => 1, 'category' => 'Music'));
	$reviewers = array(
		array('id' => 10, 'categories' => array('Dance'), 'revoked_at' => 0),
		array('id' => 11, 'categories' => array('Music'), 'revoked_at' => 0),
	);
	$reviews = array(array('id' => 100, 'entry_id' => 1, 'reviewer_id' => 10, 'status' => 0));

	$plan = pg_application_assignment_plan($entries, $reviewers, $reviews, 1, true, false);
	pg_assert_same(array(array(1, 11)), $plan['inserts']);
	pg_assert_same(array(100), $plan['deletes']);
}

function test_application_reviewers_plan_keeps_unstarted_review_of_reviewer_of_every_category()
{
	// Reviewers with no categories (every category, stored as null) keep
	// their unstarted reviews: a re-run, random ties or not, changes nothing,
	// and an entry passed on by a conflict gets no second reviewer.
	$entries = array(array('id' => 1, 'category' => ''), array('id' => 2, 'category' => ''));
	$reviewers = array(
		array('id' => 1, 'categories' => null, 'revoked_at' => 0),
		array('id' => 2, 'categories' => null, 'revoked_at' => 0),
		array('id' => 3, 'categories' => null, 'revoked_at' => 0),
	);
	$reviews = array(
		array('id' => 1, 'entry_id' => 1, 'reviewer_id' => 1, 'status' => 2),
		array('id' => 2, 'entry_id' => 2, 'reviewer_id' => 1, 'status' => 3),
		array('id' => 3, 'entry_id' => 2, 'reviewer_id' => 2, 'status' => 0),
	);

	for ($i = 0; $i < 12; $i++) {
		$plan = pg_application_assignment_plan($entries, $reviewers, $reviews, 1, false, true);
		pg_assert_same(array(), $plan['inserts'], 'nothing new');
		pg_assert_same(array(), $plan['deletes'], 'the unstarted review stays');
	}
}

function test_application_reviewers_tab_markup_follows_the_description()
{
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/edit_application_program.php');
	$start = strpos($source, '// The Reviewers tab');
	$tab = substr($source, $start, strpos($source, '$ap_setup_html =') - $start);

	// The tabs: Set-up and Reviewers as links of one segmented control.
	pg_assert_contains("\$ap_tab('setup', lang('Set-up')) . \$ap_tab('reviewers', lang('Reviewers'), count(\$ap_reviewers))", $tab, 'two tabs');
	pg_assert_contains('<a class="pg-seg-item', $tab, 'tabs are links');

	// One fill on the view ("Add reviewer"), one in the drawer's foot (Add).
	pg_assert_same(2, substr_count($tab, 'pg-button-fill'), 'two fills: the view and the drawer');
	pg_assert_contains("pg-button pg-button-fill\" data-pg-ap-drawer=\"aprog_reviewer_add\"><i class=\"bi bi-plus-circle\" aria-hidden=\"true\"></i>' . h(lang('Add reviewer'))", $tab, 'Add reviewer is the fill');

	// "Give out entries" is quiet, with a confirm that says the counts.
	pg_assert_contains("class=\"pg-button pg-button-quiet\" data-confirm-content=\"' . h(\$ap_assign_text) . '\"", $tab, 'Give out entries quiet with its confirm');

	// The drawer: a panel user or a name and an e-mail, then the categories.
	pg_assert_same(2, substr_count($tab, 'data-pg-ap-kind-choice="'), 'two kinds');
	pg_assert_contains('name="reviewer_name"', $tab, 'juror name');
	pg_assert_contains('name="reviewer_email"', $tab, 'juror e-mail');
	pg_assert_contains('name="reviewer_categories[]"', $tab, 'categories');

	// A row: initials, name, kind and pool, sent of given, the menu with
	// Remove (danger, confirmed) as its one item.
	pg_assert_contains('<span class="pg-initials', $tab, 'people as rows');
	pg_assert_same(1, substr_count($tab, 'class="menu-item'), 'one menu item');
	pg_assert_contains('class="menu-item fg-emphasis-danger" data-confirm-content=', $tab, 'Remove is danger with a confirm');

	// Conflicts: gathered lines under the list.
	pg_assert_contains('<div class="pg-gather">', $tab, 'conflicts gathered');
	pg_assert_true(strpos($tab, 'aprog_reviewers_title') < strpos($tab, 'aprog_conflicts_title'), 'conflicts under the list');
}
