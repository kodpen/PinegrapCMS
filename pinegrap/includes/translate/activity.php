<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Front-end translation - the Translations screen's automation and its
 * "Activity" tab: the values the dialog "Automation for <language>" saves,
 * the updates of a language listed a page at a time, one update with its
 * failed texts and their reasons, and those texts sent again.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/** Updates listed on one page of "Activity". */
define('PG_TR_ACTIVITY_PER_PAGE', 20);

/** Failed texts listed in an update's drawer. */
define('PG_TR_ACTIVITY_FAILED_LIMIT', 200);

/**
 * The values the dialog "Automation for <language>" posted, checked as the
 * settings card checks them (includes/settings/languages.save.php): an
 * engine of the list, a fallback that is neither "By hand" nor the engine
 * itself, and "Translate on save" off with "By hand", which has no engine
 * to send the texts to.
 *
 * @param array $posted  engine, fallback_engine, auto_update
 * @param array $options pg_tr_engine_options(): key => label
 * @return array ok, error, engine, fallback_engine, auto_update (0 or 1)
 */
function pg_tr_automation_values($posted, $options)
{
    $posted = is_array($posted) ? $posted : array();
    $options = is_array($options) ? $options : array();
    $value = function ($key) use ($posted) {
        return (isset($posted[$key]) && is_scalar($posted[$key])) ? trim((string) $posted[$key]) : '';
    };

    $engine = $value('engine');

    if (($engine === '') || ($engine === 'import') || !array_key_exists($engine, $options)) {
        return array('ok' => false, 'error' => lang('The engine is not known.'), 'engine' => '', 'fallback_engine' => '', 'auto_update' => 0);
    }

    $fallback = $value('fallback_engine');

    if (($fallback !== '') && (($fallback === 'manual') || ($fallback === 'import') || ($fallback === $engine) || !array_key_exists($fallback, $options))) {
        $fallback = '';
    }

    $auto = (!empty($posted['auto_update']) && ($engine !== 'manual')) ? 1 : 0;

    return array('ok' => true, 'error' => '', 'engine' => $engine, 'fallback_engine' => $fallback, 'auto_update' => $auto);
}

/**
 * Whether an update is still open: waiting, handed over or being worked.
 *
 * @param string $status translation_jobs.status
 * @return bool
 */
function pg_tr_activity_open($status)
{
    return in_array((string) $status, array('queued', 'sent', 'running'), true);
}

/**
 * The condition of "Activity"'s segmented filter: "Running" the open
 * updates, "Failed" the ones that failed or left failed texts.
 *
 * @param string $filter all, running or failed
 * @return string SQL, '' for all
 */
function pg_tr_activity_filter_sql($filter)
{
    if ($filter === 'running') {
        return "status IN ('queued', 'sent', 'running')";
    }

    if ($filter === 'failed') {
        return "(status = 'failed' OR failed_count > 0)";
    }

    return '';
}

/**
 * What an update covered, as its row names it: the whole site, a group of
 * the list, one page by its name, the records of a form a record save
 * opened a job for, or a picture's texts.
 *
 * @param string $scope      translation_jobs.scope
 * @param array  $groups     pg_tr_owner_groups()
 * @param array  $page_names page id => name of the translatable pages
 * @return string
 */
function pg_tr_activity_scope_label($scope, $groups, $page_names)
{
    $scope = (string) $scope;
    $groups = (array) $groups;
    $page_names = (array) $page_names;

    if ($scope === 'all') {
        return lang('All pages');
    }

    if (preg_match('/^group:([a-z]+)$/', $scope, $match) && isset($groups[$match[1]])) {
        return (string) $groups[$match[1]]['label'];
    }

    if (preg_match('/^page:(\d+)$/', $scope, $match) && isset($page_names[(int) $match[1]])) {
        return (string) $page_names[(int) $match[1]];
    }

    // Opened by a record save (pg_tr_records_saved()).
    if (preg_match('/^records:\d+$/', $scope) && isset($groups['forms'])) {
        return (string) $groups['forms']['label'];
    }

    // A picture's texts, opened by its save or a page save
    // (pg_picture_tr_texts_jobs()).
    if (preg_match('/^file:\d+$/', $scope) && isset($groups['pictures'])) {
        return (string) $groups['pictures']['label'];
    }

    // One catalog item or form, opened by its save
    // (pg_tr_owner_auto_saved()).
    if (preg_match('/^(product|product_group|offer):\d+$/', $scope) && isset($groups['catalog'])) {
        return (string) $groups['catalog']['label'];
    }

    if (preg_match('/^form:\d+$/', $scope) && isset($groups['forms'])) {
        return (string) $groups['forms']['label'];
    }

    // A page no longer among the translatable ones, or a scope not known.
    return lang('One page');
}

