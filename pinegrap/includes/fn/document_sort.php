<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * "Sort it for me" of the designer's "From a document" start
 * (designer_document.php, POST sort): the rows' sections go to Pinegrap AI,
 * which proposes another kind for a section and a shorter text for a long
 * passage. One pg_ai_chat() call, under the limits of "Write with AI"
 * (includes/fn/ai_write.php): its per-person pace and the site's daily
 * allowance. Nothing is written: each proposal is accepted or left in the
 * browser, and only what the person presses changes a row.
 *
 * Loaded by designer_document.php only, not by functions.php.
 *
 * Memory: docs/memory/designer/document-sort.md
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

// Presses a person may make in a minute.
define('PG_DOCUMENT_SORT_PER_MINUTE', 6);
// Sections one press sends.
define('PG_DOCUMENT_SORT_SECTIONS', 60);
// Characters of one section's words sent; a passage cut here gets no shorter text.
define('PG_DOCUMENT_SORT_CHARS', 12000);
// Characters of all sections together.
define('PG_DOCUMENT_SORT_TOTAL', 60000);
// A passage over this many words may get a shorter text (the reader's own mark).
define('PG_DOCUMENT_SORT_LONG', 300);

/**
 * The kinds of the document reader (assets/js/pg_document_reader.js KINDS).
 *
 * @return array
 */
function pg_document_sort_kinds()
{
    return array('intro', 'text', 'cards', 'questions', 'figures', 'testimonial', 'table', 'prices', 'contact', 'picture', 'gallery');
}

/**
 * Whether "Sort it for me" is offered: Pinegrap AI writes on this site
 * ("Write with AI" on and Pinegrap AI set up). The form is a designer's
 * already, so the person may use it.
 *
 * @return bool
 */
function pg_document_sort_on()
{
    return function_exists('pg_ai_write_engine') && (pg_ai_write_engine() === 'pinegrap');
}

/**
 * Words of a text.
 *
 * @param string $text
 * @return int
 */
function pg_document_sort_words($text)
{
    $words = preg_split('/\s+/u', trim((string) $text), -1, PREG_SPLIT_NO_EMPTY);

    return is_array($words) ? count($words) : 0;
}

/**
 * A text without markup, its paragraphs kept apart by one blank line and
 * the spaces inside them made one; at most $max characters.
 *
 * @param mixed $text
 * @param int   $max
 * @return string
 */
function pg_document_sort_plain($text, $max)
{
    $text = is_scalar($text) ? (string) $text : '';
    $text = html_entity_decode(strip_tags($text), ENT_QUOTES, 'UTF-8');
    $parts = array();

    foreach (preg_split('/\n\s*\n/u', str_replace("\r", '', $text)) as $part) {
        $part = trim((string) preg_replace('/\s+/u', ' ', $part));
        if ($part !== '') {
            $parts[] = $part;
        }
    }

    return mb_substr(implode("\n\n", $parts), 0, max(0, (int) $max));
}

/**
 * The posted sections, checked: an id the reader gives (p + a number), a
 * known kind, the kinds the words can fill, the heading and the words as
 * plain text within the limits. A section whose words were cut is marked,
 * so no shorter text is asked for it.
 *
 * @param mixed $posted the decoded JSON: [{id, kind, fits, heading, text, unsure}]
 * @return array sections, error
 */
