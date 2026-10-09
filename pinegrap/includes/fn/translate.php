<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Front-end translation: the request context and the door to the subsystem.
 *
 * A page of a visual design is served in another language under a virtual
 * language directory (/en/about). router.php recognises the prefix and
 * defines FRONTEND_LANGUAGE before anything else is loaded; everything that
 * draws the page asks pg_tr_active() and, when it says yes, reads its texts
 * through the hooks below. The texts themselves, their extraction, the
 * drawing and the engines live in includes/translate/, which is loaded only
 * on a request that needs it: a page in the source language never pays for
 * the subsystem.
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
 * Whether the translation schema is in place. The config columns and the
 * tables arrive in one step, so the column init.php read is the whole answer.
 */
function pg_tr_ready()
{
    return defined('TRANSLATION_READY') && TRANSLATION_READY;
}

/**
 * The subsystem's files. Idempotent; call before using anything that is not
 * in this module.
 */
function pg_tr_load()
{
    static $loaded = false;

    if ($loaded) {
        return;
    }

    $loaded = true;

    require_once(PG_FUNCTIONS_DIR . '/includes/translate/store.php');
    require_once(PG_FUNCTIONS_DIR . '/includes/translate/extract.php');
    require_once(PG_FUNCTIONS_DIR . '/includes/translate/render.php');
    require_once(PG_FUNCTIONS_DIR . '/includes/translate/engines.php');
    require_once(PG_FUNCTIONS_DIR . '/includes/translate/jobs.php');
    require_once(PG_FUNCTIONS_DIR . '/includes/translate/seo.php');
    require_once(PG_FUNCTIONS_DIR . '/includes/translate/content.php');
}

/**
 * The language the current front-end request is served in, or '' when it is
 * the source language (or not a front-end request at all).
 */
function pg_tr_language()
{
    return defined('FRONTEND_LANGUAGE') ? (string) FRONTEND_LANGUAGE : '';
}

/**
 * True while a page is drawn in a target language: the router found a prefix,
 * the schema is in place and the language is one of the enabled ones.
 */
function pg_tr_active()
{
    static $active = null;

    // A part of the request written for the site's own people (the
    // administrator's copy of a form e-mail) is drawn in the source language.
    if (pg_tr_suspend()) {
        return false;
    }

    if ($active !== null) {
        return $active;
    }

    $active = false;

    if (!defined('FRONTEND_LANGUAGE') || !pg_tr_ready()) {
        return $active;
    }

    $row = pg_tr_language_row(FRONTEND_LANGUAGE);

    $active = (is_array($row) && ((int) $row['enabled'] === 1) && (FRONTEND_LANGUAGE !== pg_tr_source_language()));

    return $active;
}

/**
 * Holds the request in the source language while it draws something for the
 * site's own people - the administrator's e-mail of a form posted from a page
 * in another language - and lets it go again.
 *
 * @param bool|null $on true to hold, false to let go, null to ask
 * @return bool whether the request is held
 */
function pg_tr_suspend($on = null)
{
    static $depth = 0;

    if ($on === true) {
        $depth++;
    } elseif (($on === false) && ($depth > 0)) {
        $depth--;
    }

    return ($depth > 0);
}

/**
 * A visitor's e-mail (an order receipt, a form's copy to the person who sent
 * it) drawn while the request is in another language: the texts from the
 * database translated like the page's, the links into the language's
 * directory. Unchanged in the source language.
 *
 * @param string $html
 * @return string
 */
function pg_tr_email($html)
{
    if (!pg_tr_active() || !is_string($html) || ($html === '')) {
        return $html;
    }

    pg_tr_load();

    return pg_tr_rewrite_links(pg_tr_render_db_texts($html), pg_tr_prefix(pg_tr_language()));
}

/**
 * The language the pages are written in: the setting, or the panel language
 * when the setting is empty.
 */
function pg_tr_source_language()
{
    if (defined('TRANSLATION_SOURCE_LANGUAGE') && (TRANSLATION_SOURCE_LANGUAGE !== '')) {
        return (string) TRANSLATION_SOURCE_LANGUAGE;
    }

    return 'en';
}

/**
 * The site_languages rows, enabled ones by default, in display order.
 *
 * @param bool $enabled_only
 * @return array code => row
 */
function pg_tr_languages($enabled_only = true, $recheck = false)
{
    static $all = null;

    if (!pg_tr_ready()) {
        return array();
    }

    if (($all === null) || $recheck) {
        $all = array();
        $rows = db_items("SELECT * FROM site_languages ORDER BY sort_order, code");

        if (is_array($rows)) {
            foreach ($rows as $row) {
                $all[$row['code']] = $row;
            }
        }
    }

    if (!$enabled_only) {
        return $all;
    }

    $enabled = array();

    foreach ($all as $code => $row) {
        if ((int) $row['enabled'] === 1) {
            $enabled[$code] = $row;
        }
    }

    return $enabled;
}

