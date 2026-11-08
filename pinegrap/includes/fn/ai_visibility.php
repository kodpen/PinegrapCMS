<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * AI visibility figures: which AI assistant or AI search indexer fetched which
 * page and how often (ai_fetch_daily, written by the firewall,
 * waf_ai_fetch_flush()), the pages the sitemap lists that none of them
 * fetched, what the site tells each of them (robots.txt, llms.txt, the
 * firewall switches), and the visits an AI assistant's answer sent
 * (visitors.referring_host_name against pg_visitor_ai_hosts()).
 *
 * Read from the daily rollup only, never from raw requests. The general job
 * removes days older than PG_AI_FETCH_DAYS once a day.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/**
 * Days the AI fetch rollup keeps.
 */
define('PG_AI_FETCH_DAYS', 400);

/**
 * The daily sweep's state file in data/temp/.
 */
define('PG_AI_FETCH_SWEEP_STATE', 'ai_fetch_sweep.json');

/**
 * True when the 2026.11.0 step (11.0.150) has created ai_fetch_daily.
 *
 * @return bool
 */
function pg_ai_visibility_ready()
{
    return pg_schema_has('ai_fetch_daily', 'fetches');
}

// ── The bots ───────────────────────────────────────────────────────────────

/**
 * The bots the counter files fetches under, name => kind: 'agent' for a user
 * fetcher (an assistant reading a page for someone), 'search' for an AI
 * search indexer, 'unverified' for a claim the published ranges did not prove.
 * The names are the ones waf_ai_bots() and pg_ai_crawler_catalog() use.
 *
 * @return array
 */
function pg_ai_fetch_bots()
{
    $catalog = pg_ai_crawler_catalog();
    $bots = array();

    foreach ($catalog['agent'] as $name) {
        $bots[$name] = 'agent';
    }

    foreach ($catalog['search'] as $name) {
        $bots[$name] = 'search';
    }

    $bots['unverified'] = 'unverified';

    return $bots;
}

/**
 * What the site tells each bot, from the AI crawler settings
 * (pg_ai_crawler_settings_from_row()): whether the firewall lets it in (the
 * fetchers and the search indexers each have their switch; an unverified
 * claim is never let in as a crawler), whether robots.txt carries the AI
 * section, and whether llms.txt is served.
 *
 * @param array $settings pg_ai_crawler_settings() answer
 * @return array name => kind, firewall_allowed, robots_ai_rules, llms_txt
 */
function pg_ai_bot_policy(array $settings)
{
    $policy = array();

    foreach (pg_ai_fetch_bots() as $name => $kind) {
        if ($kind === 'agent') {
            $allowed = !empty($settings['agents']);
        } elseif ($kind === 'search') {
            $allowed = !empty($settings['search']);
        } else {
            $allowed = false;
        }

        $policy[$name] = array(
            'kind'             => $kind,
            'firewall_allowed' => $allowed,
            'robots_ai_rules'  => !empty($settings['rules']),
            'llms_txt'         => !empty($settings['llms']),
        );
    }

    return $policy;
}

// ── Fetches ────────────────────────────────────────────────────────────────

/**
 * Fetches per day and bot in a window of days, oldest day first.
 *
 * @param string $first Y-m-d
 * @param string $last  Y-m-d
 * @return array of day, bot, fetches
 */
function pg_ai_fetches_by_day($first, $last)
{
    if (!pg_ai_visibility_ready()) {
        return array();
    }

    $result = db_q(
        "SELECT day, bot, SUM(fetches) AS fetches
         FROM ai_fetch_daily
         WHERE day >= ? AND day <= ?
         GROUP BY day, bot
         ORDER BY day, bot",
        array((string) $first, (string) $last)
    );

    $rows = array();

    while ($result && ($row = mysqli_fetch_assoc($result))) {
        $rows[] = array('day' => (string) $row['day'], 'bot' => (string) $row['bot'], 'fetches' => (int) $row['fetches']);
    }

    return $rows;
}

/**
 * Every known bot with its fetches in the rows, most first and then by name;
 * a bot without fetches is listed with 0, an unknown name in the rows (one a
 * later catalogue dropped) is kept.
 *
 * @param array $rows   pg_ai_fetches_by_day() answer
 * @param array $policy pg_ai_bot_policy() answer
 * @return array of bot, kind, fetches, firewall_allowed
 */
