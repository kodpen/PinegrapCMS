<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// A module of functions.php: the custom <meta> tags a visual design and a
// page add to the head (style.style_meta_tags, page.page_meta_tags).
//
// Both columns hold a JSON list of {"name": ..., "content": ...}. The design's
// tags go on every page of the design, a page's own tags on that page; a page
// tag replaces a design tag of the same name, and either replaces the tag of
// the same name Pinegrap writes itself (og:image, twitter:card, ...). The
// values are escaped when the tag is written, so the lists are content, not
// markup: every role that may edit a page's title may edit its tags.
if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/**
 * Whether style.style_meta_tags and page.page_meta_tags exist (2026.11.0,
 * 11.0.3). Probed with raw queries, so a site that has not run the upgrade
 * reads "no" and nothing writes the columns.
 */
function pg_meta_tags_ready($recheck = false)
{
    static $cached = null;

    if (($cached !== null) && !$recheck) {
        return $cached;
    }

    $cached = false;

    if (!isset(db::$con) || !db::$con) {
        return false;
    }

    $style = @mysqli_query(db::$con, "SHOW COLUMNS FROM style WHERE Field = 'style_meta_tags'");
    $page  = @mysqli_query(db::$con, "SHOW COLUMNS FROM page WHERE Field = 'page_meta_tags'");

    $cached = ($style && (@mysqli_num_rows($style) == 1) && $page && (@mysqli_num_rows($page) == 1));

    return $cached;
}

/**
 * How much a list may hold. The column is TEXT (65535 bytes); the byte limit
 * leaves room under it for any mix of characters.
 */
function pg_meta_tags_limits()
{
    return array(
        'tags'    => 30,
        'name'    => 100,
        'content' => 1000,
        'bytes'   => 60000,
    );
}

/**
 * Names a custom tag cannot take, because another setting writes them:
 * viewport is part of every designer page's head, the description comes from
 * the page's Meta Description (or the site's), robots from the page's search
 * engine switches (which the site map follows too), and charset is not a
 * name/content tag at all.
 */
function pg_meta_tags_reserved()
{
    return array('viewport', 'description', 'robots', 'charset');
}

/**
 * Whether a name can be written as a tag name: a letter first, then letters,
 * digits and . _ : - (theme-color, og:image:alt, fb:app_id,
 * msapplication-TileColor).
 */
function pg_meta_tags_name_ok($name)
{
    $limits = pg_meta_tags_limits();

    return is_string($name)
        && (strlen($name) <= $limits['name'])
        && (preg_match('/^[A-Za-z][A-Za-z0-9._:-]*$/', $name) === 1);
}

/**
 * Brings a list into the shape the columns store: rows with a valid,
 * unreserved name and some content, the content on one line and cut to the
 * limit, at most the limit of rows. Takes the stored JSON, the JSON the
 * editor posts, or a decoded array; anything else is an empty list. Rows of
 * the same name are kept: Open Graph allows several og:image tags.
 *
 * @return array List of array('name' => string, 'content' => string)
 */
function pg_meta_tags_normalize($value)
{
    if (is_string($value)) {
        $value = trim($value);
        $value = ($value === '') ? array() : json_decode($value, true);
    }

    if (!is_array($value)) {
        return array();
    }

    $limits   = pg_meta_tags_limits();
    $reserved = pg_meta_tags_reserved();
    $out      = array();

    foreach ($value as $row) {
        if (count($out) >= $limits['tags']) {
            break;
        }

        if (!is_array($row) || !isset($row['name']) || !isset($row['content'])) {
            continue;
        }

        if (!is_scalar($row['name']) || !is_scalar($row['content'])) {
            continue;
        }

        $name = trim((string) $row['name']);

        if (!pg_meta_tags_name_ok($name) || in_array(strtolower($name), $reserved, true)) {
            continue;
        }

        // An attribute value is one line: a line break or another control
        // character, with the spaces around it, becomes one space.
        $content = (string) $row['content'];

        if (function_exists('mb_check_encoding') && !mb_check_encoding($content, 'UTF-8')) {
            continue;
        }

        $content = trim((string) preg_replace('/\s*[\x00-\x1F\x7F]+\s*/u', ' ', $content));

        if (function_exists('mb_substr')) {
            $content = trim(mb_substr($content, 0, $limits['content'], 'UTF-8'));
        } else {
            $content = trim(substr($content, 0, $limits['content']));
        }

        if ($content === '') {
            continue;
        }

        $out[] = array('name' => $name, 'content' => $content);
    }

    return $out;
}