/**
 * One language's row, or null.
 */
function pg_tr_language_row($code)
{
    $languages = pg_tr_languages(false);

    return isset($languages[$code]) ? $languages[$code] : null;
}

/**
 * The address prefix of a language: its own prefix, or its code.
 */
function pg_tr_prefix($code)
{
    $row = pg_tr_language_row($code);

    if (is_array($row) && ($row['prefix'] !== '')) {
        return (string) $row['prefix'];
    }

    return (string) $code;
}

/**
 * prefix => code of the enabled languages, as the router reads them from
 * config.translation_prefixes ("en=en,de=de").
 *
 * @return array
 */
function pg_tr_prefix_map()
{
    $map = array();

    if (!defined('TRANSLATION_PREFIXES')) {
        return $map;
    }

    foreach (explode(',', (string) TRANSLATION_PREFIXES) as $pair) {
        $pair = trim($pair);

        if ($pair === '') {
            continue;
        }

        $parts = explode('=', $pair, 2);
        $prefix = trim($parts[0]);
        $code = isset($parts[1]) ? trim($parts[1]) : $prefix;

        if ($prefix !== '') {
            $map[$prefix] = $code;
        }
    }

    return $map;
}

/**
 * The names a page, a file or a short link may not take: the prefix and the
 * code of every language the site knows, enabled or not. A page called "en"
 * would be shadowed by the language directory, so the name is refused before
 * that can happen.
 *
 * @return array lower-case names
 */
function pg_tr_reserved_names()
{
    $names = array();

    foreach (pg_tr_prefix_map() as $prefix => $code) {
        $names[mb_strtolower($prefix)] = true;
        $names[mb_strtolower($code)] = true;
    }

    foreach (pg_tr_languages(false) as $code => $row) {
        $names[mb_strtolower($code)] = true;

        if ($row['prefix'] !== '') {
            $names[mb_strtolower($row['prefix'])] = true;
        }
    }

    return array_keys($names);
}

/**
 * Whether a name collides with a language directory. Only the whole first
 * segment counts: "en" is reserved, "en.png" and "english" are not.
 */
function pg_tr_is_reserved_name($name)
{
    $name = mb_strtolower(trim((string) $name), 'UTF-8');

    if ($name === '') {
        return false;
    }

    $first = $name;
    $slash = mb_strpos($name, '/');

    if ($slash !== false) {
        $first = mb_substr($name, 0, $slash);
    }

    return in_array($first, pg_tr_reserved_names(), true);
}

/**
 * An AJAX request from a translated page says which language the page was in
 * (pg_lang, in the query string or the JSON body). Only an enabled language
 * is accepted; anything else leaves the request in the source language.
 *
 * @param array|null $body the decoded JSON body, when the caller has one
 */
function pg_tr_define_from_request($body = null)
{
    if (defined('FRONTEND_LANGUAGE') || !pg_tr_ready()) {
        return;
    }

    $code = '';

    if (isset($_GET['pg_lang']) && is_string($_GET['pg_lang'])) {
        $code = $_GET['pg_lang'];
    } elseif (is_array($body) && isset($body['pg_lang']) && is_string($body['pg_lang'])) {
        $code = $body['pg_lang'];
    } elseif (isset($_POST['pg_lang']) && is_string($_POST['pg_lang'])) {
        $code = $_POST['pg_lang'];
    }

    $code = trim($code);

    if (($code === '') || !preg_match('/^[a-z]{2,3}(-[A-Za-z]{2,4})?$/', $code)) {
        return;
    }

    $row = pg_tr_language_row($code);

    if (!is_array($row) || ((int) $row['enabled'] !== 1) || ($code === pg_tr_source_language())) {
        return;
    }

    define('FRONTEND_LANGUAGE', $code);
    define('LANGUAGE_PREFIX', pg_tr_prefix($code));
    define('LANGUAGE_PATH', PATH . LANGUAGE_PREFIX . '/');

    // A script that answers a form with a redirect (to a confirmation page,
    // the next checkout step) sends the visitor to the page in this language.
    if (!headers_sent() && function_exists('header_register_callback')) {
        header_register_callback('pg_tr_redirect_hook');
    }
}

/**
 * The header callback pg_tr_define_from_request() registers.
 */
function pg_tr_redirect_hook()
{
    pg_tr_load();
    pg_tr_redirect_rewrite();
}

