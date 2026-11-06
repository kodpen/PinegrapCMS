<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The Speed and AI visibility sections of the Visitors screens: the period
 * they cover, how a page speed figure is written and rated, and the two
 * sections' markup. The figures come from includes/fn/page_vitals.php
 * (page_vitals_hourly) and includes/fn/ai_visibility.php (ai_fetch_daily,
 * visitors); nothing here queries a table itself. The Visitors overview
 * draws them as its speed and ai sections (includes/visitors/speed.php,
 * ai.php) with its own blocks, tables and charts
 * (includes/visitors/shell.php); the former screens visitor_vitals.php and
 * visitor_ai.php send their links there.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

// ── Period ─────────────────────────────────────────────────────────────────

/**
 * The periods the sections offer, key => days. The keys are the Visitors
 * overview's `p` values.
 *
 * @return array
 */
function pg_visitor_insight_periods()
{
    return array('7d' => 7, '30d' => 30, '90d' => 90);
}

/**
 * The window of a period key: whole days by the site's clock ending today.
 * An unknown key means 30 days. Pure.
 *
 * @param mixed $key
 * @param int   $now Unix time; 0 for now
 * @return array key, days, from (Y-m-d), to (Y-m-d)
 */
function pg_visitor_insight_window($key, $now = 0)
{
    $periods = pg_visitor_insight_periods();
    $key = is_scalar($key) ? (string) $key : '';

    if (!isset($periods[$key])) {
        $key = '30d';
    }

    $now = $now ? (int) $now : time();
    $to = date('Y-m-d', $now);

    // Days are stepped from noon, so a daylight saving change never skips
    // or repeats a date.
    $from = date('Y-m-d', strtotime($to . ' 12:00:00 -' . ($periods[$key] - 1) . ' days'));

    return array('key' => $key, 'days' => $periods[$key], 'from' => $from, 'to' => $to);
}

/**
 * The Visitors overview query that shows what an old Page Speed or AI
 * Visibility link showed: its period (`p`, 30 days when absent or unknown,
 * as those screens had it) and the filters in $rename, old name => the
 * overview's name. Pure.
 *
 * @param array $request the old link's query
 * @param array $rename  old parameter => overview parameter
 * @return array query parameters for visitors.php
 */
function pg_visitor_insight_overview_query(array $request, array $rename = array())
{
    $query = array('p' => pg_visitor_insight_window(isset($request['p']) ? $request['p'] : '')['key']);

    foreach ($rename as $old => $new) {
        if (isset($request[$old]) && is_scalar($request[$old]) && ((string) $request[$old] !== '')) {
            $query[$new] = (string) $request[$old];
        }
    }

    return $query;
}

/**
 * The period options for pg_filter_select().
 *
 * @return array key => label
 */
function pg_visitor_insight_period_options()
{
    $options = array();

    foreach (pg_visitor_insight_periods() as $key => $days) {
        $options[$key] = lang(array('string' => 'Last {var:1} days', 'vars' => array($days)));
    }

    return $options;
}

// ── Page speed figures ─────────────────────────────────────────────────────

/**
 * The names of the three figures, as the screens show them.
 *
 * @return array 'lcp'|'inp'|'cls' => label
 */
function pg_vitals_metric_labels()
{
    return array(
        'lcp' => lang('Largest paint (LCP)'),
        'inp' => lang('Response to input (INP)'),
        'cls' => lang('Layout shift (CLS)'),
    );
}

/**
 * A figure as text: LCP in seconds from one second up, else milliseconds;
 * INP in milliseconds; CLS as the unitless score (stored × 1000). '-' when
 * there is no value. Pure.
 *
 * @param string   $metric 'lcp', 'inp' or 'cls'
 * @param int|null $value  ms, or CLS × 1000
 * @return string
 */
function pg_vitals_display($metric, $value)
{
    if ($value === null) {
        return '-';
    }

    $value = (int) $value;

    if ($metric === 'cls') {
        return pg_format_number($value / 1000, 2);
    }

    if (($metric === 'lcp') && ($value >= 1000)) {
        return pg_format_number($value / 1000, 1) . ' s';
    }

    return pg_format_number($value, 0) . ' ms';
}

