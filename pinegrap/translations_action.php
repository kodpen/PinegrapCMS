<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The Translations screen's endpoint: "update translations", the browser
 * engine's batches and answers, a translation typed or reviewed by hand, the
 * CSV export and import, the glossary.
 *
 * JSON in, JSON out, for everything but the CSV transfers and the glossary
 * forms: the export is a file download, the import a multipart form, the
 * glossary two plain forms. Every call needs a
 * signed-in manager and the session token - or, for the visual editor's
 * Translate section, a user with edit rights to the page being edited; a
 * refusal is JSON too, so the screen's script can show it instead of
 * choking on an HTML error page.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

require('init.php');

$user = validate_user();

$translations_form_action = (isset($_POST['action']) && is_string($_POST['action'])) ? $_POST['action'] : '';
$translations_is_import = in_array($translations_form_action, array('import', 'glossary_save', 'glossary_delete'), true);
$translations_is_export = (isset($_GET['action']) && ($_GET['action'] === 'export'));

// The JSON calls. The CSV import and the glossary post forms and the export
// is a GET, so their token is checked the usual way further down.
$request = array();

if (!$translations_is_import && !$translations_is_export) {
    $request = json_decode((string) @file_get_contents('php://input'), true);
    $request = is_array($request) ? $request : array();
}

/**
 * Answers and ends the request.
 */
function translations_respond($response)
{
    header('Content-Type: application/json; charset=utf-8');
    echo encode_json($response);
    exit;
}

/**
 * For a user limited to the page: whether they may change the
 * translation of this string from that page (pg_tr_string_page_scope()).
 *
 * @return string  'yes', 'elsewhere' or 'no'
 */
function translations_page_scope($string_id, $page_id, $user)
{
    static $pages = array();

    return pg_tr_string_page_scope($string_id, $page_id, function ($other_page_id) use ($user, &$pages) {
        if (!isset($pages[$other_page_id])) {
            $pages[$other_page_id] = (pg_designer_page_access($other_page_id, $user) === 'edit');
        }

        return $pages[$other_page_id];
    });
}

/**
 * Ends the request when a user limited to the page may not change the
 * translation of this string.
 */
function translations_require_page_scope($string_id, $page_id, $user)
{
    $scope = translations_page_scope($string_id, $page_id, $user);

    if ($scope === 'elsewhere') {
        translations_respond(array('status' => 'error', 'message' => lang('This text is also used in places you cannot edit; a site manager can change its translation on the Translations screen.')));
    }

    if ($scope !== 'yes') {
        translations_respond(array('status' => 'error', 'message' => lang('Only the translations of the texts on the page you are editing can be changed here.')));
    }
}

