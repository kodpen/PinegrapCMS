<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The Security Scan's run and its section in Site Settings > Firewall and
 * HTTPS: one run joins the file scan (security_scan_files.php), the stored
 * content pass (security_scan_content.php) and the accounts and settings
 * rules (security_scan_accounts.php), resumes where its time budget ended,
 * and keeps its last run in one config_text row. The section lists the
 * findings by severity with their place, reason and what to do, and carries a
 * person's Quarantine, Restore, Ignore and Reopen. The scan itself never
 * moves or blocks anything; Quarantine is a person's click.
 *
 * Everything above the "Disk and database" line is pure but for lang().
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

// The config_text row of the joined run: its phase, the content cursor, the
// last finished run.
define('PG_SECURITY_SCAN_STATE', 'security_scan');

// The folder Quarantine moves a file to, relative to the software folder. It
// is skipped by the walk (pg_security_walk_skipped()) and never served.
define('PG_SECURITY_QUARANTINE_FOLDER', 'data/quarantine');

// The section lists at most this many findings.
define('PG_SECURITY_SECTION_MAX', 300);

/**
 * A severity's pill: words and tone.
 *
 * @param mixed $severity
 * @return array words, tone class
 */
function pg_security_severity_face($severity)
{
    switch ((int) $severity) {
        case PG_SECURITY_SEVERITY_CRITICAL:
            return array(lang('Critical'), 'pg-tone-rose');
        case PG_SECURITY_SEVERITY_HIGH:
            return array(lang('High'), 'pg-tone-amber');
        case PG_SECURITY_SEVERITY_MEDIUM:
            return array(lang('Medium'), 'pg-tone-blue');
    }

    return array(lang('Low'), 'pg-tone-gray');
}

/**
 * May Quarantine move the file of a finding of this kind? Only a file that
 * looks planted: a server settings file stays, since moving the root
 * .htaccess away could take the site down.
 *
 * @param string $kind
 * @return bool
 */
function pg_security_quarantinable($kind)
{
    return in_array((string) $kind, array('file_php_unknown', 'file_upload_executable', 'file_picture_php', 'file_shell_mark'), true);
}

/**
 * The name a quarantined file is kept under: the place's hash with an
 * extension no server runs. Every finding of one place shares it.
 *
 * @param string $place
 * @return string '' when the place is not a file place
 */
function pg_security_quarantine_name($place)
{
    $place = pg_security_place_path($place);

    return ($place === '') ? '' : sha1($place) . '.quarantine';
}

/**
 * The actions a finding's row offers, in order.
 *
 * @param array $row kind, state
 * @return string[]
 */
function pg_security_section_actions(array $row)
{
    $actions = array();

    foreach (array('quarantine', 'ignore', 'restore', 'reopen') as $action) {
        if (pg_security_state_after($row['state'], $action) === null) {
            continue;
        }

        if (($action === 'quarantine') && !pg_security_quarantinable($row['kind'])) {
            continue;
        }

        $actions[] = $action;
    }

    return $actions;
}

/**
 * The findings in the section's order: open ones first, worst and newest
 * first; then quarantined, then ignored. Gone ones are left out.
 *
 * @param array $rows
 * @return array
 */
function pg_security_section_sort(array $rows)
{
    $rank = array(PG_SECURITY_STATE_OPEN => 0, PG_SECURITY_STATE_QUARANTINED => 1, PG_SECURITY_STATE_IGNORED => 2);
    $rows = array_values(array_filter($rows, function ($row) use ($rank) {
        return isset($rank[(int) $row['state']]);
    }));

    usort($rows, function ($a, $b) use ($rank) {
        return array($rank[(int) $a['state']], -(int) $a['severity'], -(int) $a['last_seen'], (int) $a['id'])
            <=> array($rank[(int) $b['state']], -(int) $b['severity'], -(int) $b['last_seen'], (int) $b['id']);
    });

    return $rows;
}

/**
 * Open findings by severity, worst first.
 *
 * @param array $rows
 * @return array<int, int>
 */
function pg_security_section_open_counts(array $rows)
{
    $counts = array(PG_SECURITY_SEVERITY_CRITICAL => 0, PG_SECURITY_SEVERITY_HIGH => 0, PG_SECURITY_SEVERITY_MEDIUM => 0, PG_SECURITY_SEVERITY_LOW => 0);

    foreach ($rows as $row) {
        if (((int) $row['state'] === PG_SECURITY_STATE_OPEN) && isset($counts[(int) $row['severity']])) {
            $counts[(int) $row['severity']]++;
        }
    }

    return $counts;
}

