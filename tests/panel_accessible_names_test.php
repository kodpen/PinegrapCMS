<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source pins for accessible names in the panel: dialog and drawer close
 * buttons and icon-only controls carry a translated aria-label, the settings
 * autofill decoy stays out of the tab order, and the marketplace products
 * screen's inline style sits in a cascade layer so the d-none utility wins.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function panel_accessible_names_source($path)
{
	return (string) file_get_contents(dirname(__DIR__) . '/pinegrap/' . $path);
}

function test_panel_accessible_names_close_buttons()
{
	$paths = array(
		'cloudflare.php',
		'marketplace_settings.php',
		'view_parasut_inbox.php',
		'includes/designer_screen.php',
		'assets/js/image_editor_modal.js',
		'view_folders.php',
	);

	foreach ($paths as $path) {
		$source = panel_accessible_names_source($path);
		pg_assert_true((bool) preg_match_all('/<button[^>]*class="btn-close[^"]*"[^>]*>/', $source, $matches), $path . ': no close button found');

		foreach ($matches[0] as $tag) {
			pg_assert_contains('aria-label=', $tag, $path);
		}
	}

	pg_assert_contains("'Close' => lang('Close')", panel_accessible_names_source('includes/fn/editor.php'));
	pg_assert_contains("aria-label=\"' + t('Close') + '\"", panel_accessible_names_source('assets/js/image_editor_modal.js'));
	pg_assert_contains("aria-label=\"' + L.close + '\"", panel_accessible_names_source('view_folders.php'));
}

function test_panel_accessible_names_icon_buttons()
{
	$expect = array(
		'cloudflare.php' => array("aria-label=\"' . h(lang('Edit')) . '\"", "aria-label=\"' . h(lang('Delete')) . '\""),
		'view_parasut_inbox.php' => array("aria-label=\"' . h(lang('Invoice Detail')) . '\""),
		'image_editor_edit.php' => array("id=\"bd-theme\" type=\"button\" aria-label=\"' . h(lang('Software Theme')) . '\""),
		'editor_select_image.php' => array("onclick=\"esiUnsplashSearch()\" aria-label=\"' . h(lang('Search')) . '\""),
		'erp_account_prices.php' => array("<button type=\"submit\" class=\"btn btn-outline theme-secondary\" aria-label=\"' . h(lang('Search')) . '\">"),
		'view_performance_log.php' => array("<button type=\"submit\" class=\"btn btn-sm btn-solid theme-primary\" aria-label=\"' . h(lang('Search')) . '\">"),
		'includes/settings/screen.php' => array('disabled="disabled" aria-label="\' . h($pg_settings_step[2]) . \'"'),
	);

	foreach ($expect as $path => $needles) {
		$source = panel_accessible_names_source($path);

		foreach ($needles as $needle) {
			pg_assert_contains($needle, $source, $path);
		}
	}

	$source = panel_accessible_names_source('view_parasut_inbox.php');
	pg_assert_same(2, substr_count($source, "aria-label=\"' . h(lang('Invoice Detail')) . '\""), 'view_parasut_inbox.php: both detail buttons');

	$source = panel_accessible_names_source('marketplace_products.php');
	pg_assert_same(3, substr_count($source, "lang('Previous page'), 'bi-chevron-left')"), 'marketplace_products.php: previous page arrows');
	pg_assert_same(3, substr_count($source, "lang('Next page'), 'bi-chevron-right')"), 'marketplace_products.php: next page arrows');
	pg_assert_same(2, substr_count($source, "aria-label=\"' . h(\$label) . '\""), 'marketplace_products.php: the arrow helper names the link and the span');
}

function test_panel_accessible_names_autofill_decoy()
{
	$source = panel_accessible_names_source('includes/settings/screen.php');

	pg_assert_true((bool) preg_match('/<input id="fake_user_name"[^>]*>/', $source, $match), 'decoy input found');
	pg_assert_contains('tabindex="-1"', $match[0]);
	pg_assert_contains('aria-hidden="true"', $match[0]);
}

function test_panel_accessible_names_marketplace_bulk_layer()
{
	$source = panel_accessible_names_source('marketplace_products.php');

	pg_assert_contains("<style>@layer custom {\n", $source);
	pg_assert_contains("}\n}</style>", $source);
	pg_assert_true(strpos($source, "<style>\n") === false, 'unlayered inline style');
}