function pg_ai_fetch_bot_totals(array $rows, array $policy)
{
    $totals = array();

    foreach ($policy as $name => $entry) {
        $totals[$name] = array('bot' => (string) $name, 'kind' => $entry['kind'], 'fetches' => 0, 'firewall_allowed' => $entry['firewall_allowed']);
    }

    foreach ($rows as $row) {
        $name = (string) $row['bot'];

        if (!isset($totals[$name])) {
            $totals[$name] = array('bot' => $name, 'kind' => 'agent', 'fetches' => 0, 'firewall_allowed' => false);
        }

        $totals[$name]['fetches'] += (int) $row['fetches'];
    }

    $list = array_values($totals);

    usort($list, function ($a, $b) {
        if ($a['fetches'] !== $b['fetches']) {
            return ($a['fetches'] > $b['fetches']) ? -1 : 1;
        }

        return strcmp($a['bot'], $b['bot']);
    });

    return $list;
}

/**
 * The pages each bot fetched most in a window, at most $limit per bot. An
 * unverified claim names no page, and page 0 (a file, a feed) is no page.
 *
 * @param string $first Y-m-d
 * @param string $last  Y-m-d
 * @param int    $limit pages per bot
 * @return array of bot, page_id, page_name, label, url, fetches
 */
function pg_ai_top_pages($first, $last, $limit = 10)
{
    if (!pg_ai_visibility_ready()) {
        return array();
    }

    $result = db_q(
        "SELECT bot, page_id, SUM(fetches) AS fetches
         FROM ai_fetch_daily
         WHERE day >= ? AND day <= ? AND page_id > 0 AND bot <> 'unverified'
         GROUP BY bot, page_id",
        array((string) $first, (string) $last)
    );

    $rows = array();

    while ($result && ($row = mysqli_fetch_assoc($result))) {
        $rows[] = array('bot' => (string) $row['bot'], 'page_id' => (int) $row['page_id'], 'fetches' => (int) $row['fetches']);
    }

    $rows = pg_ai_top_pages_cut($rows, $limit);

    if (empty($rows)) {
        return array();
    }

    // The page names in one query; a deleted page keeps its row without one.
    $ids = array_values(array_unique(array_map(function ($row) {
        return (int) $row['page_id'];
    }, $rows)));

    $names = db_items("SELECT page_id, page_name FROM page WHERE page_id IN (" . implode(',', $ids) . ")", 'page_id');

    $content = array();

    foreach ($rows as $i => $row) {
        $content[$i] = array(
            'page_id'   => $row['page_id'],
            'page_name' => isset($names[$row['page_id']]) ? (string) $names[$row['page_id']]['page_name'] : '',
            'item_type' => '',
            'item_id'   => 0,
            'views'     => $row['fetches'],
            'deleted'   => !isset($names[$row['page_id']]),
        );
    }

    $labelled = pg_visitor_label_content_rows($content);
    $out = array();

    foreach ($rows as $i => $row) {
        $out[] = array(
            'bot'       => $row['bot'],
            'page_id'   => $row['page_id'],
            'page_name' => $content[$i]['page_name'],
            'label'     => isset($labelled[$i]['label']) ? (string) $labelled[$i]['label'] : $content[$i]['page_name'],
            'url'       => isset($labelled[$i]['url']) ? (string) $labelled[$i]['url'] : '',
            'fetches'   => $row['fetches'],
        );
    }

    return $out;
}

/**
 * Keeps the $limit pages with the most fetches of each bot, bots by name,
 * pages by fetches and then by id.
 *
 * @param array $rows  of bot, page_id, fetches
 * @param int   $limit
 * @return array
 */
function pg_ai_top_pages_cut(array $rows, $limit)
{
    $limit = max(1, (int) $limit);

    usort($rows, function ($a, $b) {
        $by_bot = strcmp((string) $a['bot'], (string) $b['bot']);

        if ($by_bot !== 0) {
            return $by_bot;
        }

        if ((int) $a['fetches'] !== (int) $b['fetches']) {
            return ((int) $a['fetches'] > (int) $b['fetches']) ? -1 : 1;
        }

        return ((int) $a['page_id'] < (int) $b['page_id']) ? -1 : 1;
    });

    $kept = array();
    $count = array();

    foreach ($rows as $row) {
        $bot = (string) $row['bot'];
        $count[$bot] = isset($count[$bot]) ? $count[$bot] + 1 : 1;

        if ($count[$bot] <= $limit) {
            $kept[] = $row;
        }
    }

    return $kept;
}

