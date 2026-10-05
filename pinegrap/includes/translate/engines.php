<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Front-end translation - the engines: the registry, and what every engine
 * shares - how a text is split into the runs an engine may translate, how its
 * answer is put back together, and how that answer is checked before it is
 * written.
 *
 * An engine only ever writes to the translation table, and what it returns is
 * untrusted input: the tokens and tags the source carried have to come back,
 * the markup is reduced to what a text may hold, and an answer whose length
 * is out of all proportion to the source is marked suspicious.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/translate/context.php');
require_once(PG_FUNCTIONS_DIR . '/includes/translate/engine_google.php');
require_once(PG_FUNCTIONS_DIR . '/includes/translate/engine_ai.php');
require_once(PG_FUNCTIONS_DIR . '/includes/translate/claude.php');

/**
 * The engines, keyed as site_languages.engine stores them.
 *
 *   kind     server  - runs here, in the request or the scheduled job
 *            browser - runs in the operator's browser (the panel sends texts
 *                      and takes answers back)
 *            queue   - an outside agent (Claude's routine) is told there is
 *                      work and fetches it through the external API
 *            panel   - no engine at all: typed or imported by hand
 *   html     the engine takes inline markup as it is; otherwise texts are
 *            split into plain runs
 *   ready    whether it can run on this site right now
 *   reason   why not, when it cannot
 *   retry    the reason is temporary (a hold after a failed call): a job
 *            waits rather than failing
 *
 * @return array
 */
function pg_tr_engines()
{
    static $engines = null;

    if ($engines !== null) {
        return $engines;
    }

    $google_key = pg_tr_google_key();
    $ai = pg_ai_state();
    $claude = pg_tr_claude_state();

    $engines = array(
        'manual' => array(
            'label'  => lang('By hand'),
            'kind'   => 'panel',
            'html'   => true,
            'ready'  => true,
            'reason' => '',
        ),
        'google' => array(
            'label'  => lang('Google Cloud Translation'),
            'kind'   => 'server',
            'html'   => true,
            'ready'  => ($google_key !== ''),
            'reason' => ($google_key !== '') ? '' : lang('No Google Cloud API key is stored. Enter one in Settings > Languages and Translation.'),
        ),
        'chrome' => array(
            'label'  => lang('Browser (Chrome Translator API)'),
            'kind'   => 'browser',
            'html'   => false,
            'ready'  => true,
            'reason' => '',
        ),
        'ai' => array(
            'label'  => 'Pinegrap AI',
            'kind'   => 'server',
            'html'   => true,
            'ready'  => $ai['ready'],
            'reason' => $ai['reason'],
            'retry'  => $ai['retry'],
        ),
        'claude' => array(
            'label'  => 'Claude',
            'kind'   => 'queue',
            'html'   => true,
            'ready'  => $claude['ready'],
            'reason' => $claude['reason'],
        ),
        'import' => array(
            'label'  => lang('CSV import'),
            'kind'   => 'panel',
            'html'   => true,
            'ready'  => true,
            'reason' => '',
        ),
    );

    return $engines;
}

/**
 * One engine's entry, or null.
 */
function pg_tr_engine($key)
{
    $engines = pg_tr_engines();

    return isset($engines[$key]) ? $engines[$key] : null;
}

/**
 * The engines a language may be set to: everything but CSV import, which is
 * not an engine. Pinegrap AI and Claude are offered only while the Workspace
 * module, whose connections they use, is switched on.
 *
 * @param mixed $keep true for every engine whatever the Workspace says (what
 *                    a saved value is checked against), or the keys to list
 *                    anyway - the engine a language is set to now, so a
 *                    select never shows another one than the stored value
 * @return array key => label
 */
function pg_tr_engine_options($keep = array())
{
    $options = array();
    $workspace = defined('WORKSPACE_ENABLED') && WORKSPACE_ENABLED;

    foreach (pg_tr_engines() as $key => $engine) {
        if ($key === 'import') {
            continue;
        }

        if (in_array($key, array('ai', 'claude'), true) && !$workspace && ($keep !== true) && !in_array($key, (array) $keep, true)) {
            continue;
        }

        $options[$key] = $engine['label'];
    }

    return $options;
}

/**
 * Where a browser shows the state of its on-device translation models. These
 * are browser pages: a web page cannot open them with a link, so the screens
 * print them to be copied into the address bar.
 *
 * @return array of array(label, address, what it shows)
 */
function pg_tr_browser_pages()
{
    return array(
        array('Chrome', 'chrome://on-device-translation-internals', lang('The language packs: installed, or install one by hand.')),
        array('Chrome', 'chrome://components', lang('The TranslateKit component the packs run on: "Check for update" when it shows 0.0.0.0.')),
        array('Edge', 'edge://components', lang('The TranslateKit component the packs run on: "Check for update" when it shows 0.0.0.0.')),
    );
}