function pg_document_sort_input($posted)
{
    $kinds = pg_document_sort_kinds();
    $out = array();
    $total = 0;

    if (!is_array($posted) || !$posted) {
        return array('sections' => array(), 'error' => lang('There are no sections to sort.'));
    }

    foreach (array_slice(array_values($posted), 0, PG_DOCUMENT_SORT_SECTIONS) as $s) {
        if (!is_array($s) || !preg_match('/^p\d{1,6}$/', (string) ($s['id'] ?? '')) || !in_array($s['kind'] ?? '', $kinds, true)) {
            continue;
        }

        $full = pg_document_sort_plain($s['text'] ?? '', PG_DOCUMENT_SORT_CHARS + 1);
        $room = max(0, min(PG_DOCUMENT_SORT_CHARS, PG_DOCUMENT_SORT_TOTAL - $total));
        $text = mb_substr($full, 0, $room);
        $total += mb_strlen($text);
        $fits = array_values(array_intersect($kinds, array_map('strval', (array) ($s['fits'] ?? array()))));

        $out[(string) $s['id']] = array(
            'id' => (string) $s['id'],
            'kind' => (string) $s['kind'],
            'fits' => $fits ?: array((string) $s['kind']),
            'heading' => pg_document_sort_plain($s['heading'] ?? '', 200),
            'text' => $text,
            'words' => pg_document_sort_words($text),
            'cut' => mb_strlen($full) > mb_strlen($text),
            'unsure' => !empty($s['unsure']),
        );
    }

    return array('sections' => $out, 'error' => $out ? '' : lang('There are no sections to sort.'));
}

/**
 * The prompt: the rules, the shared prompt rules, the answer's shape last;
 * the sections as data.
 *
 * @param array  $sections pg_document_sort_input()['sections']
 * @param string $language the site's language, by name
 * @return array messages
 */
function pg_document_sort_messages($sections, $language)
{
    $rules = array(
        'You check how a document was read into the sections of one web page, and propose changes a person accepts or leaves.',
        'Each section has a kind, the kinds its words can fill (fits) and whether the reader was unsure. Kinds: intro (the page\'s opening), text, cards (short items each with a title), questions (questions with their answers), figures (numbers with a label), testimonial (a quotation with a name), table, prices (a price list), contact (e-mail, phone, address), picture, gallery.',
        'Propose a kind only from the section\'s fits and only when it is not its kind: for an unsure section when another kind fits its words better, for a sure one only when its words clearly are another kind.',
        'For a section of kind text or intro over ' . PG_DOCUMENT_SORT_LONG . ' words whose words are not cut, you may propose a shorter text in ' . $language . ' (the language of the words when they are in another): about a quarter as long, the same facts in the same order, no fact, name, figure or claim that is not in the words, paragraphs apart by a blank line, plain text.',
        'Propose nothing where the section is fine. Never propose a change of a heading.',
    );
    // The shared rules, then the answer's shape as the last line.
    $rules = array_merge($rules, pg_ai_prompt_rules('writer', ''));
    $rules[] = 'Answer only with JSON: {"proposals": [{"id": "p3", "kind": "prices"}, {"id": "p7", "short": "..."}]}, at most one kind and one shorter text per section; {"proposals": []} when there is nothing to propose.';

    $data = array();

    foreach ($sections as $s) {
        $data[] = array('id' => $s['id'], 'kind' => $s['kind'], 'fits' => $s['fits'], 'unsure' => $s['unsure'],
            'heading' => $s['heading'], 'words' => $s['words'], 'cut' => $s['cut'], 'text' => $s['text']);
    }

    return array(
        array('role' => 'system', 'content' => implode("\n", $rules)),
        array('role' => 'user', 'content' => pg_ai_data_block('sections', (string) json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE))),
    );
}

/**
 * The JSON object of a model's answer, read past a think block, a code
 * fence or words around it; null when there is none.
 *
 * @param string $text
 * @return array|null
 */
function pg_document_sort_json($text)
{
    $text = (string) preg_replace('/<think>.*?<\/think>/isu', '', (string) $text);
    $start = strpos($text, '{');
    $end = strrpos($text, '}');

    if ($start === false || $end === false || $end <= $start) {
        return null;
    }

    $json = json_decode(substr($text, $start, $end - $start + 1), true);

    return is_array($json) ? $json : null;
}

