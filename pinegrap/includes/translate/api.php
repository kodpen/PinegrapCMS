<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Front-end translation - what the feature adds to the external API.
 *
 * Merged into the API's own lists by includes/api/modules.php while the
 * translation tables exist: the site's languages, the texts of its pages with
 * their translations, a bulk write for a translation tool, and the job queue
 * Claude's routine works through (includes/translate/claude.php). Nothing
 * under includes/api/ is edited to add a translation endpoint.
 *
 * The job endpoints answer the application Claude works through alone; the
 * rest any application whose owner may run the Translations screen.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_API_ENTRY') && !defined('PG_API_PANEL') && !defined('PG_INIT_LOADED')) {
    exit;
}

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/** Texts handed to a job's run in one answer, and taken back in one call. */
define('PG_TR_API_JOB_BATCH', 100);

/**
 * The permission row on the Application Access screen.
 *
 * @return array
 */
function translate_scope_groups()
{
    return array(
        'translations' => array(
            'label'       => lang('Translations'),
            'description' => lang('The site\'s languages, the texts of its pages and their translations'),
            'icon'        => 'bi-translate',
            'read'        => 'translations:read',
            'write'       => 'translations:write',
        ),
    );
}

/**
 * Which scopes an application owner may delegate: both to an administrator
 * or a manager, who may run the Translations screen; none to anyone else.
 *
 * @param array $owner
 * @return array
 */
function translate_owner_scopes($owner)
{
    $role = (int) ($owner['role'] ?? 9);

    return ($role <= 2) ? array('translations:read', 'translations:write') : array();
}

/**
 * The objects the endpoints answer with.
 *
 * @return array
 */
function translate_openapi_objects()
{
    return array(
        'TranslationLanguage'    => 'translate_api_language_schema',
        'TranslationString'      => 'translate_api_string_schema',
        'TranslationPage'        => 'translate_api_page_schema',
        'TranslationWriteResult' => 'translate_api_write_result_schema',
        'TranslationJob'         => 'translate_api_job_schema',
        'TranslationJobItems'    => 'translate_api_job_items_schema',
        'TranslationJobResults'  => 'translate_api_job_results_schema',
    );
}

/**
 * The route rows.
 *
 * @return array
 */
