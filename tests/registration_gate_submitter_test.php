<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the registration folder gate's submitter exception
 * (pg_cf_submitter_gate_pass()): a visitor without a session reaches a
 * custom form's confirmation page only with the reference code of a form
 * this session submitted.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// All three hold: confirmation page, code in the request, code in the session.
function test_registration_gate_submitter_passes()
{
	pg_assert_true(pg_cf_submitter_gate_pass(true, 'ABC123', array('XYZ', 'ABC123')), 'own code');
}

// Any one missing keeps the gate closed.
function test_registration_gate_submitter_refused()
{
	pg_assert_same(false, pg_cf_submitter_gate_pass(false, 'ABC123', array('ABC123')), 'not a confirmation page');
	pg_assert_same(false, pg_cf_submitter_gate_pass(true, '', array('ABC123')), 'no code');
	pg_assert_same(false, pg_cf_submitter_gate_pass(true, null, array('ABC123')), 'missing code');
	pg_assert_same(false, pg_cf_submitter_gate_pass(true, 'OTHER', array('ABC123')), 'code of another submission');
	pg_assert_same(false, pg_cf_submitter_gate_pass(true, 'ABC123', null), 'no codes in session');
	pg_assert_same(false, pg_cf_submitter_gate_pass(true, 'ABC123', 'ABC123'), 'session value not a list');
	pg_assert_same(false, pg_cf_submitter_gate_pass(true, array('ABC123'), array('ABC123')), 'code given as an array');
	pg_assert_same(false, pg_cf_submitter_gate_pass(true, '1e1', array('10')), 'numeric strings compared loosely');
}
