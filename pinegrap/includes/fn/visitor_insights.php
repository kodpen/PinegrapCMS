<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The Speed and AI visibility sections of the Visitors screens: the period
 * they cover, how a page speed figure is written and rated, a small trend
 * line, and the two sections' markup. The figures come from
 * includes/fn/page_vitals.php (page_vitals_hourly) and
 * includes/fn/ai_visibility.php (ai_fetch_daily, visitors); nothing here
 * queries a table itself. The Visitors overview draws them as its speed and
 * ai sections (includes/visitors/speed.php, ai.php); the former screens
 * visitor_vitals.php and visitor_ai.php send their links there.
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
 * A share (0-1) as a whole percent, '-' for none. Pure.
 *
 * @param float|null $share
 * @return string
 */
function pg_visitor_insight_percent($share)
{
    return ($share === null) ? '-' : pg_format_number(round((float) $share * 100), 0) . '%';
}

/**
 * The points of a line through the values, left to right over $width and
 * scaled to $height with the largest value (or $ceiling when higher) at the
 * top; a null value leaves a gap and is skipped. Pure.
 *
 * @param array $values  numbers or null, in order
 * @param int   $width
 * @param int   $height
 * @param float $ceiling the least top of the scale
 * @return string "x,y x,y …", '' with no value
 */
function pg_visitor_spark_points(array $values, $width, $height, $ceiling = 0)
{
    $values = array_values($values);
    $numbers = array_filter($values, function ($value) {
        return $value !== null;
    });

    if (empty($numbers)) {
        return '';
    }

    $top = max((float) $ceiling, (float) max($numbers));
    $top = ($top > 0) ? $top : 1;
    $steps = max(1, count($values) - 1);
    $points = array();

    foreach ($values as $i => $value) {
        if ($value === null) {
            continue;
        }

        $x = (count($values) === 1) ? $width / 2 : $width * $i / $steps;
        $y = $height - ($height * (float) $value / $top);
        $points[] = round($x, 1) . ',' . round($y, 1);
    }

    return implode(' ', $points);
}

/**
 * A small line chart as inline SVG, with an optional dashed reference line
 * (a threshold). Hidden from screen readers: the table beside it holds the
 * same figures.
 *
 * @param array $values
 * @param float $reference 0 for none
 * @return string
 */
