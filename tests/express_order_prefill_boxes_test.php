<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for _eo_prefill_billing_boxes() in
 * includes/fn/widgets_express_order.php: the express order widget's tick
 * boxes (opt_in, opt_in_sms, tax_exempt, update_contact) start from the
 * order only before the visitor's first submit, as the classic express order
 * page fills them. An unticked box posts nothing, so after a refused submit
 * it must stay unticked rather than come back ticked.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Runs $fn with an empty session and request, restoring both afterwards.
function _eo_boxes_test_isolated($fn)
{
	$saved_session = isset($_SESSION) ? $_SESSION : null;
	$saved_request = $_REQUEST;
	$_SESSION = array();
	$_REQUEST = array();
	try {
		$fn();
	} finally {
		$_REQUEST = $saved_request;
		if ($saved_session === null) {
			unset($_SESSION);
		} else {
			$_SESSION = $saved_session;
		}
	}
}

function test_express_order_boxes_start_from_the_order()
{
	_eo_boxes_test_isolated(function () {
		$lf = new liveform('express_order_boxes_test');
		_eo_prefill_billing_boxes($lf, array('opt_in' => '1', 'tax_exempt' => '1'));
		pg_assert_same('1', (string) $lf->get_field_value('opt_in'), 'the order opted in');
		pg_assert_same('1', (string) $lf->get_field_value('opt_in_sms'), 'the SMS box starts ticked');
		pg_assert_same('1', (string) $lf->get_field_value('tax_exempt'), 'the order is tax-exempt');
		pg_assert_same('1', (string) $lf->get_field_value('update_contact'), 'update my contact starts ticked');
	});
}

function test_express_order_boxes_keep_what_a_refused_submit_left()
{
	_eo_boxes_test_isolated(function () {
		// The visitor unticked every box and the submit was refused: only
		// the text fields were posted.
		$_REQUEST = array('billing_first_name' => 'Ada', 'billing_email_address' => 'ada@example.com');
		$lf = new liveform('express_order_boxes_test');
		$lf->add_fields_to_session();
		_eo_prefill_billing_boxes($lf, array('opt_in' => '1', 'tax_exempt' => '1'));
		pg_assert_same('', (string) $lf->get_field_value('opt_in_sms'), 'an unticked SMS box stays unticked');
		pg_assert_same('', (string) $lf->get_field_value('opt_in'), 'an unticked e-mail box stays unticked');
		pg_assert_same('', (string) $lf->get_field_value('tax_exempt'), 'an unticked tax exemption stays unticked');
		pg_assert_same('', (string) $lf->get_field_value('update_contact'), 'an unticked update my contact stays unticked');
	});
}

function test_express_order_boxes_keep_a_ticked_box()
{
	_eo_boxes_test_isolated(function () {
		$_REQUEST = array('billing_first_name' => 'Ada', 'opt_in_sms' => '1', 'opt_in' => '1');
		$lf = new liveform('express_order_boxes_test');
		$lf->add_fields_to_session();
		_eo_prefill_billing_boxes($lf, array());
		pg_assert_same('1', (string) $lf->get_field_value('opt_in_sms'), 'a ticked SMS box stays ticked');
		pg_assert_same('1', (string) $lf->get_field_value('opt_in'), 'a ticked e-mail box stays ticked');
	});
}
