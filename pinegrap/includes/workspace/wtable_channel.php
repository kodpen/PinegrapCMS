<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - a workflow table that belongs to a channel. ws_wtables.channel_id
 * (2026.11.0, 11.0.191) names a table's home: 0 for a person's own table,
 * else a public or private channel. Who reaches such a table is answered in
 * wtables.php (ws_wtable_home_access()); here are the move of a table from
 * one home to another, the tables of the channels a person reads, the binned
 * ones they may restore, and the card of the table's screen.
 *
 * Moving a table changes at once who reaches it, so only whoever manages it
 * moves it: the owner of a person's table, the managers of a channel's. Into
 * a channel it keeps who made it; taken out, it becomes the table of the
 * person who takes it, since a table of nobody would reach nobody. Its
 * columns, rows, shares and the canvases that bind it stay as they are.
 *
 * Results are codes, as in wtables.php, for the panel and the API to word.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/* ---------------------------------------------------------------------------
   Rules (pure)
   --------------------------------------------------------------------------- */

/**
 * Does what was sent name the person's own tables as the home, not a
 * channel? 0, an empty value and nothing do.
 *
 * @param mixed $channel_id
 * @return bool
 */
function ws_wtable_home_is_own($channel_id)
{
    return in_array($channel_id, array(null, '', 0, '0'), true);
}

/**
 * Whether a table may be given another home, from what is known about it:
 *   ready    bool   tables can belong to a channel (ws_wtable_home_ready())
 *   access   string the person's access to the table (ws_wtable_access())
 *   same     bool   the home asked for is the one the table has
 *   channel  bool   a channel is asked for, not the person's own tables
 *   writable bool   it is a public or private channel the person may write in
 *   count    int    the tables the home asked for holds now
 * A table the person does not reach is not found; one they reach but do not
 * manage is not theirs to move. The home it has already is no change, and
 * is allowed whatever else holds.
 *
 * @param array $facts
 * @return array|null a refusal (ws_wtable_refusal()), null when allowed
 */
function ws_wtable_home_set_refusal($facts)
{
    if (empty($facts['ready'])) {
        return ws_wtable_refusal('not_ready', '', 503);
    }

    $access = (string) ($facts['access'] ?? '');

    if ($access === '') {
        return ws_wtable_refusal('not_found', '', 404);
    }

    if (!ws_wtable_may($access, 'manage')) {
        return ws_wtable_refusal('forbidden', '', 403);
    }

    if (!empty($facts['same'])) {
        return null;
    }

    if (!empty($facts['channel']) && empty($facts['writable'])) {
        return ws_wtable_refusal('channel_unwritable', 'channel_id');
    }

    if (!ws_wtable_limit_allows('tables', (int) ($facts['count'] ?? 0))) {
        return ws_wtable_refusal('too_many_tables', 'channel_id', 409);
    }

    return null;
}

/* ---------------------------------------------------------------------------
   Moving a table
   --------------------------------------------------------------------------- */

/**
 * Whether the person may move the table to this home: the checks of
 * ws_wtable_home_set(), made on their own so that the API's dry run meets
 * the same refusals.
 *
 * @param array      $viewer
 * @param array|null $table      ws_wtable()
 * @param mixed      $channel_id the channel, or 0 for the person's own tables
 * @return array ok, error, field, status, channel_id (the home asked for), same (it is the home already)
 */
function ws_wtable_home_set_check($viewer, $table, $channel_id)
{
    $access = ws_wtable_access($viewer, $table);
    $own = ws_wtable_home_is_own($channel_id);
    $facts = array('ready' => ws_wtable_home_ready(), 'access' => $access, 'channel' => !$own);
    $target = 0;

    if ($facts['ready'] && ws_wtable_may($access, 'manage')) {
        $home = ws_wtable_home($table);
        $channel = $own ? null : ws_wtable_home_writable($viewer, $channel_id);
        $target = $channel ? (int) $channel['id'] : 0;

        // The home it has is compared by id, so that the table of an
        // archived channel, where nobody writes, still answers "no change".
        $facts['same'] = $own ? ($home === null) : (($home !== null) && is_numeric($channel_id) && ((int) $channel_id === (int) $home['id']));
        $facts['writable'] = ($channel !== null);
        $facts['count'] = ws_wtable_home_count((int) $viewer['id'], $target);
    }

    $refusal = ws_wtable_home_set_refusal($facts);

    if ($refusal !== null) {
        return $refusal;
    }

    return array('ok' => true, 'error' => '', 'field' => '', 'status' => 200, 'channel_id' => !empty($facts['same']) ? (int) ($table['channel_id'] ?? 0) : $target, 'same' => !empty($facts['same']));
}

