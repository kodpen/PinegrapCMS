<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The Next up card: what the open modules are waiting for, one row per
 * kind of work with a module tag, a toned note and an action opening the
 * list or record where it is done. A row goes away by itself once the work
 * is done, because every row is a count read when the card is drawn.
 *
 * Sources: form submissions of the last 24 hours, comments waiting for
 * moderation, pages with a critical SEO score, accessibility issues, the
 * person's overdue Workspace tasks and the approvals waiting on them. Each
 * is read as the tile figure or the old dashboard's widget reads the same
 * rows, behind the same gate.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_DASHBOARD_V2')) {
    exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/dashboard/v2/figures.php');

/**
 * At most this many rows: the card is a short list, not a report.
 */
if (!defined('PG_DASHBOARD_V2_QUEUE_ROWS')) {
    define('PG_DASHBOARD_V2_QUEUE_ROWS', 6);
}

/**
 * The rows from the counts, most urgent first. Pure.
 *
 * @param array $counts source => array of its numbers and url, or null when
 *                      the person may not see the source; sources: forms
 *                      (count, oldest, url), comments (count), seo (critical,
 *                      average), a11y (issues, pages), overdue (count, open),
 *                      approvals (count, url)
 * @param int   $limit
 * @return array rows: module, tag, text, note, tone, action, url
 */
function pg_dashboard_v2_queue_rows($counts, $limit = PG_DASHBOARD_V2_QUEUE_ROWS)
{
    $tags = array(
        'form'    => lang('Form'),
        'content' => lang('Content'),
        'team'    => lang('Team'),
    );

    $n = function ($source, $name) use ($counts) {
        return isset($counts[$source]) && is_array($counts[$source]) ? (int) ($counts[$source][$name] ?? 0) : 0;
    };
    $url = function ($source, $default) use ($counts) {
        $value = isset($counts[$source]['url']) ? (string) $counts[$source]['url'] : '';
        return ($value !== '') ? $value : $default;
    };
    $number = function ($value) {
        return pg_format_number((int) $value, 0);
    };
    $suffix = function ($value) {
        return array(((int) $value === 1) ? '' : 's');
    };
    $row = function ($module, $text, $note, $tone, $action, $link) use ($tags) {
        return array(
            'module' => $module,
            'tag'    => $tags[$module],
            'text'   => $text,
            'note'   => $note,
            'tone'   => $tone,
            'action' => $action,
            'url'    => $link,
        );
    };

    $rows = array();

    if ($n('overdue', 'count') > 0) {
        $count = $n('overdue', 'count');
        $rows[] = $row('team',
            lang(array('string' => '{var:1} overdue task{suffix:1}', 'vars' => $number($count), 'suffix' => $suffix($count))),
            lang(array('string' => '{var:1} open in all', 'vars' => $number(max($count, $n('overdue', 'open'))))),
            'bad', lang('Open'), 'workspace_tasks.php');
    }

    if ($n('seo', 'critical') > 0) {
        $count = $n('seo', 'critical');
        $rows[] = $row('content',
            lang(array('string' => '{var:1} page{suffix:1} with a critical SEO score', 'vars' => $number($count), 'suffix' => $suffix($count))),
            lang(array('string' => 'Average score {var:1}', 'vars' => $number($n('seo', 'average')))),
            'bad', lang('Show'), 'view_pages.php?filter=seo_critical');
    }

    if ($n('approvals', 'count') > 0) {
        $count = $n('approvals', 'count');
        $rows[] = $row('team',
            lang(array('string' => '{var:1} approval{suffix:1} waiting on you', 'vars' => $number($count), 'suffix' => $suffix($count))),
            lang('Workspace'),
            'warn', lang('Approve'), $url('approvals', 'workspace.php'));
    }

    if ($n('forms', 'count') > 0) {
        $count = $n('forms', 'count');
        $oldest = $n('forms', 'oldest');
        $rows[] = $row('form',
            lang(array('string' => '{var:1} form submission{suffix:1} in the last 24 hours', 'vars' => $number($count), 'suffix' => $suffix($count))),
            ($oldest > 0) ? lang(array('string' => 'oldest {var:1}', 'vars' => get_relative_time(array('timestamp' => $oldest, 'format' => 'plain_text')))) : '',
            'warn', lang('Reply'), $url('forms', 'view_submitted_forms.php'));
    }

    if ($n('comments', 'count') > 0) {
        $count = $n('comments', 'count');
        $rows[] = $row('content',
            lang(array('string' => '{var:1} comment{suffix:1} waiting for moderation', 'vars' => $number($count), 'suffix' => $suffix($count))),
            lang('Not shown on the site until approved'),
            'warn', lang('Moderate'), 'view_comments.php');
    }

    if ($n('a11y', 'issues') > 0) {
        $count = $n('a11y', 'issues');
        $pages = $n('a11y', 'pages');
        $rows[] = $row('content',
            lang(array('string' => '{var:1} accessibility issue{suffix:1}', 'vars' => $number($count), 'suffix' => $suffix($count))),
            lang(array('string' => 'on {var:1} page{suffix:1}', 'vars' => $number($pages), 'suffix' => $suffix($pages))),
            'plain', lang('Show'), 'view_accessibility.php');
    }

    return array_slice($rows, 0, max(1, (int) $limit));
}