function translate_api_routes()
{
    return array(

        array(
            'id'          => 'translations.languages.list',
            'method'      => 'GET',
            'path'        => '/translations/languages',
            'scope'       => 'translations:read',
            'handler'     => 'translate_api_languages_list',
            'returns'     => array('list' => 'TranslationLanguage'),
            'summary'     => 'The site\'s languages',
            'description' => 'The source language the pages are written in, then every target language in display order, each with the address its pages are served under and how far its translation has come: total texts, translated, reviewed, pending.',
            'params'      => array(),
        ),

        array(
            'id'          => 'translations.strings.list',
            'method'      => 'GET',
            'path'        => '/translations/strings',
            'scope'       => 'translations:read',
            'handler'     => 'translate_api_strings_list',
            'returns'     => array('list' => 'TranslationString'),
            'summary'     => 'The texts and their translations',
            'description' => 'Every text drawn on the site\'s pages, with its translation into the language asked for. The hash is what POST /translations is keyed on; a text that changes gets a new hash, so an old translation is never written over a new wording. status narrows the list: pending has no translation yet, machine was translated by an engine and not reviewed, reviewed was checked by a person, suspicious was flagged by the check. page_id keeps the texts of one page. Page with next_cursor.',
            'params'      => array(
                array('name' => 'language', 'in' => 'query', 'type' => 'string', 'max_length' => 16, 'required' => true, 'description' => 'A target language code, from GET /translations/languages.'),
                array('name' => 'status', 'in' => 'query', 'type' => 'enum', 'values' => array('all', 'pending', 'machine', 'reviewed', 'suspicious'), 'default' => 'all'),
                array('name' => 'page_id', 'in' => 'query', 'type' => 'int', 'min' => 1, 'description' => 'Only the texts of this page, its title and description included.'),
                array('name' => 'search', 'in' => 'query', 'type' => 'string', 'max_length' => 100, 'description' => 'Looks in the source text and the translation.'),
                array('name' => 'cursor', 'in' => 'query', 'type' => 'string', 'max_length' => 200),
                array('name' => 'limit', 'in' => 'query', 'type' => 'int', 'min' => 1, 'max' => 200, 'default' => 100),
            ),
        ),

        array(
            'id'          => 'translations.pages.get',
            'method'      => 'GET',
            'path'        => '/translations/pages/{id}',
            'scope'       => 'translations:read',
            'handler'     => 'translate_api_pages_get',
            'returns'     => 'TranslationPage',
            'summary'     => 'How far a page is translated',
            'description' => 'One page: its name and address, and for every target language the address it is served under there, how many of its texts are translated and reviewed, and whether search engines are let in under that language\'s index policy. The texts themselves are read with GET /translations/strings?page_id=.',
            'params'      => array(
                array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true),
            ),
        ),

        array(
            'id'          => 'translations.write',
            'method'      => 'POST',
            'path'        => '/translations',
            'scope'       => 'translations:write',
            'handler'     => 'translate_api_write',
            'returns'     => 'TranslationWriteResult',
            'summary'     => 'Write translations',
            'description' => 'Writes translations for one language in bulk, keyed on the hash from GET /translations/strings - the way a translation tool hands finished work back. A translation is refused when it drops a tag or a token of its text (rejected names the hash and the reason); a hash the site no longer has is stale and skipped; an empty text is skipped. What is written is reviewed unless status says machine, and shows on the pages at once. A reviewed translation already there is kept unless overwrite is true.',
            'params'      => array(
                array('name' => 'language', 'in' => 'body', 'type' => 'string', 'max_length' => 16, 'required' => true, 'description' => 'A target language code.'),
                array('name' => 'items', 'in' => 'body', 'type' => 'list', 'max_items' => 200, 'required' => true, 'description' => 'Objects of {hash, text}.'),
                array('name' => 'status', 'in' => 'body', 'type' => 'enum', 'values' => array('reviewed', 'machine'), 'default' => 'reviewed'),
                array('name' => 'overwrite', 'in' => 'body', 'type' => 'bool', 'description' => 'Write over a reviewed translation too. Default false.'),
            ),
        ),

        array(
            'id'          => 'translations.jobs.list',
            'method'      => 'GET',
            'path'        => '/translations/jobs',
            'scope'       => 'translations:read',
            'handler'     => 'translate_api_jobs_list',
            'returns'     => array('list' => 'TranslationJob'),
            'summary'     => 'The translation jobs waiting for Claude',
            'description' => 'For the application chosen for Claude in the Workspace Settings only (and, to try it, an administrator\'s API Documentation screen). status open (the default) is what is waiting to be claimed, oldest first; all is the latest 50 of every state. Each job names its source and target language and how many texts it holds.',
            'params'      => array(
                array('name' => 'status', 'in' => 'query', 'type' => 'enum', 'values' => array('open', 'running', 'done', 'failed', 'cancelled', 'all'), 'default' => 'open'),
            ),
        ),

        array(
            'id'          => 'translations.jobs.claim',
            'method'      => 'POST',
            'path'        => '/translations/jobs/{id}/claim',
            'scope'       => 'translations:write',
            'handler'     => 'translate_api_jobs_claim',
            'returns'     => 'TranslationJob',
            'summary'     => 'Take a job',
            'description' => 'Marks the job as being worked on. 409 already_claimed when another run has it and is still posting, 409 closed when it is finished or cancelled. A run that went quiet for half an hour no longer holds its job.',
            'params'      => array(
                array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true),
            ),
        ),

        array(
            'id'          => 'translations.jobs.items',
            'method'      => 'GET',
            'path'        => '/translations/jobs/{id}/items',
            'scope'       => 'translations:read',
            'handler'     => 'translate_api_jobs_items',
            'returns'     => 'TranslationJobItems',
            'summary'     => 'The texts of a job still to translate',
            'description' => 'Up to 100 of the job\'s waiting texts, oldest first, with the languages, the site owner\'s style note and the glossary of the target language. Each item carries the text and its format (inline is HTML whose tags stay as they are), where it stands on the site - the page, the kind of field, the texts before and after it - the translation its previous wording had, the glossary lines that touch it, and the reason its last answer was refused when there was one. Read it again after posting results: it answers no items when the job is done.',
            'params'      => array(
                array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true),
                array('name' => 'limit', 'in' => 'query', 'type' => 'int', 'min' => 1, 'max' => 100, 'default' => 100),
            ),
        ),

        array(
            'id'          => 'translations.jobs.results',
            'method'      => 'POST',
            'path'        => '/translations/jobs/{id}/results',
            'scope'       => 'translations:write',
            'handler'     => 'translate_api_jobs_results',
            'returns'     => 'TranslationJobResults',
            'summary'     => 'Bring translations back',
            'description' => 'The translations of up to 100 items of the job, keyed on hash. Each is checked against its text - the tags and tokens have to be there - and written as a machine translation that shows on the pages at once. rejected names what was refused and why; a refused item is asked once more and then given up. {hash, skip: true, reason} gives an item up without an answer. A hash that is not in the job is stale. The job closes by itself when every item is answered.',
            'params'      => array(
                array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true),
                array('name' => 'results', 'in' => 'body', 'type' => 'list', 'max_items' => 100, 'required' => true, 'description' => 'Objects of {hash, text} or {hash, skip, reason}.'),
            ),
        ),

        array(
            'id'          => 'translations.jobs.fail',
            'method'      => 'POST',
            'path'        => '/translations/jobs/{id}/fail',
            'scope'       => 'translations:write',
            'handler'     => 'translate_api_jobs_fail',
            'returns'     => 'TranslationJob',
            'summary'     => 'Give a job up',
            'description' => 'Closes the job as not done with the reason, which the Translations screen shows. What was already written stays.',
            'params'      => array(
                array('name' => 'id', 'in' => 'path', 'type' => 'int', 'min' => 1, 'required' => true),
                array('name' => 'reason', 'in' => 'body', 'type' => 'string', 'max_length' => 250, 'required' => true),
            ),
        ),

    );
}