if (!$translations_is_import && !$translations_is_export) {

    // The role gate answers in JSON: validate_area_access() prints a page.
    //
    // A manager runs the whole screen. A user (role 3) who edits a page's
    // content in the visual editor gets the editor's three calls - read the
    // texts of the selected element, save a translation, approve one - for a
    // page whose folder they have edit rights to, the same rule that opens
    // the page in the editor (pg_designer_page_access()). Which texts are
    // theirs is checked where a translation is written.
    $translations_page_only = ((int) $user['role'] > 2);
    $translations_page_id = (isset($request['page_id']) && is_numeric($request['page_id'])) ? (int) $request['page_id'] : 0;

    if ($translations_page_only) {
        require_once(PG_FUNCTIONS_DIR . '/includes/designer_access.php');

        $editor_action = isset($request['action']) ? (string) $request['action'] : '';

        if (!in_array($editor_action, array('node', 'save', 'status'), true) || ($translations_page_id <= 0)
            || (pg_designer_access($user) === PG_DESIGNER_ACCESS_NONE)
            || (pg_designer_page_access($translations_page_id, $user) !== 'edit')) {
            http_response_code(403);
            translations_respond(array('status' => 'error', 'message' => lang('Access denied.')));
        }
    }

    $session_token = (string) ($_SESSION['software']['token'] ?? '');
    $sent_token = (isset($request['token']) && is_string($request['token'])) ? $request['token'] : '';

    if (($session_token === '') || !hash_equals($session_token, $sent_token)) {
        http_response_code(403);
        translations_respond(array('status' => 'error', 'message' => lang('Invalid token.')));
    }

    if (!pg_tr_ready()) {
        translations_respond(array('status' => 'error', 'message' => lang('These settings arrive with the 2026.4.6 upgrade, which this site has not run yet.')));
    }

    pg_tr_load();

    $action = isset($request['action']) ? (string) $request['action'] : '';
    $language = isset($request['language']) ? trim((string) $request['language']) : '';

    // Everything but a job call names a language, and it has to be one of
    // the site's target languages.
    if (($language !== '') && (!pg_tr_language_row($language) || ($language === pg_tr_source_language()))) {
        translations_respond(array('status' => 'error', 'message' => lang('The language is not one of the site\'s target languages.')));
    }

    switch ($action) {

        // "Update translations": extract the scope and open a job for what
        // is pending; a server engine's job is then worked by "run".
        case 'update':
            $scope = isset($request['scope']) ? (string) $request['scope'] : 'all';

            if (!preg_match('/^(all|page:\d+|group:[a-z]+)$/', $scope) || ($language === '')) {
                translations_respond(array('status' => 'error', 'message' => lang('Invalid request.')));
            }

            $engine = isset($request['engine']) ? (string) $request['engine'] : '';
            $result = pg_tr_update($language, $scope, (int) $user['id'], $engine);

            if (!$result['ok']) {
                translations_respond(array('status' => 'error', 'message' => $result['error']));
            }

            log_activity(lang(array('string' => 'translations were updated ({var:1}, {var:2} text(s) sent)', 'vars' => array($language, $result['pending']))), $_SESSION['sessionusername']);

            translations_respond(array('status' => 'success') + $result);
            break;

        // The engine picked in the toolbar: the language's engine, the same
        // setting the settings card writes.
        case 'engine':
            $engine = isset($request['engine']) ? (string) $request['engine'] : '';
            $row = ($language !== '') ? pg_tr_language_row($language) : null;

            if (!$row || !array_key_exists($engine, pg_tr_engine_options(array((string) $row['engine'])))) {
                translations_respond(array('status' => 'error', 'message' => lang('The engine is not known.')));
            }

            db("UPDATE site_languages SET engine = '" . e($engine) . "', fallback_engine = IF(fallback_engine = '" . e($engine) . "', '', fallback_engine), updated_at = '" . time() . "' WHERE code = '" . e($language) . "'");

            log_activity(lang(array('string' => 'the translation engine of {var:1} was set to {var:2}', 'vars' => array($language, $engine))), $_SESSION['sessionusername']);

            translations_respond(array('status' => 'success', 'engine' => $engine));
            break;

        // The browser engine asks for its next batch of texts.
        case 'package':
            $job_id = isset($request['job_id']) ? (int) $request['job_id'] : 0;
            $package = pg_tr_job_package($job_id, 40);

            if (!$package['job']) {
                translations_respond(array('status' => 'error', 'message' => lang('The job could not be found.')));
            }

            translations_respond(array('status' => 'success', 'job' => $package['job'], 'items' => $package['items'], 'source' => $package['source']));
            break;

        // ... and brings the answers back.
        case 'results':
            $job_id = isset($request['job_id']) ? (int) $request['job_id'] : 0;
            $results = (isset($request['results']) && is_array($request['results'])) ? $request['results'] : array();
            $summary = pg_tr_job_results($job_id, $results, (int) $user['id']);

            translations_respond(array('status' => 'success', 'job' => pg_tr_job_get($job_id)) + $summary);
            break;

        // A server engine's job, worked twenty seconds at a time from the
        // panel instead of waiting for the scheduled job.
        case 'run':
            $job_id = isset($request['job_id']) ? (int) $request['job_id'] : 0;
            $run = pg_tr_job_run_server($job_id, 20);

            translations_respond(array('status' => 'success', 'job' => pg_tr_job_get($job_id)) + $run);
            break;

        case 'job':
            $job_id = isset($request['job_id']) ? (int) $request['job_id'] : 0;
            $job = pg_tr_job_get($job_id);

            if (!$job) {
                translations_respond(array('status' => 'error', 'message' => lang('The job could not be found.')));
            }

            translations_respond(array('status' => 'success', 'job' => $job));
            break;

        case 'cancel':
            $job_id = isset($request['job_id']) ? (int) $request['job_id'] : 0;
            pg_tr_job_cancel($job_id);

            translations_respond(array('status' => 'success', 'job' => pg_tr_job_get($job_id)));
            break;

        // A translation typed in the editor. What the operator wrote is
        // reviewed by definition, unless they say it is still a draft.
        case 'save':
            $string_id = isset($request['string_id']) ? (int) $request['string_id'] : 0;
            $text = isset($request['text']) ? (string) $request['text'] : '';

            // From the visual editor: wording typed since the page was last
            // saved is not stored yet. It is stored now, so the translation
            // is in place when the page is saved and extracted.
            if (($string_id <= 0) && isset($request['source']) && is_string($request['source']) && ($language !== '')) {
                $source_format = (isset($request['format']) && ($request['format'] === 'inline')) ? 'inline' : 'text';
                $normalized = pg_tr_normalize($request['source'], $source_format);

                if (($normalized !== '') && pg_tr_translatable($normalized)) {
                    $string_id = (int) pg_tr_string_upsert($normalized, $source_format, 'content');
                }
            }

            $string = db_item("SELECT * FROM translation_strings WHERE id = '$string_id' LIMIT 1");

            if (!is_array($string) || ($language === '')) {
                translations_respond(array('status' => 'error', 'message' => lang('Invalid request.')));
            }

            if ($translations_page_only) {
                translations_require_page_scope($string_id, $translations_page_id, $user);
            }

            $clean = pg_tr_sanitize_incoming($text, $string['format']);

            if (trim($clean) === '') {
                // An emptied box takes the translation away: the source shows
                // again and the text is pending once more.
                db("DELETE FROM translations WHERE string_id = '$string_id' AND language = '" . e($language) . "'");
                pg_tr_invalidate_pages($language);

                translations_respond(array('status' => 'success', 'string_id' => $string_id, 'text' => '', 'engine' => '', 'state' => 'pending', 'suspicious' => 0));
            }

            $check = pg_tr_result_check($string['source_text'], $clean, $string['format']);

            if (!$check['ok']) {
                switch ($check['error']) {
                    case 'placeholders':
                        $message = lang(array('string' => 'The placeholders of the source text ({var:1}) have to appear in the translation as well.', 'vars' => array(implode(' ', pg_tr_tokens_of($string['source_text'])))));
                        break;
                    case 'markup':
                        $message = lang('The translation has to carry the same inline tags as the source text.');
                        break;
                    default:
                        $message = lang('The translation was not accepted.');
                }

                translations_respond(array('status' => 'error', 'message' => $message));
            }

            $status = (isset($request['status']) && ($request['status'] === 'machine')) ? 'machine' : 'reviewed';
            pg_tr_save_translation($string_id, $language, $clean, 'manual', $status, (int) $user['id'], $check['suspicious'] ? 1 : 0, true);

            translations_respond(array('status' => 'success', 'string_id' => $string_id, 'text' => $clean, 'engine' => 'manual', 'state' => $status, 'suspicious' => $check['suspicious'] ? 1 : 0));
            break;

        // The visual editor's Translate section: the texts of the selected
        // element with their translation in every target language.
        case 'node':
            $node = (isset($request['node']) && is_array($request['node'])) ? $request['node'] : array();
            $targets = pg_tr_target_languages();
            $segments = array();

            foreach (array_slice(pg_tr_node_segments($node), 0, 30) as $segment) {
                $string_id = (int) db_value("SELECT id FROM translation_strings WHERE hash = '" . e($segment['hash']) . "' LIMIT 1");
                $found = array();

                if ($string_id > 0) {
                    foreach ((array) db_items("SELECT language, text, engine, status, suspicious FROM translations WHERE string_id = '$string_id'") as $row) {
                        if (isset($targets[$row['language']])) {
                            $found[$row['language']] = array(
                                'text'       => (string) $row['text'],
                                'engine'     => (string) $row['engine'],
                                'state'      => (string) $row['status'],
                                'suspicious' => (int) $row['suspicious'],
                            );
                        }
                    }
                }

                $segments[] = array(
                    'field'        => (string) $segment['field'],
                    'format'       => (string) $segment['format'],
                    'source'       => (string) $segment['text'],
                    'string_id'    => $string_id,
                    'translations' => $found ? $found : new stdClass(),
                    // Shown read-only to a user limited to the page.
                    'locked'       => ($translations_page_only && ($string_id > 0)
                                       && (translations_page_scope($string_id, $translations_page_id, $user) !== 'yes')),
                );
            }

            translations_respond(array('status' => 'success', 'segments' => $segments));
            break;

        // Reviewed or back to machine, without touching the text.
        case 'status':
            $string_id = isset($request['string_id']) ? (int) $request['string_id'] : 0;
            $status = (isset($request['status']) && ($request['status'] === 'reviewed')) ? 'reviewed' : 'machine';

            if (($string_id <= 0) || ($language === '')) {
                translations_respond(array('status' => 'error', 'message' => lang('Invalid request.')));
            }

            if ($translations_page_only) {
                translations_require_page_scope($string_id, $translations_page_id, $user);
            }

            pg_tr_set_status($string_id, $language, $status, (int) $user['id']);

            translations_respond(array('status' => 'success', 'state' => $status));
            break;

        // Every machine translation of the scope reviewed at once.
        case 'review_all':
            $scope = isset($request['scope']) ? (string) $request['scope'] : 'all';

            if (!preg_match('/^(all|page:\d+|group:[a-z]+)$/', $scope) || ($language === '')) {
                translations_respond(array('status' => 'error', 'message' => lang('Invalid request.')));
            }

            $count = pg_tr_review_all($language, $scope, (int) $user['id']);

            if ($count > 0) {
                log_activity(lang(array('string' => 'machine translations were marked as reviewed ({var:1}, {var:2} text(s))', 'vars' => array($language, $count))), $_SESSION['sessionusername']);
            }

            translations_respond(array('status' => 'success', 'count' => $count, 'message' => lang(array('string' => '{var:1} translation(s) marked as reviewed.', 'vars' => array($count)))));
            break;

        default:
            translations_respond(array('status' => 'error', 'message' => lang('Invalid request.')));
    }
}