/**
 * The tone of a figure by Google's thresholds: good, warn (needs work) or
 * bad; '' without a value. Pure.
 *
 * @param string   $metric
 * @param int|null $value
 * @return string
 */
function pg_vitals_tone($metric, $value)
{
    if ($value === null) {
        return '';
    }

    $tones = array('good' => 'good', 'needs' => 'warn', 'poor' => 'bad');
    $bucket = pg_vitals_bucket($metric, $value);

    return isset($tones[$bucket]) ? $tones[$bucket] : '';
}

/**
 * The text colour class of a tone.
 *
 * @param string $tone
 * @return string
 */
function pg_visitor_insight_tone_class($tone)
{
    $classes = array('good' => 'fg-success', 'warn' => 'fg-warning', 'bad' => 'fg-danger');

    return isset($classes[$tone]) ? $classes[$tone] : '';
}

/**
 * A share (0-1) as a whole percent through the '{var:1}%' key (Turkish
 * "%83"), '-' for none. Pure.
 *
 * @param float|null $share
 * @return string
 */
function pg_visitor_insight_percent($share)
{
    return ($share === null) ? '-' : lang(array('string' => '{var:1}%', 'vars' => array(pg_format_number(round((float) $share * 100), 0))));
}

/**
 * A figure in the tone of its rating, for a table cell: the text, kept on
 * one line, in the colour of good, needs work or poor.
 *
 * @param string   $metric 'lcp', 'inp' or 'cls'
 * @param int|null $value
 * @return array a pg_visitors_table() cell
 */
function pg_visitor_insight_figure_cell($metric, $value)
{
    $tone = pg_visitor_insight_tone_class(pg_vitals_tone($metric, $value));

    return array(
        'html' => '<span class="text-nowrap' . (($tone !== '') ? ' ' . $tone : '') . '">' . h(pg_vitals_display($metric, $value)) . '</span>',
        'sort' => ($value === null) ? -1 : (int) $value,
    );
}

// ── Shared markup ──────────────────────────────────────────────────────────

/**
 * A row of figure tiles, drawn as the Visitors overview's tiles. Each tile:
 * label, text, note, tone.
 *
 * @param array $tiles
 * @return string
 */
function pg_visitor_insight_tiles(array $tiles)
{
    $out = '';

    foreach ($tiles as $tile) {
        $tone = pg_visitor_insight_tone_class(isset($tile['tone']) ? $tile['tone'] : '');
        $out .= '
            <div class="pg-visitors-tile">
                <div class="pg-visitors-tile-label">' . h($tile['label']) . '</div>
                <div class="pg-visitors-tile-value' . (($tone !== '') ? ' ' . $tone : '') . '">' . h($tile['text']) . '</div>'
            . ((isset($tile['note']) && ($tile['note'] !== '')) ? '<div class="pg-visitors-tile-note">' . h($tile['note']) . '</div>' : '') . '
            </div>';
    }

    return '<div class="pg-visitors-tiles">' . $out . '</div>';
}

/**
 * A block with a small heading over the overview's table
 * (pg_visitors_table()), or a line when there are no rows.
 *
 * @param string $title    text, also the table's name
 * @param array  $headings pg_visitors_table() headings
 * @param array  $rows     pg_visitors_table() rows
 * @param string $empty    plain text
 * @param string $after    markup under the table
 * @param array  $opts     more pg_visitors_table() options (limit, hide_first)
 * @return string
 */
function pg_visitor_insight_card($title, array $headings, array $rows, $empty, $after = '', array $opts = array())
{
    return pg_visitors_block($title, ($rows
        ? pg_visitors_table($headings, $rows, array_merge(array('caption' => $title), $opts))
        : pg_visitors_empty($empty)) . $after);
}

/**
 * A page's name as a link to the page when it has an address.
 *
 * @param string $label
 * @param string $url
 * @return string
 */
function pg_visitor_insight_page_link($label, $url)
{
    $label = ($label !== '') ? $label : lang('Deleted page');

    return ($url !== '')
        ? '<a href="' . h($url) . '" target="_blank" rel="noopener">' . h($label) . '</a>'
        : h($label);
}