/**
 * The parts of one run, in order.
 *
 * @return string[]
 */
function pg_security_scan_phases()
{
    return array('files', 'content', 'accounts');
}

/**
 * A fresh run state, or the stored one with its defaults filled in.
 *
 * @param mixed $stored Decoded JSON of the config_text row
 * @return array
 */
function pg_security_scan_state($stored)
{
    $state = array(
        'started' => 0,
        'phase' => '',
        'content' => null,
        'files' => 0,
        'records' => 0,
        'last_finished' => 0,
        'last_files' => 0,
        'last_records' => 0,
    );

    if (is_array($stored)) {
        foreach ($state as $key => $value) {
            if (array_key_exists($key, $stored)) {
                $state[$key] = is_int($value) ? (int) $stored[$key] : $stored[$key];
            }
        }
    }

    if (!is_string($state['phase']) || (($state['started'] > 0) && !in_array($state['phase'], pg_security_scan_phases(), true))) {
        $state['phase'] = ($state['started'] > 0) ? 'files' : '';
    }

    return $state;
}

/**
 * What a running scan is doing, in words; '' when none runs.
 *
 * @param array $state From pg_security_scan_state()
 * @return string
 */
function pg_security_scan_progress(array $state)
{
    if ((int) $state['started'] === 0) {
        return '';
    }

    $number = function ($value) {
        return function_exists('pg_format_number') ? pg_format_number((int) $value, 0) : number_format((int) $value);
    };

    switch ($state['phase']) {
        case 'content':
            return lang(array('string' => 'Reading stored content: {var:1} records so far', 'vars' => $number($state['records'])));
        case 'accounts':
            return lang('Checking accounts and settings');
    }

    return lang(array('string' => 'Checking files: {var:1} so far', 'vars' => $number($state['files'])));
}

/**
 * The reason a finding is shown with and what to do about it, in the
 * reader's language, as plain text (the section escapes it).
 *
 * @param string $kind
 * @param string $detail The finding's detail
 * @return array reason, advice ('' for a kind no rule knows)
 */
function pg_security_rule_text($kind, $detail)
{
    $kind = (string) $kind;

    if (array_key_exists($kind, pg_security_file_kinds())) {
        return array('reason' => pg_security_file_reason($kind), 'advice' => pg_security_file_advice($kind));
    }

    $content = pg_security_content_rule_text($kind);

    if (is_array($content)) {
        return array('reason' => $content['reason'], 'advice' => $content['what_to_do']);
    }

    if (in_array($kind, pg_security_accounts_kinds(), true)) {
        return array('reason' => pg_security_accounts_reason($kind, $detail), 'advice' => pg_security_accounts_advice($kind));
    }

    return array('reason' => '', 'advice' => '');
}

/**
 * The detail worth showing under a finding: a stored-content finding's hosts
 * and fields, a server settings file's new or changed. The other kinds keep
 * JSON facts their reason already tells.
 *
 * @param string $kind
 * @param string $detail
 * @return string
 */
function pg_security_section_detail($kind, $detail)
{
    $kind = (string) $kind;
    $detail = (string) $detail;

    if (array_key_exists($kind, pg_security_content_kinds())) {
        return $detail;
    }

    if ($kind === 'file_server_config') {
        return ($detail === 'new') ? lang('New since the first scan') : lang('Changed since the first scan');
    }

    return '';
}

// ---------------------------------------------------------------------------
// Disk and database
// ---------------------------------------------------------------------------

/**
 * Mark a finished part's findings that the run did not see as gone (open and
 * ignored ones; pg_security_state_unseen()).
 *
 * @param string[] $kinds
 * @param int $started
 * @return void
 */
function pg_security_scan_close_kinds(array $kinds, $started)
{
    if (empty($kinds)) {
        return;
    }

    db_q(
        "UPDATE security_finding SET state = ? WHERE kind IN (" . implode(', ', array_fill(0, count($kinds), '?')) . ") AND state IN (?, ?) AND last_seen < ?",
        array_merge(array(PG_SECURITY_STATE_GONE), array_values($kinds), array(PG_SECURITY_STATE_OPEN, PG_SECURITY_STATE_IGNORED, (int) $started))
    );
}

/**
 * The stored run state.
 *
 * @return array
 */
function pg_security_scan_stored_state()
{
    return pg_security_scan_state(json_decode(pg_config_text(PG_SECURITY_SCAN_STATE), true));
}