/**
 * The public pages the sitemap lists that no verified AI fetcher or indexer
 * fetched in the window: the first $limit by name, and how many there are.
 *
 * @param string $first Y-m-d
 * @param string $last  Y-m-d
 * @param int    $limit
 * @return array total, pages (page_id, page_name)
 */
function pg_ai_unfetched_pages($first, $last, $limit = 50)
{
    if (!pg_ai_visibility_ready()) {
        return array('total' => 0, 'pages' => array());
    }

    $types = pg_sitemap_page_types();
    $sql_noindex = pg_page_noindex_ready() ? "AND (page.noindex = '0')" : "";

    $result = db_q(
        "SELECT page.page_id, page.page_name, page.page_folder, page.page_type, page.page_home, page.page_timestamp
         FROM page
         INNER JOIN folder ON folder.folder_id = page.page_folder
         WHERE (page.sitemap = '1')
            $sql_noindex
            AND (folder.folder_archived = '0')
            AND (page.page_type IN (" . implode(', ', array_fill(0, count($types), '?')) . "))
            " . pg_designer_not_binned_sql('page.page_folder') . "
         ORDER BY page.page_name",
        $types
    );

    // A page behind a login is not in the sitemap; each folder is asked once.
    $public = array();
    $rows = array();

    while ($result && ($row = mysqli_fetch_assoc($result))) {
        $folder_id = (int) $row['page_folder'];

        if (!isset($public[$folder_id])) {
            $public[$folder_id] = (get_access_control_type($folder_id) == 'public');
        }

        if ($public[$folder_id]) {
            $rows[] = $row;
        }
    }

    // The standard pages whose system widget keeps them out of sitemap.xml
    // (get_sitemap_info.php): an error, login, account, checkout or detail page.
    $unlisted = array();

    if (function_exists('pg_sw_widget_pages') && function_exists('pg_sw_page_type_widgets') && function_exists('pg_sitemap_unlisted_widget_types')) {
        foreach (pg_sitemap_unlisted_widget_types(pg_sw_page_type_widgets()) as $widget_type) {
            foreach (pg_sw_widget_pages($widget_type) as $widget_page) {
                $unlisted[(int) $widget_page['page_id']] = true;
            }
        }
    }

    $pages = array();

    foreach (pg_ai_sitemap_listed_pages($rows, $unlisted) as $row) {
        $pages[] = array('page_id' => (int) $row['page_id'], 'page_name' => (string) $row['page_name']);
    }

    $fetched = db_values(
        "SELECT DISTINCT page_id FROM ai_fetch_daily
         WHERE day >= '" . escape((string) $first) . "' AND day <= '" . escape((string) $last) . "'
            AND page_id > 0 AND bot <> 'unverified'"
    );

    return pg_ai_unfetched_cut($pages, is_array($fetched) ? $fetched : array(), $limit);
}

/**
 * The page rows sitemap.xml lists, as get_sitemap_info.php picks them: a
 * standard page among $unlisted is left out, and of the pages marked as home
 * only the newest one stays (the sitemap lists it once, at the site root).
 * $rows are the public folders' pages only.
 *
 * @param array $rows     of page_id, page_type, page_home, page_timestamp
 * @param array $unlisted page id => true
 * @return array the kept rows, in their order
 */
function pg_ai_sitemap_listed_pages(array $rows, array $unlisted)
{
    $home_id = null;
    $home_time = null;

    foreach ($rows as $row) {
        if (($row['page_home'] === 'yes') && (($home_time === null) || ((int) $row['page_timestamp'] > $home_time))) {
            $home_id = (int) $row['page_id'];
            $home_time = (int) $row['page_timestamp'];
        }
    }

    $kept = array();

    foreach ($rows as $row) {
        $page_id = (int) $row['page_id'];

        if ($row['page_home'] === 'yes') {
            if ($page_id === $home_id) {
                $kept[] = $row;
            }
        } elseif (($row['page_type'] !== 'standard') || !isset($unlisted[$page_id])) {
            $kept[] = $row;
        }
    }

    return $kept;
}

/**
 * The pages whose id is not among the fetched ones: the first $limit, and
 * how many there are.
 *
 * @param array $pages   of page_id, page_name
 * @param array $fetched page ids
 * @param int   $limit
 * @return array total, pages
 */