// ── Speed ──────────────────────────────────────────────────────────────────

/**
 * The device options of the Speed section.
 *
 * @return array '' (all), desktop, mobile, tablet => label
 */
function pg_vitals_device_options()
{
    return array(
        ''        => lang('All devices'),
        'desktop' => lang('Desktop'),
        'mobile'  => lang('Mobile'),
        'tablet'  => lang('Tablet'),
    );
}

/**
 * The Speed section: the site's p75 of each figure with its good share,
 * the slowest pages and the trend by day.
 *
 * @param array  $window pg_visitor_insight_window()
 * @param string $device '' or a width class
 * @param string $order  pg_vitals_page_orders()
 * @return string
 */
function pg_visitor_speed_section(array $window, $device = '', $order = 'lcp')
{
    $site = pg_vitals_site($window['from'], $window['to'], $device);
    $labels = pg_vitals_metric_labels();

    if ($site['views'] <= 0) {
        return '<p class="pg-visitors-empty">' . h(lang('No page speed was measured in this period. Visitors\' browsers send it from public pages; signed-in staff, Preview and the designer are not measured.')) . '</p>';
    }

    $tiles = array();

    foreach ($labels as $metric => $label) {
        $figure = $site[$metric];
        $tiles[] = array(
            'label' => $label,
            'text'  => pg_vitals_display($metric, $figure['p75']),
            'tone'  => pg_vitals_tone($metric, $figure['p75']),
            'note'  => ($figure['samples'] > 0)
                ? lang(array('string' => '{var:1} good of {var:2} measured', 'vars' => array(pg_visitor_insight_percent($figure['good_share']), pg_format_number($figure['samples'], 0))))
                : lang('Not measured'),
        );
    }

    $tiles[] = array('label' => lang('Measured views'), 'text' => pg_format_number($site['views'], 0), 'note' => lang('75th percentile shown'), 'tone' => '');

    // The slowest pages, with their addresses from the content labeller.
    $pages = pg_vitals_pages($window['from'], $window['to'], $order, 25, $device);
    $content = array();

    foreach ($pages as $i => $page) {
        $content[$i] = array('page_id' => $page['page_id'], 'page_name' => $page['page_name'], 'item_type' => '', 'item_id' => 0, 'views' => $page['views']);
    }

    $labelled = $content ? pg_visitor_label_content_rows($content) : array();
    $rows = array();

    foreach ($pages as $i => $page) {
        $label = isset($labelled[$i]['label']) ? (string) $labelled[$i]['label'] : $page['page_name'];
        $url = isset($labelled[$i]['url']) ? (string) $labelled[$i]['url'] : '';
        $row = array(
            array('html' => pg_visitor_insight_page_link($label, $url)),
            array('html' => pg_format_number($page['views'], 0), 'sort' => (int) $page['views']),
        );

        foreach (array_keys($labels) as $metric) {
            $row[] = pg_visitor_insight_figure_cell($metric, $page[$metric]['p75']);
        }

        $row[] = pg_visitor_insight_percent($page['lcp']['good_share']);
        $rows[] = $row;
    }

    $headings = array(lang('Page'), array('label' => lang('Measured views'), 'end' => true));

    foreach ($labels as $label) {
        $headings[] = array('label' => $label, 'end' => true);
    }

    $headings[] = array('label' => lang('Good LCP'), 'end' => true);

    return pg_visitor_insight_tiles($tiles) . '
    <div class="pg-visitors-grid">
        ' . pg_visitor_speed_trend($window, $device) . '
        ' . pg_visitor_insight_card(lang('Slowest pages'), $headings, $rows, lang('No page was measured in this period.'), '', array('limit' => PG_VISITORS_LIST_ROWS)) . '
    </div>';
}

/**
 * The Speed section's trend: the site's LCP (p75) by day in seconds against
 * Google's good limit as a dashed line, each day's INP, CLS and measured
 * views under its point. '' with fewer than two days.
 *
 * @param array  $window pg_visitor_insight_window()
 * @param string $device '' or a width class
 * @return string
 */