function test_panel_accessible_names_dialogs_named_by_their_title()
{
	$expect = array(
		'includes/settings/general.php' => array('cron_jobs'),
		'edit_form_item_view.php' => array('hints'),
		'edit_form_list_view.php' => array('hints', 'hints_for_advanced_search'),
		'includes/forms/product_group_form.php' => array('image_code'),
		'translations.php' => array('pg_tr_import', 'pg_tr_clear_modal', 'pg_tr_review_all_modal', 'pg_tr_accept_modal', 'pg_tr_glossary_modal'),
	);

	foreach ($expect as $path => $ids) {
		$source = panel_accessible_names_source($path);

		foreach ($ids as $id) {
			// A dialog labelled by itself is named with its whole body text.
			pg_assert_contains('id="' . $id . '" aria-labelledby="' . $id . '_title"', $source, $path);
			pg_assert_true((bool) preg_match('/<h5 class="dialog-title" id="' . $id . '_title">/', $source), $path . ': ' . $id . ' title has the id');
		}
	}
}

function test_panel_accessible_names_filter_selects()
{
	// The input-group label holds only an icon ligature ('visibility',
	// 'groups'...), which would otherwise name the select.
	$expect = array(
		'view_users.php' => array('filter_select' => 'Content that viewed'),
		'view_pages.php' => array('filter_select' => 'Content that viewed'),
		'view_files.php' => array('filter_select' => 'Content that viewed'),
		'view_ads.php' => array('ad_region_id' => 'Content that viewed'),
		'view_contacts.php' => array('contact_group' => 'Group', 'filter' => 'Content that viewed'),
		'view_submitted_forms.php' => array('custom_form' => 'Custom Form', 'custom_form_fake' => 'Custom Form'),
		'editor_select_page_or_file.php' => array('folder_id' => 'Folder', 'access_control_type' => 'Access'),
	);

	foreach ($expect as $path => $selects) {
		$source = panel_accessible_names_source($path);

		foreach ($selects as $id => $name) {
			pg_assert_true((bool) preg_match('/<select id="' . $id . '"[^>]*>/', $source, $match), $path . ': #' . $id . ' found');
			pg_assert_contains("aria-label=\"' . h(lang('" . $name . "')) . '\"", $match[0], $path . ' #' . $id);
		}
	}

	$source = panel_accessible_names_source('smtp_settings.php');
	pg_assert_contains("<label class=\"visually-hidden\" for=\"dkim_selector\">' . h(lang('Selector')) . '</label>", $source);
	pg_assert_contains("<label class=\"visually-hidden\" for=\"dkim_domain\">' . h(lang('Domain')) . '</label>", $source);
}

function test_panel_accessible_names_workspace_settings_hints()
{
	// Bootstrap 6 copies a title into aria-label on a textless element, which
	// on a labelled field replaces the label's name: the hint is form-text.
	$source = panel_accessible_names_source('includes/workspace/workdays.php');
	pg_assert_true((bool) preg_match('/<input[^>]*id="ws_hol_to"[^>]*>/', $source, $match), 'ws_hol_to found');
	pg_assert_true(strpos($match[0], 'title=') === false, 'ws_hol_to has no title');
	pg_assert_contains('aria-describedby="ws_hol_to_hint"', $match[0]);
	pg_assert_contains("<div class=\"form-text\" id=\"ws_hol_to_hint\">' . h(lang('Leave it empty for one day.')) . '</div>", $source);

	$source = panel_accessible_names_source('includes/workspace/mcp.php');
	pg_assert_true(strpos($source, "title=\"' . h(lang('Disconnect')) . '\"") === false, 'the Disconnect button repeats its text in a title');

	// The copy buttons sit in the secret sheet too, above the popover layer.
	$source = panel_accessible_names_source('includes/workspace/claude.php');
	pg_assert_contains("no-popover\" data-ws-copy-field=\"' . h(\$id) . '\" data-bs-toggle=\"tooltip\" title=\"' . h(lang('Copy')) . '\" aria-label=\"' . h(lang('Copy')) . '\"", $source);
}

function test_panel_accessible_names_edit_contact_remove_picture()
{
	$source = panel_accessible_names_source('edit_contact.php');
	pg_assert_true(strpos($source, 'title="remove"') === false, 'literal remove title');
	pg_assert_same(2, substr_count($source, "aria-label=\"' . h(lang('Remove')) . '\""), 'edit_contact.php: both remove buttons');
}

