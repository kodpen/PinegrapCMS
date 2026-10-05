<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Front-end translation - the context an engine that understands context is
 * given: the glossary, the style note, and for every text where it stands
 * (the page, the field, the texts before and after it, the translation its
 * previous wording had).
 *
 * Google and the browser translate words; the AI engines (Pinegrap AI,
 * Claude, an application through the API) translate texts, and a text is
 * translated better when the translator knows it is a button label on the
 * contact page than when it arrives alone.
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
   Glossary
   --------------------------------------------------------------------------- */

/**
 * The glossary rows that apply to a language: the ones for every language
 * ('') and the ones for this one, longer terms first so "Pinegrap CMS" is
 * matched before "Pinegrap".
 *
 * @param string $language
 * @return array rows (id, language, term, translation, keep, note, case_sensitive)
 */
function pg_tr_glossary($language)
{
    static $cache = array();

    if (isset($cache[$language])) {
        return $cache[$language];
    }

    $rows = db_items("SELECT * FROM translation_glossary WHERE language = '' OR language = '" . e($language) . "'
                      ORDER BY CHAR_LENGTH(term) DESC, id");

    $cache[$language] = is_array($rows) ? $rows : array();

    return $cache[$language];
}

/**
 * Whether a term occurs in a text, as a whole word.
 */
function pg_tr_glossary_hit($term, $text, $case_sensitive)
{
    $pattern = '/(?<![\p{L}\p{N}])' . preg_quote($term, '/') . '(?![\p{L}\p{N}])/u' . ($case_sensitive ? '' : 'i');

    return (bool) preg_match($pattern, strip_tags((string) $text));
}

/**
 * The glossary rows a text touches.
 *
 * @param string $text
 * @param array  $glossary from pg_tr_glossary()
 * @return array rows
 */
function pg_tr_glossary_matches($text, $glossary)
{
    $matches = array();

    foreach ($glossary as $row) {
        if (($row['term'] !== '') && pg_tr_glossary_hit($row['term'], $text, (int) $row['case_sensitive'] === 1)) {
            $matches[] = $row;
        }
    }

    return $matches;
}

/**
 * The terms that are not translated at all (brand names, product names):
 * what the runs splitter and the Google protection keep as they are.
 *
 * @param string $language
 * @return array of array(term, case_sensitive)
 */
function pg_tr_keep_terms($language)
{
    $terms = array();

    foreach (pg_tr_glossary($language) as $row) {
        if (((int) $row['keep'] === 1) && ($row['term'] !== '')) {
            $terms[] = array($row['term'], (int) $row['case_sensitive'] === 1);
        }
    }

    return $terms;
}

/**
 * The glossary as a few lines for an engine's instructions: "term → its
 * translation" and "term (keep as it is)".
 *
 * @param array $matches rows
 * @return array lines
 */
function pg_tr_glossary_lines($matches)
{
    $lines = array();

    foreach ($matches as $row) {
        if ((int) $row['keep'] === 1) {
            $lines[] = $row['term'] . ' = keep exactly as written' . (($row['note'] !== '') ? ' (' . $row['note'] . ')' : '');
        } elseif ($row['translation'] !== '') {
            $lines[] = $row['term'] . ' = ' . $row['translation'] . (($row['note'] !== '') ? ' (' . $row['note'] . ')' : '');
        }
    }

    return array_values(array_unique($lines));
}

/**
 * The style note the operator wrote for the AI engines.
 */
function pg_tr_style_note()
{
    return defined('TRANSLATION_STYLE_NOTE') ? trim((string) TRANSLATION_STYLE_NOTE) : '';
}

/* ---------------------------------------------------------------------------
   Where a text stands
   --------------------------------------------------------------------------- */

/**
 * For a set of strings: the page (or component or menu) each one is drawn
 * on, its field, the texts before and after it there, and the translation of
 * the wording it replaced. One place per string - the first use found - is
 * enough for a translator.
 *
 * @param array  $string_ids
 * @param string $language
 * @return array string_id => array(owner, owner_type, field, before, after, previous_translation)
 */
function pg_tr_context($string_ids, $language)
{
    $string_ids = array_values(array_unique(array_map('intval', (array) $string_ids)));
    $out = array();

    if (!$string_ids) {
        return $out;
    }

    $list = implode(',', $string_ids);

    // The first use of every string: owner types in the order a reader would
    // name them (the page before the component it sits in).
    $uses = db_items("SELECT u.string_id, u.owner_type, u.owner_id, u.field, u.position, u.prev_string_id
                      FROM translation_uses u
                      WHERE u.string_id IN ($list) AND u.string_id > 0
                      ORDER BY FIELD(u.owner_type, 'page', 'page_seo', 'shared', 'menu', 'product', 'product_group', 'attribute', 'form'), u.owner_id, u.position");

    $first = array();

    if (is_array($uses)) {
        foreach ($uses as $use) {
            $sid = (int) $use['string_id'];

            if (!isset($first[$sid])) {
                $first[$sid] = $use;
            }
        }
    }

    // The owners' names, one query per kind.
    $owners = array('page' => array(), 'shared' => array(), 'menu' => array(), 'product' => array(), 'product_group' => array(), 'attribute' => array(), 'form' => array());

    foreach ($first as $use) {
        $type = ($use['owner_type'] === 'page_seo') ? 'page' : $use['owner_type'];

        if (isset($owners[$type])) {
            $owners[$type][(int) $use['owner_id']] = true;
        }
    }

    $names = array('page' => array(), 'shared' => array(), 'menu' => array(), 'product' => array(), 'product_group' => array(), 'attribute' => array(), 'form' => array());

    if ($owners['page']) {
        foreach ((array) db_items("SELECT page_id, page_name, page_title FROM page WHERE page_id IN (" . implode(',', array_keys($owners['page'])) . ")") as $row) {
            $names['page'][(int) $row['page_id']] = ($row['page_title'] !== '') ? $row['page_title'] . ' (/' . $row['page_name'] . ')' : '/' . $row['page_name'];
        }
    }

    if ($owners['shared']) {
        foreach ((array) db_items("SELECT id, name FROM shared_components WHERE id IN (" . implode(',', array_keys($owners['shared'])) . ")") as $row) {
            $names['shared'][(int) $row['id']] = (string) $row['name'];
        }
    }

    if ($owners['menu']) {
        foreach ((array) db_items("SELECT id, name FROM menus WHERE id IN (" . implode(',', array_keys($owners['menu'])) . ")") as $row) {
            $names['menu'][(int) $row['id']] = (string) $row['name'];
        }
    }

    // The catalog and the forms: what a translator should know the text is.
    foreach (array(
        'product'       => array("SELECT id, IF(short_description <> '', short_description, name) AS label FROM products WHERE id IN (%s)", lang('Product')),
        'product_group' => array("SELECT id, name AS label FROM product_groups WHERE id IN (%s)", lang('Product Group')),
        'attribute'     => array("SELECT id, name AS label FROM product_attributes WHERE id IN (%s)", lang('Product attribute')),
        'form'          => array("SELECT page_id AS id, form_name AS label FROM custom_form_pages WHERE page_id IN (%s)", lang('Form')),
    ) as $type => $spec) {
        if (!$owners[$type]) {
            continue;
        }

        foreach ((array) db_items(sprintf($spec[0], implode(',', array_map('intval', array_keys($owners[$type]))))) as $row) {
            $names[$type][(int) $row['id']] = $spec[1] . ': ' . strip_tags((string) $row['label']);
        }
    }

    // The neighbours: the texts just before and after in the same owner.
    $neighbour_where = array();

    foreach ($first as $use) {
        $neighbour_where[] = "(u.owner_type = '" . e($use['owner_type']) . "' AND u.owner_id = '" . (int) $use['owner_id'] . "' AND u.position BETWEEN '" . max(0, (int) $use['position'] - 1) . "' AND '" . ((int) $use['position'] + 1) . "')";
    }

    $neighbours = array();

    foreach (array_chunk($neighbour_where, 100) as $chunk) {
        $rows = db_items("SELECT u.owner_type, u.owner_id, u.position, s.source_text
                          FROM translation_uses u
                          INNER JOIN translation_strings s ON s.id = u.string_id
                          WHERE " . implode(' OR ', $chunk));

        if (is_array($rows)) {
            foreach ($rows as $row) {
                $neighbours[$row['owner_type'] . '|' . $row['owner_id'] . '|' . $row['position']] = strip_tags((string) $row['source_text']);
            }
        }
    }

    // The translation of the wording a text replaced.
    $previous_ids = array();

    foreach ($first as $use) {
        if ((int) $use['prev_string_id'] > 0) {
            $previous_ids[(int) $use['prev_string_id']] = true;
        }
    }

    $previous = array();

    if ($previous_ids) {
        $rows = db_items("SELECT s.id, s.source_text, t.text
                          FROM translation_strings s
                          INNER JOIN translations t ON t.string_id = s.id AND t.language = '" . e($language) . "'
                          WHERE s.id IN (" . implode(',', array_keys($previous_ids)) . ")");

        if (is_array($rows)) {
            foreach ($rows as $row) {
                $previous[(int) $row['id']] = array('source' => (string) $row['source_text'], 'translation' => (string) $row['text']);
            }
        }
    }

    foreach ($string_ids as $sid) {
        if (!isset($first[$sid])) {
            $out[$sid] = array('owner' => '', 'owner_type' => '', 'field' => '', 'before' => '', 'after' => '', 'previous' => null);
            continue;
        }

        $use = $first[$sid];
        $type = ($use['owner_type'] === 'page_seo') ? 'page' : $use['owner_type'];
        $key = $use['owner_type'] . '|' . $use['owner_id'] . '|';

        $out[$sid] = array(
            'owner'      => isset($names[$type][(int) $use['owner_id']]) ? $names[$type][(int) $use['owner_id']] : '',
            'owner_type' => (string) $use['owner_type'],
            'field'      => (string) $use['field'],
            'before'     => isset($neighbours[$key . ((int) $use['position'] - 1)]) ? $neighbours[$key . ((int) $use['position'] - 1)] : '',
            'after'      => isset($neighbours[$key . ((int) $use['position'] + 1)]) ? $neighbours[$key . ((int) $use['position'] + 1)] : '',
            'previous'   => ((int) $use['prev_string_id'] > 0 && isset($previous[(int) $use['prev_string_id']])) ? $previous[(int) $use['prev_string_id']] : null,
        );
    }

    return $out;
}

/**
 * The items of a package for an engine that reads context: every string
 * with its text, format, hash and where it stands, plus the glossary lines
 * that touch it.
 *
 * @param array  $strings  translation_strings rows
 * @param string $language target
 * @return array items
 */
function pg_tr_package_items($strings, $language)
{
    $ids = array();

    foreach ($strings as $string) {
        $ids[] = (int) $string['id'];
    }

    $context = pg_tr_context($ids, $language);
    $glossary = pg_tr_glossary($language);
    $items = array();

    foreach ($strings as $string) {
        $sid = (int) $string['id'];
        $ctx = isset($context[$sid]) ? $context[$sid] : array('owner' => '', 'owner_type' => '', 'field' => '', 'before' => '', 'after' => '', 'previous' => null);

        $items[] = array(
            'string_id' => $sid,
            'hash'      => (string) $string['hash'],
            'format'    => (string) $string['format'],
            'kind'      => (string) $string['kind'],
            'text'      => (string) $string['source_text'],
            'where'     => array(
                'owner'      => $ctx['owner'],
                'owner_type' => $ctx['owner_type'],
                'field'      => $ctx['field'],
                'before'     => $ctx['before'],
                'after'      => $ctx['after'],
            ),
            'previous'  => $ctx['previous'],
            'glossary'  => pg_tr_glossary_lines(pg_tr_glossary_matches($string['source_text'], $glossary)),
        );
    }

    return $items;
}

/**
 * The instructions every AI engine is given with a batch: the languages, the
 * rules for tags and tokens, the style note, and the answer's shape.
 *
 * @param string $source
 * @param string $target
 * @return string
 */
function pg_tr_ai_instructions($source, $target)
{
    $known = pg_tr_known_languages();
    $source_name = isset($known[$source]) ? $known[$source][0] : $source;
    $target_name = isset($known[$target]) ? $known[$target][0] : $target;

    $lines = array(
        'You translate the texts of a website from ' . $source_name . ' (' . $source . ') to ' . $target_name . ' (' . $target . ').',
        'The texts come as a JSON list of items. Each item has an "id", the "text" to translate, and "where" it stands on the site: the page, the kind of field (a heading, a button label, a page title, a meta description, a menu item), and the texts before and after it. Use that only to choose the right wording; translate the text alone.',
        'An item may carry "previous": the wording the text replaced and how that wording was translated. Keep the terms and the tone of that translation where the text still says the same thing.',
        'An item may carry "glossary": terms with the translation to use, or terms to keep exactly as written.',
        'A text of format "inline" is HTML: keep every tag exactly as it is, with its attributes, in the right place around the translated words. Never add or remove a tag.',
        'Tokens such as ^^cart_count^^ or {var:1} are filled in by the site: keep every one of them exactly as written.',
        'Keep numbers, prices, e-mail addresses, URLs and product codes as they are. Translate the words, not the data.',
        'Match the register of a website: a button label stays short, a heading stays a heading, a meta description stays one or two sentences.',
        'Answer with one JSON object only, no words around it and no code fence: the keys are the ids as strings, the values the translations as strings. Every id of the request appears once. If a text cannot be translated, give its source text back unchanged.',
    );

    $note = pg_tr_style_note();

    if ($note !== '') {
        $lines[] = 'Style note from the site\'s owner: ' . $note;
    }

    return implode("\n", $lines);
}

/**
 * The JSON object an AI engine answered, read out of whatever came around it:
 * a code fence, a sentence, a think block.
 *
 * @param string $text
 * @return array|null id => translation
 */
function pg_tr_ai_parse_answer($text)
{
    $text = preg_replace('/<think>.*?<\/think>/isu', '', (string) $text);
    $text = trim($text);

    if ($text === '') {
        return null;
    }

    // A code fence around it, with or without a language name.
    if (preg_match('/```(?:json)?\s*(\{.*\})\s*```/isu', $text, $m)) {
        $text = $m[1];
    }

    $decoded = json_decode($text, true);

    if (!is_array($decoded)) {
        // The first { to the last }: a sentence before or after the object.
        $start = strpos($text, '{');
        $end = strrpos($text, '}');

        if (($start !== false) && ($end !== false) && ($end > $start)) {
            $decoded = json_decode(substr($text, $start, $end - $start + 1), true);
        }
    }

    if (!is_array($decoded)) {
        return null;
    }

    $out = array();

    foreach ($decoded as $key => $value) {
        if (is_string($value) || is_numeric($value)) {
            $out[(string) $key] = (string) $value;
        }
    }

    return $out;
}
