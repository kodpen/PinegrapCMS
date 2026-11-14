<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Communication › Automatic sends (messages_automatic.php): the automation
 * flows whose boxes send a message (Send an e-mail, Send an SMS, Send a
 * WhatsApp message), with their state, start and sends; the quick setup's
 * ready recipes that send a message, each a link that opens the quick setup
 * on the Flows screen with the recipe chosen; and the campaign profiles
 * still running until they are turned into flows.
 *
 * The pure parts come first (tests/messages_automatic_test.php); the reads
 * follow and load the Workspace's flow files only when called.
 *
 * Loaded where it is needed (require_once), not by functions.php.
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
 * How many of a flow's newest runs are read to count the runs of today: a
 * busy flow says "999+" rather than scanning its whole history.
 */
define('PG_MESSAGES_AUTOMATIC_TODAY_CAP', 1000);

/**
 * The flow boxes that send a message, in the Channels tab's order, with
 * the channel's English name, its icon and its tone.
 *
 * @return array kind => label, icon, tone
 */
function pg_messages_send_kinds()
{
    return array(
        'email'    => array('label' => 'E-mail', 'icon' => 'bi-envelope', 'tone' => 'blue'),
        'sms'      => array('label' => 'SMS', 'icon' => 'bi-chat-dots', 'tone' => 'amber'),
        'whatsapp' => array('label' => 'WhatsApp', 'icon' => 'bi-whatsapp', 'tone' => 'teal'),
    );
}

/**
 * How many boxes of each sending kind a flow graph holds (pure).
 *
 * @param array $graph a normalized graph (nodes, each with kind)
 * @return array kind => count, in pg_messages_send_kinds() order, only kinds present
 */
function pg_messages_graph_sends($graph)
{
    $counts = array();
    $nodes = (is_array($graph) && isset($graph['nodes']) && is_array($graph['nodes'])) ? $graph['nodes'] : array();

    foreach ($nodes as $node) {
        $kind = is_array($node) ? (string) ($node['kind'] ?? '') : '';
        $counts[$kind] = ($counts[$kind] ?? 0) + 1;
    }

    $out = array();

    foreach (array_keys(pg_messages_send_kinds()) as $kind) {
        if (!empty($counts[$kind])) {
            $out[$kind] = $counts[$kind];
        }
    }

    return $out;
}

/**
 * The sends of a flow in words: the channels it sends on, or how many
 * messages when it sends on one channel more than once.
 *
 * @param array $sends pg_messages_graph_sends()
 * @return string
 */
function pg_messages_sends_words($sends)
{
    $kinds = pg_messages_send_kinds();
    $sends = array_intersect_key((array) $sends, $kinds);

    if (empty($sends)) {
        return '';
    }

    if (count($sends) === 1) {
        $kind = key($sends);
        $n = (int) current($sends);

        if ($n > 1) {
            $plural = array('email' => '{var:1} e-mails', 'sms' => '{var:1} SMS messages', 'whatsapp' => '{var:1} WhatsApp messages');

            return lang(array('string' => $plural[$kind], 'vars' => array($n)));
        }
    }

    return implode(', ', array_map(function ($kind) use ($kinds) {
        return lang($kinds[$kind]['label']);
    }, array_keys($sends)));
}

/**
 * The flows that send a message, live first, then paused, then drafts,
 * each group in the order given (pure).
 *
 * @param array[] $flows  ws_flows rows (id, status, …)
 * @param array   $graphs flow id => graph (ws_flows_start_graphs())
 * @return array[] each flow, sends
 */
function pg_messages_automatic_rows($flows, $graphs)
{
    $groups = array('live' => array(), 'paused' => array(), 'draft' => array());

    foreach ((array) $flows as $flow) {
        $id = (int) ($flow['id'] ?? 0);
        $sends = isset($graphs[$id]) ? pg_messages_graph_sends($graphs[$id]) : array();

        if (empty($sends)) {
            continue;
        }

        $status = (string) ($flow['status'] ?? '');
        $groups[isset($groups[$status]) ? $status : 'draft'][] = array('flow' => $flow, 'sends' => $sends);
    }

    return array_merge($groups['live'], $groups['paused'], $groups['draft']);
}