/* ---------------------------------------------------------------------------
   Shared
   --------------------------------------------------------------------------- */

/**
 * The translation files, loaded for a handler.
 */
function translate_api_boot()
{
    if (!function_exists('pg_tr_ready') || !pg_tr_ready()) {
        api_fail(503, 'service_unavailable', lang('These settings arrive with the 2026.4.6 upgrade, which this site has not run yet.'));
    }

    pg_tr_load();
}

/**
 * A target language's row, or a 422 naming the field.
 */
function translate_api_language_or_422($code, $field = 'language')
{
    $code = trim((string) $code);
    $row = ($code !== '') ? pg_tr_language_row($code) : null;

    if (!$row || ($code === pg_tr_source_language())) {
        api_fail_validation(lang('The language is not one of the site\'s target languages.'), $field);
    }

    return $row;
}

/**
 * The address of the site's root under a language.
 */
function translate_api_language_url($code)
{
    $base = URL_SCHEME . HOSTNAME_SETTING . rtrim(PATH, '/') . '/';

    if ($code === pg_tr_source_language()) {
        return $base;
    }

    return $base . pg_tr_prefix($code) . '/';
}

/* ---------------------------------------------------------------------------
   Languages
   --------------------------------------------------------------------------- */

/**
 * The counts of the source language, which has nothing to translate.
 */
function translate_api_zero_stats()
{
    return array('total' => 0, 'translated' => 0, 'reviewed' => 0, 'machine' => 0, 'pending' => 0, 'seen_at' => 0);
}

function translate_api_language_present($code, $row = null)
{
    $known = pg_tr_known_languages();
    $is_source = ($code === pg_tr_source_language());
    $stats = $is_source ? translate_api_zero_stats() : pg_tr_language_stats($code);

    return array(
        'code'         => (string) $code,
        'label'        => ($row && ($row['label'] !== '')) ? (string) $row['label'] : (isset($known[$code]) ? $known[$code][0] : (string) $code),
        'native'       => isset($known[$code]) ? $known[$code][1] : (string) $code,
        'is_source'    => $is_source,
        'enabled'      => $is_source ? true : ($row ? ((int) $row['enabled'] === 1) : false),
        'prefix'       => $is_source ? '' : pg_tr_prefix($code),
        'url'          => translate_api_language_url($code),
        'engine'       => $is_source ? '' : ($row ? (string) $row['engine'] : ''),
        'index_policy' => $is_source ? 'all' : ($row ? (string) $row['index_policy'] : 'reviewed'),
        'rtl'          => isset($known[$code]) ? ((int) $known[$code][5] === 1) : false,
        'counts'       => array(
            'total'      => (int) $stats['total'],
            'translated' => (int) $stats['translated'],
            'reviewed'   => (int) $stats['reviewed'],
            'machine'    => (int) $stats['machine'],
            'pending'    => (int) $stats['pending'],
        ),
    );
}

// What translate_api_language_present() returns.
function translate_api_language_schema()
{
    return array(
        'code'         => 'string',
        'label'        => 'string',
        'native'       => 'string',
        'is_source'    => 'boolean',
        'enabled'      => 'boolean',
        'prefix'       => 'string',
        'url'          => 'string',
        'engine'       => 'string',
        'index_policy' => 'string',
        'rtl'          => 'boolean',
        'counts'       => array('total' => 'integer', 'translated' => 'integer', 'reviewed' => 'integer', 'machine' => 'integer', 'pending' => 'integer'),
    );
}