function pg_visitor_spark_svg(array $values, $reference = 0)
{
    $width = 600;
    $height = 120;
    $points = pg_visitor_spark_points($values, $width, $height, $reference * 1.25);

    if ($points === '') {
        return '';
    }

    $line = '';

    if ($reference > 0) {
        $numbers = array_filter($values, function ($value) {
            return $value !== null;
        });
        $top = max($reference * 1.25, (float) max($numbers));
        $y = round($height - ($height * $reference / $top), 1);
        $line = '<line x1="0" y1="' . $y . '" x2="' . $width . '" y2="' . $y . '" stroke="currentColor" stroke-opacity=".35" stroke-dasharray="6 6" />';
    }

    return '<svg class="d-block w-100 fg-primary" viewBox="0 0 ' . $width . ' ' . $height . '" preserveAspectRatio="none" height="120" aria-hidden="true" focusable="false">'
        . $line
        . '<polyline points="' . $points . '" fill="none" stroke="currentColor" stroke-width="2" vector-effect="non-scaling-stroke" />'
        . '</svg>';
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
 * A block with a small heading and a table, or a line when there are no rows.
 *
 * @param string $title
 * @param string $head  the header cells' markup
 * @param string $rows  the rows' markup
 * @param string $empty plain text
 * @param string $after markup under the table
 * @return string
 */
function pg_visitor_insight_card($title, $head, $rows, $empty, $after = '')
{
    return '<div class="pg-visitors-block">
            <h3 class="pg-visitors-subtitle">' . h($title) . '</h3>'
        . (($rows === '')
            ? '<p class="pg-visitors-empty is-start">' . h($empty) . '</p>'
            : pg_visitor_insight_table($head, $rows))
        . $after . '
        </div>';
}

/**
 * A table of the Visitors overview's look.
 *
 * @param string $head the header cells' markup
 * @param string $rows the rows' markup
 * @return string
 */
function pg_visitor_insight_table($head, $rows)
{
    return '<div class="table-responsive"><table class="table table-sm table-hover align-middle mb-0 pg-visitors-table"><thead><tr>' . $head . '</tr></thead><tbody>' . $rows . '</tbody></table></div>';
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
    $rows = '';

    foreach ($pages as $i => $page) {
        $cells = '';

        foreach (array_keys($labels) as $metric) {
            $p75 = $page[$metric]['p75'];
            $tone = pg_visitor_insight_tone_class(pg_vitals_tone($metric, $p75));
            $cells .= '<td class="align-middle text-nowrap' . (($tone !== '') ? ' ' . $tone : '') . '" data-order="' . (($p75 === null) ? -1 : (int) $p75) . '">' . h(pg_vitals_display($metric, $p75)) . '</td>';
        }

        $label = isset($labelled[$i]['label']) ? (string) $labelled[$i]['label'] : $page['page_name'];
        $url = isset($labelled[$i]['url']) ? (string) $labelled[$i]['url'] : '';
        $rows .= '<tr>
                <td class="align-middle text-break">' . pg_visitor_insight_page_link($label, $url) . '</td>
                <td class="align-middle" data-order="' . (int) $page['views'] . '">' . pg_format_number($page['views'], 0) . '</td>
                ' . $cells . '
                <td class="align-middle text-nowrap">' . h(pg_visitor_insight_percent($page['lcp']['good_share'])) . '</td>
            </tr>';
    }

    $head = '<th>' . h(lang('Page')) . '</th><th>' . h(lang('Measured views')) . '</th>';

    foreach ($labels as $label) {
        $head .= '<th>' . h($label) . '</th>';
    }

    $head .= '<th>' . h(lang('Good LCP')) . '</th>';

    // The trend: the LCP p75 by day against the good line, and every figure
    // by day in the table under it.
    $trend = pg_vitals_trend($window['from'], $window['to'], $device);
    $line = array();
    $trend_rows = '';

    foreach ($trend as $day) {
        $line[] = $day['lcp']['p75'];
        $cells = '';

        foreach (array_keys($labels) as $metric) {
            $cells .= '<td class="align-middle text-nowrap">' . h(pg_vitals_display($metric, $day[$metric]['p75'])) . '</td>';
        }

        $trend_rows = '<tr>
                <td class="align-middle text-nowrap">' . h(prepare_form_data_for_output($day['day'], 'date', false)) . '</td>
                <td class="align-middle">' . pg_format_number($day['views'], 0) . '</td>
                ' . $cells . '
            </tr>' . $trend_rows;
    }

    $thresholds = pg_vitals_thresholds();
    $chart = (count($line) > 1)
        ? '<div class="pg-visitors-spark"><p class="pg-visitors-note">' . h(lang(array('string' => 'Largest paint (LCP) by day; the dashed line is the good limit of {var:1}.', 'vars' => array(pg_vitals_display('lcp', $thresholds['lcp'][0]))))) . '</p>' . pg_visitor_spark_svg($line, $thresholds['lcp'][0]) . '</div>'
        : '';
    $trend_head = '<th>' . h(lang('Date')) . '</th><th>' . h(lang('Measured views')) . '</th>';

    foreach ($labels as $label) {
        $trend_head .= '<th>' . h($label) . '</th>';
    }

    return pg_visitor_insight_tiles($tiles) . '
    <div class="pg-visitors-grid">
        ' . pg_visitor_insight_card(lang('Slowest Pages'), $head, $rows, lang('No page was measured in this period.')) . '
        <div class="pg-visitors-block">
            <h3 class="pg-visitors-subtitle">' . h(lang('Trend')) . '</h3>
            ' . $chart . pg_visitor_insight_table($trend_head, $trend_rows) . '
        </div>
    </div>';
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
    $bot_rows = '';

    foreach ($bots as $bot) {
        if ($bot['kind'] === 'unverified') {
            $state = '<span class="fg-secondary">' . h(lang('Counted only')) . '</span>';
        } elseif ($bot['firewall_allowed']) {
            $state = '<span class="fg-success">' . h(lang('Allowed')) . '</span>';
        } else {
            $state = '<span class="fg-danger">' . h(lang('Blocked by the firewall')) . '</span>';
        }

        $bot_rows .= '<tr>
                <td class="align-middle fw-semibold">' . h(($bot['kind'] === 'unverified') ? lang('Unverified') : $bot['bot']) . '</td>
                <td class="align-middle">' . h(pg_ai_bot_kind_label($bot['kind'])) . '</td>
                <td class="align-middle" data-order="' . (int) $bot['fetches'] . '">' . pg_format_number($bot['fetches'], 0) . '</td>
                <td class="align-middle">' . $state . '</td>
            </tr>';
    }

    $policy_line = '<p class="pg-visitors-note">'
        . h(!empty($settings['rules']) ? lang('robots.txt carries the AI section.') : lang('robots.txt carries no AI section.')) . ' '
        . h(!empty($settings['llms']) ? lang('llms.txt is served.') : lang('llms.txt is not served.'))
        . (((int) $user['role'] < 3) ? ' <a href="' . h(OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/settings_seo.php#pgset-ai') . '">' . h(lang('AI crawler settings')) . '</a>' : '')
        . '</p>';

    // The fetches by day, every bot together.
    $by_day = array();

    foreach ($days as $row) {
        $by_day[$row['day']] = (isset($by_day[$row['day']]) ? $by_day[$row['day']] : 0) + (int) $row['fetches'];
    }

    $line = array();
    $step = strtotime($window['from'] . ' 12:00:00');

    for ($i = 0; $i < $window['days']; $i++) {
        $day = date('Y-m-d', strtotime('+' . $i . ' days', $step));
        $line[] = isset($by_day[$day]) ? $by_day[$day] : 0;
    }

    $chart = ($summary['fetches'] + $summary['unverified'] > 0)
        ? '<div class="pg-visitors-block"><h3 class="pg-visitors-subtitle">' . h(lang('Fetches by Day')) . '</h3>
            <div class="pg-visitors-spark">' . pg_visitor_spark_svg($line) . '
                <div class="pg-visitors-spark-dates"><span>' . h(prepare_form_data_for_output($window['from'], 'date', false)) . '</span><span>' . h(prepare_form_data_for_output($window['to'], 'date', false)) . '</span></div>
            </div></div>'
        : '';

    // The pages each bot read most.
    $page_rows = '';

    foreach (pg_ai_top_pages($window['from'], $window['to'], 5) as $row) {
        $page_rows .= '<tr>
                <td class="align-middle">' . h($row['bot']) . '</td>
                <td class="align-middle text-break">' . pg_visitor_insight_page_link($row['label'], $row['url']) . '</td>
                <td class="align-middle" data-order="' . (int) $row['fetches'] . '">' . pg_format_number($row['fetches'], 0) . '</td>
            </tr>';
    }

    // The visits AI assistants sent and what they led to.
    $referral_rows = '';

    foreach ($referrals['assistants'] as $row) {
        $referral_rows .= '<tr>
                <td class="align-middle fw-semibold">' . h($row['assistant']) . '</td>
                <td class="align-middle">' . pg_format_number($row['visits'], 0) . '</td>
                <td class="align-middle">' . pg_format_number($row['page_views'], 0) . '</td>
                <td class="align-middle">' . pg_format_number($row['forms_submitted'], 0) . '</td>'
            . ($shop ? '<td class="align-middle">' . pg_format_number($row['orders_completed'], 0) . '</td>
                <td class="align-middle text-end text-nowrap">' . prepare_amount($row['order_total'] / 100) . '</td>' : '') . '
            </tr>';
    }

    $referral_head = '<th>' . h(lang('Assistant')) . '</th><th>' . h(lang('Visits')) . '</th><th>' . h(lang('Page views')) . '</th><th>' . h(lang('Forms submitted')) . '</th>'
        . ($shop ? '<th>' . h(lang('Orders completed')) . '</th><th class="text-end">' . h(lang('Order total')) . '</th>' : '');
    $referral_note = ($referrals['summary_to'] !== null)
        ? '<p class="pg-visitors-note">' . h(lang(array('string' => 'Days up to {var:1} come from the daily summary, which keeps only each day\'s top referrers.', 'vars' => array(prepare_form_data_for_output($referrals['summary_to'], 'date', false))))) . '</p>'
        : '';

    // The sitemap's pages no verified AI fetched.
    $unfetched = pg_ai_unfetched_pages($window['from'], $window['to'], 50);
    $unfetched_rows = '';

    foreach ($unfetched['pages'] as $page) {
        $unfetched_rows .= '<tr><td class="align-middle text-break">' . h($page['page_name']) . '</td></tr>';
    }

    $unfetched_note = ($unfetched['total'] > count($unfetched['pages']))
        ? '<p class="pg-visitors-note">' . h(lang(array('string' => 'The first {var:1} of {var:2} pages by name.', 'vars' => array(pg_format_number(count($unfetched['pages']), 0), pg_format_number($unfetched['total'], 0))))) . '</p>'
        : '';

    return pg_visitor_insight_tiles($tiles) . '
    <div class="pg-visitors-grid is-split">
        ' . $chart . '
        <div class="is-half">
            ' . pg_visitor_insight_card(lang('AI Bots'),
                '<th>' . h(lang('Bot')) . '</th><th>' . h(lang('Kind')) . '</th><th>' . h(lang('Fetches')) . '</th><th>' . h(lang('Firewall')) . '</th>',
                $bot_rows, lang('No AI bot is known.'), $policy_line) . '
        </div>
        <div class="is-half">
            ' . pg_visitor_insight_card(lang('Most-read Pages per Bot'),
                '<th>' . h(lang('Bot')) . '</th><th>' . h(lang('Page')) . '</th><th>' . h(lang('Fetches')) . '</th>',
                $page_rows, lang('No AI bot fetched a page in this period.')) . '
        </div>
        ' . pg_visitor_insight_card(lang('Visits from AI Assistants'), $referral_head, $referral_rows,
            lang('No visit came from an AI assistant\'s answer in this period.'), $referral_note) . '
        ' . pg_visitor_insight_card(lang(array('string' => 'Sitemap Pages No AI Fetched ({var:1})', 'vars' => array(pg_format_number($unfetched['total'], 0)))),
            '<th>' . h(lang('Page')) . '</th>', $unfetched_rows, lang('Every sitemap page was fetched by an AI bot in this period.'), $unfetched_note) . '
    </div>';
}