/**
 * The column value of a normalized list: '' for none, JSON otherwise. Rows
 * from the end are dropped while the JSON is over the byte limit.
 */
function pg_meta_tags_encode($list)
{
    if (!is_array($list) || !$list) {
        return '';
    }

    $limits = pg_meta_tags_limits();
    $list   = array_values($list);

    while ($list) {
        $json = json_encode($list, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if (is_string($json) && (strlen($json) <= $limits['bytes'])) {
            return $json;
        }

        array_pop($list);
    }

    return '';
}

/**
 * The attribute a name is written in: property for the Open Graph
 * namespaces (og:, fb:, article:, book:, profile:, music:, video:, product:),
 * which Facebook and LinkedIn read only from property; name for everything
 * else (twitter:*, theme-color, verification codes, ...).
 */
function pg_meta_tags_attribute($name)
{
    return (preg_match('/^(og|fb|article|book|profile|music|video|product):/i', (string) $name) === 1) ? 'property' : 'name';
}

/**
 * The design's tags and the page's in the order they are written: the
 * design's first, without the names the page sets itself, then the page's.
 */
function pg_meta_tags_merge($design, $page)
{
    $design = is_array($design) ? $design : array();
    $page   = is_array($page) ? $page : array();

    $own = array();

    foreach ($page as $row) {
        $own[strtolower($row['name'])] = true;
    }

    $out = array();

    foreach ($design as $row) {
        if (!isset($own[strtolower($row['name'])])) {
            $out[] = $row;
        }
    }

    foreach ($page as $row) {
        $out[] = $row;
    }

    return $out;
}

/**
 * The tags as HTML, one per line, each followed by a line break and $indent
 * (the layout of the other tags in the head).
 */
function pg_meta_tags_html($list, $indent = '        ')
{
    $html = '';

    foreach ((is_array($list) ? $list : array()) as $row) {
        $html .= '<meta ' . pg_meta_tags_attribute($row['name']) . '="' . h($row['name']) . '" content="' . h($row['content']) . '">' . "\n" . $indent;
    }

    return $html;
}

/**
 * Takes out of the tags Pinegrap wrote the ones the custom list replaces: a
 * tag of the same name, and its structured properties (a custom og:image
 * takes the generated og:image:width, :height and :alt with it, since they
 * describe the other picture). The description tag carries og:description
 * too; a custom og:description leaves it as a plain description.
 * google-site-verification is never taken out: a site may be verified by
 * several owners, each with a tag of its own.
 *
 * $html is the block get_page_content.php builds, where every tag starts with
 * `<meta name="` or `<meta property="`.
 */
function pg_meta_tags_drop_overridden($html, $list)
{
    $html = (string) $html;

    foreach ((is_array($list) ? $list : array()) as $row) {
        $key = strtolower($row['name']);

        if ($key === 'google-site-verification') {
            continue;
        }

        $quoted = preg_quote($key, '/');

        if ($key === 'og:description') {
            $html = (string) preg_replace('/<meta property="og:description" (name="description")/i', '<meta $1', $html);
        }

        $html = (string) preg_replace('/[ \t]*<meta (?:name|property)="' . $quoted . '(?::[^"]*)?"[^>]*>[ \t]*(?:\r?\n)?/i', '', $html);
    }

    return $html;
}