/**
 * A flow's state as a look A pill: its English label and tone.
 *
 * @param string $status live, paused or draft
 * @return array label, tone (the class pg_messages_sends_pill() takes)
 */
function pg_messages_automatic_status($status)
{
    if ($status === 'live') {
        return array('label' => 'Live', 'tone' => 'theme-success');
    }

    if ($status === 'paused') {
        return array('label' => 'Paused', 'tone' => 'theme-warning');
    }

    return array('label' => 'Draft', 'tone' => '');
}

/**
 * Does a recipe's step list hold a box that sends a message, held steps
 * included (pure)?
 *
 * @param array $steps
 * @return bool
 */
function pg_messages_steps_send($steps)
{
    $kinds = pg_messages_send_kinds();

    foreach ((array) $steps as $step) {
        if (!is_array($step)) {
            continue;
        }

        if (isset($kinds[(string) ($step['kind'] ?? '')]) || pg_messages_steps_send($step['steps'] ?? array())) {
            return true;
        }
    }

    return false;
}

/**
 * The quick setup's recipes that send a message and that the person can
 * start: each with the start key the dialog opens on, the first of its
 * fits the catalogue offers them (pure). A recipe for any start about a
 * record names no start of its own and is left out: the dialog asks the
 * start first.
 *
 * @param array[] $recipes the catalogue's recipes (key, title, icon, fits, steps)
 * @param array   $starts  the catalogue's starts, by key
 * @return array[] the recipes, each with start
 */
function pg_messages_send_recipes($recipes, $starts)
{
    $out = array();

    foreach ((array) $recipes as $recipe) {
        if (!is_array($recipe) || !pg_messages_steps_send($recipe['steps'] ?? array())) {
            continue;
        }

        foreach ((array) ($recipe['fits'] ?? array()) as $fit) {
            if (is_string($fit) && ($fit !== 'record') && isset($starts[$fit])) {
                $out[] = $recipe + array('start' => $fit);
                break;
            }
        }
    }

    return $out;
}

/**
 * A recipe drawn as a chain of tiles: its start (the recipe's icon on
 * teal), then each top-level step's kind (pure).
 *
 * @param array $recipe
 * @return array[] icon, tone
 */
function pg_messages_recipe_chain($recipe)
{
    $map = array(
        'delay'     => array('bi-hourglass-split', 'gray'),
        'notify'    => array('bi-bell', 'gray'),
        'task_open' => array('bi-check2-square', 'violet'),
        'task'      => array('bi-check2-square', 'violet'),
        'approval'  => array('bi-person-check', 'violet'),
        'change'    => array('bi-pencil-square', 'gray'),
        'condition' => array('bi-signpost-split', 'gray'),
    );

    foreach (pg_messages_send_kinds() as $kind => $send) {
        $map[$kind] = array($send['icon'], $send['tone']);
    }

    $chain = array(array('icon' => (string) ($recipe['icon'] ?? 'bi-lightning'), 'tone' => 'teal'));

    foreach ((array) ($recipe['steps'] ?? array()) as $step) {
        $tile = $map[is_array($step) ? (string) ($step['kind'] ?? '') : ''] ?? array('bi-dot', 'gray');
        $chain[] = array('icon' => $tile[0], 'tone' => $tile[1]);
    }

    return $chain;
}

/**
 * The address that opens the quick setup on the Flows screen: on a
 * recipe, or on its first stage.
 *
 * @param string $recipe a recipe key, '' for none
 * @return string
 */
function pg_messages_quick_url($recipe)
{
    $recipe = (string) $recipe;

    return 'workspace_flows.php?quick=' . (preg_match('/^[a-z0-9_]{1,64}$/', $recipe) ? $recipe : '1');
}