/**
 * The browser pages as a small list with a copy button each, for the
 * Translations screen and the settings card.
 *
 * @return string HTML
 */
function pg_tr_browser_pages_html()
{
    $rows = '';

    foreach (pg_tr_browser_pages() as $page) {
        $rows .= '<div class="d-flex align-items-center gap-2 mt-1 flex-wrap">'
            . '<span class="badge text-bg-light fw-normal">' . h($page[0]) . '</span>'
            . '<code class="user-select-all">' . h($page[1]) . '</code>'
            . '<button type="button" class="btn btn-sm btn-ghost btn-icon py-0 pg-tr-copy" data-copy="' . h($page[1]) . '" onclick="if (navigator.clipboard) { navigator.clipboard.writeText(this.getAttribute(\'data-copy\')); this.querySelector(\'i\').className = \'bi bi-clipboard-check\'; }" title="' . h(lang('Copy')) . '" aria-label="' . h(lang('Copy')) . '"><i class="bi bi-clipboard" aria-hidden="true"></i></button>'
            . '<span class="text-body-secondary">' . h($page[2]) . '</span>'
            . '</div>';
    }

    return '<div class="small mt-2">' . h(lang('Copy the address into the browser\'s address bar; browsers do not open these pages from a link.')) . $rows . '</div>';
}

/* ---------------------------------------------------------------------------
   Runs: what an engine may translate, what it must leave alone
   --------------------------------------------------------------------------- */

/**
 * The fixed parts of a text: data tokens the renderer fills in, and the
 * placeholders of the language file.
 */
function pg_tr_tokens_of($text)
{
    preg_match_all('/\^\^[a-z0-9_]+\^\^(?:%%[^%]*%%)?|\{[a-z_]+(?::[0-9]+)?(?:\|[a-z])?\}/i', (string) $text, $matches);

    return $matches[0];
}

/**
 * The patterns that match glossary terms an engine must leave as written,
 * as whole words; a term the glossary spells with capitals is matched as
 * spelled, the others in any case. For a /u pattern with the / delimiter.
 *
 * @param array $keep_terms array of term, or of array(term, case_sensitive)
 * @return array of pattern fragments
 */
function pg_tr_keep_patterns($keep_terms)
{
    $patterns = array();

    foreach ((array) $keep_terms as $keep) {
        $term = is_array($keep) ? (string) $keep[0] : (string) $keep;
        $case_sensitive = is_array($keep) && !empty($keep[1]);

        if ($term === '') {
            continue;
        }

        $quoted = preg_quote($term, '/');
        $patterns[] = '(?<![\p{L}\p{N}])' . ($case_sensitive ? $quoted : '(?i:' . $quoted . ')') . '(?![\p{L}\p{N}])';
    }

    return $patterns;
}

/**
 * A text split into pieces: the runs of words an engine translates and the
 * fixed pieces (tags, tokens) that are put back around its answers.
 *
 * An inline text is split at every tag; a plain text only at its tokens. A
 * run of whitespace alone is a fixed piece.
 *
 * @param string $text   normalised text
 * @param string $format text | inline
 * @return array of array('type' => 'run'|'fixed', 'text' => string)
 */
