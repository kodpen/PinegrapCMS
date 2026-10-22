<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Front-end translation - the texts that do not live in a page's tree: the
 * catalog (products, product groups, attributes and their options), the
 * custom forms (title, confirmation message, field labels and options) and
 * the records of those a list or detail widget includes in the translation,
 * the texts a system widget's settings hold and the custom HTML blocks of the
 * visual pages. The software's own wording (lang()) comes from the language
 * files in includes/local/; for a language that has none, the texts of the
 * cookie window are taken in here as well (pg_tr_ui_translate()).
 *
 * Extraction puts them in the store like any page text; drawing them is one
 * pass over the finished page (pg_tr_render_db_texts()): every text node,
 * every leaf block of HTML and the worded attributes whose source text has a
 * translation are swapped for it. The widgets that print these texts are not
 * touched one by one - a product name is translated wherever it is printed,
 * in a listing, a breadcrumb, a cart row or a structured-data block.
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
 * The groups of non-page owners the Translations screen lists below the
 * pages, each with the owner types it covers.
 *
 * @return array key => array(label, icon, types)
 */
function pg_tr_owner_groups()
{
    return array(
        'catalog' => array(
            'label' => lang('Products and product groups'),
            'icon'  => 'bi-bag',
            'types' => array('product', 'product_group', 'attribute'),
        ),
        // The forms, and the records of those a list or detail widget
        // includes in the translation (pg_tr_extract_form_records()).
        'forms' => array(
            'label' => lang('Forms'),
            'icon'  => 'bi-ui-checks',
            'types' => array('form', 'form_records'),
        ),
        // The cookie window (pg_consent_ui_keys(), pg_tr_ui_translate()):
        // the one part of the software's wording the site translates, and
        // only for a language with no language file. Left out of the
        // whole-site scope. The owner type keeps its old name, 'ui'.
        'ui' => array(
            'label' => lang('Cookie window'),
            'icon'  => 'bi-shield-check',
            'types' => array('ui'),
        ),
    );
}

/* ---------------------------------------------------------------------------
   The cookie window, for a language with no language file
   ---------------------------------------------------------------------------
   lang() draws the software's wording from includes/local/<code>.json. The
   software ships Turkish and English; a site may add any language. The
   software's interface is not part of the site's translations - on a page in
   a language with no file it stays English - with one exception: the cookie
   window, which every visitor reads first and which has to be understood.
   For its keys (pg_consent_ui_keys()) lang() asks here: the template the
   site's source language has for the key, with its {var} placeholders, is
   taken into the "Cookie window" group of the Translations screen, and its
   translation is the wording from then on. Until there is one, the English
   wording stays. A language with a file never comes here. */

/**
 * The translation of a lang() key on a page in a language with no language
 * file: a template whose {var} placeholders lang() fills in, or null while
 * there is none.
 *
 * @param string $key
 * @return string|null
 */
function pg_tr_ui_translate($key)
{
    static $memo = array();

    $key = (string) $key;

    if (array_key_exists($key, $memo)) {
        return $memo[$key];
    }

    $memo[$key] = null;

    $string = pg_tr_ui_string($key);

    if ($string === null) {
        return null;
    }

    list($hash, $normalized, $format) = $string;

    pg_tr_ui_record($hash, $normalized, $format);

    $map = pg_tr_map_load(pg_tr_language());

    if (!isset($map[$hash]) || ((string) $map[$hash]['text'] === '')) {
        return null;
    }

    $memo[$key] = (string) $map[$hash]['text'];

    return $memo[$key];
}

/**
 * A lang() key as the store keeps it: the source-language template,
 * normalized, with its hash and format. Null for a key the software does not
 * know or a template with nothing to translate.
 *
 * @param string $key
 * @return array|null array(hash, normalized, format)
 */
function pg_tr_ui_string($key)
{
    $template = pg_tr_ui_source_template($key);

    if ($template === null) {
        return null;
    }

    $format = (strpos($template, '<') !== false) ? 'inline' : 'text';
    $normalized = pg_tr_normalize($template, $format);

    if (($normalized === '') || !pg_tr_translatable($normalized)) {
        return null;
    }

    return array(pg_tr_hash($normalized, $format), $normalized, $format);
}

/**
 * The "Cookie window" group made to hold the cookie window and nothing else.
 *
 * Takes the window's texts in before a visitor has seen them, so the group
 * lists them and "Update translations" sends them - written at once, because
 * the caller asks for the pending texts right after. And lets go of what an
 * earlier release took in under the same owner: every other text of the
 * software a page drew in such a language (the toolbar, the dynamic code
 * block, the widgets' labels). Their uses go, and the translations of a
 * text left with no use at all; the source strings stay, as source texts
 * always do. Runs on every extraction of the group, a few queries that
 * change nothing once the group is clean.
 *
 * @return int the texts of the window in the group
 */