/**
 * The counts behind the rows, each source behind its gate; null for a
 * source the person may not see.
 *
 * @param array $user
 * @return array
 */
function pg_dashboard_v2_queue_counts($user)
{
    require_once(PG_FUNCTIONS_DIR . '/includes/dashboard/widgets.php');

    $now = time();
    $counts = array_fill_keys(array('forms', 'comments', 'seo', 'a11y', 'overdue', 'approvals'), null);

    if (pg_dashboard_v2_figure_allowed('forms', $user)) {
        $raw = pg_dashboard_v2_figure_raw('forms', $user);
        $counts['forms'] = array(
            'count'  => (int) $raw['pending'],
            'oldest' => (int) $raw['oldest'],
            // The list opened on the 24 hours that were counted, every form.
            'url'    => pg_dashboard_list_url('view_submitted_forms.php', array(
                'advanced_filters' => 'false',
                'custom_form'      => '[All]',
                'query'            => ''), $now - 86400, $now),
        );
    }

    if (pg_dashboard_v2_figure_gate('staff', $user)) {
        // As the Comment Moderation widget counts them for a staff role:
        // comments on pages in the Recycle Bin are not waiting for anyone.
        $counts['comments'] = array('count' => (int) db_value(
            "SELECT COUNT(*) FROM comments LEFT JOIN page ON comments.page_id = page.page_id
            WHERE comments.published = '0'" . pg_comment_live_page_sql(pg_recycle_bin_folder_ids())));

        $seo = pg_dashboard_v2_figure_raw('seo', $user);
        $counts['seo'] = array('critical' => (int) $seo['critical'], 'average' => (int) $seo['average']);

        $a11y = pg_dashboard_v2_figure_raw('a11y', $user);
        $counts['a11y'] = array('issues' => (int) $a11y['issues'], 'pages' => (int) $a11y['pages']);
    }

    if (pg_dashboard_v2_gate('workspace', $user)) {
        $signals = pg_dashboard_v2_ws_signals($user);
        $counts['overdue'] = array('count' => (int) ($signals['overdue'] ?? 0), 'open' => (int) ($signals['open'] ?? 0));
        $counts['approvals'] = array('count' => (int) ($signals['approvals'] ?? 0), 'url' => (string) ($signals['approval_url'] ?? ''));
    }

    return $counts;
}

function pg_dashboard_v2_card_queue($user, $period, $compare)
{
    if (!pg_dashboard_v2_gate('all', $user)) {
        return array('status' => 'denied');
    }

    $rows = pg_dashboard_v2_queue_rows(pg_dashboard_v2_queue_counts($user));

    return array('status' => 'success', 'data' => array(
        'sub'   => lang('From the open modules; a row goes when its work is done'),
        'rows'  => $rows,
        'empty' => empty($rows) ? lang('Nothing is waiting for you.') : '',
    ));
}
