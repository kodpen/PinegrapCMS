<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Front-end translation - drawing: the node hook the tree renderer calls, the
 * cached body of a page in a language, and the last pass over the assembled
 * page (language attributes, address prefixes, alternates).
 *
 * Nothing is copied: the same tree is drawn with its texts looked up in the
 * store. A text without a translation is drawn in the source language.
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
 * Counters of the texts drawn on this request: how many there were, how many
 * had a translation, how many of those were machine output, and how many
 * came unchanged from Google (the attribution the Cloud terms ask for hangs
 * on that one).
 *
 * @param string $op reset | get | or a counter name to increment
 * @return array|null
 */
function pg_tr_render_counter($op = 'get')
{
    static $counts = array('total' => 0, 'hits' => 0, 'machine' => 0, 'google' => 0);

    if ($op === 'reset') {
        $counts = array('total' => 0, 'hits' => 0, 'machine' => 0, 'google' => 0);
        return $counts;
    }

    if ($op === 'get') {
        return $counts;
    }

    if (isset($counts[$op])) {
        $counts[$op]++;
    }

    return null;
}

/**
 * What the body cache said about the page, when a cached body was served: the
 * texts in it were not drawn on this request, so the counters do not know
 * them.
 *
 * @param array|null $set
 * @return array reviewed, mt_google
 */
function pg_tr_render_body_flags($set = null)
{
    static $flags = array('reviewed' => 1, 'mt_google' => 0, 'cached' => 0);

    if (is_array($set)) {
        $flags = array_merge($flags, $set);
    }

    return $flags;
}

/**
 * One text in the current language.
 *
 * @param string $text
 * @param string $format
 * @param string $kind
 * @return string
 */
function pg_tr_render_text($text, $format = 'text', $kind = 'content')
{
    $normalized = pg_tr_normalize($text, $format);

    if (($normalized === '') || !pg_tr_translatable($normalized)) {
        return $text;
    }

    $found = pg_tr_lookup(pg_tr_hash($normalized, $format), pg_tr_language());

    pg_tr_render_counter('total');

    if ($found === null) {
        return $text;
    }

    pg_tr_render_counter('hits');

    // A machine translation that is the source text itself shows the page's
    // own words: no engine output is drawn, nothing to attribute.
    if (($found['status'] !== 'reviewed') && !pg_tr_same_as_source($normalized, $found['text'])) {
        pg_tr_render_counter('machine');

        if ($found['engine'] === 'google') {
            pg_tr_render_counter('google');
        }
    }

    return (string) $found['text'];
}

/**
 * The render hook: a node's props with their texts translated.
 *
 * @param array  $props
 * @param string $type
 * @return array
 */
function pg_tr_render_props($props, $type)
{
    $fields = pg_tr_node_fields($type, $props);

    if (!$fields) {
        return $props;
    }

    // The first node of the request loads the language's map: from here on a
    // lookup is an array read.
    pg_tr_map_load(pg_tr_language());

    foreach ($fields as $spec) {
        list($field, $format) = $spec;
        $raw = pg_tr_field_get($props, $field);
        $translated = pg_tr_render_text($raw, $format);

        if ($translated !== $raw) {
            pg_tr_field_set($props, $field, $translated);
        }
    }

    return $props;
}

/**
 * The rules a page is drawn by in a language: what is translated, and what
 * keeps the page from the search engines. A body drawn under other rules is
 * drawn again (pg_tr_render_page_body()), and what the pages answered the
 * search engines is dropped once when they change (pg_tr_render_rules_check()).
 *
 * @return string
 */
function pg_tr_render_rules()
{
    return '2026.11.0';
}

/**
 * Drops the drawn bodies and the kept robots answers of every language once
 * after the rules changed (an update), so the sitemap does not go on reading
 * answers given under the old ones for pages nobody has visited since. A
 * marker in data/temp says the rules in force were applied; when it cannot
 * be written nothing is dropped, rather than dropping on every request - the
 * bodies are still drawn again by their fingerprint.
 */
