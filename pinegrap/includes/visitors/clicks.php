<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The "Clicks and scrolling" section of the Visitors overview: for the
 * period, the pages with counted views, the tenth of the page half of their
 * views reach and their most clicked element with its share; a page opens
 * its own view (clicks_page): its elements with clicks and share of views,
 * the share of views reaching each tenth, the clicks counted as "other".
 * A device filter (clicks_device) sits at the heading's end.
 *
 * The figures come from includes/fn/page_clicks.php (page_click_daily).
 * Labels and addresses were sent by visitors' browsers: they are printed as
 * text only. While the switch is off the section says in two lines what
 * would be kept and what never is, with the way to turn it on for whoever
 * may change Site Settings.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_VISITORS_SCREEN')) {
    exit;
}

// Pages the section lists; each one's most clicked element is one more read.
const PG_VISITORS_CLICKS_PAGES = 20;

/**
 * Whether the "Clicks and scrolling" switch is on: the settings text
 * page_clicks = '1'. A stand-in until the switch's own reader lands with
 * its Features card; then the section calls that one.
 *
 * @return bool
 */
function pg_visitors_clicks_on()
{
    if (!function_exists('pg_config_text')) {
        require_once(PG_FUNCTIONS_DIR . '/includes/fn/config_text.php');
    }

    return pg_config_text('page_clicks') === '1';
}

/**
 * The device choices of the filter, value => label.
 *
 * @return array
 */
function pg_visitors_clicks_devices()
{
    return array(
        ''        => lang('All devices'),
        'desktop' => lang('Desktop'),
        'mobile'  => lang('Mobile'),
        'tablet'  => lang('Tablet'),
    );
}

/**
 * The filter's device and the page asked for, from the query. Pure.
 *
 * @param array $request $_GET
 * @return array device ('' for all), page_id (0 for the list)
 */
function pg_visitors_clicks_request(array $request)
{
    $device = (isset($request['clicks_device']) && is_string($request['clicks_device'])) ? $request['clicks_device'] : '';
    $page = (isset($request['clicks_page']) && is_string($request['clicks_page']) && ctype_digit($request['clicks_page'])) ? (int) $request['clicks_page'] : 0;

    return array(
        'device'  => array_key_exists($device, pg_visitors_clicks_devices()) ? $device : '',
        'page_id' => max(0, $page),
    );
}

/**
 * The address of the section with the period kept, for a page's view or
 * the list, a device kept when one is chosen. Pure.
 *
 * @param array  $period  pg_visitor_period()
 * @param int    $page_id 0 for the list
 * @param string $device
 * @return string
 */
function pg_visitors_clicks_url(array $period, $page_id, $device)
{
    $query = pg_visitor_period_query($period);
    $more = array();

    if ($device !== '') {
        $more['clicks_device'] = $device;
    }

    if ((int) $page_id > 0) {
        $more['clicks_page'] = (string) (int) $page_id;
    }

    if ($more) {
        $query .= (($query !== '') ? '&' : '') . http_build_query($more, '', '&');
    }

    return 'visitors.php' . (($query !== '') ? '?' . $query : '') . '#' . pg_visitors_anchor('clicks');
}

/**
 * The part of the page an element sat in, as a reader names it. Pure.
 *
 * @param string $area pg_page_clicks_areas()
 * @return string text
 */
function pg_visitors_clicks_area($area)
{
    $names = array(
        'header' => lang('Header'),
        'nav'    => lang('Navigation'),
        'main'   => lang('Main content'),
        'footer' => lang('Footer'),
        'aside'  => lang('Side column'),
        'page'   => lang('Page'),
    );

    return isset($names[$area]) ? $names[$area] : $names['page'];
}

/**
 * A tenth of the page as the percent of its height. Pure.
 *
 * @param int|null $tenth 0-10
 * @return string text; '' for none
 */
function pg_visitors_clicks_tenth($tenth)
{
    return ($tenth === null) ? '' : pg_visitors_percent(max(0, min(10, (int) $tenth)) * 10, 0);
}

/**
 * A share (0-1) as a percent. Pure.
 *
 * @param float|null $share
 * @return string text; '–' for none
 */
function pg_visitors_clicks_share($share)
{
    return ($share === null) ? '–' : pg_visitors_percent(round((float) $share * 100, 1), 1);
}

/**
 * What a reader is told about an element: its label, else its address,
 * else the kind of element. Text, escaped by whoever prints it. Pure.
 *
 * @param array $element pg_page_clicks_page() element
 * @return string
 */
function pg_visitors_clicks_name(array $element)
{
    foreach (array('label', 'url') as $key) {
        if (isset($element[$key]) && trim((string) $element[$key]) !== '') {
            return (string) $element[$key];
        }
    }

    return (isset($element['role']) && $element['role'] === 'button') ? lang('Button') : lang('Link');
}

/**
 * The section while the switch is off: what would be kept, what never is,
 * and the way to turn it on for whoever may open Site Settings.
 *
 * @param array $user validate_user()
 * @return string HTML
 */
