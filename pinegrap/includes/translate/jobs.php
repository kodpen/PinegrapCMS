<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Front-end translation - "update translations": the jobs, the texts they
 * carry, and the CSV round trip.
 *
 * An update extracts the texts of its scope (one page or the whole site),
 * finds the ones the language has no translation for, and opens a job for
 * the language's engine with exactly those. A server engine works the job
 * here, within a time budget, and the scheduled job finishes what is left; a
 * browser engine is handed the texts by the panel and returns the answers; a
 * queue engine (Claude) is told there is work and fetches the job through the
 * external API. Only texts without a translation are ever sent: pressing the
 * button twice sends nothing the second time.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/** A job nobody has touched for this long is closed as failed. */
define('PG_TR_JOB_STALE', 86400);

/**
 * The owners a scope names: 'all' is every owner; 'page:12' is the page, its
 * SEO fields and what it references.
 *
 * @return array of array(owner_type, owner_id); empty for all
 */
function pg_tr_scope_owners($scope)
{
    if (preg_match('/^page:(\d+)$/', (string) $scope, $m)) {
        return pg_tr_page_owners((int) $m[1]);
    }

    // A group of the Translations screen: every owner of its types.
    if (preg_match('/^group:([a-z]+)$/', (string) $scope, $m)) {
        $groups = pg_tr_owner_groups();
        $owners = array();

        foreach (isset($groups[$m[1]]) ? $groups[$m[1]]['types'] : array('-') as $type) {
            $owners[] = array($type, '*');
        }

        return $owners;
    }

    return array();
}

/**
 * Extracts the scope, so what is pending reflects the pages as they are now.
 */
function pg_tr_scope_extract($scope)
{
    if (preg_match('/^page:(\d+)$/', (string) $scope, $m)) {
        $result = pg_tr_extract_page((int) $m[1]);

        return array('pages' => $result['ok'] ? 1 : 0, 'segments' => $result['segments']);
    }

    if (preg_match('/^group:([a-z]+)$/', (string) $scope, $m)) {
        $result = pg_tr_extract_group($m[1]);

        return array('pages' => 0, 'segments' => $result['segments']);
    }

    return pg_tr_extract_all();
}

/**
 * Opens a job with the given strings as waiting items.
 *
 * @return int job id, 0 when there was nothing to send
 */