function pg_tr_render_rules_check()
{
    static $checked = false;

    if ($checked) {
        return;
    }

    $checked = true;

    $marker = PG_FUNCTIONS_DIR . '/data/temp/translate_rules_' . pg_tr_render_rules();

    if (is_file($marker)) {
        return;
    }

    if (@file_put_contents($marker, (string) time()) === false) {
        return;
    }

    pg_tr_invalidate_pages();
}

/**
 * The drawn body of a page in the current language. Served from
 * page_translations while the source body it was drawn from is unchanged;
 * otherwise drawn now and kept.
 *
 * @param int    $page_id
 * @param string $page_tree_code the source body, as pg_designer_save_page() wrote it
 * @param string $style_name
 * @param string $additional_body_classes
 * @return string
 */
function pg_tr_render_page_body($page_id, $page_tree_code, $style_name, $additional_body_classes)
{
    $language = pg_tr_language();

    pg_tr_render_rules_check();

    // The source body, and the rules a body is drawn by: a body drawn before
    // they changed is drawn again.
    $fingerprint = sha1(pg_tr_render_rules() . "\n" . $page_tree_code);

    $cached = db_item("SELECT tree_code, fingerprint, coverage, reviewed, mt_google FROM page_translations
                       WHERE page_id = '$page_id' AND language = '" . e($language) . "' LIMIT 1");

    if (is_array($cached) && ($cached['fingerprint'] === $fingerprint) && ($cached['tree_code'] !== '')) {
        pg_tr_render_body_flags(array('reviewed' => (int) $cached['reviewed'], 'mt_google' => (int) $cached['mt_google'], 'cached' => 1));

        return (string) $cached['tree_code'];
    }

    $tree_json = pg_page_tree_json($page_id);
    $tree = ($tree_json !== '') ? json_decode($tree_json, true) : null;

    if (!is_array($tree)) {
        return $page_tree_code;
    }

    pg_tr_map_load($language);
    pg_tr_render_counter('reset');

    $code = generate_style_code_from_tree($tree, $style_name, $additional_body_classes);

    $counts = pg_tr_render_counter('get');
    $coverage = ($counts['total'] > 0) ? (int) round(($counts['hits'] / $counts['total']) * 100) : 100;
    $reviewed = (($counts['total'] === $counts['hits']) && ($counts['machine'] === 0)) ? 1 : 0;
    $mt_google = ($counts['google'] > 0) ? 1 : 0;

    pg_tr_render_body_flags(array('reviewed' => $reviewed, 'mt_google' => $mt_google, 'cached' => 0));

    db("INSERT INTO page_translations (page_id, language, tree_code, fingerprint, coverage, reviewed, mt_google, rendered_at)
        VALUES ('$page_id', '" . e($language) . "', '" . e($code) . "', '" . e($fingerprint) . "', '$coverage', '$reviewed', '$mt_google', '" . time() . "')
        ON DUPLICATE KEY UPDATE tree_code = VALUES(tree_code), fingerprint = VALUES(fingerprint), coverage = VALUES(coverage),
            reviewed = VALUES(reviewed), mt_google = VALUES(mt_google), rendered_at = VALUES(rendered_at)");

    return $code;
}

/* ---------------------------------------------------------------------------
   The last pass over the page
   --------------------------------------------------------------------------- */

/**
 * The pages of the site by name, read once per request: the link prefixer
 * asks whether a name is a page, the search engine helpers which page an
 * address is.
 *
 * @return array names (lower-cased name => page id), home (the page id the
 *               site root shows, 0 when no page is marked)
 */
function pg_tr_page_index()
{
    static $index = null;

    if ($index !== null) {
        return $index;
    }

    $index = array('names' => array(), 'home' => 0);
    $rows = db_items("SELECT page_id, page_name, page_home FROM page");

    if (is_array($rows)) {
        foreach ($rows as $row) {
            $index['names'][mb_strtolower((string) $row['page_name'], 'UTF-8')] = (int) $row['page_id'];

            if (($index['home'] === 0) && ($row['page_home'] === 'yes')) {
                $index['home'] = (int) $row['page_id'];
            }
        }
    }

    return $index;
}

/**
 * The page names of the site, lower-cased, for the link prefixer.
 *
 * @return array name => page id
 */
function pg_tr_page_names()
{
    $index = pg_tr_page_index();

    return $index['names'];
}

/**
 * Whether a path below PATH is a file: a row of the files table, a physical
 * file or directory under the web root, or the software directory. Files
 * keep their one address; the prefix is for pages only.
 *
 * @param string $rest the path without PATH, decoded
 */
function pg_tr_path_is_file($rest)
{
    static $cache = array();

    if (isset($cache[$rest])) {
        return $cache[$rest];
    }

    $is_file = false;
    $first = $rest;
    $slash = strpos($rest, '/');

    if ($slash !== false) {
        $first = substr($rest, 0, $slash);
    }

    if ((strcasecmp($first, SOFTWARE_DIRECTORY) === 0) || ($first === 'files') || ($first === 'pages')) {
        $is_file = true;
    } elseif (in_array(strtolower($rest), array('robots.txt', 'sitemap.xml'), true)) {
        $is_file = true;
    } elseif ((strpos($rest, '..') === false) && (strpos($rest, "\0") === false)
        && (file_exists(dirname(PG_FUNCTIONS_DIR) . '/' . $rest))) {
        $is_file = true;
    } elseif (db_value("SELECT COUNT(*) FROM files WHERE name = '" . e($rest) . "'") > 0) {
        $is_file = true;
    }

    $cache[$rest] = $is_file;

    return $is_file;
}

/**
 * A root-relative path (PATH + rest) with the language prefix added when the
 * path is a page of this site. Already-prefixed paths, files, the software
 * directory and unknown names come back unchanged.
 *
 * @param string $path   decoded path, starting with /
 * @param string $prefix the language prefix
 * @return string
 */
function pg_tr_prefix_path($path, $prefix)
{
    $base = (string) PATH;

    if (strpos($path, $base) !== 0) {
        return $path;
    }

    $rest = substr($path, strlen($base));
    $first = $rest;
    $slash = strpos($rest, '/');

    if ($slash !== false) {
        $first = substr($rest, 0, $slash);
    }

    if ($rest === '') {
        return $base . $prefix . '/';
    }

    // Another language's directory, or this one's: leave it.
    if (isset(pg_tr_prefix_map()[$first])) {
        return $path;
    }

    if (pg_tr_path_is_file($rest)) {
        return $path;
    }

    $names = pg_tr_page_names();

    if (!isset($names[mb_strtolower($first, 'UTF-8')])) {
        return $path;
    }

    return $base . $prefix . '/' . $rest;
}

/**
 * One attribute value of a link or a form, prefixed where it points to a page
 * of this site. Addresses on other hosts, anchors, mail and phone links come
 * back as they were.
 *
 * @param string $value  the attribute as written (entities included)
 * @param string $prefix
 * @param bool   $pages  true for href/action (pages get the prefix), false
 *                       for src/poster (files only: a relative address is
 *                       made root-relative)
 * @return string
 */
function pg_tr_rewrite_url($value, $prefix, $pages = true)
{
    $raw = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $trimmed = trim($raw);

    if (($trimmed === '') || ($trimmed[0] === '#') || preg_match('~^(mailto|tel|sms|javascript|data|blob):~i', $trimmed)) {
        return $value;
    }

    $host_part = '';
    $path = $trimmed;

    if (preg_match('~^(https?:)?//~i', $trimmed)) {
        $parts = parse_url($trimmed);

        if (!is_array($parts) || empty($parts['host'])) {
            return $value;
        }

        $hosts = array();

        foreach (array(defined('HOSTNAME') ? HOSTNAME : '', defined('HOSTNAME_SETTING') ? HOSTNAME_SETTING : '', isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '') as $h) {
            $h = strtolower(preg_replace('~:\d+$~', '', trim((string) $h)));

            if ($h !== '') {
                $hosts[$h] = true;
            }
        }

        if (!isset($hosts[strtolower($parts['host'])])) {
            return $value;
        }

        $host_part = (isset($parts['scheme']) ? $parts['scheme'] . ':' : '') . '//' . $parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : '');
        $path = (isset($parts['path']) ? $parts['path'] : '/')
              . (isset($parts['query']) ? '?' . $parts['query'] : '')
              . (isset($parts['fragment']) ? '#' . $parts['fragment'] : '');
    }

    $query = '';
    $q = strpos($path, '?');
    $f = strpos($path, '#');
    $cut = false;

    if (($q !== false) && (($f === false) || ($q < $f))) {
        $cut = $q;
    } elseif ($f !== false) {
        $cut = $f;
    }

    if ($cut !== false) {
        $query = substr($path, $cut);
        $path = substr($path, 0, $cut);
    }

    $relative = ($path === '' || $path[0] !== '/');

    if ($relative) {
        // Resolved against the site root, which is where every address the
        // software writes starts. "logo.png" on /en/about would otherwise be
        // asked for as /en/logo.png.
        if ($path === '') {
            return $value;
        }

        $resolved = rawurldecode(PATH . ltrim($path, './'));

        if (strpos($resolved, '..') !== false) {
            return $value;
        }

        $rest = substr($resolved, strlen((string) PATH));

        if (pg_tr_path_is_file($rest)) {
            return h($host_part . pg_tr_encode_path($resolved) . $query);
        }

        if (!$pages) {
            return $value;
        }

        $decoded = $resolved;
    } else {
        $decoded = rawurldecode($path);

        if (!$pages) {
            return $value;
        }
    }

    $new = pg_tr_prefix_path($decoded, $prefix);

    if ($new === $decoded && !$relative) {
        return $value;
    }

    return h($host_part . pg_tr_encode_path($new) . $query);
}

/**
 * A decoded path re-encoded segment by segment.
 */
function pg_tr_encode_path($path)
{
    $segments = explode('/', $path);

    foreach ($segments as $i => $segment) {
        $segments[$i] = rawurlencode($segment);
    }

    return str_replace('%3A', ':', implode('/', $segments));
}

/**
 * Adds the language prefix to the links and form actions of the page and
 * makes relative file addresses root-relative. Only attributes inside tags
 * are touched; a URL inside a script is left alone.
 *
 * @param string $content
 * @param string $prefix
 * @return string
 */
function pg_tr_rewrite_links($content, $prefix)
{
    return preg_replace_callback(
        '~<(a|area|link|form|img|source|video|audio|iframe|track|embed|object)\b([^>]*)>~i',
        function ($match) use ($prefix) {
            $tag = strtolower($match[1]);
            $attrs = $match[2];

            // A link that names its language (the language switcher's rows)
            // already points where it means to.
            if (($tag === 'a') && preg_match('~\shreflang=~i', $attrs)) {
                return $match[0];
            }

            // A stylesheet or an icon is a file; only a canonical, alternate,
            // prev or next <link> points to a page.
            $page_attrs = ($tag === 'a' || $tag === 'area' || $tag === 'form'
                || (($tag === 'link') && preg_match('~\srel=("|\')(canonical|alternate|prev|next)\1~i', $attrs)));

            $attrs = preg_replace_callback(
                '~\s(href|action|src|poster|data)=("([^"]*)"|\'([^\']*)\')~i',
                function ($m) use ($page_attrs, $prefix) {
                    $name = strtolower($m[1]);
                    $quote = ($m[2][0] === '"') ? '"' : "'";
                    $value = ($quote === '"') ? $m[3] : $m[4];
                    $pages = $page_attrs && ($name === 'href' || $name === 'action');

                    $new = pg_tr_rewrite_url($value, $prefix, $pages);

                    if ($new === $value) {
                        return $m[0];
                    }

                    return ' ' . $m[1] . '=' . $quote . $new . $quote;
                },
                $attrs);

            return '<' . $match[1] . $attrs . '>';
        },
        $content);
}

/**
 * The absolute address of a page in a language, from the source canonical.
 *
 * @param string $canonical the canonical URL of the source page
 * @param string $code      language code; the source language gives the source address
 * @return string
 */
function pg_tr_alternate_url($canonical, $code)
{
    if ($code === pg_tr_source_language()) {
        return $canonical;
    }

    $decoded = html_entity_decode($canonical, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $parts = parse_url($decoded);

    if (!is_array($parts) || empty($parts['host'])) {
        return $canonical;
    }

    $path = isset($parts['path']) ? rawurldecode($parts['path']) : '/';
    $prefixed = pg_tr_prefix_path($path, pg_tr_prefix($code));

    if ($prefixed === $path) {
        // Not a page address (a file, a foreign path): no alternate exists.
        return '';
    }

    return (isset($parts['scheme']) ? $parts['scheme'] . '://' : '//') . $parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : '')
         . pg_tr_encode_path($prefixed) . (isset($parts['query']) ? '?' . $parts['query'] : '');
}

/**
 * The last pass. For a page in the source language: the hreflang alternates
 * alone. For a page in a target language: the <html> language, the prefix on
 * every link to a page, canonical and og:url, og:locale, the alternates, and
 * the robots directive the language's index policy asks for.
 *
 * @param string $content
 * @return string
 */
function pg_tr_render_finalize($content)
{
    $source = pg_tr_source_language();
    $languages = pg_tr_languages();
    $targets = array();

    foreach ($languages as $code => $row) {
        if ($code !== $source) {
            $targets[$code] = $row;
        }
    }

    if (!$targets) {
        return $content;
    }

    // The canonical address of the page as the pipeline wrote it: the source
    // address, from which the alternates are derived. Unescaped here, so an
    // address with a query string is not escaped twice on its way out. A page
    // whose canonical link names another address (page.canonical_url) is
    // still this page: its own address is kept aside (pg_page_own_url()).
    $canonical = function_exists('pg_page_own_url') ? pg_page_own_url() : '';

    if (($canonical === '') && preg_match('~<link\s+rel="canonical"\s+href="([^"]+)"~i', $content, $m)) {
        $canonical = html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    $active = pg_tr_active();
    $current = $active ? pg_tr_language() : $source;
    $noindex = false;

    if (!$active) {
        // The page in its own language: the stored body carries the fixed
        // lang="en" of generate_style_code_from_tree(), which is wrong for
        // every site not written in English once the site says what its
        // language is.
        $content = preg_replace_callback('~<html\b([^>]*)>~i', function ($match) use ($source) {
            $attrs = $match[1];

            if (preg_match('~\slang=("|\')[^"\']*\1~i', $attrs)) {
                $attrs = preg_replace('~\slang=("|\')[^"\']*\1~i', ' lang="' . h($source) . '"', $attrs, 1);
            } else {
                $attrs .= ' lang="' . h($source) . '"';
            }

            return '<html' . $attrs . '>';
        }, $content, 1);
    }

    if ($active) {
        $row = $targets[$current];
        $prefix = pg_tr_prefix($current);

        // The language of the document. Unreviewed Google output is marked the
        // way the Cloud terms ask, with the source it was translated from.
        $flags = pg_tr_render_body_flags();
        $counts = pg_tr_render_counter('get');
        $mt_google = (($flags['mt_google'] === 1) || ($counts['google'] > 0));
        $lang_value = $current . ($mt_google ? '-x-mtfrom-' . $source : '');

        $content = preg_replace_callback('~<html\b([^>]*)>~i', function ($match) use ($lang_value, $current) {
            $attrs = $match[1];

            if (preg_match('~\slang=("|\')[^"\']*\1~i', $attrs)) {
                $attrs = preg_replace('~\slang=("|\')[^"\']*\1~i', ' lang="' . h($lang_value) . '"', $attrs, 1);
            } else {
                $attrs .= ' lang="' . h($lang_value) . '"';
            }

            if (!preg_match('~\sdata-pg-lang=~i', $attrs)) {
                $attrs .= ' data-pg-lang="' . h($current) . '"';
            }

            if ($row = pg_tr_language_row($current)) {
                $known = pg_tr_known_languages();
                $rtl = isset($known[$current]) && ($known[$current][5] === 1);

                if ($rtl && !preg_match('~\sdir=~i', $attrs)) {
                    $attrs .= ' dir="rtl"';
                }
            }

            return '<html' . $attrs . '>';
        }, $content, 1);

        // The texts the widgets, the forms and the catalog printed from the
        // database (and the custom HTML blocks), swapped for their
        // translations before the links are rewritten.
        $content = pg_tr_render_db_texts($content);

        $content = pg_tr_rewrite_links($content, $prefix);

        // A form that posts carries the language, so the script that handles
        // it answers in it (init.php reads pg_lang): its messages, its
        // redirect, the e-mail it sends.
        $hidden = '<input type="hidden" name="pg_lang" value="' . h($current) . '">';

        $content = preg_replace_callback('~<form\b[^>]*>~i', function ($match) use ($hidden) {
            return preg_match('~\smethod\s*=\s*("|\')?post~i', $match[0]) ? $match[0] . $hidden : $match[0];
        }, $content);

        // The addresses in structured data (JSON-LD): url and @id of this
        // site's pages point at the page in this language.
        $content = preg_replace_callback('~(<script\b[^>]*type=("|\')application/ld\+json\2[^>]*>)(.*?)(</script>)~is', function ($match) use ($prefix) {
            $json = preg_replace_callback('~("(?:url|@id|item)"\s*:\s*")((?:[^"\\\\]|\\\\.)*)(")~', function ($m) use ($prefix) {
                $url = str_replace('\\/', '/', $m[2]);

                if (!preg_match('~^https?://~i', $url)) {
                    return $m[0];
                }

                $new = html_entity_decode(pg_tr_rewrite_url(h($url), $prefix, true), ENT_QUOTES | ENT_HTML5, 'UTF-8');

                return ($new === $url) ? $m[0] : $m[1] . str_replace('/', '\\/', $new) . $m[3];
            }, $match[3]);

            return $match[1] . $json . $match[4];
        }, $content);

        // og:url is a meta content, not a link attribute.
        $content = preg_replace_callback('~(<meta\s+property="og:url"\s+content=")([^"]*)(")~i', function ($m) use ($prefix) {
            return $m[1] . pg_tr_rewrite_url($m[2], $prefix, true) . $m[3];
        }, $content, 1);

        if ($row['og_locale'] !== '') {
            $content = preg_replace('~(<meta\s+property="og:locale"\s+content=")[^"]*(")~i', '${1}' . h($row['og_locale']) . '$2', $content, 1);
        }

        // Index policy: a language for visitors only, or one whose machine
        // output is not to be indexed until somebody has read it. Counted
        // again: the database texts swapped in above count as well.
        $counts = pg_tr_render_counter('get');

        if ($row['index_policy'] === 'none') {
            $noindex = true;
        } elseif ($row['index_policy'] === 'reviewed') {
            $noindex = (($flags['reviewed'] !== 1) || ($counts['machine'] > 0) || ($counts['total'] > $counts['hits']));
        }

        if ($noindex) {
            if (preg_match('~<meta\s+name="robots"\s+content="([^"]*)"~i', $content, $rm)) {
                $follow = (stripos($rm[1], 'nofollow') !== false) ? 'nofollow' : 'follow';
                $content = preg_replace('~<meta\s+name="robots"\s+content="[^"]*"~i', '<meta name="robots" content="noindex, ' . $follow . '"', $content, 1);
            } else {
                $content = preg_replace('~</head>~i', '        <meta name="robots" content="noindex, follow">' . "\n" . '</head>', $content, 1);
            }
        }

        // Under the reviewed policy the answer depends on everything drawn
        // on the page - shared components, menus, the catalog - which
        // sitemap.xml and the alternates on the other languages' pages
        // cannot draw to find out. It is kept for them (pg_tr_indexable()).
        if (($row['index_policy'] === 'reviewed') && ($canonical !== '')) {
            pg_tr_page_verdict_keep($canonical, $current, !$noindex);
        }
    }

    // The alternates: the source and x-default on the source, and every
    // enabled target whose address of the page the search engines may index
    // - the rule sitemap.xml follows; for the language being drawn, the
    // robots directive just written. Only for an address that is a page, and
    // none at all when no other language may be indexed.
    if (($canonical !== '') && !preg_match('~<link\b[^>]*\shreflang=~i', $content)) {
        $links = '';
        $source_url = pg_tr_alternate_url($canonical, $source);

        if ($source_url !== '') {
            $versions = array();

            foreach ($targets as $code => $row) {
                if ($active && ($code === $current)) {
                    // Under the reviewed policy an address below a page (a
                    // catalog item) is offered in no language, as in
                    // pg_tr_indexable(), so the set stays the same on every
                    // page that names it.
                    $indexable = !$noindex && (($row['index_policy'] !== 'reviewed') || (pg_tr_page_id_of_url($canonical) > 0));
                } else {
                    $indexable = pg_tr_indexable($row, $canonical, true);
                }

                if (!$indexable) {
                    continue;
                }

                $url = pg_tr_alternate_url($canonical, $code);

                if ($url !== '') {
                    $versions[$code] = $url;
                }
            }

            if ($versions) {
                $links .= '        <link rel="alternate" hreflang="' . h($source) . '" href="' . h($source_url) . '">' . "\n";

                foreach ($versions as $code => $url) {
                    $links .= '        <link rel="alternate" hreflang="' . h($code) . '" href="' . h($url) . '">' . "\n";
                }

                $links .= '        <link rel="alternate" hreflang="x-default" href="' . h($source_url) . '">' . "\n";
            }
        }

        if ($links !== '') {
            $content = preg_replace('~</head>~i', $links . '</head>', $content, 1);
        }
    }

    // A feed of the page (?rss=true) comes through here as well: it says its
    // language, and in a target language its addresses lead to the pages in
    // that language. Anything else passes unchanged.
    $feed_prefix = $active ? pg_tr_prefix($current) : '';

    $content = pg_tr_rss_localize($content, $current, $active ? function ($value) use ($feed_prefix) {
        return pg_tr_rewrite_url($value, $feed_prefix, true);
    } : null);

    return $content;
}

/**
 * A feed (RSS 2.0) in a language: the channel names the language with
 * <language>, unless it already does, and given a rewrite the addresses of
 * the channel and of its items - <link> and a permalink <guid> - are passed
 * through it. An enclosure is a file and keeps its address. Content that is
 * not an RSS document comes back unchanged.
 *
 * @param string        $content
 * @param string        $language language code
 * @param callable|null $rewrite  takes an escaped address, returns one
 * @return string
 */
function pg_tr_rss_localize($content, $language, $rewrite = null)
{
    $content = (string) $content;

    if (!preg_match('~^\s*(?:<\?xml\b[^>]*\?>\s*)?<rss\b~i', $content)) {
        return $content;
    }

    if ($rewrite !== null) {
        $content = preg_replace_callback('~(<link>)([^<]*)(</link>)~i', function ($m) use ($rewrite) {
            return $m[1] . call_user_func($rewrite, $m[2]) . $m[3];
        }, $content);

        // A guid is an address unless it says otherwise: isPermaLink
        // defaults to true.
        $content = preg_replace_callback('~(<guid\b([^>]*)>)([^<]*)(</guid>)~i', function ($m) use ($rewrite) {
            if (preg_match('~\bisPermaLink\s*=\s*("|\')false\1~i', $m[2])) {
                return $m[0];
            }

            return $m[1] . call_user_func($rewrite, $m[3]) . $m[4];
        }, $content);
    }

    $language = trim((string) $language);

    if (($language === '') || !preg_match('~<channel\b[^>]*>~i', $content, $open, PREG_OFFSET_CAPTURE)) {
        return $content;
    }

    // The channel's own elements are the ones before its first item.
    $start = $open[0][1] + strlen($open[0][0]);
    $first_item = stripos($content, '<item', $start);
    $head = ($first_item === false) ? substr($content, $start) : substr($content, $start, $first_item - $start);

    if (stripos($head, '<language>') !== false) {
        return $content;
    }

    $element = '<language>' . h($language) . '</language>';
    $description = stripos($head, '</description>');

    if ($description === false) {
        return substr($content, 0, $start) . $element . substr($content, $start);
    }

    $eol = (strpos($content, "\r\n") !== false) ? "\r\n" : "\n";
    $indent = preg_match('~([ \t]*)<description>~i', $head, $im) ? $im[1] : '';
    $at = $start + $description + strlen('</description>');

    return substr($content, 0, $at) . $eol . $indent . $element . substr($content, $at);
}

/**
 * A menu item's name in the current language (get_menu_content(),
 * get_menu_sequence()).
 */
function pg_tr_menu_label($name)
{
    return pg_tr_render_text($name, 'text');
}