/**
 * sitemap.xml with the other languages' addresses and their alternates
 * (get_sitemap_info.php).
 */
function pg_tr_sitemap($content)
{
    if (!pg_tr_ready() || !pg_tr_languages()) {
        return $content;
    }

    pg_tr_load();

    return pg_tr_sitemap_render($content);
}

/**
 * The robots.txt rules of the hidden pages, repeated under every language
 * directory (get_robots.txt.php).
 */
function pg_tr_robots_rules($rules)
{
    if (!pg_tr_ready() || !pg_tr_languages()) {
        return $rules;
    }

    pg_tr_load();

    return pg_tr_robots_render($rules);
}

/**
 * After a page is saved in the Visual Page Editor: for every language set to
 * translate on save whose engine works without the Translations screen
 * (Pinegrap AI, Claude, Google), the page's pending texts become a job. The
 * job is only opened here - Claude is told, the server engines are worked by
 * the scheduled job - so saving takes no longer than it did.
 *
 * @param int $page_id
 * @param int $user_id
 */
function pg_tr_auto_update_page($page_id, $user_id = 0)
{
    if (!pg_tr_ready() || ((int) $page_id <= 0)) {
        return;
    }

    $auto = array();

    foreach (pg_tr_languages() as $code => $row) {
        if (($code !== pg_tr_source_language()) && !empty($row['auto_update']) && in_array((string) $row['engine'], array('ai', 'claude', 'google'), true)) {
            $auto[$code] = $row;
        }
    }

    if (!$auto) {
        return;
    }

    pg_tr_load();
    pg_tr_auto_update($auto, 'page:' . (int) $page_id, $user_id);
}

/**
 * What the visual editor needs for its Translate section: the target
 * languages and where translations are read and written. Null on a site with
 * one language or before the 2026.4.6 upgrade.
 *
 * Whoever may edit a page's text in the editor may edit its translations
 * there: a manager every text of the site, a user (role 3) the texts of the
 * pages whose folder they have edit rights to (translations_action.php
 * checks each request). The Translations screen itself stays the manager's,
 * so a user gets no link to it.
 *
 * @param array $user
 * @return array|null
 */
function pg_tr_editor_config($user)
{
    if (!pg_tr_ready() || !is_array($user) || ((int) $user['role'] > 3)) {
        return null;
    }

    $source = pg_tr_source_language();
    $languages = array();

    foreach (pg_tr_languages() as $code => $row) {
        if ($code === $source) {
            continue;
        }

        pg_tr_load();
        $languages[] = array('code' => (string) $code, 'label' => pg_tr_language_label($code));
    }

    if (!$languages) {
        return null;
    }

    return array(
        'source'    => $source,
        'languages' => $languages,
        'actionUrl' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/translations_action.php',
        'screenUrl' => ((int) $user['role'] <= 2) ? OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/translations.php' : '',
    );
}

/**
 * A record's own fields in the current language, for a form list or form
 * item view widget that includes its records in the translation: the text
 * boxes and text areas, the fields pg_tr_extract_form_records() reads. Done
 * on the raw values, before the widget escapes them and turns line breaks
 * into <br>. Unchanged in the source language.
 *
 * @param array $fields identifier => array('data', 'type', 'wysiwyg'), as
 *                      pg_sw_index_form_data() builds them
 * @return array
 */
function pg_tr_record_fields($fields)
{
    if (!pg_tr_active() || !is_array($fields)) {
        return $fields;
    }

    pg_tr_load();

    foreach ($fields as $key => $field) {
        if (!is_array($field) || !isset($field['data'], $field['type']) || ($field['data'] === '')
            || !in_array($field['type'], array('text box', 'text area'), true)) {
            continue;
        }

        $fields[$key]['data'] = !empty($field['wysiwyg'])
            ? pg_tr_render_html_value($field['data'])
            : pg_tr_render_text($field['data'], 'text');
    }

    return $fields;
}

/**
 * A label a message repeats (a form field's), in the current language.
 */
function pg_tr_label($text)
{
    return pg_tr_text((string) $text, 'text', 'content');
}

/**
 * Whether a language has a file of its own for the software's wording
 * (includes/local/<code>.json). One that has one speaks it; for one that has
 * none, the cookie window is translated through the site's translations
 * (pg_tr_ui_text()) and the rest of the wording stays English.
 *
 * @param string $code
 * @return bool
 */
function pg_tr_ui_has_file($code)
{
    static $known = array();

    $code = (string) $code;

    if (!isset($known[$code])) {
        $known[$code] = (bool) preg_match('/^[a-z]{2,3}(-[A-Za-z]{2,4})?$/', $code)
            && is_file(PG_FUNCTIONS_DIR . '/includes/local/' . $code . '.json');
    }

    return $known[$code];
}