function pg_visitor_speed_trend(array $window, $device = '')
{
    $trend = pg_vitals_trend($window['from'], $window['to'], $device);

    if (count($trend) < 2) {
        return '';
    }

    $labels = pg_vitals_metric_labels();
    $thresholds = pg_vitals_thresholds();
    $limit = round($thresholds['lcp'][0] / 1000, 2);
    $days = array();
    $lcp = array();
    $good = array();
    $notes = array();

    foreach ($trend as $day) {
        $days[] = prepare_form_data_for_output($day['day'], 'date', false);
        $lcp[] = ($day['lcp']['p75'] === null) ? null : round($day['lcp']['p75'] / 1000, 2);
        $good[] = $limit;
        $notes[] = $labels['inp'] . ': ' . pg_vitals_display('inp', $day['inp']['p75']) . "\n"
            . $labels['cls'] . ': ' . pg_vitals_display('cls', $day['cls']['p75']) . "\n"
            . lang('Measured views') . ': ' . pg_format_number($day['views'], 0);
    }

    return pg_visitors_block(lang('Trend'), pg_visitors_chart('speed_chart', array(
        'labels'  => $days,
        'series'  => array(
            array('label' => $labels['lcp'], 'data' => $lcp, 'unit' => 's'),
            array('label' => lang('Good limit'), 'data' => $good, 'unit' => 's', 'dashed' => true),
        ),
        'notes'   => $notes,
        'caption' => lang('Page speed by day'),
        'height'  => '12rem',
    )));
}

// ── AI visibility ──────────────────────────────────────────────────────────

/**
 * The name of a bot kind.
 *
 * @param string $kind 'agent', 'search' or 'unverified'
 * @return string
 */
function pg_ai_bot_kind_label($kind)
{
    $labels = array(
        'agent'      => lang('Assistant fetching for a person'),
        'search'     => lang('AI search indexer'),
        'unverified' => lang('Unverified claim'),
    );

    return isset($labels[$kind]) ? $labels[$kind] : (string) $kind;
}

/**
 * The fetches of the verified bots and how many of them fetched at all. Pure.
 *
 * @param array $bots pg_ai_fetch_bot_totals() answer
 * @return array fetches, bots, unverified
 */
function pg_ai_fetch_summary(array $bots)
{
    $summary = array('fetches' => 0, 'bots' => 0, 'unverified' => 0);

    foreach ($bots as $bot) {
        if ($bot['kind'] === 'unverified') {
            $summary['unverified'] += (int) $bot['fetches'];
            continue;
        }

        $summary['fetches'] += (int) $bot['fetches'];

        if ((int) $bot['fetches'] > 0) {
            $summary['bots']++;
        }
    }

    return $summary;
}

/**
 * The AI visibility section: fetches per bot and what the site tells it, the
 * pages each bot read most, the fetches by day, the visits AI assistants
 * sent and what those visitors did, and the sitemap pages no AI fetched.
 *
 * @param array $window pg_visitor_insight_window()
 * @param array $user   the signed-in user (a settings link for role < 3)
 * @return string
 */
