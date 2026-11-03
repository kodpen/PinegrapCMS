<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The Activity card: the newest events of widget 7's feed
 * (pg_dashboard_activity_kinds(), with every kind's own gate) as a
 * timeline, and chips that narrow it to Content, Form, Team or Security.
 * The feed's rows are HTML for the old card; here they become plain text,
 * since the script draws them with textContent.
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
 * The chip a feed kind is shown under; '' for a kind only "All" shows.
 *
 * @param string $kind a key of pg_dashboard_activity_kinds()
 * @return string content, form, team, security or ''
 */
function pg_dashboard_v2_activity_chip($kind)
{
    $chips = array(
        'update'   => 'content',
        'comment'  => 'content',
        'campaign' => 'content',
        'form'     => 'form',
        'contact'  => 'form',
        'user'     => 'team',
        'log'      => 'security',
    );

    return $chips[(string) $kind] ?? '';
}

/**
 * A feed row's HTML text as plain text: tags dropped, entities read, the
 * feed's " &middot; " kept as a dot, spaces folded.
 *
 * @param string $html
 * @return string
 */
function pg_dashboard_v2_plain($html)
{
    $text = html_entity_decode(strip_tags((string) $html), ENT_QUOTES | ENT_HTML5, 'UTF-8');

    return trim(preg_replace('/\s+/u', ' ', $text));
}

/**
 * The feed's rows merged newest first (the sort key breaks ties, as the
 * widget does), each with its chip, at most $limit.
 *
 * @param array $kinds pg_dashboard_activity_kinds()
 * @param int   $limit
 * @return array rows of kind, chip, ts, text, note, url
 */
function pg_dashboard_v2_activity_rows($kinds, $limit)
{
    $rows = array();

    foreach ((array) $kinds as $key => $kind) {
        foreach ((array) ($kind['items'] ?? array()) as $item) {
            $rows[] = array(
                'kind' => (string) $key,
                'chip' => pg_dashboard_v2_activity_chip($key),
                'ts'   => (int) $item['ts'],
                'sort' => (string) ($item['sort'] ?? ''),
                'text' => pg_dashboard_v2_plain($item['name'] ?? ''),
                'note' => pg_dashboard_v2_plain($item['meta'] ?? ''),
                'url'  => html_entity_decode((string) ($item['href'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
            );
        }
    }

    usort($rows, function ($a, $b) {
        if ($a['ts'] === $b['ts']) {
            return strcmp($a['sort'], $b['sort']);
        }
        return ($a['ts'] > $b['ts']) ? -1 : 1;
    });

    $out = array();

    foreach (array_slice($rows, 0, (int) $limit) as $row) {
        unset($row['sort']);
        $out[] = $row;
    }

    return $out;
}

function pg_dashboard_v2_card_activity($user, $period, $compare)
{
    require_once(PG_FUNCTIONS_DIR . '/includes/dashboard/widgets.php');
    require_once(PG_FUNCTIONS_DIR . '/includes/dashboard/widgets/widget_7.php');

    // Five rows a chip are drawn; each kind is read to the same depth, so
    // a chip always has its five when the feed holds them.
    $kinds = pg_dashboard_activity_kinds($user, 5);
    $rows = pg_dashboard_v2_activity_rows($kinds, 60);

    foreach ($rows as $i => $row) {
        $rows[$i]['when'] = get_relative_time(array('timestamp' => $row['ts'], 'format' => 'plain_text'));
        unset($rows[$i]['ts']);
    }

    $names = array(
        'all'      => lang('All'),
        'content'  => lang('Content'),
        'form'     => lang('Form'),
        'team'     => lang('Team'),
        'security' => lang('Security'),
    );

    // A chip is offered only when one of its kinds is open to the person.
    $open = array('all' => true);

    foreach (array_keys($kinds) as $kind) {
        $chip = pg_dashboard_v2_activity_chip($kind);

        if ($chip !== '') {
            $open[$chip] = true;
        }
    }

    $chips = array();

    foreach ($names as $key => $label) {
        if (isset($open[$key])) {
            $chips[] = array('key' => $key, 'label' => $label);
        }
    }

    return array('status' => 'success', 'data' => array(
        'chips'       => $chips,
        'chips_label' => lang('Filter activity'),
        'rows'        => $rows,
        'shown'       => 5,
        'none'        => lang('Nothing of this kind lately.'),
        'empty'       => empty($rows) ? lang('No activity recorded yet.') : '',
        'url'         => ((int) $user['role'] < 3) ? 'view_log.php' : '',
        'link'        => lang('Open all activity'),
    ));
}
