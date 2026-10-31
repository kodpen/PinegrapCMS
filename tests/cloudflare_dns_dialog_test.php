<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source pins for the Cloudflare Tools Add/Edit DNS Record dialogs: hidden
 * type groups are disabled so only the visible priority box is posted,
 * Content is required where the record type needs it, and an MX row's Edit
 * button carries its priority into the dialog.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function cloudflare_dns_dialog_source()
{
	return (string) file_get_contents(dirname(__DIR__) . '/pinegrap/cloudflare.php');
}

function cloudflare_dns_dialog_toggle()
{
	$source = cloudflare_dns_dialog_source();
	$start = strpos($source, 'function toggleExtraFields(');
	pg_assert_true($start !== false, 'toggleExtraFields found');
	return substr($source, $start, strpos($source, 'document.addEventListener("DOMContentLoaded"', $start) - $start);
}

function test_cloudflare_dns_dialog_hidden_groups_are_disabled()
{
	$toggle = cloudflare_dns_dialog_toggle();

	// Both priority boxes are named "priority": the hidden one must not be posted.
	pg_assert_contains('querySelectorAll("input, select")', $toggle, 'fields of a group are walked');
	pg_assert_contains('.disabled = !visible', $toggle, 'hidden fields are disabled, visible ones enabled');
	pg_assert_contains('querySelectorAll(".type-extra")', $toggle, 'type groups go through the toggle');
	pg_assert_contains('querySelectorAll(".common-content")', $toggle, 'the content box goes through the toggle');
}

function test_cloudflare_dns_dialog_content_required_with_its_box()
{
	$toggle = cloudflare_dns_dialog_toggle();
	pg_assert_contains('.required = !hideContent', $toggle, 'Content is required whenever it is shown');
	pg_assert_contains('["srv","caa"].includes(type)', $toggle, 'only SRV and CAA hide Content');
}

function test_cloudflare_dns_dialog_mx_priority_reaches_edit()
{
	$source = cloudflare_dns_dialog_source();

	pg_assert_true((bool) preg_match('/\$r\[\'type\'\] === \'MX\'[^\n]*\n[^\n]*data-priority="\'\.\(int\)\$r\[\'priority\'\]/', $source), 'MX row carries data-priority');
	pg_assert_contains('document.getElementById("edit_priority").value = button.getAttribute("data-priority")', $source, 'Edit fills the MX priority');

	// The fill runs before the toggle so the shown box holds the stored value.
	$fill = strpos($source, 'document.getElementById("edit_priority").value');
	$toggle = strpos($source, 'toggleExtraFields(document.getElementById("edit_type"), "edit")');
	pg_assert_true($fill !== false && $toggle !== false && $fill < $toggle, 'MX priority filled before the toggle');
}

function test_cloudflare_dns_dialog_keeps_both_priority_ids()
{
	$source = cloudflare_dns_dialog_source();
	foreach (array('add_priority', 'add_priority2', 'edit_priority', 'edit_priority2') as $id) {
		pg_assert_contains('name="priority" id="' . $id . '"', $source, $id . ' kept');
	}
}
