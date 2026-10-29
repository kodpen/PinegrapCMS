<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Icon-only panel controls that sit on or next to another target keep a box
 * of at least 24x24 CSS px, so a tap on a phone does not land on the
 * neighbouring action: the Online Engagement card's chat button (above the
 * row's stretched name link), the audit trail's Open icon (beside the
 * record's history link) and the ERP account list's contact icon (inside
 * the row that opens the drawer).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function panel_tap_targets_source($path)
{
	return (string) file_get_contents(dirname(__DIR__) . '/pinegrap/' . $path);
}

// The opening tag (a or button) that holds the given icon class.
function panel_tap_targets_tag($source, $icon)
{
	if (!preg_match('/<(?:a|button)\b[^>]*>\s*<i class="bi ' . preg_quote($icon, '/') . '\b/', $source, $match)) {
		return '';
	}

	return $match[0];
}

// A bare icon link gets an inline-flex box with a 1.5rem (24 px) minimum.
function panel_tap_targets_assert_box($tag, $label)
{
	pg_assert_true($tag !== '', $label . ': control found');
	pg_assert_contains('d-inline-flex align-items-center justify-content-center', $tag, $label . ': centred box');
	pg_assert_contains('min-width:1.5rem;min-height:1.5rem', $tag, $label . ': 24 px minimum');
}

function test_panel_tap_targets_engagement_chat_button()
{
	$tag = panel_tap_targets_tag(panel_tap_targets_source('includes/dashboard/widgets/widget_4.php'), 'bi-chat-text');

	pg_assert_true($tag !== '', 'chat button found');
	// .btn-sm with no padding is 14x21 px; px-2 py-1 makes it about 30x29.
	pg_assert_false((bool) preg_match('/\bp-0\b/', $tag), 'chat button has padding');
	pg_assert_contains('px-2 py-1', $tag, 'chat button padding');
	pg_assert_contains('position-relative z-2', $tag, 'chat button stays above the stretched link');
	pg_assert_contains('data-pg-focus-key="chat-with-', $tag, 'chat button focus key');
}

function test_panel_tap_targets_audit_open_icon()
{
	$source = panel_tap_targets_source('includes/fn/audit_list.php');

	panel_tap_targets_assert_box(panel_tap_targets_tag($source, 'bi-box-arrow-up-right'), 'audit Open icon');
	// A deleted record still has no Open link.
	pg_assert_contains("if (\$open !== '') {", $source, 'Open link only for a live record');
}

function test_panel_tap_targets_erp_account_contact_icon()
{
	panel_tap_targets_assert_box(panel_tap_targets_tag(panel_tap_targets_source('erp_accounts.php'), 'bi-person-check'), 'ERP contact icon');
}