/**
 * Work on one full scan for at most $budget seconds, from where the last
 * call stopped: the files, then the stored content, then the accounts and
 * settings. One call at a time: a second one (the scheduled task beside
 * Scan now) answers busy at once.
 *
 * @param int $budget Seconds
 * @return array done (bool), busy (bool), state (the run state after the call)
 */
function pg_security_scan_run($budget)
{
    $result = array('done' => false, 'busy' => false, 'state' => pg_security_scan_state(null));

    if (!pg_security_finding_ready()) {
        $result['done'] = true;

        return $result;
    }

    if ((int) db_value("SELECT GET_LOCK('pg_security_scan', 0)") !== 1) {
        $result['busy'] = true;
        $result['state'] = pg_security_scan_stored_state();

        return $result;
    }

    $state = pg_security_scan_stored_state();
    $now = time();
    $deadline = microtime(true) + max(1, (int) $budget);

    if ((int) $state['started'] === 0) {
        $state['started'] = $now;
        $state['phase'] = 'files';
        $state['content'] = null;
        $state['files'] = 0;
        $state['records'] = 0;
    }

    if ($state['phase'] === 'files') {
        $files = pg_security_files_run(max(1, (int) $budget));
        $state['files'] += (int) $files['files'];

        if ($files['done']) {
            $state['phase'] = 'content';
        }
    }

    if (($state['phase'] === 'content') && (microtime(true) < $deadline)) {
        $pass = pg_security_content_pass($state['content'], $deadline, $now);
        $state['content'] = $pass['cursor'];
        $state['records'] += (int) $pass['read'];

        if ($pass['done']) {
            $state['phase'] = 'accounts';
        }
    }

    if (($state['phase'] === 'accounts') && (microtime(true) < $deadline)) {
        foreach (pg_security_accounts_scan($now) as $sighting) {
            pg_security_finding_record($sighting, $now);
        }

        pg_security_scan_close_kinds(pg_security_accounts_kinds(), (int) $state['started']);

        $state['last_finished'] = $now;
        $state['last_files'] = (int) $state['files'];
        $state['last_records'] = (int) $state['records'];
        $state['started'] = 0;
        $state['phase'] = '';
        $state['content'] = null;
        $state['files'] = 0;
        $state['records'] = 0;
        $result['done'] = true;
    }

    pg_config_text_save(PG_SECURITY_SCAN_STATE, json_encode($state));
    db_value("SELECT RELEASE_LOCK('pg_security_scan')");

    $result['state'] = $state;

    return $result;
}

/**
 * The findings the section lists, in its order.
 *
 * @return array
 */
function pg_security_section_rows()
{
    if (!pg_security_finding_ready()) {
        return array();
    }

    $rows = db_items(
        "SELECT id, kind, place, severity, state, detail, note, acted_at, first_seen, last_seen FROM security_finding
        WHERE state <> '" . PG_SECURITY_STATE_GONE . "' ORDER BY state, severity DESC, last_seen DESC LIMIT " . PG_SECURITY_SECTION_MAX
    );

    return pg_security_section_sort(is_array($rows) ? $rows : array());
}

/**
 * The quarantine folder on disk, made with what keeps it from being served
 * the first time it is needed.
 *
 * @return string '' when it cannot be made
 */
function pg_security_quarantine_folder()
{
    $folder = rtrim(PG_FUNCTIONS_DIR, '/\\') . '/' . PG_SECURITY_QUARANTINE_FOLDER;

    if (!is_dir($folder) && !@mkdir($folder, 0750, true)) {
        return '';
    }

    if (!is_file($folder . '/.htaccess')) {
        @file_put_contents($folder . '/.htaccess', "Require all denied\n<IfModule !mod_authz_core.c>\ndeny from all\n</IfModule>\n");
    }

    if (!is_file($folder . '/index.html')) {
        @file_put_contents($folder . '/index.html', '');
    }

    return $folder;
}

/**
 * Move the files of every open, quarantinable finding of one place, or move
 * one back. Returns the error sentence, '' on success.
 *
 * @param array $row The finding
 * @param string $action quarantine or restore
 * @return string
 */
