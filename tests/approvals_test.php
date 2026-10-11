<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for ws_approval_outcome() (includes/workspace/approvals.php): where
 * an approval request stands from its rule, the answers given and its
 * deadline. A refusal settles it whatever the rule, "any" is met by one
 * approval, "all" by everybody's, and a deadline that passed with the rule
 * unmet lets it expire.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Function definitions only: nothing in the file runs a query when loaded.
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/approvals.php');

function test_approvals_any_is_met_by_the_first_approval()
{
	pg_assert_same('approved', ws_approval_outcome('any', array('pending', 'approved', 'pending'), 1000, 0));
	pg_assert_same('approved', ws_approval_outcome('any', array('approved'), 1000, 0));
}

function test_approvals_all_waits_for_everybody()
{
	pg_assert_same('open', ws_approval_outcome('all', array('approved', 'pending'), 1000, 0));
	pg_assert_same('approved', ws_approval_outcome('all', array('approved', 'approved'), 1000, 0));
}

function test_approvals_a_refusal_settles_it_whatever_the_rule()
{
	pg_assert_same('rejected', ws_approval_outcome('all', array('approved', 'rejected'), 1000, 0));
	pg_assert_same('rejected', ws_approval_outcome('any', array('pending', 'rejected'), 1000, 0));
	// A refusal counts even when the deadline has passed.
	pg_assert_same('rejected', ws_approval_outcome('all', array('rejected', 'pending'), 2000, 1500));
}

function test_approvals_nobody_answered_stays_open()
{
	pg_assert_same('open', ws_approval_outcome('any', array('pending', 'pending'), 1000, 0));
	pg_assert_same('open', ws_approval_outcome('all', array('pending'), 1000, 2000));
}

function test_approvals_the_deadline_lets_it_expire()
{
	pg_assert_same('expired', ws_approval_outcome('any', array('pending'), 2000, 2000));
	pg_assert_same('expired', ws_approval_outcome('all', array('approved', 'pending'), 2001, 2000));
	// Before the deadline it is still open.
	pg_assert_same('open', ws_approval_outcome('all', array('approved', 'pending'), 1999, 2000));
}

function test_approvals_a_met_rule_wins_over_a_passed_deadline()
{
	pg_assert_same('approved', ws_approval_outcome('all', array('approved', 'approved'), 3000, 2000));
	pg_assert_same('approved', ws_approval_outcome('any', array('approved', 'pending'), 3000, 2000));
}

function test_approvals_an_unknown_rule_reads_as_any()
{
	pg_assert_same('approved', ws_approval_outcome('', array('approved', 'pending'), 1000, 0));
}

function test_approvals_nobody_asked_is_never_approved()
{
	pg_assert_same('open', ws_approval_outcome('all', array(), 1000, 0));
	pg_assert_same('expired', ws_approval_outcome('all', array(), 2000, 1000));
}