/**
 * A text of the cookie window (a lang() key of pg_consent_ui_keys()) on a
 * page drawn in a language with no language file: the site's translation of
 * it, a template whose {var} placeholders lang() fills in. Null while there
 * is none - lang() keeps the English wording - and for every other key: the
 * software's own wording (the toolbar, the dynamic code block, the widgets'
 * labels) is the interface language the settings choose, not part of the
 * site's translations. Null as well in the source language, in a language
 * with a file and in the panel.
 *
 * @param string $key
 * @return string|null
 */
function pg_tr_ui_text($key)
{
    static $busy = false;
    static $keys = null;

    if ($keys === null) {
        $keys = function_exists('pg_consent_ui_keys') ? array_flip(pg_consent_ui_keys()) : array();
    }

    if (!isset($keys[(string) $key])) {
        return null;
    }

    // The lookup itself may print through lang() (a message of the store):
    // that one stays as it is. Before the configuration is read there is no
    // answer yet, and pg_tr_active() would keep a "no" for the request.
    if ($busy || !pg_tr_ready() || !pg_tr_active() || pg_tr_ui_has_file(pg_tr_language())) {
        return null;
    }

    $busy = true;
    pg_tr_load();
    $text = pg_tr_ui_translate($key);
    $busy = false;

    return $text;
}

/**
 * The number ("1.234,56") or date ("j.n.Y") format of the language a page is
 * served in, '' in the source language or when the language has none.
 *
 * @param string $what number | date
 * @return string
 */
function pg_tr_locale($what)
{
    if (!pg_tr_active()) {
        return '';
    }

    pg_tr_load();

    return pg_tr_locale_value($what);
}

/**
 * The visual pages whose translated texts match a site search made on a page
 * in another language; empty in the source language.
 *
 * @return array of array(page_id, name, folder_id, title, excerpt)
 */
function pg_tr_search_pages($query)
{
    if (!pg_tr_active()) {
        return array();
    }

    pg_tr_load();

    return pg_tr_search_pages_find($query);
}

/**
 * The query string an address of the software gets on a translated page, so
 * a request made from it is answered in the same language; '' otherwise.
 */
function pg_tr_ajax_suffix($first = true)
{
    if (!pg_tr_active()) {
        return '';
    }

    return ($first ? '?' : '&') . 'pg_lang=' . rawurlencode(pg_tr_language());
}

/**
 * The render hook: a node's props with their texts replaced by the current
 * language's translations. Called by _render_tree_node() for every node, so
 * the shared components and the system widgets drawn on every request go
 * through the same door as the page body.
 *
 * @param array  $props
 * @param string $type  the node type (content, semantic, component, ...)
 * @return array
 */
function pg_tr_props($props, $type)
{
    if (!pg_tr_active()) {
        return $props;
    }

    pg_tr_load();

    return pg_tr_render_props($props, $type);
}

/**
 * One text in the current language, or the text itself when there is no
 * translation (or no translation context).
 *
 * @param string $text
 * @param string $format text | inline
 * @param string $kind   content | seo | ui | option
 */
function pg_tr_text($text, $format = 'text', $kind = 'content')
{
    if (!pg_tr_active() || ($text === '') || ($text === null)) {
        return $text;
    }

    pg_tr_load();

    return pg_tr_render_text($text, $format, $kind);
}

/**
 * The assembled page on its way out: the language attributes, the address
 * prefix on the links to pages, the alternates, the language switchers. Also
 * runs for a page in the source language, which gets the alternates and the
 * switchers alone.
 *
 * @param string $content
 * @return string
 */
function pg_tr_finalize($content)
{
    if (pg_tr_ready() && pg_tr_languages()) {
        pg_tr_load();
        $content = pg_tr_render_finalize($content);
    }

    // The language switchers last: the language pass has run, so their
    // names and addresses stay as drawn (widgets_language.php). A site with
    // one language loses them.
    return function_exists('pg_language_switcher_expand') ? pg_language_switcher_expand($content) : $content;
}

/**
 * The drawn body of a page in the current language, from the cache or drawn
 * now. Falls back to the source body when there is nothing to draw from.
 *
 * @param int    $page_id
 * @param string $page_tree_code the source body
 * @param string $style_name
 * @param string $additional_body_classes
 * @return string
 */
function pg_tr_page_body($page_id, $page_tree_code, $style_name, $additional_body_classes)
{
    if (!pg_tr_active() || ($page_tree_code === '')) {
        return $page_tree_code;
    }

    pg_tr_load();

    return pg_tr_render_page_body((int) $page_id, $page_tree_code, $style_name, $additional_body_classes);
}