function pg_tr_ui_sync()
{
    $hashes = array();

    foreach (pg_consent_ui_keys() as $key) {
        $string = pg_tr_ui_string($key);

        if ($string !== null) {
            pg_tr_ui_record($string[0], $string[1], $string[2]);
            $hashes[$string[0]] = "'" . e($string[0]) . "'";
        }
    }

    pg_tr_ui_record_flush();

    $keep = $hashes ? " AND string_id NOT IN (SELECT id FROM translation_strings WHERE hash IN (" . implode(',', $hashes) . "))" : '';

    db("DELETE FROM translation_uses WHERE owner_type = 'ui'" . $keep);

    db("DELETE t FROM translations t
        INNER JOIN translation_strings s ON s.id = t.string_id
        WHERE s.kind = 'ui'
          AND NOT EXISTS (SELECT 1 FROM translation_uses u WHERE u.string_id = s.id)");

    return count($hashes);
}

/**
 * What a lang() key says in the site's source language, placeholders kept,
 * or null for a string the software does not know. Only the software's own
 * wording is taken in: every key the code uses is in the Turkish file
 * (tools/check_lang.php), text a caller passes through lang() - a label read
 * from the database, say - is not.
 *
 * @param string $key
 * @return string|null
 */
function pg_tr_ui_source_template($key)
{
    $turkish = lang(array('string' => $key, 'language' => 'tr', 'if_known' => true));

    if ($turkish === null) {
        return null;
    }

    $source = pg_tr_source_language();

    if ($source === 'tr') {
        return $turkish;
    }

    // A source language with a file speaks its file; one without one has
    // the software's English wording.
    return pg_tr_ui_has_file($source) ? lang(array('string' => $key, 'language' => $source)) : $key;
}

/**
 * Notes a template a visitor was shown, so "Update translations" of the
 * Cookie window group finds it. The ones already in the store are read
 * once a request; the new ones are written when the request ends, in one go.
 *
 * @param string $hash
 * @param string $normalized
 * @param string $format
 */
function pg_tr_ui_record($hash, $normalized, $format)
{
    static $known = null;

    if ($known === null) {
        $known = array();

        foreach ((array) db_items("SELECT s.hash
                                   FROM translation_uses u
                                   INNER JOIN translation_strings s ON s.id = u.string_id
                                   WHERE u.owner_type = 'ui'") as $row) {
            $known[$row['hash']] = true;
        }

        register_shutdown_function('pg_tr_ui_record_flush');
    }

    if (isset($known[$hash])) {
        return;
    }

    $known[$hash] = true;
    pg_tr_ui_record_queue($hash, array($normalized, $format));
}

/**
 * The templates waiting to be written: adds one, or hands the lot over and
 * empties the list.
 *
 * @return array hash => array(normalized, format)
 */
function pg_tr_ui_record_queue($hash = null, $row = null)
{
    static $queue = array();

    if ($hash === null) {
        $out = $queue;
        $queue = array();

        return $out;
    }

    $queue[$hash] = $row;

    return $queue;
}

/**
 * Writes the templates noted during the request: the strings (kind 'ui'
 * unless the same words are already a page's text) and one use each. A use
 * another request wrote in the meantime is not written twice.
 */
function pg_tr_ui_record_flush()
{
    $queue = pg_tr_ui_record_queue();

    if (!$queue || !isset(db::$con) || !db::$con) {
        return;
    }

    $now = time();

    foreach (array_chunk($queue, 100, true) as $chunk) {
        $values = array();

        foreach ($chunk as $hash => $row) {
            $values[] = "('" . e($hash) . "', '" . e($row[0]) . "', '" . e($row[1]) . "', 'ui', '" . mb_strlen($row[0]) . "', '$now', '$now')";
        }

        db("INSERT INTO translation_strings (hash, source_text, format, kind, chars, first_seen, last_seen)
            VALUES " . implode(',', $values) . "
            ON DUPLICATE KEY UPDATE last_seen = VALUES(last_seen)");

        db("INSERT INTO translation_uses (string_id, owner_type, owner_id, node_id, field, position, prev_string_id, seen_at)
            SELECT s.id, 'ui', 0, 'lang', 'text', 0, 0, '$now'
            FROM translation_strings s
            WHERE s.hash IN ('" . implode("','", array_map('e', array_keys($chunk))) . "')
              AND NOT EXISTS (SELECT 1 FROM translation_uses x WHERE x.owner_type = 'ui' AND x.string_id = s.id)");
    }
}

/* ---------------------------------------------------------------------------
   HTML split into the pieces a translator works on
   --------------------------------------------------------------------------- */

/**
 * The block elements a piece of HTML is split at.
 */
function pg_tr_block_tags()
{
    return array('p', 'li', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'td', 'th', 'dd', 'dt', 'figcaption',
        'blockquote', 'div', 'caption', 'summary', 'label', 'legend', 'address', 'section', 'article',
        'header', 'footer', 'aside', 'main', 'nav', 'ul', 'ol', 'dl', 'table', 'thead', 'tbody', 'tfoot',
        'tr', 'figure', 'details', 'form', 'fieldset');
}

/**
 * The elements whose content is not words to translate: inside a text they
 * are drawn as they are, content and all.
 */
function pg_tr_opaque_tags()
{
    return array('script', 'style', 'template', 'noscript', 'textarea', 'select', 'svg', 'math',
        'iframe', 'object', 'video', 'audio', 'canvas', 'pre');
}

/**
 * The attributes whose value is words a visitor reads or a screen reader
 * says: an image's description, a tooltip, a field's hint, an accessible
 * name. One list for every path: a designer node's attributes
 * (pg_tr_node_fields()), the tags inside an inline text or a piece of
 * stored HTML (pg_tr_html_attr_texts()) and the finished-page pass that
 * draws them (pg_tr_render_attrs()).
 *
 * @return array
 */
function pg_tr_worded_attrs()
{
    return array('alt', 'title', 'placeholder', 'aria-label', 'aria-description', 'data-bs-title', 'data-bs-content');
}

/**
 * Whether an element shows its value attribute as its label: only an input
 * of type submit, button or reset. Any other input's value is what the form
 * posts and stays as it is; so is a <button>'s value, whose label is its
 * content. One rule for a node's attributes and for tags in HTML.
 *
 * @param string $tag  the element's tag name
 * @param string $type its type attribute
 * @return bool
 */
function pg_tr_input_value_is_label($tag, $type)
{
    return (strtolower(trim((string) $tag)) === 'input')
        && in_array(strtolower(trim((string) $type)), array('submit', 'button', 'reset'), true);
}

/**
 * Goes over the worded attributes (pg_tr_worded_attrs()) of the tags in a
 * piece of HTML and lets a callback replace each value. The content of a
 * script, style, text area, pre or code element and comments are passed
 * over (a text area's own tag is not: its placeholder is a hint); so is a
 * tag marked translate="no" or notranslate. The value of a submit, button
 * or reset input is its label and is gone over as well
 * (pg_tr_input_value_is_label()). Values are read whole, so the words of
 * one attribute are never taken for another attribute.
 *
 * Extraction and drawing both go through here, so the value a page looks up
 * is the value extraction stored.
 *
 * @param string   $html
 * @param callable $callback function (string $value, string $name): string|null -
 *                           the value with entities resolved; returns the new
 *                           plain-text value, or null to keep the attribute
 * @return string the HTML, changed attributes written with double quotes
 */
function pg_tr_html_attrs_map($html, $callback)
{
    $worded = array_flip(pg_tr_worded_attrs());
    $tag_body = '(?:"[^"]*"|\'[^\']*\'|[^"\'>])*';

    $tag = function ($source) use ($worded, $callback) {
        if (!preg_match('~^<([a-zA-Z][a-zA-Z0-9-]*)(.*?)(/?>)$~s', $source, $parts)
            || preg_match('~\stranslate\s*=\s*["\']?no\b~i', $parts[2])
            || preg_match('~\sclass\s*=\s*(["\'])(?:(?!\1).)*\bnotranslate\b~is', $parts[2])) {
            return $source;
        }

        $attr_pattern = '~(\s+)([^\s"\'>/=]+)(?:(\s*=\s*)("[^"]*"|\'[^\']*\'|[^\s"\'>]+))?~';
        $value_is_label = false;

        if ((strtolower($parts[1]) === 'input') && preg_match_all($attr_pattern, $parts[2], $found, PREG_SET_ORDER)) {
            foreach ($found as $attr) {
                if ((strtolower($attr[2]) === 'type') && isset($attr[4])) {
                    $value_is_label = pg_tr_input_value_is_label($parts[1], trim($attr[4], '"\''));
                }
            }
        }

        $attrs = preg_replace_callback($attr_pattern, function ($m) use ($worded, $callback, $value_is_label) {
            $name = strtolower($m[2]);

            if ((!isset($worded[$name]) && (($name !== 'value') || !$value_is_label)) || !isset($m[4])) {
                return $m[0];
            }

            $raw = $m[4];

            if (($raw[0] === '"') || ($raw[0] === "'")) {
                $raw = substr($raw, 1, -1);
            }

            $value = call_user_func($callback, html_entity_decode($raw, ENT_QUOTES | ENT_HTML5, 'UTF-8'), $name);

            return ($value === null) ? $m[0] : $m[1] . $m[2] . '="' . h($value) . '"';
        }, $parts[2]);

        return '<' . $parts[1] . $attrs . $parts[3];
    };

    return preg_replace_callback('~<!--.*?-->|(<(script|style|textarea|pre|code)\b' . $tag_body . '>)(.*?</\2\s*>)|<[a-zA-Z][a-zA-Z0-9-]*' . $tag_body . '>~is', function ($m) use ($tag) {
        if (strpos($m[0], '<!--') === 0) {
            return $m[0];
        }

        if (isset($m[1]) && ($m[1] !== '')) {
            return $tag($m[1]) . $m[3];
        }

        return $tag($m[0]);
    }, (string) $html);
}

/**
 * The worded attribute values of a piece of HTML as texts to translate, in
 * the order they stand: what pg_tr_render_attrs() looks up on the page.
 *
 * @param string $html
 * @return array of array(text, 'text', attribute name) - normalised, translatable
 */
function pg_tr_html_attr_texts($html)
{
    $out = array();

    if (strpos((string) $html, '<') === false) {
        return $out;
    }

    pg_tr_html_attrs_map($html, function ($value, $name) use (&$out) {
        $normalized = pg_tr_normalize($value, 'text');

        if (($normalized !== '') && pg_tr_translatable($normalized)) {
            $out[] = array($normalized, 'text', $name);
        }

        return null;
    });

    return $out;
}

/**
 * A piece of the page with its worded attributes in the current language:
 * every value that has a translation is swapped for it.
 *
 * @param string        $html
 * @param callable|null $lookup pg_tr_db_text() unless given
 * @return string
 */
function pg_tr_render_attrs($html, $lookup = null)
{
    if ($lookup === null) {
        $lookup = 'pg_tr_db_text';
    }

    return pg_tr_html_attrs_map($html, function ($value) use ($lookup) {
        if (!preg_match('~\p{L}~u', $value)) {
            return null;
        }

        $found = call_user_func($lookup, $value, array('text'));

        return ($found === null) ? null : strip_tags((string) $found[0]);
    });
}

/**
 * A piece of inline HTML cut where a translated text could not carry what is
 * there. A translation holds words and the editor's inline tags
 * (pg_tr_clean_inline()); an image, a form control, a button, an embed is
 * none of those, and a block swapped whole for its translation lost it. Every
 * other tag is a cut, an element whose content is not words
 * (pg_tr_opaque_tags()) is one cut with its content, and so is a comment. A
 * stretch whose inline tags do not close inside it (a <b> around a button)
 * is cut at those tags as well. The stretches between the cuts are the
 * texts; the cuts are drawn as they are. Extraction and drawing cut alike,
 * so the text a translator gets is the text the page looks up.
 *
 * @param string $html
 * @return array of array('text' | 'keep', html), in order
 */
function pg_tr_inline_pieces($html)
{
    $inline = pg_tr_inline_tags();
    $opaque = array_flip(pg_tr_opaque_tags());
    $tag_pattern = '~^(?:<!--.*?-->|<[a-zA-Z/!][^>]*>)$~s';
    $tokens = preg_split('~(<!--.*?-->|<[a-zA-Z/!][^>]*>)~s', (string) $html, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
    $pieces = array();
    $run = array();

    // A stretch of words and inline tags: one text when its tags close
    // inside it, otherwise its tags are cuts too.
    $flush = function () use (&$run, &$pieces, $tag_pattern) {
        if (!$run) {
            return;
        }

        $open = array();
        $balanced = true;

        foreach ($run as $token) {
            if (!preg_match($tag_pattern, $token) || !preg_match('~^<(/?)([a-zA-Z][a-zA-Z0-9]*)~', $token, $tag)) {
                continue;
            }

            $name = strtolower($tag[2]);

            if (($name === 'br') || ($name === 'wbr') || (substr($token, -2) === '/>')) {
                continue;
            }

            if ($tag[1] === '') {
                $open[] = $name;
            } elseif (array_pop($open) !== $name) {
                $balanced = false;
                break;
            }
        }

        $balanced = $balanced && !$open;
        $text = '';

        foreach ($run as $token) {
            if ($balanced || !preg_match($tag_pattern, $token)) {
                $text .= $token;
                continue;
            }

            if ($text !== '') {
                $pieces[] = array((trim(strip_tags($text)) === '') ? 'keep' : 'text', $text);
                $text = '';
            }

            $pieces[] = array('keep', $token);
        }

        if ($text !== '') {
            $pieces[] = array((trim(strip_tags($text)) === '') ? 'keep' : 'text', $text);
        }

        $run = array();
    };

    $count = count($tokens);

    for ($i = 0; $i < $count; $i++) {
        $token = $tokens[$i];

        if (!preg_match($tag_pattern, $token)) {
            $run[] = $token;
            continue;
        }

        $name = preg_match('~^<(/?)([a-zA-Z][a-zA-Z0-9-]*)~', $token, $tag) ? strtolower($tag[2]) : '';

        if (($name !== '') && isset($inline[$name])) {
            $run[] = $token;
            continue;
        }

        $flush();

        // An element whose content is not words: up to its own end tag.
        if (($name !== '') && ($tag[1] === '') && isset($opaque[$name]) && (substr($token, -2) !== '/>')) {
            $depth = 1;

            while (($depth > 0) && ($i + 1 < $count)) {
                $next = $tokens[++$i];
                $token .= $next;

                if (preg_match($tag_pattern, $next) && preg_match('~^<(/?)' . preg_quote($name, '~') . '\b~i', $next, $inner)) {
                    $depth += ($inner[1] === '') ? 1 : -1;
                }
            }
        }

        $pieces[] = array('keep', $token);
    }

    $flush();

    return $pieces;
}

/**
 * A translation of inline markup with the attributes put back that the
 * cleaner took off the page's tags (pg_tr_inline_attrs(): an id, data-*,
 * the attributes a collapse link or a tooltip works by). The store keeps the
 * cleaned tag; the page's own tag replaces each one the translation carries,
 * in order, so a link opens what it opened in the source language.
 *
 * @param string $raw        the text as the page printed it
 * @param string $translated its translation, cleaned tags
 * @return string
 */
function pg_tr_inline_restore_attrs($raw, $translated)
{
    $translated = (string) $translated;

    if ((strpos($translated, '<') === false) || !preg_match_all('~<([a-zA-Z][a-zA-Z0-9]*)(\s[^>]*)?>~', (string) $raw, $tags, PREG_SET_ORDER)) {
        return $translated;
    }

    $inline = pg_tr_inline_tags();
    $kept = pg_tr_inline_attrs();
    $queues = array();

    foreach ($tags as $tag) {
        $name = strtolower($tag[1]);

        if (!isset($inline[$name]) || empty($tag[2])) {
            continue;
        }

        // Only a tag that loses an attribute in the cleaner; a value is
        // consumed whole, so the words in a title are not read as names.
        preg_match_all('~([^\s"\'>/=]+)(?:\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s"\'>]+))?~', $tag[2], $attrs);
        $lossy = false;

        foreach ($attrs[1] as $attr) {
            if (!isset($kept[strtolower($attr)])) {
                $lossy = true;
                break;
            }
        }

        if (!$lossy) {
            continue;
        }

        $void = in_array($name, array('br', 'wbr'), true);
        $clean = pg_tr_clean_inline($tag[0] . ($void ? '' : '</' . $name . '>'));

        if (!$void) {
            $clean = substr($clean, 0, -strlen('</' . $name . '>'));
        }

        $queues[$clean][] = $tag[0];
    }

    if (!$queues) {
        return $translated;
    }

    return preg_replace_callback('~<[a-z][a-z0-9]*\b[^>]*>~', function ($m) use (&$queues) {
        return empty($queues[$m[0]]) ? $m[0] : array_shift($queues[$m[0]]);
    }, $translated);
}

/**
 * A stretch of inline HTML the page printed, its texts in the current
 * language: cut by pg_tr_inline_pieces(), each text looked up, the cuts and
 * the tags' own attributes kept. Null when no text of it has a translation.
 *
 * @param string        $html
 * @param callable|null $lookup pg_tr_db_text() unless given
 * @return string|null
 */
function pg_tr_render_inline($html, $lookup = null)
{
    if ($lookup === null) {
        $lookup = 'pg_tr_db_text';
    }

    $out = '';
    $changed = false;

    foreach (pg_tr_inline_pieces($html) as $piece) {
        if ($piece[0] !== 'text') {
            $out .= $piece[1];
            continue;
        }

        preg_match('~^(\s*)(.*?)(\s*)$~s', $piece[1], $parts);
        $core = $parts[2];
        $plain = (strpos($core, '<') === false);

        $found = $plain
            ? call_user_func($lookup, html_entity_decode($core, ENT_QUOTES | ENT_HTML5, 'UTF-8'), array('text'))
            : call_user_func($lookup, $core, array('inline'));

        if ($found === null) {
            $out .= $piece[1];
            continue;
        }

        $changed = true;
        $out .= $parts[1] . (($found[1] === 'inline') ? pg_tr_inline_restore_attrs($core, $found[0]) : h($found[0])) . $parts[3];
    }

    return $changed ? $out : null;
}

/**
 * A piece of stored HTML (a product description, a confirmation message, a
 * custom HTML block) as the texts a translator gets: every leaf block - one
 * with no block inside it - as one text, inline tags kept; text that sits
 * beside blocks as a text of its own. HTML with no block at all is one text.
 * An element a text cannot carry (an image, a form control, a button) cuts
 * it into the texts around it (pg_tr_inline_pieces()). Scripts, styles,
 * code and anything marked translate="no" are left out. The worded
 * attributes (an image's alt, a link's aria-label) follow the texts, each
 * a plain text of its own (pg_tr_html_attr_texts()).
 *
 * @param string $html
 * @return array of array(text, format) - normalised, translatable; an
 *               attribute's value as array(text, 'text', attribute name)
 */
function pg_tr_html_segments($html)
{
    $html = (string) $html;
    $attrs = pg_tr_html_attr_texts($html);
    $out = pg_tr_html_text_segments($html);

    return $attrs ? array_merge($out, $attrs) : $out;
}

/**
 * The words of pg_tr_html_segments(), the attributes left out.
 *
 * @param string $html
 * @return array of array(text, format)
 */
function pg_tr_html_text_segments($html)
{
    $out = array();

    if (trim(strip_tags($html)) === '') {
        return $out;
    }

    $push = function ($html) use (&$out) {
        foreach (pg_tr_inline_pieces($html) as $piece) {
            $inner = trim($piece[1]);

            if (($piece[0] !== 'text') || ($inner === '')) {
                continue;
            }

            $plain = (strpos($inner, '<') === false);
            $format = $plain ? 'text' : 'inline';
            $value = $plain ? html_entity_decode($inner, ENT_QUOTES | ENT_HTML5, 'UTF-8') : $inner;
            $normalized = pg_tr_normalize($value, $format);

            if (($normalized !== '') && pg_tr_translatable($normalized)) {
                $out[] = array($normalized, $format);
            }
        }
    };

    $blocks = pg_tr_block_tags();

    if (!preg_match('~<(' . implode('|', $blocks) . ')\b~i', $html)) {
        $push(trim($html));
        return $out;
    }

    $document = new DOMDocument();
    $previous = libxml_use_internal_errors(true);
    $document->loadHTML('<?xml encoding="utf-8"?><div id="pg-tr-root">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);

    $root = $document->getElementById('pg-tr-root');

    if (!$root) {
        return $out;
    }

    $skip = array('script' => 1, 'style' => 1, 'template' => 1, 'noscript' => 1, 'code' => 1, 'pre' => 1, 'textarea' => 1, 'svg' => 1, 'math' => 1, 'iframe' => 1, 'object' => 1);
    $block_set = array_flip($blocks);

    $inner_html = function ($node) use ($document) {
        $html = '';

        foreach ($node->childNodes as $child) {
            $html .= $document->saveHTML($child);
        }

        return $html;
    };

    $has_block = function ($node) use (&$has_block, $block_set) {
        foreach ($node->childNodes as $child) {
            if (($child->nodeType === XML_ELEMENT_NODE) && (isset($block_set[strtolower($child->nodeName)]) || $has_block($child))) {
                return true;
            }
        }

        return false;
    };

    $walk = function ($node) use (&$walk, $push, $inner_html, $has_block, $skip, $block_set, $document) {
        $run = '';

        $flush = function () use (&$run, $push) {
            if (trim(strip_tags($run)) !== '') {
                $push(trim($run));
            }

            $run = '';
        };

        foreach ($node->childNodes as $child) {
            if ($child->nodeType === XML_ELEMENT_NODE) {
                $name = strtolower($child->nodeName);

                if (isset($skip[$name]) || ($child->getAttribute('translate') === 'no') || preg_match('~(^|\s)notranslate(\s|$)~', (string) $child->getAttribute('class'))) {
                    $flush();
                    continue;
                }

                if (isset($block_set[$name]) || $has_block($child)) {
                    $flush();

                    if ($has_block($child)) {
                        $walk($child);
                    } else {
                        $push(trim($inner_html($child)));
                    }

                    continue;
                }
            }

            if (($child->nodeType === XML_ELEMENT_NODE) || ($child->nodeType === XML_TEXT_NODE)) {
                $run .= $document->saveHTML($child);
            }
        }

        $flush();
    };

    $walk($root);

    return $out;
}

/**
 * The segments of one stored field, ready for pg_tr_store_segments().
 *
 * @param string $field the field name; a field split into several texts
 *                      gets "#n" after it, an attribute's value
 *                      "@<attribute>#n"
 * @param string $html
 * @param int    $position running counter
 * @return array
 */
function pg_tr_field_segments($field, $html, &$position)
{
    $segments = array();
    $parts = pg_tr_html_segments($html);
    $texts = 0;

    foreach ($parts as $part) {
        $texts += isset($part[2]) ? 0 : 1;
    }

    $many = ($texts > 1);
    $i = 0;
    $a = 0;

    foreach ($parts as $part) {
        list($text, $format) = $part;

        if (isset($part[2])) {
            $name = $field . '@' . $part[2] . '#' . $a++;
        } else {
            $name = $many ? $field . '#' . $i : $field;
            $i++;
        }

        $segments[] = array(
            'hash'     => pg_tr_hash($text, $format),
            'text'     => $text,
            'format'   => $format,
            'node_id'  => 'field',
            'field'    => $name,
            'position' => $position++,
        );
    }

    return $segments;
}

/* ---------------------------------------------------------------------------
   Extraction
   --------------------------------------------------------------------------- */

/**
 * Writes the segments of many owners of one type at once: the strings in
 * batches, then the uses replaced for the whole type. Used where there are
 * thousands of owners (the catalog), where one round trip per owner would be
 * the slow part of an update.
 *
 * @param string $owner_type
 * @param array  $by_owner owner_id => segments; owners not listed keep nothing
 * @param string $kind
 * @param array  $kinds    field => kind, for fields of another kind (seo)
 * @return int segments written
 */
function pg_tr_store_owner_type($owner_type, $by_owner, $kind = 'content', $kinds = array())
{
    $now = time();
    $strings = array();

    foreach ($by_owner as $segments) {
        foreach ($segments as $segment) {
            $base = preg_replace('~#\d+$~', '', $segment['field']);
            $strings[$segment['hash']] = array($segment['text'], $segment['format'], isset($kinds[$base]) ? $kinds[$base] : $kind);
        }
    }

    // The strings, a few hundred to a statement.
    $ids = array();

    foreach (array_chunk($strings, 200, true) as $chunk) {
        $values = array();

        foreach ($chunk as $hash => $row) {
            $values[] = "('" . e($hash) . "', '" . e($row[0]) . "', '" . e($row[1]) . "', '" . e($row[2]) . "', '" . mb_strlen($row[0]) . "', '$now', '$now')";
        }

        db("INSERT INTO translation_strings (hash, source_text, format, kind, chars, first_seen, last_seen)
            VALUES " . implode(',', $values) . "
            ON DUPLICATE KEY UPDATE last_seen = VALUES(last_seen)");
    }

    foreach (array_chunk(array_keys($strings), 500) as $chunk) {
        foreach ((array) db_items("SELECT id, hash FROM translation_strings WHERE hash IN ('" . implode("','", array_map('e', $chunk)) . "')") as $row) {
            $ids[$row['hash']] = (int) $row['id'];
        }
    }

    // The wording a field had before, so a changed text keeps the old
    // translation beside it as context.
    $previous = array();

    foreach ((array) db_items("SELECT owner_id, field, string_id, prev_string_id FROM translation_uses WHERE owner_type = '" . e($owner_type) . "'") as $row) {
        $previous[$row['owner_id'] . '|' . $row['field']] = $row;
    }

    db("DELETE FROM translation_uses WHERE owner_type = '" . e($owner_type) . "'");

    $values = array();
    $count = 0;

    foreach ($by_owner as $owner_id => $segments) {
        foreach ($segments as $segment) {
            if (!isset($ids[$segment['hash']])) {
                continue;
            }

            $string_id = $ids[$segment['hash']];
            $key = (int) $owner_id . '|' . $segment['field'];
            $prev = 0;

            if (isset($previous[$key])) {
                $prev = ((int) $previous[$key]['string_id'] !== $string_id) ? (int) $previous[$key]['string_id'] : (int) $previous[$key]['prev_string_id'];
            }

            $values[] = "('$string_id', '" . e($owner_type) . "', '" . (int) $owner_id . "', '" . e($segment['node_id']) . "', '" . e($segment['field']) . "', '" . (int) $segment['position'] . "', '$prev', '$now')";
            $count++;
        }
    }

    foreach (array_chunk($values, 300) as $chunk) {
        db("INSERT INTO translation_uses (string_id, owner_type, owner_id, node_id, field, position, prev_string_id, seen_at)
            VALUES " . implode(',', $chunk));
    }

    return $count;
}

/**
 * Whether a table has a column, for the catalog fields that arrived over the
 * years.
 */
function pg_tr_has_column($table, $column)
{
    static $cache = array();
    $key = $table . '.' . $column;

    if (!isset($cache[$key])) {
        $cache[$key] = (bool) db_value("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '" . e($table) . "' AND COLUMN_NAME = '" . e($column) . "'");
    }

    return $cache[$key];
}

/**
 * The catalog: every enabled product and product group (the name as the
 * visitor reads it - short_description; name itself is the stock code -
 * descriptions, details, SEO title and description) and every attribute
 * with its options.
 *
 * @return array('owners' => int, 'segments' => int)
 */
function pg_tr_extract_catalog()
{
    $summary = array('owners' => 0, 'segments' => 0);

    if (!defined('ECOMMERCE') || !ECOMMERCE) {
        return $summary;
    }

    if (function_exists('set_time_limit')) {
        @set_time_limit(300);
    }

    $seo = array('title' => 'seo', 'meta_description' => 'seo');

    foreach (array(
        // name is the unique stock code of a product and a group; what the
        // visitor reads as the name is short_description.
        'product'       => array('products', array('short_description', 'full_description', 'details', 'title', 'meta_description')),
        'product_group' => array('product_groups', array('short_description', 'full_description', 'title', 'meta_description')),
    ) as $owner_type => $spec) {
        list($table, $fields) = $spec;
        $fields = array_values(array_filter($fields, function ($field) use ($table) {
            return pg_tr_has_column($table, $field);
        }));

        if (!$fields) {
            continue;
        }

        $where = pg_tr_has_column($table, 'enabled') ? " WHERE enabled = '1'" : '';
        $by_owner = array();
        $offset = 0;

        do {
            $rows = db_items("SELECT id, " . implode(', ', $fields) . " FROM $table$where ORDER BY id LIMIT $offset, 1000");
            $rows = is_array($rows) ? $rows : array();

            foreach ($rows as $row) {
                $position = 0;
                $segments = array();

                foreach ($fields as $field) {
                    $segments = array_merge($segments, pg_tr_field_segments($field, (string) $row[$field], $position));
                }

                if ($segments) {
                    $by_owner[(int) $row['id']] = $segments;
                }
            }

            $offset += 1000;
        } while (count($rows) === 1000);

        $summary['owners'] += count($by_owner);
        $summary['segments'] += pg_tr_store_owner_type($owner_type, $by_owner, 'content', $seo);
    }

    // Attributes: the label (or the name, when there is no label) and every
    // option.
    if (pg_tr_has_column('product_attributes', 'id')) {
        $by_owner = array();
        $label_column = pg_tr_has_column('product_attributes', 'label') ? 'label' : 'name';

        foreach ((array) db_items("SELECT id, name, $label_column AS label FROM product_attributes ORDER BY id") as $row) {
            $position = 0;
            $label = ((string) $row['label'] !== '') ? (string) $row['label'] : (string) $row['name'];
            $by_owner[(int) $row['id']] = pg_tr_field_segments('label', $label, $position);
        }

        if (pg_tr_has_column('product_attribute_options', 'attribute_id')) {
            foreach ((array) db_items("SELECT id, attribute_id, label FROM product_attribute_options ORDER BY attribute_id, sort_order, id") as $row) {
                $aid = (int) $row['attribute_id'];
                $position = isset($by_owner[$aid]) ? count($by_owner[$aid]) : 0;

                foreach (pg_tr_field_segments('option:' . (int) $row['id'], (string) $row['label'], $position) as $segment) {
                    $segment['node_id'] = 'option';
                    $by_owner[$aid][] = $segment;
                }
            }
        }

        $by_owner = array_filter($by_owner);
        $summary['owners'] += count($by_owner);
        $summary['segments'] += pg_tr_store_owner_type('attribute', $by_owner, 'option');
    }

    return $summary;
}

/**
 * The custom forms: the title, the confirmation message, and every field's
 * label, help text and options - the texts the validation messages and the
 * confirmation repeat. One owner per form page.
 *
 * @return array('owners' => int, 'segments' => int)
 */
function pg_tr_extract_forms()
{
    $summary = array('owners' => 0, 'segments' => 0);
    $by_owner = array();

    foreach ((array) db_items("SELECT page_id, form_name, confirmation_message FROM custom_form_pages ORDER BY page_id") as $row) {
        $position = 0;
        $pid = (int) $row['page_id'];
        $by_owner[$pid] = array_merge(
            pg_tr_field_segments('form_name', (string) $row['form_name'], $position),
            pg_tr_field_segments('confirmation_message', (string) $row['confirmation_message'], $position)
        );
    }

    $columns = array('label');

    foreach (array('information', 'validation_message') as $column) {
        if (pg_tr_has_column('form_fields', $column)) {
            $columns[] = $column;
        }
    }

    foreach ((array) db_items("SELECT id, page_id, " . implode(', ', $columns) . " FROM form_fields ORDER BY page_id, sort_order") as $row) {
        $pid = (int) $row['page_id'];
        $position = isset($by_owner[$pid]) ? count($by_owner[$pid]) : 0;

        foreach ($columns as $column) {
            foreach (pg_tr_field_segments($column . ':' . (int) $row['id'], (string) $row[$column], $position) as $segment) {
                $segment['node_id'] = 'field';
                $by_owner[$pid][] = $segment;
            }
        }
    }

    if (pg_tr_has_column('form_field_options', 'label')) {
        foreach ((array) db_items("SELECT o.id, o.label, f.page_id FROM form_field_options o INNER JOIN form_fields f ON f.id = o.form_field_id ORDER BY f.page_id, o.id") as $row) {
            $pid = (int) $row['page_id'];
            $position = isset($by_owner[$pid]) ? count($by_owner[$pid]) : 0;

            foreach (pg_tr_field_segments('option:' . (int) $row['id'], (string) $row['label'], $position) as $segment) {
                $segment['node_id'] = 'option';
                $by_owner[$pid][] = $segment;
            }
        }
    }

    $by_owner = array_filter($by_owner);
    $summary['owners'] = count($by_owner);
    $summary['segments'] = pg_tr_store_owner_type('form', $by_owner);

    // The records of the forms a widget includes in the translation; a form
    // no widget includes any more keeps none.
    $record_forms = pg_tr_record_forms();

    foreach ($record_forms as $form_page_id) {
        $summary['segments'] += pg_tr_extract_form_records($form_page_id);
    }

    db("DELETE FROM translation_uses WHERE owner_type = 'form_records'"
        . ($record_forms ? " AND owner_id NOT IN (" . implode(',', array_map('intval', $record_forms)) . ")" : ''));

    return $summary;
}

/* ---------------------------------------------------------------------------
   The records of a form a widget shows
   --------------------------------------------------------------------------- */

/**
 * The custom form a form list or form item view widget shows. With
 * $included, only when the widget includes the form's records in the
 * translation (its "include the contents in translation" setting).
 *
 * @param string|null $config_json system_region_config
 * @param bool        $included
 * @return int the form's page id, 0 for none
 */
function pg_tr_widget_record_form($config_json, $included = true)
{
    $config = ($config_json !== null && $config_json !== '') ? json_decode((string) $config_json, true) : null;

    if (!is_array($config) || ($included && empty($config['translate_records']))) {
        return 0;
    }

    $type = isset($config['regionType']) ? (string) $config['regionType'] : '';

    // The list's earlier type names render the same widget (_expand_system_widgets()).
    if (!in_array($type, array('form_list_view', 'submitted_forms_list', 'form_list', 'blog_list', 'form_item_view'), true)) {
        return 0;
    }

    foreach (array('custom_form_page_id', 'form_page_id') as $key) {
        if (isset($config[$key]) && ((int) $config[$key] > 0)) {
            return (int) $config[$key];
        }
    }

    if (!empty($config['source_page']) && is_string($config['source_page'])) {
        return (int) db_value("SELECT page_id FROM page WHERE page_name = '" . e($config['source_page']) . "' LIMIT 1");
    }

    return 0;
}

/**
 * Every custom form whose records a list or detail widget includes in the
 * translation. Read once a request: an extraction asks for every widget of
 * every page and changes no widget on the way.
 *
 * @return array form page ids
 */
function pg_tr_record_forms()
{
    static $cached = null;

    if ($cached !== null) {
        return $cached;
    }

    $forms = array();
    $rows = db_items("SELECT system_region_config FROM shared_components WHERE system_region_config LIKE '%translate_records%'");

    foreach ((array) $rows as $row) {
        $form_page_id = pg_tr_widget_record_form($row['system_region_config']);

        if ($form_page_id > 0) {
            $forms[$form_page_id] = true;
        }
    }

    $cached = array_keys($forms);

    return $cached;
}

/**
 * The records of one custom form, the way a widget that includes them in the
 * translation shows them: the text boxes and text areas of every complete
 * record, a rich-text area split into its blocks like any stored HTML.
 * Choices, addresses, dates and codes are left out - a choice prints its
 * option, whose label pg_tr_extract_forms() reads. One owner per form; the
 * field names the record and the form field ("r12:34"). Read once a
 * request: a widget on every page would otherwise read its form's records
 * again for each of them.
 *
 * @param int $form_page_id
 * @return int segments
 */
function pg_tr_extract_form_records($form_page_id)
{
    static $done = array();

    $form_page_id = (int) $form_page_id;

    if ($form_page_id <= 0) {
        return 0;
    }

    if (isset($done[$form_page_id])) {
        return $done[$form_page_id];
    }

    if (function_exists('set_time_limit')) {
        @set_time_limit(300);
    }

    $segments = array();
    $position = 0;

    $rows = db_items("SELECT form_data.form_id, form_data.form_field_id, form_data.data, form_fields.type, form_fields.wysiwyg
                      FROM form_data
                      INNER JOIN forms ON forms.id = form_data.form_id
                      INNER JOIN form_fields ON form_fields.id = form_data.form_field_id
                      WHERE forms.page_id = '$form_page_id'
                        AND forms.complete = '1'
                        AND form_fields.type IN ('text box', 'text area')
                      ORDER BY forms.id, form_fields.sort_order, form_data.id");

    foreach ((array) $rows as $row) {
        $field = 'r' . (int) $row['form_id'] . ':' . (int) $row['form_field_id'];
        $value = (string) $row['data'];

        // Written in the rich-text editor: HTML, split like a description.
        if (($row['type'] === 'text area') && !empty($row['wysiwyg'])) {
            foreach (pg_tr_field_segments($field, $value, $position) as $segment) {
                $segment['node_id'] = 'record';
                $segments[] = $segment;
            }

            continue;
        }

        // Plain text, line breaks included: the widget escapes it and turns
        // the breaks into <br>, after the translation (pg_tr_record_fields()).
        $normalized = pg_tr_normalize($value, 'text');

        if (($normalized === '') || !pg_tr_translatable($normalized)) {
            continue;
        }

        $segments[] = array(
            'hash'     => pg_tr_hash($normalized, 'text'),
            'text'     => $normalized,
            'format'   => 'text',
            'node_id'  => 'record',
            'field'    => $field,
            'position' => $position++,
        );
    }

    $done[$form_page_id] = pg_tr_store_owner_segments('form_records', $form_page_id, $segments);

    return $done[$form_page_id];
}

/**
 * After a widget was read (pg_tr_extract_shared()): the records of its form,
 * when it includes them in the translation. The form of a list or detail
 * widget that does not include them loses its records from the store unless
 * another widget still includes them.
 *
 * @param string|null $config_json system_region_config
 * @return int the form whose records were read, 0 for none
 */
function pg_tr_extract_widget_records($config_json)
{
    $form_page_id = pg_tr_widget_record_form($config_json);

    if ($form_page_id > 0) {
        pg_tr_extract_form_records($form_page_id);

        return $form_page_id;
    }

    $shown = pg_tr_widget_record_form($config_json, false);

    if (($shown > 0) && !in_array($shown, pg_tr_record_forms(), true)) {
        db("DELETE FROM translation_uses WHERE owner_type = 'form_records' AND owner_id = '$shown'");
    }

    return 0;
}

/**
 * pg_tr_store_segments() for an owner with many texts (the records of a
 * form): the strings are written many to a statement instead of one round
 * trip each, then the owner's uses are replaced. A statement holds up to 200
 * strings and stops short of half a megabyte, well inside the smallest
 * max_allowed_packet a server ships with: blog posts are long.
 *
 * @param string $owner_type
 * @param int    $owner_id
 * @param array  $segments
 * @param string $kind
 * @return int uses written
 */
function pg_tr_store_owner_segments($owner_type, $owner_id, $segments, $kind = 'content')
{
    $now = time();
    $strings = array();

    foreach ($segments as $segment) {
        $strings[$segment['hash']] = array($segment['text'], $segment['format']);
    }

    $values = array();
    $bytes = 0;

    $flush = function () use (&$values, &$bytes) {
        if ($values) {
            db("INSERT INTO translation_strings (hash, source_text, format, kind, chars, first_seen, last_seen)
                VALUES " . implode(',', $values) . "
                ON DUPLICATE KEY UPDATE last_seen = VALUES(last_seen)");
        }

        $values = array();
        $bytes = 0;
    };

    foreach ($strings as $hash => $row) {
        $value = "('" . e($hash) . "', '" . e($row[0]) . "', '" . e($row[1]) . "', '" . e($kind) . "', '" . mb_strlen($row[0]) . "', '$now', '$now')";

        if ($values && ((count($values) >= 200) || ($bytes + strlen($value) > 524288))) {
            $flush();
        }

        $values[] = $value;
        $bytes += strlen($value) + 1;
    }

    $flush();

    $ids = array();

    foreach (array_chunk(array_keys($strings), 500) as $chunk) {
        foreach ((array) db_items("SELECT id, hash FROM translation_strings WHERE hash IN ('" . implode("','", array_map('e', $chunk)) . "')") as $row) {
            $ids[$row['hash']] = (int) $row['id'];
        }
    }

    $uses = array();

    foreach ($segments as $segment) {
        if (isset($ids[$segment['hash']])) {
            $uses[] = array(
                'string_id' => $ids[$segment['hash']],
                'node_id'   => $segment['node_id'],
                'field'     => $segment['field'],
                'position'  => $segment['position'],
            );
        }
    }

    pg_tr_uses_rewrite($owner_type, $owner_id, $uses);

    return count($uses);
}

/**
 * The texts a system widget's settings hold for the visitor: the messages
 * and labels the operator typed (empty_message, checkout_button_label, ...).
 * Any setting whose name ends in _message, _label, _heading, _title, _text or
 * _subject, or is one of those words, and that holds words.
 *
 * @param string|null $config_json system_region_config
 * @param int         $position running counter
 * @return array segments
 */
function pg_tr_widget_config_segments($config_json, &$position)
{
    $config = ($config_json !== null && $config_json !== '') ? json_decode((string) $config_json, true) : null;
    $segments = array();

    if (!is_array($config)) {
        return $segments;
    }

    // A widget's settings sit under "config" in some widgets and at the top
    // in others.
    $settings = (isset($config['config']) && is_array($config['config'])) ? array_merge($config, $config['config']) : $config;

    foreach ($settings as $key => $value) {
        if (!is_string($key) || !is_string($value) || (strpos($value, '^^') !== false)) {
            continue;
        }

        if (!preg_match('~(^|_)(message|label|heading|title|text|subject)$~', $key)) {
            continue;
        }

        foreach (pg_tr_field_segments('cfg:' . $key, $value, $position) as $segment) {
            $segment['node_id'] = 'cfg';
            $segments[] = $segment;
        }
    }

    return $segments;
}

/**
 * The texts of a custom HTML block of a tree, for pg_tr_walk_tree().
 *
 * @param string $html
 * @param string $node_id
 * @param int    $position
 * @return array segments
 */
function pg_tr_custom_html_segments($html, $node_id, &$position)
{
    $segments = array();

    // The editor's own markers, not content.
    if (preg_match('~^\s*<!--pg-[a-z-]+-->\s*$~', (string) $html)) {
        return $segments;
    }

    foreach (pg_tr_field_segments('html', (string) $html, $position) as $segment) {
        $segment['node_id'] = $node_id;
        $segments[] = $segment;
    }

    return $segments;
}

/* ---------------------------------------------------------------------------
   Drawing: one pass over the finished page
   --------------------------------------------------------------------------- */

/**
 * The translation of one text as the page printed it, or null.
 *
 * @param string $value  the text (entities resolved)
 * @param array  $formats the formats to try, in order
 * @return string|null the translation (HTML for an inline text)
 */
function pg_tr_db_text($value, $formats = array('text', 'inline'))
{
    foreach ($formats as $format) {
        $normalized = pg_tr_normalize($value, $format);

        if (($normalized === '') || !pg_tr_translatable($normalized)) {
            continue;
        }

        $found = pg_tr_lookup(pg_tr_hash($normalized, $format), pg_tr_language());

        if ($found !== null) {
            if (($found['status'] !== 'reviewed') && !pg_tr_same_as_source($normalized, $found['text'])) {
                pg_tr_render_counter('machine');

                if ($found['engine'] === 'google') {
                    pg_tr_render_counter('google');
                }
            }

            return array((string) $found['text'], $format);
        }
    }

    return null;
}

/**
 * The texts a widget, a form or the catalog printed from the database,
 * swapped for their translations: leaf blocks of HTML first (a description
 * paragraph with its bold words), then single text nodes, then the worded
 * attributes and the description metas. The structured data of a product
 * variant (window.pgCivVariants) is translated in its script; other scripts,
 * styles, code and text areas are left alone.
 *
 * @param string $content the page
 * @return string
 */
function pg_tr_render_db_texts($content)
{
    pg_tr_map_load(pg_tr_language());

    $parts = preg_split('~(<(script|style|textarea|pre|code)\b[^>]*>.*?</\2>)~is', $content, -1, PREG_SPLIT_DELIM_CAPTURE);
    $out = '';
    $count = count($parts);

    for ($i = 0; $i < $count; $i++) {
        $part = $parts[$i];

        // preg_split hands the tag name back as its own piece after each
        // protected block; it is skipped.
        if (($i % 3) === 2) {
            continue;
        }

        // A protected block keeps its content; its own tag's worded
        // attributes (a text area's placeholder) are drawn like any other.
        if (($i % 3) === 1) {
            $out .= pg_tr_render_attrs((stripos($part, 'pgCivVariants') !== false) ? pg_tr_render_json_strings($part) : $part);
            continue;
        }

        $out .= pg_tr_render_db_html($part);
    }

    return $out;
}

/**
 * pg_tr_render_db_texts() for a stretch of HTML with no script in it.
 */
function pg_tr_render_db_html($html)
{
    $leaf = 'p|li|h[1-6]|td|th|dd|dt|figcaption|blockquote|div|caption|summary|label|legend|address';
    $nested = $leaf . '|ul|ol|dl|table|thead|tbody|tfoot|tr|section|article|header|footer|aside|main|nav|form|fieldset|figure|details|select|option|svg';

    // Leaf blocks with inline markup inside: the whole inner HTML is one
    // text, or the texts around the elements a text cannot carry.
    $html = preg_replace_callback('~<(' . $leaf . ')\b([^>]*)>((?:(?!</?(?:' . $nested . ')\b)[\s\S])*?)</\1>~i', function ($m) {
        // A block of text alone is the text pass's.
        if ((strpos($m[3], '<') === false) || preg_match('~\stranslate="no"|\bnotranslate\b~i', $m[2])) {
            return $m[0];
        }

        $translated = pg_tr_render_inline($m[3]);

        return ($translated === null) ? $m[0] : '<' . $m[1] . $m[2] . '>' . $translated . '</' . $m[1] . '>';
    }, $html);

    // Single text nodes.
    $html = preg_replace_callback('~>([^<>]+)<~', function ($m) {
        $raw = $m[1];

        if ((trim($raw) === '') || !preg_match('~\p{L}~u', $raw)) {
            return $m[0];
        }

        $value = html_entity_decode($raw, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $found = pg_tr_db_text($value);

        if ($found === null) {
            return $m[0];
        }

        preg_match('~^\s*~', $raw, $lead);
        preg_match('~\s*$~', $raw, $trail);

        $text = ($found[1] === 'inline') ? $found[0] : h($found[0]);

        return '>' . $lead[0] . $text . $trail[0] . '<';
    }, $html);

    // Worded attributes: those of the stored HTML and those an inline text
    // carries in its own tags, both extracted as texts of their own.
    $html = pg_tr_render_attrs($html);

    // The title and description metas a product or group page sets.
    $html = preg_replace_callback('~(<meta\s+(?:name|property)="(?:description|og:title|og:description|twitter:title|twitter:description)"\s+content=")([^"]*)(")~i', function ($m) {
        $found = pg_tr_db_text(html_entity_decode($m[2], ENT_QUOTES | ENT_HTML5, 'UTF-8'), array('text'));

        return ($found === null) ? $m[0] : $m[1] . h(strip_tags($found[0])) . $m[3];
    }, $html);

    return $html;
}

/**
 * A stored piece of HTML (a record's rich-text field) with its texts in the
 * current language, taken apart the way pg_tr_html_segments() stored it: HTML
 * with no block in it is one text, anything else goes by its leaf blocks and
 * loose texts.
 *
 * @param string $html
 * @return string
 */
function pg_tr_render_html_value($html)
{
    $html = (string) $html;

    if (trim(strip_tags($html)) === '') {
        return $html;
    }

    if (!preg_match('~<(' . implode('|', pg_tr_block_tags()) . ')\b~i', $html)) {
        $translated = pg_tr_render_inline(trim($html));

        return pg_tr_render_attrs(($translated === null) ? $html : $translated);
    }

    return pg_tr_render_db_html($html);
}

/**
 * The string values of a script's JSON (a product's variants), translated.
 */
function pg_tr_render_json_strings($script)
{
    return preg_replace_callback('~"((?:[^"\\\\]|\\\\.)*)"~', function ($m) {
        $decoded = json_decode('"' . $m[1] . '"');

        if (!is_string($decoded) || !preg_match('~\p{L}~u', $decoded)) {
            return $m[0];
        }

        // A rich-text value with blocks in it (a variant's description of
        // two paragraphs) was stored block by block (pg_tr_html_segments()),
        // never as one text: translated the same way the page's own copy is.
        if (preg_match('~<(' . implode('|', pg_tr_block_tags()) . ')\b~i', $decoded)) {
            $html = pg_tr_render_html_value($decoded);

            return ($html === $decoded) ? $m[0] : json_encode($html, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG);
        }

        $found = pg_tr_db_text($decoded);

        if ($found === null) {
            return $m[0];
        }

        return json_encode($found[0], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG);
    }, $script);
}

/* ---------------------------------------------------------------------------
   Number and date formats
   --------------------------------------------------------------------------- */

/**
 * The number or date format the current language is set to, '' for none
 * (the software's own then applies).
 *
 * @param string $what number | date
 * @return string
 */
function pg_tr_locale_value($what)
{
    $row = pg_tr_language_row(pg_tr_language());

    if (!is_array($row)) {
        return '';
    }

    $value = ($what === 'number') ? (string) $row['locale_number'] : (string) $row['locale_date'];

    if ($value === '') {
        $known = pg_tr_known_languages();
        $code = pg_tr_language();

        if (isset($known[$code])) {
            $value = ($what === 'number') ? (string) $known[$code][3] : (string) $known[$code][4];
        }
    }

    return $value;
}