function pg_ai_unfetched_cut(array $pages, array $fetched, $limit)
{
    $seen = array();

    foreach ($fetched as $id) {
        $seen[(int) $id] = true;
    }

    $left = array();

    foreach ($pages as $page) {
        if (!isset($seen[(int) $page['page_id']])) {
            $left[] = $page;
        }
    }

    return array('total' => count($left), 'pages' => array_slice($left, 0, max(0, (int) $limit)));
}

// ── Visits sent by AI assistants ───────────────────────────────────────────

/**
 * The referrer hosts of AI assistants, host => assistant. A visit whose
 * referrer is one of these came from a link in an assistant's answer. The
 * www. form of a host is matched by pg_visitor_ai_assistant().
 *
 * @return array
 */
function pg_visitor_ai_hosts()
{
    return array(
        'chatgpt.com'           => 'ChatGPT',
        'chat.openai.com'       => 'ChatGPT',
        'perplexity.ai'         => 'Perplexity',
        'copilot.microsoft.com' => 'Copilot',
        'gemini.google.com'     => 'Gemini',
        'bard.google.com'       => 'Gemini',
        'claude.ai'             => 'Claude',
        'you.com'               => 'You.com',
        'poe.com'               => 'Poe',
        'chat.mistral.ai'       => 'Mistral',
        'chat.deepseek.com'     => 'DeepSeek',
        'meta.ai'               => 'Meta AI',
        'grok.com'              => 'Grok',
    );
}

/**
 * The assistant a referrer host belongs to, '' for any other host.
 *
 * @param string $host
 * @return string
 */
function pg_visitor_ai_assistant($host)
{
    $host = rtrim(mb_strtolower(trim((string) $host)), '.');

    if (strpos($host, 'www.') === 0) {
        $host = substr($host, 4);
    }

    $hosts = pg_visitor_ai_hosts();

    return isset($hosts[$host]) ? $hosts[$host] : '';
}

/**
 * Every host pg_visitor_ai_assistant() accepts, the www. forms included, for
 * an IN () list.
 *
 * @return array
 */
function pg_visitor_ai_host_list()
{
    $list = array();

    foreach (array_keys(pg_visitor_ai_hosts()) as $host) {
        $list[] = $host;
        $list[] = 'www.' . $host;
    }

    return $list;
}

/**
 * Visits an AI assistant sent in a window of days, with what they led to:
 * from the visitor rows, and for the days the retention period has emptied
 * from the daily summary (the visitor report's rule, summary_to the last
 * such day). A host outside a summarized day's top values is not there.
 *
 * @param string $first Y-m-d
 * @param string $last  Y-m-d
 * @return array totals, assistants, summary_to
 */
function pg_ai_referrals($first, $last)
{
    $hosts = pg_visitor_ai_host_list();
    $in = "'" . implode("','", array_map('escape', $hosts)) . "'";
    $rows = array();
    $summary_to = null;

    $start = strtotime($first . ' 00:00:00');
    $stop = strtotime($last . ' 00:00:00 +1 day');
    $raw_from = function_exists('pg_visitor_summary_raw_from') ? pg_visitor_summary_raw_from() : 0;

    if (($raw_from > $start) && pg_schema_has('visitor_stats_daily', 'dim_value')) {
        $summary_last = min((string) $last, date('Y-m-d', $raw_from - 1));

        $summary = db_items(
            "SELECT dim_value AS host,
                COALESCE(SUM(visits), 0) AS visits,
                COALESCE(SUM(page_views), 0) AS page_views,
                COALESCE(SUM(forms_submitted), 0) AS forms_submitted,
                COALESCE(SUM(orders_completed), 0) AS orders_completed,
                COALESCE(SUM(order_total), 0) AS order_total
             FROM visitor_stats_daily
             WHERE (dimension = 'referring_host_name') AND (is_rest = 0)
                AND (stat_date >= '" . escape((string) $first) . "') AND (stat_date <= '" . escape($summary_last) . "')
                AND (dim_value IN (" . $in . "))
             GROUP BY dim_value"
        );

        if (is_array($summary) && !empty($summary)) {
            $rows = array_merge($rows, $summary);
            $summary_to = $summary_last;
        }

        $start = max($start, $raw_from);
    }

    $raw = db_items(
        "SELECT referring_host_name AS host,
            COUNT(*) AS visits,
            COALESCE(SUM(page_views), 0) AS page_views,
            COALESCE(SUM(custom_form_submitted), 0) AS forms_submitted,
            COALESCE(SUM(order_completed), 0) AS orders_completed,
            COALESCE(SUM(order_total), 0) AS order_total
         FROM visitors
         WHERE start_timestamp >= '" . (int) $start . "' AND start_timestamp < '" . (int) $stop . "'
            AND referring_host_name IN (" . $in . ")
         GROUP BY referring_host_name"
    );

    if (is_array($raw)) {
        $rows = array_merge($rows, $raw);
    }

    $folded = pg_ai_referrals_fold($rows);
    $folded['summary_to'] = $summary_to;

    return $folded;
}