function translate_api_languages_list($params)
{
    translate_api_boot();

    $out = array(translate_api_language_present(pg_tr_source_language()));

    foreach (pg_tr_languages(false) as $code => $row) {
        if ($code === pg_tr_source_language()) {
            continue;
        }

        $out[] = translate_api_language_present($code, $row);
    }

    api_ok_list($out, count($out));
}

/* ---------------------------------------------------------------------------
   Strings
   --------------------------------------------------------------------------- */

function translate_api_string_present($row)
{
    $has = ($row['text'] !== null);

    return array(
        'id'          => (int) $row['id'],
        'hash'        => (string) $row['hash'],
        'format'      => (string) $row['format'],
        'kind'        => (string) $row['kind'],
        'source_text' => (string) $row['source_text'],
        'text'        => $has ? (string) $row['text'] : null,
        'status'      => $has ? (string) $row['status'] : 'pending',
        'engine'      => $has ? (string) $row['engine'] : '',
        'suspicious'  => $has && ((int) $row['suspicious'] === 1),
        'updated_at'  => $has ? api_time($row['updated_at']) : null,
    );
}

// What translate_api_string_present() returns.
function translate_api_string_schema()
{
    return array(
        'id'          => 'integer',
        'hash'        => 'string',
        'format'      => 'string',
        'kind'        => 'string',
        'source_text' => 'string',
        'text'        => 'string?',
        'status'      => 'string',
        'engine'      => 'string',
        'suspicious'  => 'boolean',
        'updated_at'  => 'string?',
    );
}

