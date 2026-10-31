<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the way back from a delivery note to the filtered Delivery Notes
 * list: the list passes its address as send_to, and the note's back arrow,
 * breadcrumb, form and reloads keep it.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function erp_waybill_back_arrow_source($file)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
}

function test_erp_waybill_list_view_button_passes_send_to()
{
	$source = erp_waybill_back_arrow_source('erp_waybills.php');

	pg_assert_contains("h(escape_javascript('edit_erp_waybill.php?id=' . \$id . '&send_to=' . urlencode(REQUEST_URL)))", $source, 'the View button carries the filtered list');
	pg_assert_false(strpos($source, "edit_erp_waybill.php?id=' . \$id . '\\'") !== false, 'no View button without send_to');
}

function test_erp_waybill_note_keeps_send_to()
{
	$source = erp_waybill_back_arrow_source('edit_erp_waybill.php');

	pg_assert_contains("\$send_to = pg_send_to_url('');", $source, 'send_to is read once');
	pg_assert_contains("/edit_erp_waybill.php?id=' . \$waybill_id . \$send_to_query", $source, 'reloads keep send_to');
	pg_assert_contains("'cancel' => array('enable' => 'true', 'url' => pg_send_to_url('erp_waybills.php'))", $source, 'the back arrow goes to the filtered list');
	pg_assert_contains("'url' => pg_send_to_list_url(\$list_url)", $source, 'the breadcrumb keeps the filtered list');
	pg_assert_contains('<form name="form" action="\' . $self_action . \'" method="post">', $source, 'the form posts send_to');
	pg_assert_false(strpos($source, "'url' => 'erp_waybills.php'") !== false, 'no bare back arrow');
}