function pg_tr_job_create($language, $engine, $scope, $string_ids, $user_id = 0)
{
    $string_ids = array_values(array_unique(array_map('intval', (array) $string_ids)));

    if (!$string_ids) {
        return 0;
    }

    db("INSERT INTO translation_jobs (language, engine, scope, status, total, created_by, created_at)
        VALUES ('" . e($language) . "', '" . e($engine) . "', '" . e($scope) . "', 'queued', '" . count($string_ids) . "', '" . (int) $user_id . "', '" . time() . "')");

    $job_id = (int) mysqli_insert_id(db::$con);

    foreach (array_chunk($string_ids, 500) as $chunk) {
        $values = array();

        foreach ($chunk as $id) {
            $values[] = "('$job_id', '$id')";
        }

        db("INSERT INTO translation_job_items (job_id, string_id) VALUES " . implode(',', $values));
    }

    return $job_id;
}

/**
 * One job's row, or null.
 */
function pg_tr_job_get($job_id)
{
    $row = db_item("SELECT * FROM translation_jobs WHERE id = '" . (int) $job_id . "' LIMIT 1");

    return is_array($row) ? $row : null;
}

/**
 * The waiting items of a job with their strings, oldest first.
 *
 * @return array rows of translation_strings
 */
function pg_tr_job_waiting($job_id, $limit = 100)
{
    $rows = db_items("SELECT s.*
                      FROM translation_job_items i
                      INNER JOIN translation_strings s ON s.id = i.string_id
                      WHERE i.job_id = '" . (int) $job_id . "' AND i.status = 'waiting'
                      ORDER BY s.id
                      LIMIT " . max(1, (int) $limit));

    return is_array($rows) ? $rows : array();
}

/**
 * Records the outcome of one item and keeps the job's counters in step.
 */
function pg_tr_job_item_done($job_id, $string_id, $status, $error = '')
{
    $job_id = (int) $job_id;
    $string_id = (int) $string_id;
    $status = in_array($status, array('done', 'failed', 'skipped'), true) ? $status : 'failed';

    db("UPDATE translation_job_items SET status = '$status', error = '" . e(mb_substr((string) $error, 0, 255)) . "'
        WHERE job_id = '$job_id' AND string_id = '$string_id' AND status = 'waiting'");

    if (mysqli_affected_rows(db::$con) < 1) {
        return;
    }

    if ($status === 'failed') {
        db("UPDATE translation_jobs SET failed_count = failed_count + 1 WHERE id = '$job_id'");
    } else {
        db("UPDATE translation_jobs SET done_count = done_count + 1 WHERE id = '$job_id'");
    }
}

/**
 * Closes a job whose items are all answered. Returns the status it ended in,
 * or '' while items are still waiting.
 */
function pg_tr_job_close_if_complete($job_id)
{
    $job_id = (int) $job_id;
    $waiting = (int) db_value("SELECT COUNT(*) FROM translation_job_items WHERE job_id = '$job_id' AND status = 'waiting'");

    if ($waiting > 0) {
        return '';
    }

    $job = pg_tr_job_get($job_id);

    if (!$job) {
        return '';
    }

    $status = (((int) $job['done_count'] === 0) && ((int) $job['failed_count'] > 0)) ? 'failed' : 'done';

    db("UPDATE translation_jobs SET status = '$status', finished_at = '" . time() . "' WHERE id = '$job_id' AND status IN ('queued', 'sent', 'running')");

    return $status;
}

/**
 * Marks a job failed with a reason; its waiting items are skipped.
 */
function pg_tr_job_fail($job_id, $error)
{
    $job_id = (int) $job_id;

    db("UPDATE translation_job_items SET status = 'skipped' WHERE job_id = '$job_id' AND status = 'waiting'");
    db("UPDATE translation_jobs SET status = 'failed', error = '" . e(mb_substr((string) $error, 0, 2000)) . "', finished_at = '" . time() . "' WHERE id = '$job_id'");
}

/**
 * Cancels a job the operator no longer wants.
 */
function pg_tr_job_cancel($job_id)
{
    $job_id = (int) $job_id;

    db("UPDATE translation_job_items SET status = 'skipped' WHERE job_id = '$job_id' AND status = 'waiting'");
    db("UPDATE translation_jobs SET status = 'cancelled', finished_at = '" . time() . "' WHERE id = '$job_id' AND status IN ('queued', 'sent', 'running')");
}

/**
 * Works a server engine's job for up to $budget seconds. Returns how many
 * items were answered in this call and whether the job is finished.
 *
 * @return array('done' => int, 'failed' => int, 'finished' => bool, 'error' => string)
 */
function pg_tr_job_run_server($job_id, $budget = 20)
{
    $job = pg_tr_job_get($job_id);
    $result = array('done' => 0, 'failed' => 0, 'finished' => false, 'error' => '');

    if (!$job || !in_array($job['status'], array('queued', 'running'), true)) {
        $result['finished'] = true;
        return $result;
    }

    $engine = pg_tr_engine($job['engine']);

    if (!$engine || ($engine['kind'] !== 'server')) {
        return $result;
    }

    if (!$engine['ready']) {
        $result['error'] = $engine['reason'];

        if (!empty($engine['retry'])) {
            // A hold after a failed call: the job keeps its place and the
            // next run tries again.
            db("UPDATE translation_jobs SET error = '" . e(mb_substr($engine['reason'], 0, 2000)) . "' WHERE id = '" . (int) $job_id . "'");
            return $result;
        }

        pg_tr_job_fail($job_id, $engine['reason']);
        $result['finished'] = true;
        return $result;
    }

    db("UPDATE translation_jobs SET status = 'running', claimed_at = '" . time() . "' WHERE id = '" . (int) $job_id . "'");

    // A model call may take most of a minute on its own; the budget is
    // checked between batches, not inside one.
    if (function_exists('set_time_limit')) {
        @set_time_limit(max(60, (int) $budget) + 90);
    }

    $started = microtime(true);
    $source = pg_tr_source_language();
    $language = $job['language'];
    $keep_terms = pg_tr_keep_terms($language);
    // One model call per turn of the loop for the AI engine: a slow model
    // then costs one call per request, a fast one goes round again within
    // the budget.
    $per_batch = ($job['engine'] === 'ai') ? PG_TR_AI_BATCH_ITEMS : 60;
    // The slowest batch so far: a batch that would not end within the
    // budget is left to the next run, so a request stays close to its budget
    // instead of running into the server's or the proxy's time limit.
    $slowest = 0.0;

    while ((microtime(true) - $started + $slowest) < $budget) {
        $batch_started = microtime(true);
        $strings = pg_tr_job_waiting($job_id, $per_batch);

        if (!$strings) {
            break;
        }

        $texts = array();
        $by_id = array();

        foreach ($strings as $string) {
            $texts[(int) $string['id']] = $string['source_text'];
            $by_id[(int) $string['id']] = $string;
        }

        $error = '';
        $answers = array();

        if ($job['engine'] === 'google') {
            $answers = pg_tr_google_translate($texts, $source, $language, $error, $keep_terms);
        } elseif ($job['engine'] === 'ai') {
            $answers = pg_tr_ai_translate($strings, $source, $language, $error);
        }

        if (!$answers && ($error !== '')) {
            // Nothing came back: the key, the quota or the network. The job
            // stops here rather than burning through every batch with the
            // same error; the next run picks it up again.
            db("UPDATE translation_jobs SET error = '" . e(mb_substr($error, 0, 2000)) . "' WHERE id = '" . (int) $job_id . "'");
            $result['error'] = $error;
            break;
        }

        foreach ($by_id as $id => $string) {
            if (!isset($answers[$id])) {
                pg_tr_job_item_done($job_id, $id, 'failed', ($error !== '') ? $error : 'no answer');
                $result['failed']++;
                continue;
            }

            $accepted = pg_tr_accept_result($string, $language, $answers[$id], $job['engine'], (int) $job['created_by']);

            if ($accepted['ok']) {
                pg_tr_job_item_done($job_id, $id, 'done');
                $result['done']++;
            } else {
                pg_tr_job_item_done($job_id, $id, 'failed', $accepted['error']);
                $result['failed']++;
            }
        }

        $slowest = max($slowest, microtime(true) - $batch_started);
    }

    $result['finished'] = (pg_tr_job_close_if_complete($job_id) !== '');

    return $result;
}

/**
 * The scheduled job's turn: works the open server jobs within the budget,
 * tells Claude about the jobs waiting for it, and closes the ones nobody has
 * touched for a day.
 *
 * @return array('jobs' => int, 'done' => int, 'failed' => int, 'queue' => string)
 */
function pg_tr_jobs_run_due($budget = 40)
{
    $summary = array('jobs' => 0, 'done' => 0, 'failed' => 0, 'queue' => '');
    $started = microtime(true);
    $stale = time() - PG_TR_JOB_STALE;

    $old = db_items("SELECT id FROM translation_jobs WHERE status IN ('queued', 'sent', 'running') AND GREATEST(created_at, claimed_at) < '$stale'");

    if (is_array($old)) {
        foreach ($old as $row) {
            pg_tr_job_fail($row['id'], 'stale');
        }
    }

    // A Claude run that was told about a job and never claimed it, or claimed
    // it and went quiet, is not waited for: the job is queued again and the
    // next run is told.
    db("UPDATE translation_jobs SET status = 'queued'
        WHERE engine = 'claude' AND status IN ('sent', 'running') AND claimed_at < '" . (time() - PG_TR_CLAUDE_STALE) . "'");

    $dispatch = pg_tr_claude_dispatch();
    $summary['queue'] = $dispatch['status'];

    $jobs = db_items("SELECT id FROM translation_jobs WHERE status IN ('queued', 'running') AND engine IN ('google', 'ai') ORDER BY id");

    if (!is_array($jobs)) {
        return $summary;
    }

    foreach ($jobs as $row) {
        $left = $budget - (microtime(true) - $started);

        if ($left < 2) {
            break;
        }

        $result = pg_tr_job_run_server($row['id'], $left);
        $summary['jobs']++;
        $summary['done'] += $result['done'];
        $summary['failed'] += $result['failed'];

        if (($result['error'] !== '') && ($result['done'] === 0)) {
            // The same error would meet the next job too.
            break;
        }
    }

    return $summary;
}

/**
 * "Update translations": extract the scope, open a job for what is pending,
 * and hand it on: a server engine's job is worked by the screen's run calls
 * (and the scheduled job), a browser engine's job is handed to the panel,
 * Claude is told there is work.
 *
 * Nothing is translated in this request. Extracting a large site takes a
 * request of its own; translating on top of it ran that request into the
 * server's time limit, and the screen's loop never started.
 *
 * @param string $language
 * @param string $scope    all | page:ID
 * @param int    $user_id
 * @param string $engine   '' = the language's engine
 * @return array
 */
function pg_tr_update($language, $scope, $user_id = 0, $engine = '')
{
    $row = pg_tr_language_row($language);

    if (!$row || ($language === pg_tr_source_language())) {
        return array('ok' => false, 'error' => lang('The language is not one of the site\'s target languages.'));
    }

    $engine = ($engine !== '') ? $engine : (string) $row['engine'];
    $spec = pg_tr_engine($engine);

    if (!$spec || ($engine === 'import')) {
        return array('ok' => false, 'error' => lang('The engine is not known.'));
    }

    if (function_exists('set_time_limit')) {
        @set_time_limit(120);
    }

    $extracted = pg_tr_scope_extract($scope);

    // The cookie window of a language with a language file comes from that
    // file (pg_tr_ui_text()): nothing to send.
    $pending = (($scope === 'group:ui') && pg_tr_ui_has_file($language)) ? array() : pg_tr_pending_string_ids($language, pg_tr_scope_owners($scope));

    $result = array(
        'ok'        => true,
        'engine'    => $engine,
        'extracted' => $extracted,
        'pending'   => count($pending),
        'job_id'    => 0,
        'job'       => null,
        'finished'  => true,
        'error'     => '',
    );

    if (!$pending) {
        return $result;
    }

    if ($spec['kind'] === 'panel') {
        // By hand: nothing to send; the pending texts wait in the editor.
        return $result;
    }

    if (!$spec['ready']) {
        $result['ok'] = false;
        $result['error'] = $spec['reason'];
        return $result;
    }

    $job_id = pg_tr_job_create($language, $engine, $scope, $pending, $user_id);
    $result['job_id'] = $job_id;
    $result['finished'] = false;

    if ($spec['kind'] === 'queue') {
        // The job waits for the routine; busy or held, the scheduled job
        // tells it again later.
        $dispatch = pg_tr_claude_dispatch();
        $result['queue'] = $dispatch['status'];
        $result['error'] = $dispatch['ok'] ? '' : $dispatch['error'];
    } elseif ($spec['kind'] !== 'server') {
        db("UPDATE translation_jobs SET status = 'sent', claimed_at = '" . time() . "' WHERE id = '$job_id'");
    }

    $result['job'] = pg_tr_job_get($job_id);

    return $result;
}

/**
 * Translate on save: extracts the scope once and opens a job for each
 * language's engine with what is pending, without working it here. A job of
 * the same language and scope still waiting is not opened twice.
 *
 * @param array  $languages code => site_languages row
 * @param string $scope
 * @param int    $user_id
 * @return int jobs opened
 */
function pg_tr_auto_update($languages, $scope, $user_id = 0)
{
    $opened = 0;
    $claude = false;

    pg_tr_scope_extract($scope);
    $owners = pg_tr_scope_owners($scope);

    foreach ($languages as $code => $row) {
        $engine = (string) $row['engine'];
        $spec = pg_tr_engine($engine);

        if (!$spec || !$spec['ready']) {
            continue;
        }

        $waiting = (int) db_value("SELECT COUNT(*) FROM translation_jobs
            WHERE language = '" . e($code) . "' AND scope = '" . e($scope) . "' AND status IN ('queued', 'sent')");

        if ($waiting > 0) {
            continue;
        }

        $pending = pg_tr_pending_string_ids($code, $owners);

        if (!$pending) {
            continue;
        }

        pg_tr_job_create($code, $engine, $scope, $pending, $user_id);
        $opened++;

        if ($engine === 'claude') {
            $claude = true;
        }
    }

    if ($claude) {
        pg_tr_claude_dispatch();
    }

    return $opened;
}

/**
 * The next batch of a browser engine's job: the waiting texts split into the
 * runs the browser translates, with the source language it translates from.
 *
 * @return array('job' => row, 'items' => array of array(string_id, format, runs))
 */
function pg_tr_job_package($job_id, $limit = 40)
{
    $job = pg_tr_job_get($job_id);
    $items = array();

    if (!$job) {
        return array('job' => null, 'items' => $items, 'source' => pg_tr_source_language());
    }

    $keep_terms = pg_tr_keep_terms($job['language']);

    foreach (pg_tr_job_waiting($job_id, $limit) as $string) {
        $pieces = pg_tr_runs_split($string['source_text'], $string['format'], $keep_terms);

        $items[] = array(
            'string_id' => (int) $string['id'],
            'format'    => $string['format'],
            'runs'      => pg_tr_runs_only($pieces),
        );
    }

    return array('job' => $job, 'items' => $items, 'source' => pg_tr_source_language());
}

/**
 * Answers from a browser engine: the translated runs of each item are joined
 * back around the fixed pieces, checked and written.
 *
 * @param int    $job_id
 * @param array  $results array of array(string_id, runs => array, error => string)
 * @param int    $user_id
 * @return array('done' => int, 'failed' => int, 'finished' => bool)
 */
function pg_tr_job_results($job_id, $results, $user_id = 0)
{
    $job = pg_tr_job_get($job_id);
    $summary = array('done' => 0, 'failed' => 0, 'finished' => false);

    if (!$job || !in_array($job['status'], array('queued', 'sent', 'running'), true)) {
        $summary['finished'] = true;
        return $summary;
    }

    $keep_terms = pg_tr_keep_terms($job['language']);

    foreach ((array) $results as $item) {
        if (!is_array($item) || empty($item['string_id'])) {
            continue;
        }

        $string_id = (int) $item['string_id'];
        $string = db_item("SELECT * FROM translation_strings WHERE id = '$string_id' LIMIT 1");

        if (!is_array($string)) {
            continue;
        }

        if (!empty($item['error']) || !isset($item['runs']) || !is_array($item['runs'])) {
            pg_tr_job_item_done($job_id, $string_id, 'failed', !empty($item['error']) ? (string) $item['error'] : 'no answer');
            $summary['failed']++;
            continue;
        }

        $pieces = pg_tr_runs_split($string['source_text'], $string['format'], $keep_terms);
        $joined = pg_tr_runs_join($pieces, $item['runs'], $string['format']);

        if ($joined === false) {
            pg_tr_job_item_done($job_id, $string_id, 'failed', 'runs');
            $summary['failed']++;
            continue;
        }

        $accepted = pg_tr_accept_result($string, $job['language'], $joined, $job['engine'], $user_id);

        if ($accepted['ok']) {
            pg_tr_job_item_done($job_id, $string_id, 'done');
            $summary['done']++;
        } else {
            pg_tr_job_item_done($job_id, $string_id, 'failed', $accepted['error']);
            $summary['failed']++;
        }
    }

    $summary['finished'] = (pg_tr_job_close_if_complete($job_id) !== '');

    return $summary;
}

/* ---------------------------------------------------------------------------
   CSV
   --------------------------------------------------------------------------- */

/**
 * The texts of a scope as CSV: hash, status, source, translation. The hash
 * is the key an import is matched on, so a text that changed in the meantime
 * is not overwritten by an old row.
 *
 * @return string
 */
function pg_tr_csv_export($language, $scope = 'all', $only_pending = false)
{
    $owners = pg_tr_scope_owners($scope);
    $where = pg_tr_owner_where($owners);

    $rows = db_items("SELECT DISTINCT s.id, s.hash, s.source_text, s.format, t.text, t.status
                      FROM translation_uses u
                      INNER JOIN translation_strings s ON s.id = u.string_id
                      LEFT JOIN translations t ON t.string_id = s.id AND t.language = '" . e($language) . "'
                      WHERE u.string_id > 0" . ($where !== '' ? " AND ($where)" : '') . ($only_pending ? ' AND t.id IS NULL' : '') . "
                      ORDER BY s.id");

    $handle = fopen('php://temp', 'r+');
    fwrite($handle, "\xEF\xBB\xBF");
    fputcsv($handle, array('hash', 'format', 'status', 'source', 'translation'), ';');

    if (is_array($rows)) {
        foreach ($rows as $row) {
            fputcsv($handle, array(
                $row['hash'],
                $row['format'],
                ($row['text'] === null) ? 'pending' : $row['status'],
                $row['source_text'],
                ($row['text'] === null) ? '' : $row['text'],
            ), ';');
        }
    }

    rewind($handle);
    $csv = stream_get_contents($handle);
    fclose($handle);

    return $csv;
}

/**
 * Reads a CSV written by pg_tr_csv_export() (or one shaped like it) and
 * writes the translations it carries. Rows are matched by hash; a row whose
 * hash no longer exists is counted as stale; an empty translation is skipped.
 * Imported texts are written as reviewed unless $as_machine says otherwise.
 *
 * @return array('written' => int, 'skipped' => int, 'stale' => int, 'rejected' => int)
 */
function pg_tr_csv_import($language, $csv, $user_id = 0, $as_machine = false)
{
    $summary = array('written' => 0, 'skipped' => 0, 'stale' => 0, 'rejected' => 0);
    $csv = (string) $csv;

    if (substr($csv, 0, 3) === "\xEF\xBB\xBF") {
        $csv = substr($csv, 3);
    }

    $handle = fopen('php://temp', 'r+');
    fwrite($handle, $csv);
    rewind($handle);

    $header = fgetcsv($handle, 0, ';');

    if (!is_array($header)) {
        fclose($handle);
        return $summary;
    }

    // Accept a comma-separated file too.
    $delimiter = ';';

    if ((count($header) === 1) && (strpos($header[0], ',') !== false)) {
        $delimiter = ',';
        rewind($handle);
        $header = fgetcsv($handle, 0, ',');
    }

    $columns = array_flip(array_map('strtolower', array_map('trim', $header)));

    if (!isset($columns['hash']) || !isset($columns['translation'])) {
        fclose($handle);
        return $summary;
    }

    while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
        $hash = isset($row[$columns['hash']]) ? trim($row[$columns['hash']]) : '';
        $translation = isset($row[$columns['translation']]) ? (string) $row[$columns['translation']] : '';

        if (($hash === '') || !preg_match('/^[0-9a-f]{40}$/', $hash)) {
            $summary['rejected']++;
            continue;
        }

        if (trim($translation) === '') {
            $summary['skipped']++;
            continue;
        }

        $strings = pg_tr_strings_by_hash(array($hash));

        if (!isset($strings[$hash])) {
            $summary['stale']++;
            continue;
        }

        $string = $strings[$hash];
        $clean = pg_tr_sanitize_incoming($translation, $string['format']);
        $check = pg_tr_result_check($string['source_text'], $clean, $string['format']);

        if (!$check['ok']) {
            $summary['rejected']++;
            continue;
        }

        pg_tr_save_translation((int) $string['id'], $language, $clean, 'import', $as_machine ? 'machine' : 'reviewed', $user_id, $check['suspicious'] ? 1 : 0, true);
        $summary['written']++;
    }

    fclose($handle);

    return $summary;
}