/**
 * Moves a table into a channel, to another channel, or out of its channel
 * to the person's own tables. From then on the new home's rights answer
 * who reaches it. A share that the move made pointless goes: the one in the
 * channel it now belongs to, or the one with the person who now owns it.
 *
 * @param array      $viewer
 * @param array|null $table
 * @param mixed      $channel_id the channel, or 0 for the person's own tables
 * @return array ok, error, field, status, channel_id, changed
 */
function ws_wtable_home_set($viewer, $table, $channel_id)
{
    $check = ws_wtable_home_set_check($viewer, $table, $channel_id);

    if (!$check['ok'] || $check['same']) {
        return $check + array('changed' => false);
    }

    $table_id = (int) $table['id'];
    $target = (int) $check['channel_id'];
    $from = ws_wtable_home($table);

    db("UPDATE ws_wtables SET channel_id = '" . $target . "'" . (($target === 0) ? ", user_id = '" . (int) $viewer['id'] . "'" : '') . ", updated_at = '" . time() . "'
        WHERE id = '" . $table_id . "'");

    db("DELETE FROM ws_wtable_shares WHERE table_id = '" . $table_id . "' AND "
        . (($target > 0) ? "target_type = 'channel' AND target_id = '" . $target . "'" : "target_type = 'user' AND target_id = '" . (int) $viewer['id'] . "'"));

    log_activity(($target > 0)
        ? lang(array('string' => 'workspace table ({var:1}) was moved to the channel {var:2}', 'vars' => array($table['name'], (string) (ws_wtable_home_channel($target)['name'] ?? ''))))
        : lang(array('string' => 'workspace table ({var:1}) was taken out of the channel {var:2}', 'vars' => array($table['name'], (string) ($from['name'] ?? '')))),
        (string) ($_SESSION['sessionusername'] ?? ''));

    return $check + array('changed' => true);
}

/* ---------------------------------------------------------------------------
   The tables of channels
   --------------------------------------------------------------------------- */

/**
 * The tables that belong to a channel the person reads, the latest changed
 * first, each with its access; binned ones are left out.
 *
 * @param array $viewer
 * @return array[] ws_wtables rows with access
 */
function ws_wtables_of_channels($viewer)
{
    if (!ws_wtable_home_ready() || empty($viewer['member'])) {
        return array();
    }

    $channel_ids = array();

    foreach ((array) db_values("SELECT DISTINCT channel_id FROM ws_wtables WHERE channel_id > 0" . ws_wtable_live_sql()) as $channel_id) {
        $channel = ws_wtable_home_channel((int) $channel_id);

        if ($channel && ws_can_read_channel($viewer, $channel)) {
            $channel_ids[] = (int) $channel_id;
        }
    }

    if (empty($channel_ids)) {
        return array();
    }

    $rows = (array) db_items("SELECT " . ws_wtables_select_sql() . " FROM ws_wtables
        WHERE channel_id IN ('" . implode("','", $channel_ids) . "')" . ws_wtable_live_sql() . " ORDER BY updated_at DESC, id DESC");

    foreach ($rows as $i => $row) {
        $rows[$i]['access'] = ws_wtable_access($viewer, $row);
    }

    return $rows;
}

/**
 * The binned tables of the channels the person may manage, the latest
 * deleted first: the ones they may restore (ws_wtable_restore_check()).
 *
 * @param array $viewer
 * @return array[] ws_wtables rows with access owner
 */
function ws_wtables_binned_of_channels($viewer)
{
    if (!ws_wtable_home_ready() || !ws_wtable_bin_ready() || empty($viewer['member'])) {
        return array();
    }

    $rows = array();

    foreach ((array) db_items("SELECT " . ws_wtables_select_sql() . ", deleted_at, deleted_by
        FROM ws_wtables WHERE channel_id > 0 AND deleted_at > 0 ORDER BY deleted_at DESC, id DESC") as $row) {
        if (ws_wtable_may(ws_wtable_access($viewer, $row), 'manage') && (ws_wtable_home($row) !== null)) {
            $rows[] = $row + array('access' => 'owner');
        }
    }

    return $rows;
}

/**
 * The channels the person may move a table into: the public and private
 * ones they may write in, in the order of their channel list.
 *
 * @param array $viewer
 * @return array[] id, name, kind
 */
function ws_wtable_home_choices($viewer)
{
    $out = array();

    foreach (ws_wtable_home_ready() ? ws_channels_for($viewer) : array() as $channel) {
        if (in_array((string) $channel['kind'], ws_wtable_home_kinds(), true) && empty($channel['archived'])) {
            $out[] = array('id' => (int) $channel['id'], 'name' => (string) $channel['name'], 'kind' => (string) $channel['kind']);
        }
    }

    return $out;
}

/* ---------------------------------------------------------------------------
   The table's screen
   --------------------------------------------------------------------------- */

/**
 * What the screen says when a move is refused: the two answers of its own,
 * else the screen's words for the code (ws_wtable_screen_refusal_text()).
 *
 * @param array $result     ws_wtable_home_set()
 * @param bool  $to_channel a channel was asked for, not the person's own tables
 * @return string
 */
function ws_wtable_home_refusal_text($result, $to_channel)
{
    if ((string) $result['error'] === 'channel_unwritable') {
        return lang('Choose a channel you may write in.');
    }

    if (((string) $result['error'] === 'too_many_tables') && $to_channel) {
        return lang(array('string' => 'That channel has {var:1} tables already, the most a channel may have.', 'vars' => ws_wtable_limits()['tables']));
    }

    return ws_wtable_screen_refusal_text($result);
}

/**
 * The line under a channel table's name: whose it is and what the person
 * may do with it.
 *
 * @param array  $home   ws_wtable_home()
 * @param string $access ws_wtable_access()
 * @return string HTML
 */
function ws_wtable_home_note($home, $access)
{
    $may = array('owner' => lang('You manage it'), 'edit' => lang('Can edit'), 'view' => lang('Can view'));

    return '<p class="small fg-secondary mb-5"><i class="bi ' . (((string) $home['kind'] === 'private') ? 'bi-lock' : 'bi-hash') . ' me-1" aria-hidden="true"></i>'
        . h(lang(array('string' => 'Table of the channel {var:1} · {var:2}', 'vars' => array((string) $home['name'], $may[(string) $access] ?? '')))) . '</p>';
}

/**
 * The "Belongs to" card of a table's screen: where the table belongs, and
 * for whoever manages it the form that moves it. Empty for a person's table
 * seen by somebody it is shared with, and before the step has run.
 *
 * @param array  $viewer
 * @param array  $table    ws_wtable_for(), with access
 * @param string $self_url the screen's address
 * @return string HTML
 */
function ws_wtable_home_card($viewer, $table, $self_url)
{
    $home = ws_wtable_home($table);
    $manage = ws_wtable_may((string) $table['access'], 'manage');

    if (!ws_wtable_home_ready() || (($home === null) && !$manage)) {
        return '';
    }

    $form = '';

    if ($manage) {
        $options = '<option value="0"' . (($home === null) ? ' selected' : '') . '>' . h(lang('My own tables')) . '</option>';
        $listed = false;

        foreach (ws_wtable_home_choices($viewer) as $channel) {
            $here = ($home !== null) && ((int) $home['id'] === $channel['id']);
            $listed = $listed || $here;
            $options .= '<option value="' . $channel['id'] . '"' . ($here ? ' selected' : '') . '>#' . h($channel['name']) . '</option>';
        }

        // An archived home is not in the list of channels to write in.
        if (($home !== null) && !$listed) {
            $options .= '<option value="' . (int) $home['id'] . '" selected>#' . h($home['name']) . '</option>';
        }

        $form = '
                    <form method="post" action="' . h($self_url) . '" class="disable_shortcut mt-5" data-ws-confirm="' . h(lang(array('string' => 'Move the table {var:1}? Who reaches it changes at once.', 'vars' => (string) $table['name']))) . '">
                        ' . get_token_field() . '
                        <input type="hidden" name="wt_action" value="table_home">
                        <input type="hidden" name="wt_table" value="' . (int) $table['id'] . '">
                        <div class="mb-3"><label class="form-label small mb-1" for="ws_wt_home_channel">' . h(lang('Move to')) . '</label>
                            <select class="form-control form-control-sm" name="channel" id="ws_wt_home_channel" data-pg-combobox>' . $options . '</select>
                            <div class="form-text">' . h(lang('In a channel the table is nobody\'s alone: the channel\'s managers decide about it from then on. Its columns, rows and shares stay.')) . '</div></div>
                        <button type="submit" class="btn btn-sm btn-outline theme-primary rounded-pill px-5">' . h(lang('Move')) . '</button>
                    </form>';
    }

    $line = ($home !== null)
        ? '<p class="mb-2"><i class="bi ' . (((string) $home['kind'] === 'private') ? 'bi-lock' : 'bi-hash') . ' me-1 opacity-75" aria-hidden="true"></i>' . h($home['name']) . '</p>'
            . '<p class="small fg-secondary mb-0">' . h(lang('Whoever reads the channel reads this table, whoever may write there changes it, and the channel\'s managers delete, share and move it.')) . '</p>'
        : '<p class="small fg-secondary mb-0">' . h(lang('Your own table: others reach it only through a share.')) . '</p>';

    return '
            <div class="card mb-5" id="ws-wt-home">
                <div class="card-header"><h2 class="h6 mb-0"><i class="bi bi-house me-1" aria-hidden="true"></i>' . h(lang('Belongs to')) . '</h2></div>
                <div class="card-body">
                    ' . $line . '
                    ' . $form . '
                </div>
            </div>';
}