function translate_api_strings_list($params)
{
    translate_api_boot();

    $row = translate_api_language_or_422($params['language']);
    $language = (string) $row['code'];
    $limit = (int) ($params['limit'] ?? 100);
    $where = array('u.string_id > 0');

    if (!empty($params['page_id'])) {
        $owner_where = pg_tr_owner_where(pg_tr_page_owners((int) $params['page_id']));
        $where[] = ($owner_where !== '') ? '(' . $owner_where . ')' : '0 = 1';
    }

    switch ((string) ($params['status'] ?? 'all')) {
        case 'pending':
            $where[] = 't.id IS NULL';
            break;
        case 'machine':
            $where[] = "t.status = 'machine'";
            break;
        case 'reviewed':
            $where[] = "t.status = 'reviewed'";
            break;
        case 'suspicious':
            $where[] = 't.suspicious = 1';
            break;
    }

    if (!empty($params['search'])) {
        $like = "'%" . escape_like($params['search']) . "%'";
        $where[] = "(s.source_text LIKE $like OR t.text LIKE $like)";
    }

    if (!empty($params['cursor'])) {
        $cursor = api_cursor_decode($params['cursor']);

        if ($cursor === null) {
            api_fail(400, 'invalid_cursor', lang('The cursor could not be read.'), 'cursor');
        }

        $where[] = 's.id > ' . (int) $cursor['i'];
    }

    $rows = db_items("SELECT DISTINCT s.id, s.hash, s.source_text, s.format, s.kind, t.text, t.engine, t.status, t.suspicious, t.updated_at
                      FROM translation_uses u
                      INNER JOIN translation_strings s ON s.id = u.string_id
                      LEFT JOIN translations t ON t.string_id = s.id AND t.language = '" . e($language) . "'
                      WHERE " . implode(' AND ', $where) . "
                      ORDER BY s.id
                      LIMIT " . ($limit + 1));

    $rows = is_array($rows) ? $rows : array();
    $next = null;

    if (count($rows) > $limit) {
        array_pop($rows);
        $last = end($rows);
        $next = api_cursor_encode((int) $last['id'], (int) $last['id']);
    }

    $out = array();

    foreach ($rows as $string) {
        $out[] = translate_api_string_present($string);
    }

    api_ok_list($out, $limit, $next);
}

/* ---------------------------------------------------------------------------
   Pages
   --------------------------------------------------------------------------- */

/**
 * One page under one target language: where it is served and how far it is
 * translated there.
 */
function translate_api_page_language_present($page, $code, $row)
{
    $page_id = (int) $page['page_id'];
    $stats = pg_tr_page_stats($code);
    $own = isset($stats[$page_id]) ? $stats[$page_id] : translate_api_zero_stats();
    $total = (int) $own['total'];
    $all_reviewed = ($total > 0) && ((int) $own['reviewed'] >= $total);

    switch ((string) $row['index_policy']) {
        case 'none':
            $indexed = false;
            break;
        case 'all':
            $indexed = true;
            break;
        default:
            $indexed = $all_reviewed;
    }

    return array(
        'code'       => (string) $code,
        'url'        => translate_api_language_url($code) . rawurlencode((string) $page['page_name']),
        'total'      => $total,
        'translated' => (int) $own['translated'],
        'reviewed'   => (int) $own['reviewed'],
        'coverage'   => ($total > 0) ? (int) floor(((int) $own['translated'] * 100) / $total) : 0,
        'complete'   => ($total > 0) && ((int) $own['translated'] >= $total),
        'indexed'    => $indexed && ((int) $row['enabled'] === 1),
    );
}

function translate_api_page_present($page)
{
    $page_id = (int) $page['page_id'];
    $languages = array();

    foreach (pg_tr_languages(false) as $code => $row) {
        if ($code !== pg_tr_source_language()) {
            $languages[] = translate_api_page_language_present($page, $code, $row);
        }
    }

    return array(
        'id'        => $page_id,
        'name'      => (string) $page['page_name'],
        'title'     => (string) $page['page_title'],
        'url'       => translate_api_language_url(pg_tr_source_language()) . rawurlencode((string) $page['page_name']),
        'languages' => $languages,
    );
}

// What translate_api_page_present() returns.
function translate_api_page_schema()
{
    return array(
        'id'        => 'integer',
        'name'      => 'string',
        'title'     => 'string',
        'url'       => 'string',
        'languages' => array(array(
            'code'       => 'string',
            'url'        => 'string',
            'total'      => 'integer',
            'translated' => 'integer',
            'reviewed'   => 'integer',
            'coverage'   => 'integer',
            'complete'   => 'boolean',
            'indexed'    => 'boolean',
        )),
    );
}

function translate_api_pages_get($params)
{
    translate_api_boot();

    $page = db_item("SELECT page.page_id, page.page_name, page.page_title FROM page WHERE page.page_id = '" . (int) $params['id'] . "'" . pg_designer_not_binned_sql() . " LIMIT 1");

    if (!is_array($page)) {
        api_fail_not_found(lang('Page'));
    }

    api_ok(translate_api_page_present($page));
}

/* ---------------------------------------------------------------------------
   Bulk write
   --------------------------------------------------------------------------- */

/**
 * Writes one answer for a string after the checks every engine's answer
 * passes: the text cleaned for its format, tags and tokens in place.
 *
 * @return array('ok' => bool, 'reason' => string, 'suspicious' => bool)
 */
function translate_api_write_one($string, $language, $text, $engine, $status, $user_id, $force)
{
    $clean = pg_tr_sanitize_incoming((string) $text, $string['format']);

    if (trim($clean) === '') {
        return array('ok' => false, 'reason' => 'empty', 'suspicious' => false);
    }

    $check = pg_tr_result_check($string['source_text'], $clean, $string['format']);

    if (!$check['ok']) {
        return array('ok' => false, 'reason' => (string) $check['error'], 'suspicious' => false);
    }

    $written = pg_tr_save_translation((int) $string['id'], $language, $clean, $engine, $status, $user_id, $check['suspicious'] ? 1 : 0, $force);

    if ($written === false) {
        return array('ok' => false, 'reason' => 'reviewed_kept', 'suspicious' => false);
    }

    return array('ok' => true, 'reason' => '', 'suspicious' => (bool) $check['suspicious']);
}

// What translate_api_write() answers with.
function translate_api_write_result_schema()
{
    return array(
        'language' => 'string',
        'written'  => 'integer',
        'skipped'  => 'integer',
        'stale'    => 'string[]',
        'rejected' => array(array('hash' => 'string', 'reason' => 'string')),
    );
}

function translate_api_write($params)
{
    translate_api_boot();

    $row = translate_api_language_or_422($params['language']);
    $language = (string) $row['code'];
    $status = ((string) ($params['status'] ?? 'reviewed') === 'machine') ? 'machine' : 'reviewed';
    $force = !empty($params['overwrite']);
    $app = api_current_app();
    $user_id = (int) ($app['owner']['id'] ?? 0);

    $items = array();

    foreach ((array) $params['items'] as $index => $item) {
        if (!is_array($item) || !isset($item['hash']) || !preg_match('/^[0-9a-f]{40}$/', (string) $item['hash'])) {
            api_fail_validation(lang('Each item is an object with a hash and a text.'), 'items[' . $index . ']');
        }

        $items[] = array('hash' => (string) $item['hash'], 'text' => isset($item['text']) ? (string) $item['text'] : '');
    }

    $strings = pg_tr_strings_by_hash(array_map(function ($item) { return $item['hash']; }, $items));
    $result = array('language' => $language, 'written' => 0, 'skipped' => 0, 'stale' => array(), 'rejected' => array());

    foreach ($items as $item) {
        if (!isset($strings[$item['hash']])) {
            $result['stale'][] = $item['hash'];
            continue;
        }

        if (trim($item['text']) === '') {
            $result['skipped']++;
            continue;
        }

        $one = translate_api_write_one($strings[$item['hash']], $language, $item['text'], 'api', $status, $user_id, $force);

        if ($one['ok']) {
            $result['written']++;
        } elseif ($one['reason'] === 'reviewed_kept') {
            $result['skipped']++;
        } else {
            $result['rejected'][] = array('hash' => $item['hash'], 'reason' => $one['reason']);
        }
    }

    if ($result['written'] > 0) {
        log_activity(lang(array('string' => 'translations were written through the API ({var:1}, {var:2} text(s))', 'vars' => array($language, $result['written']))), (string) ($app['name'] ?? 'API'));
    }

    api_ok($result);
}

/* ---------------------------------------------------------------------------
   Claude's queue
   --------------------------------------------------------------------------- */

/**
 * A 403 for any application but the one Claude works through - and the
 * temporary credential an administrator's API Documentation screen makes for
 * "Try it", so the queue can be tried from there. That credential is the
 * administrator's own and lasts fifteen minutes; what it can do here (claim a
 * job, write machine translations) the same administrator can do on the
 * Translations screen.
 */
function translate_api_claude_guard()
{
    translate_api_boot();

    if (pg_tr_claude_is_caller() || translate_api_is_docs_trial()) {
        return;
    }

    api_fail(403, 'forbidden', lang('Only the application Claude works through may use this endpoint.'));
}

/**
 * Whether the call comes from an administrator's documentation-screen
 * credential (api_docs.php names it __test__<user id>).
 */
function translate_api_is_docs_trial()
{
    $app = api_current_app();

    if (!is_array($app) || !isset($app['name']) || (strpos((string) $app['name'], '__test__') !== 0)) {
        return false;
    }

    return isset($app['owner']['role']) && ((int) $app['owner']['role'] === 0)
        && ((string) $app['name'] === '__test__' . (int) ($app['owner_user_id'] ?? -1));
}

/**
 * One of Claude's jobs, or a 404.
 */
function translate_api_job_or_404($id)
{
    $job = pg_tr_job_get((int) $id);

    if (!$job || ($job['engine'] !== 'claude')) {
        api_fail_not_found(lang('Job'));
    }

    return $job;
}

function translate_api_job_present($job)
{
    $waiting = (int) db_value("SELECT COUNT(*) FROM translation_job_items WHERE job_id = '" . (int) $job['id'] . "' AND status = 'waiting'");

    return array(
        'id'          => (int) $job['id'],
        'status'      => (string) $job['status'],
        'source'      => pg_tr_source_language(),
        'target'      => (string) $job['language'],
        'engine'      => (string) $job['engine'],
        'scope'       => (string) $job['scope'],
        'total'       => (int) $job['total'],
        'done'        => (int) $job['done_count'],
        'failed'      => (int) $job['failed_count'],
        'waiting'     => $waiting,
        'error'       => (string) $job['error'],
        'created_at'  => api_time($job['created_at']),
        'claimed_at'  => ((int) $job['claimed_at'] > 0) ? api_time($job['claimed_at']) : null,
        'finished_at' => ((int) $job['finished_at'] > 0) ? api_time($job['finished_at']) : null,
    );
}

// What translate_api_job_present() returns.
function translate_api_job_schema()
{
    return array(
        'id'          => 'integer',
        'status'      => 'string',
        'source'      => 'string',
        'target'      => 'string',
        'engine'      => 'string',
        'scope'       => 'string',
        'total'       => 'integer',
        'done'        => 'integer',
        'failed'      => 'integer',
        'waiting'     => 'integer',
        'error'       => 'string',
        'created_at'  => 'string',
        'claimed_at'  => 'string?',
        'finished_at' => 'string?',
    );
}

function translate_api_jobs_list($params)
{
    translate_api_claude_guard();

    $status = (string) ($params['status'] ?? 'open');

    if ($status === 'all') {
        $rows = db_items("SELECT * FROM translation_jobs WHERE engine = 'claude' ORDER BY id DESC LIMIT 50");
    } else {
        $where = ($status === 'open') ? "status IN ('queued', 'sent')" : "status = '" . e($status) . "'";
        $rows = db_items("SELECT * FROM translation_jobs WHERE engine = 'claude' AND $where ORDER BY id ASC LIMIT 50");
    }

    $out = array();

    foreach ((array) $rows as $job) {
        $out[] = translate_api_job_present($job);
    }

    api_ok_list($out, 50);
}

function translate_api_jobs_claim($params)
{
    translate_api_claude_guard();

    $job = translate_api_job_or_404($params['id']);
    $stale = time() - PG_TR_CLAUDE_STALE;

    if (($job['status'] === 'running') && ((int) $job['claimed_at'] > $stale)) {
        api_fail(409, 'already_claimed', lang('Another run is working on this job.'));
    }

    if (!in_array($job['status'], array('queued', 'sent', 'running'), true)) {
        api_fail(409, 'closed', lang('This job is already closed.'));
    }

    // One run wins when two reach for the same job; a run that went quiet
    // loses it.
    db("UPDATE translation_jobs SET status = 'running', claimed_at = '" . time() . "'
        WHERE id = '" . (int) $job['id'] . "' AND (status IN ('queued', 'sent') OR (status = 'running' AND claimed_at <= '$stale'))");

    if (mysqli_affected_rows(db::$con) < 1) {
        api_fail(409, 'already_claimed', lang('Another run is working on this job.'));
    }

    api_ok(translate_api_job_present(translate_api_job_or_404($job['id'])));
}

// What translate_api_jobs_items() answers with.
function translate_api_job_items_schema()
{
    return array(
        'job'        => 'TranslationJob',
        'source'     => array('code' => 'string', 'label' => 'string'),
        'target'     => array('code' => 'string', 'label' => 'string'),
        'style_note' => 'string',
        'glossary'   => 'string[]',
        'items'      => array(array(
            'hash'       => 'string',
            'format'     => 'string',
            'kind'       => 'string',
            'text'       => 'string',
            'where'      => array('owner' => 'string', 'owner_type' => 'string', 'field' => 'string', 'before' => 'string', 'after' => 'string'),
            'previous'   => array('text' => 'string', 'translation' => 'string'),
            'glossary'   => 'string[]',
            'last_error' => 'string',
        )),
    );
}

function translate_api_jobs_items($params)
{
    translate_api_claude_guard();

    $job = translate_api_job_or_404($params['id']);
    $limit = min(PG_TR_API_JOB_BATCH, max(1, (int) ($params['limit'] ?? PG_TR_API_JOB_BATCH)));
    $known = pg_tr_known_languages();
    $source = pg_tr_source_language();
    $target = (string) $job['language'];
    $items = array();

    if (in_array($job['status'], array('queued', 'sent', 'running'), true)) {
        $strings = pg_tr_job_waiting($job['id'], $limit);
        $errors = array();

        if ($strings) {
            $ids = array();

            foreach ($strings as $string) {
                $ids[] = (int) $string['id'];
            }

            foreach ((array) db_items("SELECT string_id, error FROM translation_job_items WHERE job_id = '" . (int) $job['id'] . "' AND string_id IN (" . implode(',', $ids) . ") AND error <> ''") as $row) {
                $errors[(int) $row['string_id']] = (string) $row['error'];
            }
        }

        foreach (pg_tr_package_items($strings, $target) as $item) {
            $sid = (int) $item['string_id'];

            $items[] = array(
                'hash'       => $item['hash'],
                'format'     => $item['format'],
                'kind'       => $item['kind'],
                'text'       => $item['text'],
                'where'      => $item['where'],
                'previous'   => array(
                    'text'        => is_array($item['previous']) ? (string) $item['previous']['source'] : '',
                    'translation' => is_array($item['previous']) ? (string) $item['previous']['translation'] : '',
                ),
                'glossary'   => $item['glossary'],
                'last_error' => isset($errors[$sid]) ? $errors[$sid] : '',
            );
        }

        // Reading the items counts as activity: the run has the job.
        db("UPDATE translation_jobs SET claimed_at = '" . time() . "' WHERE id = '" . (int) $job['id'] . "' AND status = 'running'");
    }

    api_ok(array(
        'job'        => translate_api_job_present(translate_api_job_or_404($job['id'])),
        'source'     => array('code' => $source, 'label' => isset($known[$source]) ? $known[$source][0] : $source),
        'target'     => array('code' => $target, 'label' => isset($known[$target]) ? $known[$target][0] : $target),
        'style_note' => pg_tr_style_note(),
        'glossary'   => pg_tr_glossary_lines(pg_tr_glossary($target)),
        'items'      => $items,
    ));
}

// What translate_api_jobs_results() answers with.
function translate_api_job_results_schema()
{
    return array(
        'job'      => 'TranslationJob',
        'written'  => 'integer',
        'skipped'  => 'integer',
        'stale'    => 'string[]',
        'rejected' => array(array('hash' => 'string', 'reason' => 'string', 'retry' => 'boolean')),
    );
}

function translate_api_jobs_results($params)
{
    translate_api_claude_guard();

    $job = translate_api_job_or_404($params['id']);

    if (!in_array($job['status'], array('queued', 'sent', 'running'), true)) {
        api_fail(409, 'closed', lang('This job is already closed.'));
    }

    $job_id = (int) $job['id'];
    $language = (string) $job['language'];
    $result = array('written' => 0, 'skipped' => 0, 'stale' => array(), 'rejected' => array());
    $answers = array();

    foreach ((array) $params['results'] as $index => $item) {
        if (!is_array($item) || !isset($item['hash']) || !preg_match('/^[0-9a-f]{40}$/', (string) $item['hash'])) {
            api_fail_validation(lang('Each result is an object with a hash and a text.'), 'results[' . $index . ']');
        }

        $answers[] = array(
            'hash'   => (string) $item['hash'],
            'text'   => isset($item['text']) ? (string) $item['text'] : '',
            'skip'   => !empty($item['skip']),
            'reason' => isset($item['reason']) ? mb_substr((string) $item['reason'], 0, 255) : '',
        );
    }

    $strings = pg_tr_strings_by_hash(array_map(function ($answer) { return $answer['hash']; }, $answers));

    // The run has the job from here on, whatever its state was.
    db("UPDATE translation_jobs SET status = 'running', claimed_at = '" . time() . "' WHERE id = '$job_id' AND status IN ('queued', 'sent', 'running')");

    foreach ($answers as $answer) {
        if (!isset($strings[$answer['hash']])) {
            $result['stale'][] = $answer['hash'];
            continue;
        }

        $string = $strings[$answer['hash']];
        $string_id = (int) $string['id'];
        $item = db_item("SELECT status, error FROM translation_job_items WHERE job_id = '$job_id' AND string_id = '$string_id' LIMIT 1");

        if (!is_array($item)) {
            $result['stale'][] = $answer['hash'];
            continue;
        }

        if ($item['status'] !== 'waiting') {
            $result['skipped']++;
            continue;
        }

        if ($answer['skip']) {
            pg_tr_job_item_done($job_id, $string_id, 'skipped', ($answer['reason'] !== '') ? $answer['reason'] : 'skipped by the run');
            $result['skipped']++;
            continue;
        }

        $accepted = pg_tr_accept_result($string, $language, $answer['text'], 'claude', (int) $job['created_by']);

        if ($accepted['ok']) {
            pg_tr_job_item_done($job_id, $string_id, 'done');
            $result['written']++;
            continue;
        }

        // A refused answer is asked once more, with the reason beside the
        // item; the second refusal gives the item up.
        if ((string) $item['error'] === '') {
            db("UPDATE translation_job_items SET error = '" . e(mb_substr((string) $accepted['error'], 0, 255)) . "'
                WHERE job_id = '$job_id' AND string_id = '$string_id' AND status = 'waiting'");
            $result['rejected'][] = array('hash' => $answer['hash'], 'reason' => (string) $accepted['error'], 'retry' => true);
        } else {
            pg_tr_job_item_done($job_id, $string_id, 'failed', $accepted['error']);
            $result['rejected'][] = array('hash' => $answer['hash'], 'reason' => (string) $accepted['error'], 'retry' => false);
        }
    }

    pg_tr_job_close_if_complete($job_id);

    api_ok(array('job' => translate_api_job_present(translate_api_job_or_404($job_id))) + $result);
}

function translate_api_jobs_fail($params)
{
    translate_api_claude_guard();

    $job = translate_api_job_or_404($params['id']);

    if (!in_array($job['status'], array('queued', 'sent', 'running'), true)) {
        api_fail(409, 'closed', lang('This job is already closed.'));
    }

    pg_tr_job_fail($job['id'], (string) $params['reason']);

    api_ok(translate_api_job_present(translate_api_job_or_404($job['id'])));
}
