<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The new dashboard's top section, preset by preset: the shortcuts, the
 * four tiles and the "since your last sign-in" chips, and the pools they
 * are taken from. A shortcut or chip the person may not reach is neither
 * drawn nor offered; a stored tile the person may not see turns into the
 * next allowed figure.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_DASHBOARD_V2')) {
    exit;
}

/**
 * Each preset's top section. Simple has none of its own and takes the
 * first default's.
 *
 * @return array preset => array(actions, tiles, since)
 */
function pg_dashboard_v2_top_presets()
{
    return array(
        'corporate' => array(
            'actions' => array('edit_page', 'forms', 'team_task', 'announce'),
            'tiles'   => array('visits', 'forms', 'seo', 'tasks'),
            'since'   => array('visits', 'forms', 'comments', 'blocked', 'tasks_done'),
        ),
        'blog' => array(
            'actions' => array('write_post', 'comments', 'visual_editor', 'newsletter'),
            'tiles'   => array('visits', 'subs', 'seo', 'inbox'),
            'since'   => array('readers', 'comments', 'subs', 'drafts'),
        ),
        'ecommerce' => array(
            'actions' => array('orders', 'products', 'campaign', 'visual_editor'),
            'tiles'   => array('orders', 'visits', 'online', 'seo'),
            'since'   => array('orders', 'sales', 'refunds', 'visits', 'blocked'),
        ),
        'agency' => array(
            'actions' => array('designs', 'prototype', 'client_tasks', 'new_page'),
            'tiles'   => array('tasks', 'visits', 'a11y', 'flows'),
            'since'   => array('comments', 'tasks_done', 'approvals', 'files'),
        ),
    );
}

/**
 * The first default: Ecommerce when the shop is on, else Corporate. A role
 * default comes before it once one is set.
 *
 * @return string
 */
function pg_dashboard_v2_first_preset()
{
    return (defined('ECOMMERCE') && (ECOMMERCE === true)) ? 'ecommerce' : 'corporate';
}

/**
 * A preset's top section; Simple and an unknown name take the first
 * default's.
 *
 * @param string $preset
 * @return array actions, tiles, since
 */
function pg_dashboard_v2_top_for($preset)
{
    $presets = pg_dashboard_v2_top_presets();

    return isset($presets[$preset]) ? $presets[$preset] : $presets[pg_dashboard_v2_first_preset()];
}

/**
 * Every shortcut: label, screen, gate (a key of pg_dashboard_v2_gate() or
 * one of the screen rules below) and, for a label that carries a count, the
 * figure counted.
 *
 * @return array key => array(label, url, gate[, count])
 */
function pg_dashboard_v2_shortcuts()
{
    return array(
        'edit_page'     => array('label' => lang('Edit page'), 'url' => 'view_pages.php', 'gate' => 'files'),
        'forms'         => array('label' => lang('Forms'), 'url' => 'view_submitted_forms.php', 'gate' => 'forms', 'count' => 'forms'),
        'team_task'     => array('label' => lang('Task for the team'), 'url' => 'workspace_tasks.php', 'gate' => 'workspace'),
        'announce'      => array('label' => lang('Publish announcement'), 'url' => 'workspace.php', 'gate' => 'workspace'),
        'write_post'    => array('label' => lang('Write a post'), 'url' => 'add_page.php', 'gate' => 'add_page'),
        'comments'      => array('label' => lang('Comments'), 'url' => 'view_comments.php', 'gate' => 'files', 'count' => 'comments'),
        'visual_editor' => array('label' => lang('Visual Editor'), 'url' => 'view_styles.php', 'gate' => 'designer'),
        'newsletter'    => array('label' => lang('Send newsletter'), 'url' => 'add_email_campaign.php', 'gate' => 'emails'),
        'orders'        => array('label' => lang('Orders'), 'url' => 'view_orders.php', 'gate' => 'shop', 'count' => 'orders'),
        'products'      => array('label' => lang('Products'), 'url' => 'view_products.php', 'gate' => 'shop'),
        'campaign'      => array('label' => lang('Campaign'), 'url' => 'view_offers.php', 'gate' => 'shop'),
        'designs'       => array('label' => lang('Designs'), 'url' => 'view_styles.php', 'gate' => 'designer'),
        'prototype'     => array('label' => lang('Share prototype'), 'url' => 'view_styles.php', 'gate' => 'designer'),
        'client_tasks'  => array('label' => lang('Client tasks'), 'url' => 'workspace_tasks.php', 'gate' => 'workspace'),
        'new_page'      => array('label' => lang('New page'), 'url' => 'add_page.php', 'gate' => 'add_page'),
        'upload_file'   => array('label' => lang('Upload file'), 'url' => 'add_file.php', 'gate' => 'files'),
        'add_user'      => array('label' => lang('Add user'), 'url' => 'add_user.php', 'gate' => 'staff'),
        'start_flow'    => array('label' => lang('Start flow'), 'url' => 'workspace_flows.php', 'gate' => 'workspace'),
        'get_report'    => array('label' => lang('Get report'), 'url' => 'view_visitor_reports.php', 'gate' => 'visitors'),
        'back_up'       => array('label' => lang('Back up'), 'url' => 'backups.php', 'gate' => 'staff'),
    );
}