// ── CSV ──

validate_area_access($user, 'manager');
validate_token_field();

if (!pg_tr_ready()) {
    output_error(lang('These settings arrive with the 2026.4.6 upgrade, which this site has not run yet.'));
}

pg_tr_load();

if ($translations_is_export) {

    $language = isset($_GET['language']) ? trim((string) $_GET['language']) : '';
    $scope = isset($_GET['scope']) ? (string) $_GET['scope'] : 'all';
    $pending = !empty($_GET['pending']);

    if (!pg_tr_language_row($language) || ($language === pg_tr_source_language()) || !preg_match('/^(all|page:\d+|group:[a-z]+)$/', $scope)) {
        output_error(lang('Invalid request.'));
    }

    $csv = pg_tr_csv_export($language, $scope, $pending);
    $file_name = 'translations-' . preg_replace('/[^a-z0-9-]/i', '', $language) . '-' . date('Ymd-His') . '.csv';

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $file_name . '"');
    header('Cache-Control: no-store, private');
    echo $csv;
    exit;
}

include_once('liveform.class.php');

$language = isset($_POST['language']) ? trim((string) $_POST['language']) : '';
$back = PATH . SOFTWARE_DIRECTORY . '/translations.php?language=' . urlencode($language);

$liveform = new liveform('translations');