/**
 * The proposals kept from an answer: a kind from the section's fits other
 * than its own; a shorter text only for a long passage sent whole, of
 * text or intro, and at most four fifths of its words. One of each per
 * section, in the sections' order.
 *
 * @param mixed $answer   pg_document_sort_json()
 * @param array $sections pg_document_sort_input()['sections']
 * @return array|null [{id, kind?, short?, words?}], null for an answer that cannot be read
 */
function pg_document_sort_proposals($answer, $sections)
{
    if (!is_array($answer) || !isset($answer['proposals']) || !is_array($answer['proposals'])) {
        return null;
    }

    $kept = array();

    foreach ($answer['proposals'] as $p) {
        $id = is_array($p) ? (string) ($p['id'] ?? '') : '';

        if (!isset($sections[$id])) {
            continue;
        }

        $s = $sections[$id];
        $kind = is_string($p['kind'] ?? null) ? strtolower(trim($p['kind'])) : '';

        if ($kind !== '' && $kind !== $s['kind'] && in_array($kind, $s['fits'], true) && !isset($kept[$id]['kind'])) {
            $kept[$id]['kind'] = $kind;
        }

        $short = isset($p['short']) ? pg_document_sort_plain($p['short'], PG_DOCUMENT_SORT_CHARS) : '';
        $words = pg_document_sort_words($short);

        if ($short !== '' && !isset($kept[$id]['short']) && !$s['cut'] && in_array($s['kind'], array('text', 'intro'), true)
            && $s['words'] > PG_DOCUMENT_SORT_LONG && $words >= 10 && $words <= floor($s['words'] * 4 / 5)) {
            $kept[$id]['short'] = $short;
            $kept[$id]['words'] = $words;
        }
    }

    $out = array();

    foreach ($sections as $id => $s) {
        if (isset($kept[$id])) {
            $out[] = array('id' => $id) + $kept[$id];
        }
    }

    return $out;
}

/**
 * One press of "Sort it for me": the proposals, or why there are none.
 * off is true when Pinegrap AI is not there, so the form takes the button
 * away.
 *
 * @param mixed $posted the decoded sections
 * @param array $user   validate_user()
 * @return array the JSON answer
 */
function pg_document_sort_request($posted, $user)
{
    $fail = function ($message, $off = false) {
        return array('status' => 'error', 'message' => $message, 'off' => $off);
    };

    $problem = pg_ai_write_problem();

    if ($problem !== '') {
        return $fail(lang('Pinegrap AI is off.') . ' ' . $problem, true);
    }

    $in = pg_document_sort_input($posted);

    if ($in['error'] !== '') {
        return $fail($in['error']);
    }

    $state = pg_ai_state();

    if (!$state['ready']) {
        return $fail($state['reason']);
    }

    if (function_exists('waf_rate_exceeded') && waf_rate_exceeded('document_sort|' . (int) $user['id'], 'document_sort', PG_DOCUMENT_SORT_PER_MINUTE, 60)) {
        return $fail(lang('You are asking for drafts too quickly. Please wait a moment.'));
    }

    $cap = pg_ai_write_daily_cap();

    if (!pg_ai_write_usage_take($cap)['taken']) {
        return $fail(lang(array('string' => 'This site has used its {var:1} drafts for today. Writing with AI is open again tomorrow.', 'vars' => $cap)));
    }

    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }

    if (function_exists('set_time_limit') && function_exists('ws_ai_call_timeout')) {
        @set_time_limit(ws_ai_call_timeout() + 30);
    }

    $reply = pg_ai_chat(pg_document_sort_messages($in['sections'], pg_ai_write_site_language()['name']), array('temperature' => 0.2, 'max_tokens' => 6000));

    if (!$reply['ok']) {
        return $fail($reply['error']);
    }

    $proposals = pg_document_sort_proposals(pg_document_sort_json($reply['text']), $in['sections']);

    return ($proposals === null) ? $fail(lang('Pinegrap AI did not answer.')) : array('status' => 'success', 'proposals' => $proposals);
}