/**
 * The order "+ Add shortcut" offers the pool in.
 *
 * @return string[]
 */
function pg_dashboard_v2_shortcut_pool()
{
    return array('new_page', 'upload_file', 'add_user', 'start_flow', 'get_report', 'back_up', 'visual_editor', 'forms', 'orders');
}

/**
 * At most six shortcuts are kept.
 */
define('PG_DASHBOARD_V2_MAX_ACTIONS', 6);

/**
 * May the person open a shortcut's screen? The card gates, plus the rules of
 * the screens no card opens.
 *
 * @param string $gate
 * @param array  $user
 * @return bool
 */
function pg_dashboard_v2_screen_allowed($gate, $user)
{
    $role = (int) $user['role'];

    switch ((string) $gate) {
        case 'forms':
            return defined('FORMS') && (FORMS === true) && (($role < 3) || !empty($user['manage_forms']));
        case 'add_page':
            return ($role < 3) || !empty($user['create_pages']);
        case 'emails':
            return ($role < 3) || !empty($user['manage_emails']);
    }

    return pg_dashboard_v2_gate($gate, $user);
}

/**
 * The shortcuts to draw: the stored or preset keys the person may open,
 * known ones only, each once, at most six.
 *
 * @param string[] $keys
 * @param array    $user
 * @return string[]
 */
function pg_dashboard_v2_actions_for($keys, $user)
{
    $shortcuts = pg_dashboard_v2_shortcuts();
    $out = array();

    foreach ((array) $keys as $key) {
        $key = (string) $key;

        if (isset($shortcuts[$key]) && !in_array($key, $out, true) && pg_dashboard_v2_screen_allowed($shortcuts[$key]['gate'], $user)) {
            $out[] = $key;
        }

        if (count($out) >= PG_DASHBOARD_V2_MAX_ACTIONS) {
            break;
        }
    }

    return $out;
}

/**
 * The next pool shortcut "+ Add shortcut" would add, or '' when none is
 * left or six are drawn.
 *
 * @param string[] $current the drawn keys
 * @param array    $user
 * @return string
 */
function pg_dashboard_v2_next_action($current, $user)
{
    if (count($current) >= PG_DASHBOARD_V2_MAX_ACTIONS) {
        return '';
    }

    $shortcuts = pg_dashboard_v2_shortcuts();

    foreach (pg_dashboard_v2_shortcut_pool() as $key) {
        if (!in_array($key, $current, true) && pg_dashboard_v2_screen_allowed($shortcuts[$key]['gate'], $user)) {
            return $key;
        }
    }

    return '';
}

/**
 * The four tiles: each stored figure the person may see stays; one they may
 * not see becomes the next allowed figure not already shown, so the row
 * keeps its four places as long as four figures are allowed.
 *
 * @param string[] $keys
 * @param string[] $allowed the figures the person may see, in select order
 * @return string[]
 */
function pg_dashboard_v2_tiles_for($keys, $allowed)
{
    $out = array();
    $keys = array_slice(array_values((array) $keys), 0, 4);

    foreach ($keys as $key) {
        $out[] = in_array($key, $allowed, true) && !in_array($key, $out, true) ? (string) $key : null;
    }

    foreach ($out as $i => $key) {
        if ($key !== null) {
            continue;
        }

        $out[$i] = '';

        foreach ($allowed as $candidate) {
            if (!in_array($candidate, $out, true) && !in_array($candidate, $keys, true)) {
                $out[$i] = $candidate;
                break;
            }
        }
    }

    return array_values(array_filter($out, 'strlen'));
}

/**
 * A "since your last sign-in" chip: the count after $since and its words,
 * or null when the person may not see it, it has no source yet or the
 * count is zero.
 *
 * @param string $key   a key of a preset's since list
 * @param array  $user
 * @param int    $since signed_in_before
 * @return array|null text, tone, url
 */