if (!pg_tr_language_row($language) || ($language === pg_tr_source_language())) {
    $liveform->mark_error('', lang('The language is not one of the site\'s target languages.'));
    go($back);
}

// ── the glossary ──
//
// A term with the translation the engines are to use, or a term they are to
// keep as written; for one language or for every language.

if ($translations_form_action === 'glossary_delete') {
    $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
    $row = db_item("SELECT term FROM translation_glossary WHERE id = '$id' LIMIT 1");

    if (is_array($row)) {
        db("DELETE FROM translation_glossary WHERE id = '$id'");
        log_activity(lang(array('string' => 'the glossary term "{var:1}" was removed', 'vars' => array($row['term']))), $_SESSION['sessionusername']);
        $liveform->add_notice(lang('The term was removed from the glossary.'));
    }

    go($back . '#pg_tr_glossary');
}

if ($translations_form_action === 'glossary_save') {
    $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
    $term = isset($_POST['term']) ? trim(preg_replace('/\s+/u', ' ', (string) $_POST['term'])) : '';
    $translation = isset($_POST['translation']) ? trim(preg_replace('/\s+/u', ' ', (string) $_POST['translation'])) : '';
    $keep = !empty($_POST['keep']) ? 1 : 0;
    $case_sensitive = !empty($_POST['case_sensitive']) ? 1 : 0;
    $note = isset($_POST['note']) ? trim((string) $_POST['note']) : '';
    $for_all = !empty($_POST['all_languages']);

    if ($term === '') {
        $liveform->mark_error('term', lang('Please enter the term.'));
        go($back . '#pg_tr_glossary');
    }

    if (!$keep && ($translation === '')) {
        $liveform->mark_error('translation', lang('Enter the translation to use, or mark the term as one to keep as written.'));
        go($back . '#pg_tr_glossary');
    }

    // A kept term has no translation; a translated term is for one language.
    if ($keep) {
        $translation = '';
    } else {
        $for_all = false;
    }

    $values = "language = '" . ($for_all ? '' : e($language)) . "', term = '" . e(mb_substr($term, 0, 255)) . "', translation = '" . e(mb_substr($translation, 0, 255)) . "',
        keep = '$keep', case_sensitive = '$case_sensitive', note = '" . e(mb_substr($note, 0, 255)) . "'";

    if (($id > 0) && db_value("SELECT id FROM translation_glossary WHERE id = '$id' LIMIT 1")) {
        db("UPDATE translation_glossary SET $values WHERE id = '$id'");
    } else {
        db("INSERT INTO translation_glossary SET $values");
    }

    log_activity(lang(array('string' => 'the glossary term "{var:1}" was saved ({var:2})', 'vars' => array($term, $for_all ? lang('all languages') : $language))), $_SESSION['sessionusername']);
    $liveform->add_notice(lang('The term was saved. It applies to the texts sent to an engine from now on.'));

    go($back . '#pg_tr_glossary');
}

// ── the import: a posted file, written as reviewed translations, then back
// to the screen with the counts ──

if (empty($_FILES['csv']) || !is_uploaded_file($_FILES['csv']['tmp_name'])) {
    $liveform->mark_error('', lang('Please choose a CSV file.'));
    go($back);
}

$csv = (string) file_get_contents($_FILES['csv']['tmp_name']);
$summary = pg_tr_csv_import($language, $csv, (int) $user['id'], !empty($_POST['as_machine']));

log_activity(lang(array('string' => 'translations were imported from a CSV file ({var:1}, {var:2} text(s))', 'vars' => array($language, $summary['written']))), $_SESSION['sessionusername']);

$liveform->add_notice(lang(array(
    'string' => 'CSV import: {var:1} translation(s) written, {var:2} empty row(s) skipped, {var:3} row(s) whose text has since changed, {var:4} row(s) rejected.',
    'vars'   => array($summary['written'], $summary['skipped'], $summary['stale'], $summary['rejected']))));

go($back);