/**
 * The runs of today as the list shows them: "999+" at the cap.
 *
 * @param int $count
 * @param int $cap
 * @return string
 */
function pg_messages_automatic_today($count, $cap)
{
    $count = (int) $count;

    return ($count >= (int) $cap) ? ((int) $cap - 1) . '+' : (string) $count;
}

/**
 * The list's columns.
 *
 * @return array key => spec, as pg_list_table_printed() takes them
 */
function pg_messages_automatic_columns()
{
    return array(
        'name'    => array('heading' => lang('Name')),
        'start'   => array('heading' => lang('Starts')),
        'sends'   => array('heading' => lang('Channel')),
        'status'  => array('heading' => lang('Status'), 'kind' => 'badge'),
        'today'   => array('heading' => lang('Runs today')),
        'changed' => array('heading' => lang('Last Modified'), 'kind' => 'date'),
    );
}

/**
 * A flow's channels as their tiles, then the words.
 *
 * @param array $sends pg_messages_graph_sends()
 * @return string markup
 */
function pg_messages_sends_cell_html($sends)
{
    $kinds = pg_messages_send_kinds();
    $tiles = '';

    foreach (array_keys($sends) as $kind) {
        $tiles .= '<span class="pg-icon-tile pg-tone-' . h($kinds[$kind]['tone']) . '" title="' . h(lang($kinds[$kind]['label'])) . '" aria-hidden="true"><span class="bi ' . h($kinds[$kind]['icon']) . '"></span></span>';
    }

    return '<span class="pg-messages-sends">' . $tiles . '<span>' . h(pg_messages_sends_words($sends)) . '</span></span>';
}

/**
 * The recipes card: the ready recipes that send a message, each a link
 * that opens the quick setup with it, and the quick setup's own button.
 *
 * @param array[] $recipes pg_messages_send_recipes()
 * @return string '' without a recipe
 */
function pg_messages_recipes_html($recipes)
{
    if (empty($recipes)) {
        return '';
    }

    $cards = '';

    foreach ($recipes as $recipe) {
        $chain = '';

        foreach (pg_messages_recipe_chain($recipe) as $index => $tile) {
            $chain .= ($index ? '<span class="bi bi-chevron-right pg-text-3" aria-hidden="true"></span>' : '')
                . '<span class="pg-icon-tile pg-tone-' . h($tile['tone']) . '" aria-hidden="true"><span class="bi ' . h($tile['icon']) . '"></span></span>';
        }

        $cards .= '<a class="pg-card-inner pg-messages-recipe" href="' . h(pg_messages_quick_url((string) $recipe['key'])) . '">'
            . '<span class="pg-messages-recipe-title">' . h((string) $recipe['title']) . '</span>'
            . '<span class="pg-messages-recipe-chain">' . $chain . '</span></a>';
    }

    return '<section class="pg-card pg-messages-card mb-5">'
        . '<div class="pg-messages-card-head"><h2 class="pg-title-section">' . h(lang('Ready recipes')) . '</h2>'
        . '<a class="btn btn-sm btn-outline theme-secondary rounded-pill px-4" href="' . h(pg_messages_quick_url('')) . '"><i class="bi bi-magic me-2" aria-hidden="true"></i>' . h(lang('Quick setup')) . '</a></div>'
        . '<p class="pg-text-2 mb-3">' . h(lang('Start from a common send; the quick setup asks for the rest.')) . '</p>'
        . '<div class="pg-messages-recipes">' . $cards . '</div></section>';
}

/**
 * The campaign profiles still running for the person: enabled ones, a
 * role 3 user's own only.
 *
 * @param array $user
 * @return int
 */
function pg_messages_profiles_running($user)
{
    $where = "enabled = '1'";

    if ((int) ($user['role'] ?? 3) >= 3) {
        $where .= " AND created_user_id = '" . (int) ($user['id'] ?? 0) . "'";
    }

    return (int) db_value("SELECT COUNT(*) FROM email_campaign_profiles WHERE " . $where);
}