function pg_visitor_ai_section(array $window, array $user)
{
    $settings = pg_ai_crawler_settings();
    $days = pg_ai_fetches_by_day($window['from'], $window['to']);
    $bots = pg_ai_fetch_bot_totals($days, pg_ai_bot_policy($settings));
    $summary = pg_ai_fetch_summary($bots);
    $referrals = pg_ai_referrals($window['from'], $window['to']);
    $totals = $referrals['totals'];
    $shop = defined('ECOMMERCE') && (ECOMMERCE === true);

    $tiles = array(
        array('label' => lang('AI fetches'), 'text' => pg_format_number($summary['fetches'], 0), 'note' => lang(array('string' => '{var:1} bots fetched pages', 'vars' => array(pg_format_number($summary['bots'], 0)))), 'tone' => ''),
        array('label' => lang('Visits from AI assistants'), 'text' => pg_format_number($totals['visits'], 0), 'note' => lang(array('string' => '{var:1} page views', 'vars' => array(pg_format_number($totals['page_views'], 0)))), 'tone' => ''),
        array('label' => lang('Forms submitted'), 'text' => pg_format_number($totals['forms_submitted'], 0), 'note' => lang('by those visitors'), 'tone' => ''),
    );

    if ($shop) {
        $tiles[] = array('label' => lang('Orders completed'), 'text' => pg_format_number($totals['orders_completed'], 0), 'note' => html_entity_decode(strip_tags(prepare_amount($totals['order_total'] / 100)), ENT_QUOTES, 'UTF-8'), 'tone' => '');
    }

    // The bots, each with what the site tells it.
    $bot_rows = array();

    foreach ($bots as $bot) {
        if ($bot['kind'] === 'unverified') {
            $state = '<span class="fg-secondary">' . h(lang('Counted only')) . '</span>';
        } elseif ($bot['firewall_allowed']) {
            $state = '<span class="fg-success">' . h(lang('Allowed')) . '</span>';
        } else {
            $state = '<span class="fg-danger">' . h(lang('Blocked by the firewall')) . '</span>';
        }

        // The kind sits under the name, so the table fits a phone.
        $bot_rows[] = array(
            array('html' => '<span class="fw-semibold text-nowrap">' . h(($bot['kind'] === 'unverified') ? lang('Unverified') : $bot['bot']) . '</span>'
                . '<span class="pg-visitors-cell-note">' . h(pg_ai_bot_kind_label($bot['kind'])) . '</span>'),
            array('html' => $state),
            array('html' => pg_format_number($bot['fetches'], 0), 'sort' => (int) $bot['fetches']),
        );
    }

    $policy_line = '<p class="pg-visitors-note">'
        . h(!empty($settings['rules']) ? lang('robots.txt carries the AI section.') : lang('robots.txt carries no AI section.')) . ' '
        . h(!empty($settings['llms']) ? lang('llms.txt is served.') : lang('llms.txt is not served.'))
        . (((int) $user['role'] < 3) ? ' <a href="' . h(OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/settings_seo.php#pgset-ai') . '">' . h(lang('AI crawler settings')) . '</a>' : '')
        . '</p>';

    // The fetches by day, every bot together, each day of the window.
    $by_day = array();

    foreach ($days as $row) {
        $by_day[$row['day']] = (isset($by_day[$row['day']]) ? $by_day[$row['day']] : 0) + (int) $row['fetches'];
    }

    $labels = array();
    $line = array();
    $step = strtotime($window['from'] . ' 12:00:00');

    for ($i = 0; $i < $window['days']; $i++) {
        $day = date('Y-m-d', strtotime('+' . $i . ' days', $step));
        $labels[] = prepare_form_data_for_output($day, 'date', false);
        $line[] = isset($by_day[$day]) ? $by_day[$day] : 0;
    }

    $chart = ($summary['fetches'] + $summary['unverified'] > 0)
        ? pg_visitors_block(lang('Fetches by day'), pg_visitors_chart('ai_chart', array(
            'labels'  => $labels,
            'series'  => array(array('label' => lang('AI fetches'), 'data' => $line)),
            'caption' => lang('Fetches by day'),
            'height'  => '12rem',
        )))
        : '';

    // The pages each bot read most, a row a bot in the bots' order.
    $page_rows = pg_visitor_ai_page_rows($bots, pg_ai_top_pages($window['from'], $window['to'], 3));

    // The visits AI assistants sent and what they led to.
    $referral_rows = array();

    foreach ($referrals['assistants'] as $row) {
        $cells = array(
            array('html' => '<span class="fw-semibold">' . h($row['assistant']) . '</span>'),
            array('html' => pg_format_number($row['visits'], 0), 'sort' => (int) $row['visits']),
            array('html' => pg_format_number($row['page_views'], 0), 'sort' => (int) $row['page_views']),
            array('html' => pg_format_number($row['forms_submitted'], 0), 'sort' => (int) $row['forms_submitted']),
        );

        if ($shop) {
            $cells[] = array('html' => pg_format_number($row['orders_completed'], 0), 'sort' => (int) $row['orders_completed']);
            $cells[] = array('html' => prepare_amount($row['order_total'] / 100), 'sort' => (int) $row['order_total']);
        }

        $referral_rows[] = $cells;
    }

    $referral_head = array(
        lang('Assistant'),
        array('label' => lang('Visits'), 'end' => true),
        array('label' => lang('Page views'), 'end' => true),
        array('label' => lang('Forms submitted'), 'end' => true),
    );

    if ($shop) {
        $referral_head[] = array('label' => lang('Orders completed'), 'end' => true);
        $referral_head[] = array('label' => lang('Order total'), 'end' => true);
    }

    $referral_note = ($referrals['summary_to'] !== null)
        ? '<p class="pg-visitors-note">' . h(lang(array('string' => 'Days up to {var:1} come from the daily summary, which keeps only each day\'s top referrers.', 'vars' => array(prepare_form_data_for_output($referrals['summary_to'], 'date', false))))) . '</p>'
        : '';

    // The sitemap's pages no verified AI fetched.
    $unfetched = pg_ai_unfetched_pages($window['from'], $window['to'], 50);
    $unfetched_rows = array();

    foreach ($unfetched['pages'] as $page) {
        $unfetched_rows[] = array((string) $page['page_name']);
    }

    $unfetched_note = ($unfetched['total'] > count($unfetched['pages']))
        ? '<p class="pg-visitors-note">' . h(lang(array('string' => 'The first {var:1} of {var:2} pages by name.', 'vars' => array(pg_format_number(count($unfetched['pages']), 0), pg_format_number($unfetched['total'], 0))))) . '</p>'
        : '';

    return pg_visitor_insight_tiles($tiles) . '
    <div class="pg-visitors-grid is-split">
        ' . $chart . '
        <div class="is-half">
            ' . pg_visitor_insight_card(lang('AI bots'),
                array(lang('Bot'), lang('Firewall'), array('label' => lang('Fetches'), 'end' => true)),
                $bot_rows, lang('No AI bot is known.'), $policy_line) . '
        </div>
        <div class="is-half">
            ' . pg_visitor_insight_card(lang('Most-read pages per bot'),
                array(lang('Bot'), lang('Most read')),
                $page_rows, lang('No AI bot fetched a page in this period.')) . '
        </div>
        ' . pg_visitor_insight_card(lang('Visits from AI assistants'), $referral_head, $referral_rows,
            lang('No visit came from an AI assistant\'s answer in this period.'), $referral_note) . '
        ' . pg_visitor_insight_card(lang(array('string' => 'Sitemap pages no AI fetched ({var:1})', 'vars' => array(pg_format_number($unfetched['total'], 0)))),
            array(lang('Page')), $unfetched_rows, lang('Every sitemap page was fetched by an AI bot in this period.'), $unfetched_note,
            array('limit' => PG_VISITORS_LIST_ROWS, 'hide_first' => true)) . '
    </div>';
}