function pg_visitors_clicks_off(array $user)
{
    $manager = isset($user['role']) && ((int) $user['role'] <= 2);
    $action = $manager
        ? '<a class="btn btn-sm btn-outline theme-primary" href="' . h(pg_settings_link('features', 'pgset-features')) . '">'
            . '<i class="bi bi-toggle-on me-1" aria-hidden="true"></i>' . h(lang('Turn on in Site Settings')) . '</a>'
        : '<p class="pg-visitors-note">' . h(lang('A manager can turn it on in Site Settings, Features.')) . '</p>';

    return '
        <div class="pg-visitors-clicks-off">
            <p class="pg-visitors-clicks-off-text">' . h(lang('When it is on, the site counts which links and buttons visitors click on each page and how far down they scroll, per page, day and device.')) . '</p>
            <p class="pg-visitors-clicks-off-text">' . h(lang('No cookie, no identifier and nothing about a visitor is kept; signed-in members and visitors who refused statistics are never counted.')) . '</p>
            ' . $action . '
        </div>';
}

/**
 * The rows of the pages table: the page with its most clicked element and
 * that element's share as a quiet line under it, so the figures fit a phone.
 * Pure: every text is escaped here.
 *
 * @param array  $pages   pg_page_clicks_pages(), each with 'top' (an
 *                        element or null) added
 * @param array  $period
 * @param string $device
 * @return array
 */
function pg_visitors_clicks_page_rows(array $pages, array $period, $device)
{
    $rows = array();

    foreach ($pages as $page) {
        $name = ((string) $page['page_name'] !== '') ? (string) $page['page_name'] : lang('Untitled page');
        $top = isset($page['top']) && is_array($page['top']) ? $page['top'] : null;
        $note = $top
            ? lang(array('string' => 'Most clicked: {var:1} ({var:2})', 'vars' => array(pg_visitors_clicks_name($top), pg_visitors_clicks_share($top['share']))))
            : lang('No clicks counted');

        $rows[] = array(
            array('html' => '<a href="' . h(pg_visitors_clicks_url($period, (int) $page['page_id'], $device)) . '">' . h($name) . '</a>'
                . '<span class="pg-visitors-cell-note text-break">' . h($note) . '</span>', 'sort' => $name),
            array('html' => h(pg_format_number((int) $page['views'], 0)), 'sort' => (int) $page['views']),
            ($page['half_tenth'] === null) ? '–' : pg_visitors_clicks_tenth($page['half_tenth']),
        );
    }

    return $rows;
}

/**
 * The rows of a page's elements table: the label first with the part, the
 * kind and the address as a quiet line under it, then clicks and share;
 * "other" as one line at the end. Pure: every text is escaped here.
 *
 * @param array $figures pg_page_clicks_page()
 * @return array
 */
function pg_visitors_clicks_element_rows(array $figures)
{
    $rows = array();

    foreach ($figures['elements'] as $element) {
        $label = ((string) $element['label'] !== '') ? (string) $element['label'] : pg_visitors_clicks_name($element);
        $note = array(pg_visitors_clicks_area((string) $element['area']), ($element['role'] === 'button') ? lang('Button') : lang('Link'));

        if ((string) $element['url'] !== '') {
            $note[] = (string) $element['url'];
        }

        $rows[] = array(
            array('html' => h($label) . '<span class="pg-visitors-cell-note text-break">' . h(implode(' · ', $note)) . '</span>', 'sort' => $label),
            array('html' => h(pg_format_number((int) $element['clicks'], 0)), 'sort' => (int) $element['clicks']),
            pg_visitors_clicks_share($element['share']),
        );
    }

    if ((int) $figures['other'] > 0) {
        $rows[] = array(
            array('html' => h(lang('Other elements')) . '<span class="pg-visitors-cell-note">' . h(lang('Clicks past the elements a page keeps in a day')) . '</span>'),
            array('html' => h(pg_format_number((int) $figures['other'], 0)), 'sort' => (int) $figures['other']),
            pg_visitors_clicks_share(function_exists('pg_page_clicks_share') ? pg_page_clicks_share((int) $figures['other'], (int) $figures['views']) : null),
        );
    }

    return $rows;
}

/**
 * The bars of the share of views reaching each tenth, the top first. Pure.
 *
 * @param array $reach pg_page_clicks_reach()
 * @return array pg_visitors_bars() rows
 */
function pg_visitors_clicks_reach_rows(array $reach)
{
    $rows = array();

    for ($tenth = 1; $tenth <= 10; $tenth++) {
        $share = isset($reach[$tenth]) ? $reach[$tenth] : null;

        if ($share === null) {
            continue;
        }

        $rows[] = array(
            'label' => lang(array('string' => '{var:1} depth', 'vars' => array(pg_visitors_clicks_tenth($tenth)))),
            'value' => round((float) $share * 100, 1),
            'text'  => pg_visitors_clicks_share($share),
        );
    }

    return $rows;
}

/**
 * The list of the period's pages.
 *
 * @param array  $period
 * @param string $device
 * @return string HTML
 */