function test_panel_accessible_names_performance_log_filters()
{
	$source = panel_accessible_names_source('view_performance_log.php');

	foreach (array('perf_log_search' => 'name="search"', 'perf_log_range' => 'name="range"', 'perf_log_area' => 'name="area"') as $id => $field) {
		pg_assert_contains('<label for="' . $id . '"', $source, 'performance log label points at ' . $id);
		pg_assert_contains('id="' . $id . '" ' . $field, $source, 'performance log field ' . $id . ' has its id');
	}
}

// Dashboard cards: the Calendars card's stretched link has a name, review
// stars are read as a number, the brief's progress bar repeats the printed
// percentage so it stays out of the reading order, and the Online Engagement
// chat control is a named button beside the row link, never inside it.
function test_panel_accessible_names_dashboard_cards()
{
	$calendars = panel_accessible_names_source('includes/dashboard/widgets/widget_20.php');
	pg_assert_true((bool) preg_match('/<a [^>]*class="[^"]*stretched-link[^"]*"[^>]*>/', $calendars, $match), 'calendar link');
	pg_assert_contains('aria-label="\' . h(lang(\'Calendars\')) . \'"', $match[0]);

	$comments = panel_accessible_names_source('includes/dashboard/widgets/widget_25.php');
	pg_assert_contains('<span aria-hidden="true">\' . str_repeat(\'★\'', $comments);
	pg_assert_contains("'string' => '{var:1} out of 5'", $comments);
	pg_assert_contains('<span class="visually-hidden">', $comments);

	$brief = panel_accessible_names_source('includes/dashboard/brief.php');
	pg_assert_contains('<span class="pg-brief-ws-bar" aria-hidden="true">', $brief);
	pg_assert_true(strpos($brief, 'role="progressbar"') === false, 'unnamed progressbar');

	$engagement = panel_accessible_names_source('includes/dashboard/widgets/widget_4.php');
	pg_assert_contains('aria-label="\' . h(lang(\'Start Chat\')) . \'"', $engagement);
	pg_assert_contains('<a href="edit_user.php?id=\' . (int) $eg_person[\'id\'] . \'"', $engagement);
	pg_assert_contains('<button type="button" class="btn btn-sm', $engagement);
}

// The Paraşüt inbox: both lists scroll inside their card on a phone, the pager
// has a name, named arrows and a current page that is no link, the detail
// dialog shows the translated type and status, the toolbar name is translated.
function test_panel_accessible_names_parasut_inbox()
{
	$source = panel_accessible_names_source('view_parasut_inbox.php');

	pg_assert_same(2, substr_count($source, '<div class="card-body p-0 table-responsive">'), 'both lists scroll in their card');
	pg_assert_contains("<nav aria-label=\"' . h(lang('Pagination')) . '\">", $source);
	pg_assert_contains("\$arrow(\$current - 1, \$current > 1, lang('Previous page'), '&laquo;')", $source);
	pg_assert_contains("\$arrow(\$current + 1, \$current < \$total, lang('Next page'), '&raquo;')", $source);
	pg_assert_contains("aria-label=\"' . h(\$label) . '\">' . \$inner . '</a>", $source, 'a live arrow is named');
	pg_assert_contains('<span aria-hidden="true">\' . $glyph . \'</span>', $source, 'arrow glyph hidden');
	// A disabled arrow is a span, so Tab does not reach it.
	pg_assert_contains("<li class=\"page-item disabled\"><span class=\"page-link\" aria-label=\"' . h(\$label) . '\">", $source);
	pg_assert_contains('<li class="page-item active" aria-current="page"><span class="page-link">', $source);
	pg_assert_true(strpos($source, '&laquo;</a>') === false && strpos($source, '&raquo;</a>') === false, 'no bare glyph links');

	pg_assert_same(2, substr_count($source, 'data-type-label="'), 'both detail buttons carry the type label');
	pg_assert_same(2, substr_count($source, 'data-status-label="'), 'both detail buttons carry the status label');
	pg_assert_contains('btn.dataset.typeLabel', $source);
	pg_assert_contains('btn.dataset.statusLabel', $source);
	pg_assert_true(strpos($source, 'btn.dataset.type ') === false && strpos($source, 'btn.dataset.status ') === false, 'raw codes not shown');

	pg_assert_contains("aria-label=\"' . h(lang('Button Bar')) . '\"", $source);
	pg_assert_true(strpos($source, 'aria-label="Button Bar"') === false, 'no literal toolbar name');
}