/**
 * The rows of "Most-read pages per bot": one a bot, in the order of $bots,
 * its pages as links with their fetches, most read first. A bot without a
 * page in $pages has no row. Pure.
 *
 * @param array $bots  pg_ai_fetch_bot_totals() answer, in the order shown
 * @param array $pages pg_ai_top_pages() answer
 * @return array pg_visitors_table() rows
 */
function pg_visitor_ai_page_rows(array $bots, array $pages)
{
    $by_bot = array();
    $fetches = h(lang('Fetches')) . ': ';

    foreach ($pages as $page) {
        $by_bot[(string) $page['bot']][] = '<span class="pg-visitors-read">' . pg_visitor_insight_page_link((string) $page['label'], (string) $page['url'])
            . ' <span class="pg-visitors-read-count"><span class="visually-hidden">' . $fetches . '</span>' . pg_format_number($page['fetches'], 0) . '</span></span>';
    }

    $rows = array();

    foreach ($bots as $bot) {
        $name = (string) $bot['bot'];

        if (empty($by_bot[$name])) {
            continue;
        }

        $rows[] = array(
            array('html' => '<span class="fw-semibold text-nowrap">' . h($name) . '</span>'),
            array('html' => '<div class="pg-visitors-reads">' . implode(' ', $by_bot[$name]) . '</div>'),
        );
    }

    return $rows;
}