/**
 * How long a finished update took, from when it was handed over (or
 * opened, when it never was) to when it ended.
 *
 * @param array $job translation_jobs row
 * @return int|null seconds, null while it is open or never ended
 */
function pg_tr_activity_took($job)
{
    $finished = (int) ($job['finished_at'] ?? 0);

    if ($finished <= 0) {
        return null;
    }

    $started = (int) ($job['claimed_at'] ?? 0);
    $started = ($started > 0) ? $started : (int) ($job['created_at'] ?? 0);

    return ($started > 0) ? max(0, $finished - $started) : null;
}

/**
 * A duration as the "Took" column writes it: seconds under a minute,
 * minutes under an hour, then hours, each rounded.
 *
 * @param int|null $seconds
 * @return string '—' for none
 */
function pg_tr_activity_duration_text($seconds)
{
    if ($seconds === null) {
        return '—';
    }

    $seconds = max(0, (int) $seconds);

    if ($seconds < 60) {
        return lang(array('string' => '{var:1} s', 'vars' => array($seconds)));
    }

    if ($seconds < 3600) {
        return lang(array('string' => '{var:1} min', 'vars' => array((int) round($seconds / 60))));
    }

    return lang(array('string' => '{var:1} h', 'vars' => array((int) round($seconds / 3600))));
}

/**
 * The failed texts of an update that are still pending: one translated
 * since (by hand, by a later update) is not sent again, and an id that is
 * no text any more is dropped.
 *
 * @param array $failed_ids
 * @param array $statuses   pg_tr_screen_statuses(): id => status or null
 * @return int[]
 */
function pg_tr_activity_retry_ids($failed_ids, $statuses)
{
    $statuses = (array) $statuses;
    $ids = array();

    foreach ((array) $failed_ids as $id) {
        $id = (int) $id;

        if (($id > 0) && array_key_exists($id, $statuses) && ($statuses[$id] === null) && !in_array($id, $ids, true)) {
            $ids[] = $id;
        }
    }

    return $ids;
}

/**
 * The names of the people who started updates, as a change is signed
 * elsewhere in the panel (pg_user_display_name()).
 *
 * @param int[] $user_ids
 * @return array id => name
 */