function pg_security_quarantine_move(array $row, $action)
{
    if (!pg_security_quarantinable($row['kind'])) {
        return lang('This finding is not a file that can be moved.');
    }

    $path = pg_security_place_disk_path($row['place']);
    $folder = pg_security_quarantine_folder();

    if (($path === '') || ($folder === '')) {
        return lang('The quarantine folder could not be made. Check the permissions of the data folder.');
    }

    $kept = $folder . '/' . pg_security_quarantine_name($row['place']);

    if ($action === 'quarantine') {
        if (!is_file($path) || is_link($path)) {
            return lang('The file is no longer at its place. Run a scan to update the list.');
        }

        return @rename($path, $kept) ? '' : lang('The file could not be moved. Check the permissions of its folder.');
    }

    if (file_exists($path)) {
        return lang('A file is at this place again, so nothing was moved back over it.');
    }

    if (!is_file($kept)) {
        return lang('The quarantined file is missing.');
    }

    if (!is_dir(dirname($path))) {
        return lang('The folder the file came from is gone.');
    }

    return @rename($kept, $path) ? '' : lang('The file could not be moved back. Check the permissions of its folder.');
}

/**
 * A person's action on a finding: Quarantine, Restore, Ignore (with a note)
 * or Reopen. Quarantine and Restore move every finding of the same place.
 *
 * @param int $id
 * @param string $action quarantine, restore, ignore, reopen
 * @param mixed $note The Ignore note
 * @param int $user_id
 * @return array ok (bool), message
 */
function pg_security_finding_act($id, $action, $note, $user_id)
{
    $row = pg_security_finding_ready() ? db_item("SELECT * FROM security_finding WHERE id = '" . (int) $id . "'") : null;

    if (!is_array($row)) {
        return array('ok' => false, 'message' => lang('This finding is no longer listed. Reload the page.'));
    }

    $to = pg_security_state_after($row['state'], (string) $action);

    if (($to === null) || !in_array($action, pg_security_section_actions($row), true)) {
        return array('ok' => false, 'message' => lang('This finding has changed since the page was drawn. Reload the page.'));
    }

    $now = time();

    if (($action === 'quarantine') || ($action === 'restore')) {
        $error = pg_security_quarantine_move($row, $action);

        if ($error !== '') {
            return array('ok' => false, 'message' => $error);
        }

        $kinds = array_values(array_filter(array_keys(pg_security_file_kinds()), 'pg_security_quarantinable'));

        db_q(
            "UPDATE security_finding SET state = ?, acted_by = ?, acted_at = ? WHERE place = ? AND state = ? AND kind IN (" . implode(', ', array_fill(0, count($kinds), '?')) . ")",
            array_merge(array($to, (int) $user_id, $now, (string) $row['place'], (int) $row['state']), $kinds)
        );

        log_activity(($action === 'quarantine' ? 'quarantined the file ' : 'restored the quarantined file ') . $row['place']);

        return array('ok' => true, 'message' => ($action === 'quarantine')
            ? lang(array('string' => '{var:1} was moved to the quarantine folder.', 'vars' => $row['place']))
            : lang(array('string' => '{var:1} is back at its place.', 'vars' => $row['place'])));
    }

    $note = ($action === 'ignore') ? pg_security_note_clean($note) : (string) $row['note'];

    db_q(
        "UPDATE security_finding SET state = ?, note = ?, acted_by = ?, acted_at = ? WHERE id = ?",
        array($to, $note, (int) $user_id, $now, (int) $row['id'])
    );

    log_activity(($action === 'ignore' ? 'ignored the security finding ' : 'reopened the security finding ') . $row['kind'] . ' at ' . $row['place']);

    return array('ok' => true, 'message' => ($action === 'ignore') ? lang('The finding is ignored.') : lang('The finding is open again.'));
}

/**
 * The last run's line: when it finished and what it read, or what a running
 * scan is doing.
 *
 * @param array $state From pg_security_scan_state()
 * @return string HTML
 */
function pg_security_section_run_line(array $state)
{
    if ((int) $state['started'] > 0) {
        return h(pg_security_scan_progress($state));
    }

    if ((int) $state['last_finished'] === 0) {
        return h(lang('No scan has run yet. The scheduled task runs one each day.'));
    }

    return lang(array(
        'string' => 'Last scan {var:1}: {var:2} files and {var:3} records.',
        'vars' => array(
            '<span title="' . h(get_absolute_time(array('timestamp' => (int) $state['last_finished'], 'format' => 'plain_text'))) . '">' . get_relative_time(array('timestamp' => (int) $state['last_finished'], 'mid_sentence' => true)) . '</span>',
            h(pg_format_number((int) $state['last_files'], 0)),
            h(pg_format_number((int) $state['last_records'], 0)),
        ),
    ));
}

/**
 * One finding as a row of the list.
 *
 * @param array $row
 * @return string HTML
 */