function pg_dashboard_v2_since_chip($key, $user, $since)
{
    $since = (int) $since;

    if ($since <= 0) {
        return null;
    }

    $role = (int) $user['role'];
    $count = 0;
    $tone = '';
    $url = '';
    $text = '';

    switch ((string) $key) {
        case 'visits':
        case 'readers':
            if (!pg_dashboard_v2_gate('visitors', $user)) {
                return null;
            }
            $count = (int) db_value("SELECT COUNT(*) FROM visitors WHERE start_timestamp >= " . $since);
            $text = ($key === 'readers')
                ? lang(array('string' => '+{var:1} reader{suffix:1}', 'vars' => pg_format_number($count, 0)))
                : lang(array('string' => '+{var:1} visit{suffix:1}', 'vars' => pg_format_number($count, 0)));
            $tone = 'good';
            $url = 'view_visitor_reports.php';
            break;

        case 'forms':
            if (!(defined('FORMS') && (FORMS === true) && ($role < 3))) {
                return null;
            }
            $count = (int) db_value("SELECT COUNT(*) FROM forms WHERE complete = '1' AND submitted_timestamp >= " . $since);
            $text = lang(array('string' => '{var:1} new form{suffix:1}', 'vars' => pg_format_number($count, 0)));
            $tone = 'warn';
            $url = 'view_submitted_forms.php';
            break;

        case 'comments':
            if (!pg_dashboard_v2_gate('files', $user)) {
                return null;
            }
            $count = (int) db_value("SELECT COUNT(*) FROM comments WHERE created_timestamp >= " . $since);
            $text = lang(array('string' => '{var:1} comment{suffix:1}', 'vars' => pg_format_number($count, 0)));
            $url = 'view_comments.php';
            break;

        case 'blocked':
            if (!(($role < 3) && function_exists('waf_mode') && (waf_mode() !== 'off'))) {
                return null;
            }
            $count = (int) db_value("SELECT COALESCE(SUM(hit_count), 0) FROM waf_log
                WHERE action IN ('block', 'rate', 'ban') AND log_timestamp >= " . $since);
            $text = lang(array('string' => '{var:1} block{suffix:1}', 'vars' => pg_format_number($count, 0)));
            $url = 'view_waf_log.php';
            break;

        case 'tasks_done':
            if (!pg_dashboard_v2_gate('workspace', $user)) {
                return null;
            }
            $count = (int) db_value("SELECT COUNT(*) FROM ws_tasks
                WHERE status = 'done' AND completed_by = '" . (int) $user['id'] . "' AND completed_at >= '" . $since . "'");
            $text = lang(array('string' => '{var:1} task{suffix:1} done', 'vars' => pg_format_number($count, 0)));
            $tone = 'good';
            $url = 'workspace_tasks.php';
            break;

        case 'subs':
            if ($role >= 3) {
                return null;
            }
            $count = (int) db_value("SELECT COUNT(*) FROM contacts WHERE opt_in = '1' AND timestamp >= " . $since);
            $text = lang(array('string' => '{var:1} new subscriber{suffix:1}', 'vars' => pg_format_number($count, 0)));
            $tone = 'good';
            $url = 'view_contacts.php';
            break;

        case 'orders':
            if (!pg_dashboard_v2_gate('shop', $user)) {
                return null;
            }
            $count = (int) db_value("SELECT COUNT(*) FROM orders WHERE status != 'incomplete' AND order_date >= " . $since);
            $text = lang(array('string' => '{var:1} new order{suffix:1}', 'vars' => pg_format_number($count, 0)));
            $tone = 'good';
            $url = 'view_orders.php';
            break;

        case 'sales':
            if (!pg_dashboard_v2_gate('shop_reports', $user)) {
                return null;
            }
            $cents = (int) db_value("SELECT COALESCE(SUM(total), 0) FROM orders
                WHERE status IN ('complete', 'exported') AND order_date >= " . $since);
            $count = $cents;
            $text = lang(array('string' => '{var:1} in sales', 'vars' => pg_format_money($cents / 100, BASE_CURRENCY_SYMBOL)));
            $tone = 'good';
            $url = 'view_orders.php';
            break;

        case 'files':
            if (!pg_dashboard_v2_gate('files', $user)) {
                return null;
            }
            $count = (int) db_value("SELECT COUNT(*) FROM files WHERE design = '0' AND timestamp >= " . $since);
            $text = lang(array('string' => '{var:1} file{suffix:1} uploaded', 'vars' => pg_format_number($count, 0)));
            $url = 'view_files.php';
            break;

        default:
            // drafts, refunds, approvals: no time-stamped source yet.
            return null;
    }

    if ($count <= 0) {
        return null;
    }

    return array('text' => $text, 'tone' => $tone, 'url' => $url);
}
