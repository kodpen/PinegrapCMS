<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source pins for dashboard figures and states that used to live only in a
 * native title: the order's shipped state, a firewall row's request count
 * and score limit, a threat source's address count, a slow page's request
 * count and peak, the Search Console comparison basis and Away presence.
 * A title shows on mouse hover only, never on focus or touch.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function dashboard_hover_figures_source($id)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/dashboard/widgets/widget_' . $id . '.php');
}

// Recent Orders writes the shipped state in the meta line; the glyph is
// decoration.
function test_dashboard_hover_figures_order_shipped_state_is_text()
{
	$source = dashboard_hover_figures_source(8);

	pg_assert_contains("' &mdash; ' . \$ship_title", $source);
	pg_assert_contains("'bi-box-seam') . '\" aria-hidden=\"true\"", $source);
}

// A firewall row says what its count counts and shows the score limit.
function test_dashboard_hover_figures_firewall_counts_and_limit_are_text()
{
	$source = dashboard_hover_figures_source(21);

	pg_assert_contains("'string' => '{var:1} request{suffix:1}'", $source);
	pg_assert_contains('<span class="visually-hidden">', $source);
	pg_assert_contains("\$waf_event_score . ' / ' . (int) \$waf_threshold_value . '</span>", $source);
	pg_assert_false(strpos($source, 'title="\' . lang(\'Requests\')') !== false, 'request count only in a title');
}

// Threat sources and slowest pages print their second figure as a line.
function test_dashboard_hover_figures_bar_figures_are_text()
{
	foreach (array(22 => '{var:1} address{suffix:1}', 23 => '{var:1} request{suffix:1} · peak {var:2} ms') as $id => $key) {
		$source = dashboard_hover_figures_source($id);

		pg_assert_contains($key, $source);
		pg_assert_false((bool) preg_match('/<div class="progress[^"]*"[^>]*\btitle=/', $source), 'figure only in the bar title (widget ' . $id . ')');
		pg_assert_contains('<div class="fg-secondary mt-1" style="font-size:.625rem">', $source);
	}
}

// The Search Console change says what it compares against.
function test_dashboard_hover_figures_search_console_basis_is_text()
{
	$source = dashboard_hover_figures_source(31);

	pg_assert_contains("<span class=\"visually-hidden\"> ' . h(lang('Against the 28 days before'))", $source);
}

// Away is written in the seen line, not only as the avatar dot's colour.
function test_dashboard_hover_figures_away_is_text()
{
	$source = dashboard_hover_figures_source(4);

	pg_assert_contains("\$eg_seen_text = h(lang('Away')) . ' &middot; ' . get_relative_time(", $source);
}