function pg_tr_runs_split($text, $format = 'text', $keep_terms = array())
{
    $fixed = array(
        '\^\^(?i:[a-z0-9_]+)\^\^(?:%%[^%]*%%)?',
        '\{(?i:[a-z_]+)(?::[0-9]+)?(?:\|(?i:[a-z]))?\}',
    );

    if ($format === 'inline') {
        array_unshift($fixed, '<[^>]+>');
    }

    // Glossary terms that are never translated are fixed pieces too.
    $fixed = array_merge($fixed, pg_tr_keep_patterns($keep_terms));

    $alternation = '(' . implode('|', $fixed) . ')';
    $pattern = '/' . $alternation . '/u';

    $parts = preg_split($pattern, (string) $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
    $pieces = array();

    foreach ($parts as $part) {
        // A tag, a token, a kept term, whitespace, or a piece without a letter
        // in it (a dash, an ampersand, a number) is nothing an engine should
        // be asked.
        $is_fixed = preg_match('/^' . $alternation . '$/u', $part) || (trim($part) === '') || !preg_match('/\p{L}/u', $part);

        if ($is_fixed) {
            $pieces[] = array('type' => 'fixed', 'text' => $part);
            continue;
        }

        // Leading and trailing spaces stay fixed, so an engine that trims
        // does not glue two words together across a tag.
        $lead = (preg_match('/^\s+/', $part, $m)) ? $m[0] : '';
        $trail = (preg_match('/\s+$/', $part, $m)) ? $m[0] : '';
        $core = trim($part);

        if ($lead !== '') {
            $pieces[] = array('type' => 'fixed', 'text' => $lead);
        }

        // An inline run is HTML text: entities are resolved for the engine
        // and escaped again when the answer is joined.
        $pieces[] = array('type' => 'run', 'text' => ($format === 'inline') ? html_entity_decode($core, ENT_QUOTES | ENT_HTML5, 'UTF-8') : $core);

        if ($trail !== '') {
            $pieces[] = array('type' => 'fixed', 'text' => $trail);
        }
    }

    return $pieces;
}

/**
 * The runs alone, in order, for an engine that takes plain text.
 *
 * @return array index => text
 */
function pg_tr_runs_only($pieces)
{
    $runs = array();

    foreach ($pieces as $piece) {
        if ($piece['type'] === 'run') {
            $runs[] = $piece['text'];
        }
    }

    return $runs;
}

/**
 * The pieces joined back with the engine's answers in place of the runs.
 *
 * @param array  $pieces   from pg_tr_runs_split()
 * @param array  $answers  index => translated run, same count and order
 * @param string $format
 * @return string|false false when the answers do not fit the runs
 */
function pg_tr_runs_join($pieces, $answers, $format = 'text')
{
    $answers = array_values((array) $answers);
    $expected = count(pg_tr_runs_only($pieces));

    if (count($answers) !== $expected) {
        return false;
    }

    $out = '';
    $i = 0;

    foreach ($pieces as $piece) {
        if ($piece['type'] === 'fixed') {
            $out .= $piece['text'];
            continue;
        }

        $answer = trim((string) $answers[$i++]);

        if ($answer === '') {
            return false;
        }

        $out .= ($format === 'inline') ? pg_tr_text_escape($answer) : $answer;
    }

    return $out;
}

/**
 * Whether an engine's answer may be written: the tokens of the source are all
 * there, the same number of times, and nothing came back empty. Also whether
 * it is worth a second look: a length out of proportion (shorter than a fifth
 * or longer than five times the source).
 *
 * @param string $source     normalised source
 * @param string $translated sanitised answer
 * @param string $format
 * @return array('ok' => bool, 'suspicious' => bool, 'error' => string)
 */
function pg_tr_result_check($source, $translated, $format = 'text')
{
    $translated = (string) $translated;

    if (trim($translated) === '') {
        return array('ok' => false, 'suspicious' => false, 'error' => 'empty');
    }

    $source_tokens = pg_tr_tokens_of($source);
    $result_tokens = pg_tr_tokens_of($translated);
    sort($source_tokens);
    sort($result_tokens);

    if ($source_tokens !== $result_tokens) {
        return array('ok' => false, 'suspicious' => false, 'error' => 'placeholders');
    }

    if ($format === 'inline') {
        // The same tags, the same number of times, in any order.
        preg_match_all('/<\/?([a-z][a-z0-9]*)\b/i', $source, $s);
        preg_match_all('/<\/?([a-z][a-z0-9]*)\b/i', $translated, $t);
        $s = array_map('strtolower', $s[1]);
        $t = array_map('strtolower', $t[1]);
        sort($s);
        sort($t);

        if ($s !== $t) {
            return array('ok' => false, 'suspicious' => false, 'error' => 'markup');
        }
    }

    $source_length = max(1, mb_strlen(trim(strip_tags($source))));
    $result_length = mb_strlen(trim(strip_tags($translated)));
    $ratio = $result_length / $source_length;
    $suspicious = (($source_length > 3) && (($ratio < 0.2) || ($ratio > 5)));

    // An answer that is the source word for word is written - "Blog" is
    // "Blog" in most languages, and refusing it would send it again on every
    // run - but marked for a look, unless nothing in it was there to
    // translate (a brand name the glossary keeps).
    if ((mb_strtolower(trim($translated)) === mb_strtolower(trim($source))) && pg_tr_runs_only(pg_tr_runs_split($source, $format))) {
        $suspicious = true;
    }

    return array('ok' => true, 'suspicious' => $suspicious, 'error' => '');
}

/**
 * Writes one engine answer for a string, after sanitising and checking it.
 *
 * @param array  $string     translation_strings row
 * @param string $language
 * @param string $translated raw answer (joined text, or the engine's HTML)
 * @param string $engine
 * @param int    $user_id
 * @return array('ok' => bool, 'error' => string, 'suspicious' => bool)
 */
function pg_tr_accept_result($string, $language, $translated, $engine, $user_id = 0)
{
    $format = $string['format'];
    $clean = pg_tr_sanitize_incoming($translated, $format);
    $check = pg_tr_result_check($string['source_text'], $clean, $format);

    if (!$check['ok']) {
        return array('ok' => false, 'error' => $check['error'], 'suspicious' => false);
    }

    pg_tr_save_translation((int) $string['id'], $language, $clean, $engine, 'machine', $user_id, $check['suspicious'] ? 1 : 0);

    return array('ok' => true, 'error' => '', 'suspicious' => $check['suspicious']);
}