function pg_security_section_row(array $row)
{
    $face = pg_security_severity_face($row['severity']);
    $text = pg_security_rule_text($row['kind'], $row['detail']);
    $detail = pg_security_section_detail($row['kind'], $row['detail']);
    $state = (int) $row['state'];
    $id = (int) $row['id'];
    $buttons = '';

    $labels = array(
        'quarantine' => array(lang('Quarantine'), 'bi-archive'),
        'ignore' => array(lang('Ignore'), 'bi-eye-slash'),
        'restore' => array(lang('Restore'), 'bi-arrow-counterclockwise'),
        'reopen' => array(lang('Reopen'), 'bi-arrow-repeat'),
    );

    foreach (pg_security_section_actions($row) as $action) {
        $confirm = ($action === 'quarantine')
            ? ' data-pg-scan-confirm="' . h(lang('The file is moved to a folder the server never runs, and the site goes on without it. Restore puts it back.')) . '"'
            : '';
        $buttons .= '<button type="button" class="pg-button pg-button-quiet pg-button-sm" data-pg-scan-act="' . $action . '" data-pg-scan-id="' . $id . '"' . $confirm . '><i class="bi ' . $labels[$action][1] . '" aria-hidden="true"></i><span class="pg-btn-label">' . h($labels[$action][0]) . '</span></button>';
    }

    $status = '';

    if ($state === PG_SECURITY_STATE_QUARANTINED) {
        $status = lang(array('string' => 'Quarantined {var:1}', 'vars' => get_relative_time(array('timestamp' => (int) $row['acted_at'], 'mid_sentence' => true))));
    } elseif ($state === PG_SECURITY_STATE_IGNORED) {
        $status = lang(array('string' => 'Ignored {var:1}', 'vars' => get_relative_time(array('timestamp' => (int) $row['acted_at'], 'mid_sentence' => true))))
            . (((string) $row['note'] !== '') ? ' · ' . h($row['note']) : '');
    }

    $note = '';

    if (in_array('ignore', pg_security_section_actions($row), true)) {
        $note = '
                <div class="pg-scan-note mt-2" data-pg-scan-note="' . $id . '" hidden>
                    <label class="form-label" for="pg_scan_note_' . $id . '">' . h(lang('Why is it ignored?')) . '</label>
                    <div class="d-flex flex-wrap gap-2">
                        <input type="text" class="form-control form-control-sm pg-f-md" id="pg_scan_note_' . $id . '" maxlength="255"/>
                        <button type="button" class="pg-button pg-button-quiet pg-button-sm" data-pg-scan-act="ignore" data-pg-scan-id="' . $id . '" data-pg-scan-send><span class="pg-btn-label">' . h(lang('Ignore this finding')) . '</span></button>
                    </div>
                </div>';
    }

    return '
            <li class="pg-list-row' . ($state === PG_SECURITY_STATE_OPEN ? '' : ' is-read') . '" data-pg-scan-row="' . $id . '">
                <span class="pg-icon-tile ' . h($face[1]) . '" aria-hidden="true"><span class="bi bi-shield-exclamation"></span></span>
                <div class="pg-list-row-main">
                    <div class="pg-list-row-head"><span class="pg-list-row-title">' . h($text['reason']) . '</span><span class="pg-pill ' . h($face[1]) . '">' . h($face[0]) . '</span></div>
                    <div class="pg-list-row-text"><span class="font-monospace text-break">' . h($row['place']) . '</span>' . ($detail !== '' ? ' · ' . h($detail) : '') . '</div>
                    <div class="pg-list-row-text">' . h(lang('What to do')) . ': ' . h($text['advice']) . '</div>' . ($status !== '' ? '
                    <div class="pg-list-row-text pg-text-meta">' . $status . '</div>' : '') . '
                    <div class="pg-list-row-actions">' . $buttons . '<span class="pg-list-row-place">' . lang(array('string' => 'Found {var:1}', 'vars' => get_relative_time(array('timestamp' => (int) $row['first_seen'], 'mid_sentence' => true)))) . '</span></div>' . $note . '
                </div>
            </li>';
}

/**
 * The list of findings, or the sentence that there are none.
 *
 * @param array $rows From pg_security_section_rows()
 * @return string HTML
 */
function pg_security_section_list(array $rows)
{
    if (empty($rows)) {
        return '<p class="pg-text-2 mb-0">' . h(lang('No findings. The scan reports here what it finds in files, stored content, accounts and settings.')) . '</p>';
    }

    $html = '';

    foreach ($rows as $row) {
        $html .= pg_security_section_row($row);
    }

    return '<ul class="list-unstyled mb-0" id="pg_scan_list_rows">' . $html . '
        </ul>';
}