/**
 * Referrer host rows added up by assistant, most visits first, and in total.
 *
 * @param array $rows of host, visits, page_views, forms_submitted, orders_completed, order_total
 * @return array totals, assistants (assistant and the same figures)
 */
function pg_ai_referrals_fold(array $rows)
{
    $figures = array('visits', 'page_views', 'forms_submitted', 'orders_completed', 'order_total');
    $totals = array_fill_keys($figures, 0);
    $by = array();

    foreach ($rows as $row) {
        $assistant = pg_visitor_ai_assistant(isset($row['host']) ? $row['host'] : '');

        if ($assistant === '') {
            continue;
        }

        if (!isset($by[$assistant])) {
            $by[$assistant] = array('assistant' => $assistant) + array_fill_keys($figures, 0);
        }

        foreach ($figures as $figure) {
            $value = isset($row[$figure]) ? (int) round((float) $row[$figure]) : 0;
            $by[$assistant][$figure] += $value;
            $totals[$figure] += $value;
        }
    }

    $list = array_values($by);

    usort($list, function ($a, $b) {
        if ($a['visits'] !== $b['visits']) {
            return ($a['visits'] > $b['visits']) ? -1 : 1;
        }

        return strcmp($a['assistant'], $b['assistant']);
    });

    return array('totals' => $totals, 'assistants' => $list);
}

// ── Retention ──────────────────────────────────────────────────────────────

/**
 * The first day kept: PG_AI_FETCH_DAYS before $now.
 *
 * @param int $now
 * @return string Y-m-d
 */
function pg_ai_fetch_keep_from($now)
{
    return date('Y-m-d', strtotime('-' . PG_AI_FETCH_DAYS . ' days', (int) $now));
}

/**
 * Where the daily sweep keeps when it last finished.
 *
 * @return string
 */
function pg_ai_fetch_sweep_file()
{
    return PG_FUNCTIONS_DIR . '/data/temp/' . PG_AI_FETCH_SWEEP_STATE;
}

/**
 * Whether the daily sweep is owed: it has not finished in the last day.
 *
 * @return bool
 */
function pg_ai_fetch_sweep_due()
{
    if (!pg_ai_visibility_ready()) {
        return false;
    }

    $state = @json_decode((string) @file_get_contents(pg_ai_fetch_sweep_file()), true);
    $done = (is_array($state) && isset($state['done_at'])) ? (int) $state['done_at'] : 0;

    return $done < (time() - 86400);
}

/**
 * The daily pass (job.php): the days older than PG_AI_FETCH_DAYS go, five
 * thousand rows at a time within the time budget. Writes the state file when
 * through.
 *
 * @param int $seconds
 * @return int rows removed
 */
function pg_ai_fetch_sweep($seconds = 5)
{
    if (!pg_ai_visibility_ready()) {
        return 0;
    }

    $removed = 0;
    $deadline = microtime(true) + max(1, (int) $seconds);
    $keep_from = pg_ai_fetch_keep_from(time());

    while (microtime(true) < $deadline) {
        if (db_q("DELETE FROM ai_fetch_daily WHERE day < ? LIMIT 5000", array($keep_from)) === false) {
            return $removed;
        }

        $affected = max(0, (int) mysqli_affected_rows(db::$con));
        $removed += $affected;

        if ($affected < 5000) {
            $file = pg_ai_fetch_sweep_file();

            if (!is_dir(dirname($file))) {
                @mkdir(dirname($file), 0755, true);
            }

            @file_put_contents($file, json_encode(array('done_at' => time())), LOCK_EX);
            break;
        }
    }

    return $removed;
}