function pg_tr_activity_user_names($user_ids)
{
    $user_ids = array_values(array_unique(array_filter(array_map('intval', (array) $user_ids))));
    $names = array();

    if (!$user_ids) {
        return $names;
    }

    $rows = db_items("SELECT u.user_id, u.user_username, c.first_name, c.last_name
                      FROM user u
                      LEFT JOIN contacts c ON c.id = u.user_contact
                      WHERE u.user_id IN (" . implode(',', $user_ids) . ")");

    foreach ((array) $rows as $row) {
        $names[(int) $row['user_id']] = pg_user_display_name($row['first_name'], $row['last_name'], $row['user_username']);
    }

    return $names;
}

/**
 * One page of a language's updates, newest first.
 *
 * @param string $language
 * @param string $filter   all, running or failed
 * @param int    $page     1 and up
 * @return array rows, total
 */
function pg_tr_activity_jobs($language, $filter, $page)
{
    $where = "language = '" . e($language) . "'";
    $condition = pg_tr_activity_filter_sql($filter);

    if ($condition !== '') {
        $where .= ' AND ' . $condition;
    }

    $total = (int) db_value("SELECT COUNT(*) FROM translation_jobs WHERE $where");
    $offset = (max(1, (int) $page) - 1) * PG_TR_ACTIVITY_PER_PAGE;
    $rows = db_items("SELECT * FROM translation_jobs WHERE $where ORDER BY id DESC LIMIT $offset, " . PG_TR_ACTIVITY_PER_PAGE);

    return array('rows' => is_array($rows) ? $rows : array(), 'total' => $total);
}

/**
 * One update of a language for its drawer: the row, the texts it skipped,
 * and its failed texts with the start of their source and the reason the
 * engine gave (translation_job_items.error).
 *
 * @param int    $job_id
 * @param string $language
 * @return array|null job, skipped, failed (string_id, hash, source, format, error), failed_total
 */
function pg_tr_activity_job_detail($job_id, $language)
{
    $job = pg_tr_job_get($job_id);

    if (!$job || ((string) $job['language'] !== (string) $language)) {
        return null;
    }

    $job_id = (int) $job['id'];
    $skipped = (int) db_value("SELECT COUNT(*) FROM translation_job_items WHERE job_id = '$job_id' AND status = 'skipped'");
    $failed_total = (int) db_value("SELECT COUNT(*) FROM translation_job_items WHERE job_id = '$job_id' AND status = 'failed'");
    $rows = db_items("SELECT i.string_id, i.error, s.hash, s.format, LEFT(s.source_text, 400) AS source
                      FROM translation_job_items i
                      INNER JOIN translation_strings s ON s.id = i.string_id
                      WHERE i.job_id = '$job_id' AND i.status = 'failed'
                      ORDER BY i.string_id
                      LIMIT " . PG_TR_ACTIVITY_FAILED_LIMIT);
    $failed = array();

    foreach ((array) $rows as $row) {
        $source = (string) $row['source'];

        // A text kept with inline HTML is shown as its words.
        if ((string) $row['format'] === 'html') {
            $source = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($source), ENT_QUOTES, 'UTF-8')));
        }

        $failed[] = array(
            'string_id' => (int) $row['string_id'],
            'hash'      => (string) $row['hash'],
            'source'    => $source,
            'error'     => (string) $row['error'],
        );
    }

    return array('job' => $job, 'skipped' => $skipped, 'failed' => $failed, 'failed_total' => $failed_total);
}

/**
 * "Try again with the failed ones": the failed texts of a finished update
 * that are still pending go to a new update of the same engine, or of the
 * language's fallback engine, filed under the same scope.
 *
 * @param string $language
 * @param int    $job_id
 * @param bool   $fallback true for the language's fallback engine
 * @param int    $user_id
 * @return array pg_tr_screen_translate_again()'s answer
 */
function pg_tr_activity_retry($language, $job_id, $fallback, $user_id = 0)
{
    $job = pg_tr_job_get($job_id);
    $row = pg_tr_language_row($language);
    $none = array('ok' => false, 'error' => '', 'engine' => '', 'pending' => 0, 'sent' => array(), 'kept' => array(), 'job_id' => 0, 'job' => null, 'finished' => true);

    if (!$job || !$row || ((string) $job['language'] !== (string) $language)) {
        $none['error'] = lang('The job could not be found.');
        return $none;
    }

    if (pg_tr_activity_open($job['status'])) {
        $none['error'] = lang('The update is still running.');
        return $none;
    }

    $engine = $fallback ? (string) $row['fallback_engine'] : (string) $job['engine'];

    if ($engine === '') {
        $none['error'] = lang('No fallback engine is set for this language.');
        return $none;
    }

    $failed = db_values("SELECT string_id FROM translation_job_items WHERE job_id = '" . (int) $job['id'] . "' AND status = 'failed'");
    $ids = pg_tr_activity_retry_ids((array) $failed, pg_tr_screen_statuses($language, (array) $failed));

    if (!$ids) {
        $none['ok'] = true;
        $none['engine'] = $engine;
        return $none;
    }

    return pg_tr_screen_translate_again($language, (string) $job['scope'], $ids, false, $user_id, $engine);
}