/**
 * How many times each live or paused flow started today, from its newest
 * runs (at most PG_MESSAGES_AUTOMATIC_TODAY_CAP read per flow, on the
 * runs' flow index).
 *
 * @param int[] $ids
 * @param int   $since the day's start, Unix seconds
 * @return array flow id => count
 */
function pg_messages_automatic_runs_today($ids, $since)
{
    $out = array();

    foreach (array_slice(array_values(array_unique(array_map('intval', (array) $ids))), 0, 200) as $id) {
        if ($id <= 0) {
            continue;
        }

        $out[$id] = (int) db_value("SELECT COUNT(*) FROM (SELECT started_at FROM ws_flow_runs WHERE flow_id = '" . $id . "'
            ORDER BY id DESC LIMIT " . PG_MESSAGES_AUTOMATIC_TODAY_CAP . ") AS newest WHERE started_at >= '" . (int) $since . "'");
    }

    return $out;
}

/**
 * The list of flows that send a message for the person, on the list table
 * driver (printed whole). The caller has checked pg_flows_user_may().
 *
 * @param array $user   validate_user()
 * @param array $levels the kept sort levels (pg_list_state())
 * @return array source, page, count, quick (the person builds flows), recipes
 */
function pg_messages_automatic_list($user, $levels)
{
    require_once(PG_FUNCTIONS_DIR . '/includes/workspace/bootstrap.php');

    $viewer = ws_viewer($user);
    $flows = ws_flows_with_converted($viewer, ws_flows_list($viewer));
    $rows = empty($flows) ? array() : pg_messages_automatic_rows($flows, ws_flows_start_graphs($flows));
    $listed = array_map(function ($row) {
        return $row['flow'];
    }, $rows);
    $starts = empty($listed) ? array() : ws_flows_starts($viewer, $listed);
    $counted = array();

    foreach ($rows as $row) {
        if ((string) $row['flow']['status'] !== 'draft') {
            $counted[] = (int) $row['flow']['id'];
        }
    }

    $today = pg_messages_automatic_runs_today($counted, strtotime('today'));
    $list_rows = array();

    foreach ($rows as $row) {
        $flow = $row['flow'];
        $id = (int) $flow['id'];
        $status = pg_messages_automatic_status((string) $flow['status']);
        $count = $today[$id] ?? null;
        $name = ((string) $flow['name'] !== '') ? h((string) $flow['name']) : '[' . h(lang('No name')) . ']';

        $list_rows[] = array(
            'id' => $id,
            'cells' => array(
                'name' => pg_list_cell('<a href="workspace_flows.php?flow=' . $id . '" data-pg-open>' . $name . '</a>'),
                'start' => pg_list_cell(h((string) ($starts[$id]['text'] ?? ''))),
                'sends' => pg_list_cell(pg_messages_sends_cell_html($row['sends']), 'align-middle', array('data-pg-sort-value' => array_sum($row['sends']))),
                'status' => pg_list_cell(pg_messages_sends_pill(lang($status['label']), $status['tone']), 'align-middle', array('data-pg-sort-value' => (int) array_search((string) $flow['status'], array('live', 'paused', 'draft'), true))),
                'today' => pg_list_cell(($count === null) ? '—' : h(pg_messages_automatic_today($count, PG_MESSAGES_AUTOMATIC_TODAY_CAP)), 'align-middle text-end', array('data-pg-sort-value' => (int) $count)),
                'changed' => pg_list_table_when_cell($flow['updated_at'], ''),
            ),
        );
    }

    $list = pg_list_table_printed(array(
        'list' => 'messages_automatic',
        'columns' => pg_messages_automatic_columns(),
        'levels' => $levels,
        'rows' => $list_rows,
    ));

    $quick = ws_flow_quick_for($viewer);
    $list['count'] = count($list_rows);
    $list['quick'] = is_array($quick);
    $list['recipes'] = $quick ? pg_messages_send_recipes($quick['recipes'] ?? array(), $quick['starts'] ?? array()) : array();

    return $list;
}