function pg_visitors_clicks_list(array $period, $device)
{
    $pages = pg_page_clicks_pages($period['from'], $period['to'], $device, PG_VISITORS_CLICKS_PAGES);

    if (!$pages) {
        return pg_visitors_empty(lang('No page views were counted in this period yet.'));
    }

    foreach ($pages as $index => $page) {
        $figures = pg_page_clicks_page((int) $page['page_id'], $period['from'], $period['to'], $device, 1);
        $pages[$index]['top'] = $figures['elements'] ? $figures['elements'][0] : null;
    }

    return pg_visitors_table(
        array(
            lang('Page'),
            array('label' => lang('Views'), 'end' => true),
            array('label' => lang('Half depth'), 'end' => true),
        ),
        pg_visitors_clicks_page_rows($pages, $period, $device),
        array('caption' => lang('Clicks and scrolling by page'), 'limit' => PG_VISITORS_LIST_ROWS * 2)
    ) . '<p class="pg-visitors-note">' . h(lang('Half depth: how far down the page half of its views came. Each element counts once a page view, so its share is never more than all the views.')) . '</p>';
}

/**
 * One page's view.
 *
 * @param array  $period
 * @param int    $page_id
 * @param string $device
 * @return string HTML
 */
function pg_visitors_clicks_page(array $period, $page_id, $device)
{
    $name = (string) db_value("SELECT page_name FROM page WHERE page_id = '" . (int) $page_id . "'");
    $back = '<p class="pg-visitors-clicks-back"><a href="' . h(pg_visitors_clicks_url($period, 0, $device)) . '">'
        . '<i class="bi bi-arrow-left me-1" aria-hidden="true"></i>' . h(lang('All pages')) . '</a></p>';

    if ($name === '') {
        return $back . pg_visitors_empty(lang('This page is no longer there.'));
    }

    $figures = pg_page_clicks_page($page_id, $period['from'], $period['to'], $device);
    $title = '<h3 class="pg-visitors-subtitle pg-visitors-clicks-page">' . h($name) . '</h3>';

    if ($figures['views'] <= 0) {
        return $back . $title . pg_visitors_empty(lang('No page views were counted on this page in this period.'));
    }

    $half = pg_page_clicks_half_tenth($figures['reach']);
    $tiles = '<div class="pg-visitors-tiles">'
        . pg_visitors_tile(lang('Views'), pg_format_number($figures['views'], 0))
        . pg_visitors_tile(lang('Half the views reach'), ($half === null) ? '–' : pg_visitors_clicks_tenth($half))
        . pg_visitors_tile(lang('Elements clicked'), pg_format_number(count($figures['elements']), 0))
        . '</div>';

    $elements = pg_visitors_table(
        array(
            lang('Element'),
            array('label' => lang('Clicks'), 'end' => true),
            array('label' => lang('Share of views'), 'end' => true),
        ),
        pg_visitors_clicks_element_rows($figures),
        array('caption' => lang('Elements clicked'), 'limit' => PG_VISITORS_LIST_ROWS * 2)
    );

    return $back . $title . $tiles . '
        <div class="pg-visitors-grid is-split">
            ' . pg_visitors_block(lang('Elements clicked'), $elements) . '
            ' . pg_visitors_block(lang('Scroll depth'), '<p class="pg-visitors-note mt-0 mb-2">' . h(lang('The share of views that came down at least this far on the page.')) . '</p>'
                . pg_visitors_bars(pg_visitors_clicks_reach_rows($figures['reach']), array('label' => lang('Scroll depth')))) . '
        </div>';
}

/**
 * The section.
 *
 * @param array $period pg_visitor_period()
 * @param array $user   validate_user()
 * @return string HTML
 */
function pg_visitors_section_clicks(array $period, array $user)
{
    $sections = pg_visitors_sections();
    $title = $sections['clicks'];

    if (!pg_visitors_clicks_on()) {
        return pg_visitors_section('clicks', $title, pg_visitors_clicks_off($user));
    }

    if (!function_exists('pg_page_clicks_ready') || !pg_page_clicks_ready()) {
        return pg_visitors_section('clicks', $title, pg_visitors_empty(lang('The database has not been upgraded yet. Run the software update first.')));
    }

    $asked = pg_visitors_clicks_request($_GET);
    $keep = array('p', 'from', 'to', 'compare', 'clicks_device', 'clicks_page');
    $aside = pg_filter_select('clicks_device', pg_visitors_clicks_devices(), $asked['device'], array(
        'label'  => lang('Device'),
        'icon'   => 'phone',
        'action' => 'visitors.php#' . pg_visitors_anchor('clicks'),
        'keep'   => $keep,
        'id'     => 'pg_visitors_clicks_device',
    ));

    $body = ($asked['page_id'] > 0)
        ? pg_visitors_clicks_page($period, $asked['page_id'], $asked['device'])
        : pg_visitors_clicks_list($period, $asked['device']);

    return pg_visitors_section('clicks', $title, $body, $aside);
}
