<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The Publish check's weight measure, pure over a page the server rendered
 * and the sizes of the files it loads: the bytes of its markup, styles,
 * scripts and first-screen pictures, a picture more than twice its drawn
 * size, a picture without width and height, scripts that hold the first
 * paint, and the rules of the design's own stylesheets no page uses (listed,
 * never removed). Graded green, amber or red against a fixed budget, and
 * read into the Publish check's finding shape (group `weight`), which never
 * blocks a page.
 *
 * The caller renders the draft, asks pg_publish_weight_urls() which files
 * the page loads, gives their sizes (pg_publish_weight_local_files() for the
 * site's own files) and hands both to pg_publish_weight_measure().
 *
 * Loaded where the Publish check runs, not by functions.php.
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
 * The budget in bytes per measure: amber above it, red above twice it. The
 * Publish sheet shows these numbers next to the page's.
 *
 * @return int[] markup, styles, scripts, images (first screen), total
 */
function pg_publish_weight_budget()
{
    return array(
        'markup' => 100 * 1024,
        'styles' => 150 * 1024,
        'scripts' => 300 * 1024,
        'images' => 1000 * 1024,
        'total' => 1500 * 1024,
    );
}

/**
 * The finding texts in the panel's language, code => sentence, for
 * pg_publish_weight_findings() and pg_publish_weight_failed().
 *
 * @return string[]
 */
function pg_publish_weight_texts()
{
    return array(
        'weight_markup' => lang('The page markup is heavier than the budget'),
        'weight_styles' => lang('The styles are heavier than the budget'),
        'weight_scripts' => lang('The scripts are heavier than the budget'),
        'weight_images' => lang('The pictures of the first screen are heavier than the budget'),
        'weight_total' => lang('The page is heavier than the budget'),
        'img_oversized' => lang('The picture is more than twice the size it is drawn at'),
        'img_no_size' => lang('The picture has no width and height'),
        'script_blocking' => lang('The script holds the first paint'),
        'css_unused' => lang('Style rules no page uses'),
        'weight_failed' => lang('The page weight could not be measured'),
    );
}

/**
 * A file address without its query and fragment.
 *
 * @param string $url
 * @return string
 */
function pg_publish_weight_url($url)
{
    $url = trim((string)$url);
    $cut = strcspn($url, '?#');

    return substr($url, 0, $cut);
}

/**
 * The rendered page as a document; null without DOM or markup.
 *
 * @param string $html
 * @return DOMDocument|null
 */
function pg_publish_weight_document($html)
{
    if (!class_exists('DOMDocument') || strpos((string)$html, '<') === false) {
        return null;
    }

    $document = new DOMDocument();
    $previous = libxml_use_internal_errors(true);
    $loaded = $document->loadHTML('<?xml encoding="UTF-8">' . $html);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);

    return $loaded ? $document : null;
}

/**
 * Whether an element sits inside one of the named elements.
 *
 * @param DOMNode  $element
 * @param string[] $names lower-case tag names
 * @return bool
 */
function pg_publish_weight_inside($element, $names)
{
    for ($n = $element->parentNode; $n instanceof DOMElement; $n = $n->parentNode) {
        if (in_array(strtolower($n->nodeName), $names, true)) {
            return true;
        }
    }

    return false;
}

/**
 * The editor node an element was drawn from: the nearest data-pg-node at or
 * above it (the editor's audit render writes it), '' on a served page.
 *
 * @param DOMNode $element
 * @return string
 */
function pg_publish_weight_node($element)
{
    for ($n = $element; $n instanceof DOMElement; $n = $n->parentNode) {
        if ($n->hasAttribute('data-pg-node')) {
            return (string)$n->getAttribute('data-pg-node');
        }
    }

    return '';
}

/**
 * Whether a script element runs JavaScript (not JSON data or a template).
 *
 * @param DOMElement $script
 * @return bool
 */
function pg_publish_weight_is_js($script)
{
    $type = strtolower(trim($script->getAttribute('type')));

    return in_array($type, array('', 'text/javascript', 'application/javascript', 'module'), true);
}

/**
 * The page's stylesheets, scripts and pictures, in document order each.
 *
 * @param DOMDocument $document
 * @return array styles: DOMElement[], scripts: DOMElement[], images: DOMElement[]
 */
function pg_publish_weight_elements($document)
{
    $out = array('styles' => array(), 'scripts' => array(), 'images' => array());

    foreach ($document->getElementsByTagName('link') as $link) {
        $rel = preg_split('/\s+/', strtolower(trim($link->getAttribute('rel'))));

        if (in_array('stylesheet', $rel, true) && trim($link->getAttribute('href')) !== '') {
            $out['styles'][] = $link;
        }
    }

    foreach ($document->getElementsByTagName('script') as $script) {
        if (pg_publish_weight_is_js($script)) {
            $out['scripts'][] = $script;
        }
    }

    foreach ($document->getElementsByTagName('img') as $img) {
        if (trim($img->getAttribute('src')) !== '' && !pg_publish_weight_inside($img, array('noscript', 'template'))) {
            $out['images'][] = $img;
        }
    }

    return $out;
}

/**
 * The files a rendered page loads (stylesheets, scripts, pictures), without
 * query, once each: the addresses whose sizes pg_publish_weight_measure()
 * needs.
 *
 * @param string $html
 * @return string[]
 */
function pg_publish_weight_urls($html)
{
    $document = pg_publish_weight_document($html);

    if ($document === null) {
        return array();
    }

    $els = pg_publish_weight_elements($document);
    $urls = array();

    foreach ($els['styles'] as $el) {
        $urls[] = pg_publish_weight_url($el->getAttribute('href'));
    }

    foreach (array_merge($els['scripts'], $els['images']) as $el) {
        if (trim($el->getAttribute('src')) !== '') {
            $urls[] = pg_publish_weight_url($el->getAttribute('src'));
        }
    }

    return array_values(array_unique(array_filter($urls, 'strlen')));
}

/**
 * One file's size entry: bytes, and a picture's width and height in pixels
 * (0 when not known); null when the file's size was not given.
 *
 * @param array  $files url => bytes, or url => array(bytes, width, height)
 * @param string $url
 * @return array|null
 */
function pg_publish_weight_file($files, $url)
{
    $key = isset($files[$url]) ? $url : pg_publish_weight_url($url);

    if (!isset($files[$key])) {
        return null;
    }

    $f = $files[$key];

    if (!is_array($f)) {
        return array('bytes' => max(0, (int)$f), 'width' => 0, 'height' => 0);
    }

    return array(
        'bytes' => max(0, isset($f['bytes']) ? (int)$f['bytes'] : 0),
        'width' => max(0, isset($f['width']) ? (int)$f['width'] : 0),
        'height' => max(0, isset($f['height']) ? (int)$f['height'] : 0),
    );
}

/**
 * A width or height attribute in pixels; 0 when missing or no number.
 *
 * @param DOMElement $img
 * @param string     $name
 * @return int
 */
function pg_publish_weight_dimension($img, $name)
{
    $v = trim($img->getAttribute($name));

    return preg_match('/^\d+$/', $v) ? (int)$v : 0;
}

/**
 * The weight of a page the server rendered.
 *
 * Markup is the page's bytes without its inline styles and scripts; styles
 * and scripts are the inline ones plus each linked file once; the first
 * screen's pictures are those not loaded lazily. A file whose size is not
 * given adds nothing and is listed under `unknown`.
 *
 * @param string $html  the rendered page
 * @param array  $files url => bytes, or url => array(bytes, width, height)
 * @return array bytes (markup, styles, scripts, images, total), unknown,
 *               oversized, unsized (pictures), blocking (scripts)
 */
function pg_publish_weight_measure($html, $files)
{
    $html = (string)$html;
    $files = is_array($files) ? $files : array();
    $out = array(
        'bytes' => array('markup' => 0, 'styles' => 0, 'scripts' => 0, 'images' => 0, 'total' => 0),
        'unknown' => array(), 'oversized' => array(), 'unsized' => array(), 'blocking' => array(),
    );
    $document = pg_publish_weight_document($html);

    if ($document === null) {
        $out['bytes']['markup'] = $out['bytes']['total'] = strlen($html);

        return $out;
    }

    $inline = 0;
    $seen = array();
    $els = pg_publish_weight_elements($document);

    // Adds a linked file's bytes to a measure once per address.
    $add = function ($measure, $src) use ($files, &$seen, &$out) {
        $url = pg_publish_weight_url($src);

        if ($url === '' || isset($seen[$url])) {
            return null;
        }

        $seen[$url] = true;
        $file = pg_publish_weight_file($files, $src);

        if ($file === null) {
            $out['unknown'][] = $url;
        } elseif ($measure !== '') {
            $out['bytes'][$measure] += $file['bytes'];
        }

        return $file;
    };

    foreach ($document->getElementsByTagName('style') as $style) {
        $inline += strlen($style->textContent);
        $out['bytes']['styles'] += strlen($style->textContent);
    }

    foreach ($els['styles'] as $link) {
        $add('styles', $link->getAttribute('href'));
    }

    foreach ($els['scripts'] as $script) {
        $src = trim($script->getAttribute('src'));

        if ($src === '') {
            $inline += strlen($script->textContent);
            $out['bytes']['scripts'] += strlen($script->textContent);
            continue;
        }

        $add('scripts', $src);

        // A classic script in the head without async or defer is fetched and
        // run before anything is painted.
        if (pg_publish_weight_inside($script, array('head')) && strtolower(trim($script->getAttribute('type'))) !== 'module'
            && !$script->hasAttribute('async') && !$script->hasAttribute('defer')) {
            $out['blocking'][] = array('src' => pg_publish_weight_url($src), 'node' => pg_publish_weight_node($script));
        }
    }

    foreach ($els['images'] as $i => $img) {
        $src = $img->getAttribute('src');
        $first = strtolower(trim($img->getAttribute('loading'))) !== 'lazy';
        $file = $add($first ? 'images' : '', $src);

        if ($file === null) {
            $file = pg_publish_weight_file($files, $src);
        }

        $row = array(
            'src' => pg_publish_weight_url($src),
            'node' => pg_publish_weight_node($img),
            'index' => $i,
            'bytes' => $file ? $file['bytes'] : 0,
            'width' => $file ? $file['width'] : 0,
            'height' => $file ? $file['height'] : 0,
            'drawn_width' => pg_publish_weight_dimension($img, 'width'),
            'drawn_height' => pg_publish_weight_dimension($img, 'height'),
        );

        if (!$row['drawn_width'] || !$row['drawn_height']) {
            $out['unsized'][] = $row;
        }

        // Measured by the width when it is drawn with one, else the height;
        // a vector picture has no pixel size and is never too large.
        if ($row['drawn_width'] && $row['width']) {
            $over = $row['width'] > 2 * $row['drawn_width'];
        } else {
            $over = $row['drawn_height'] && $row['height'] && $row['height'] > 2 * $row['drawn_height'];
        }

        if ($over) {
            $out['oversized'][] = $row;
        }
    }

    $out['bytes']['markup'] = max(0, strlen($html) - $inline);
    $out['bytes']['total'] = $out['bytes']['markup'] + $out['bytes']['styles'] + $out['bytes']['scripts'] + $out['bytes']['images'];

    return $out;
}

/**
 * Each measure's grade against the budget, and the worst one: green at or
 * under the budget, amber up to twice it, red above.
 *
 * @param int[]      $bytes  the measure's bytes
 * @param int[]|null $budget pg_publish_weight_budget() when null
 * @return array state, measures (measure => grade)
 */
function pg_publish_weight_grade($bytes, $budget = null)
{
    $budget = is_array($budget) ? $budget : pg_publish_weight_budget();
    $rank = array('green' => 0, 'amber' => 1, 'red' => 2);
    $out = array('state' => 'green', 'measures' => array());

    foreach ($budget as $measure => $limit) {
        $n = isset($bytes[$measure]) ? (int)$bytes[$measure] : 0;
        $grade = ($n <= $limit) ? 'green' : (($n <= 2 * $limit) ? 'amber' : 'red');
        $out['measures'][$measure] = $grade;

        if ($rank[$grade] > $rank[$out['state']]) {
            $out['state'] = $grade;
        }
    }

    return $out;
}

/**
 * The class (`.name`) and id (`#name`) names a page's markup uses, as keys;
 * merge the pages' maps with `+` for the whole design.
 *
 * @param string $html
 * @return array name => true
 */
function pg_publish_weight_used_names($html)
{
    $used = array();

    if (!preg_match_all('/\s(class|id)\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s"\'>]+))/i', (string)$html, $m, PREG_SET_ORDER)) {
        return $used;
    }

    foreach ($m as $a) {
        $value = isset($a[4]) && $a[4] !== '' ? $a[4] : ((isset($a[3]) && $a[3] !== '') ? $a[3] : $a[2]);
        $prefix = strtolower($a[1]) === 'class' ? '.' : '#';

        foreach (preg_split('/\s+/', trim($value), -1, PREG_SPLIT_NO_EMPTY) as $name) {
            $used[$prefix . $name] = true;
        }
    }

    return $used;
}

/**
 * The position just past the block that opens at $open (a `{`), quoted
 * strings skipped; the end of the text when it is not closed.
 *
 * @param string $css
 * @param int    $open
 * @return int
 */
function pg_publish_weight_block_end($css, $open)
{
    $depth = 0;
    $n = strlen($css);

    for ($i = $open; $i < $n; $i++) {
        $c = $css[$i];

        if ($c === '"' || $c === "'") {
            $close = strpos($css, $c, $i + 1);
            $i = ($close === false) ? $n : $close;
        } elseif ($c === '{') {
            $depth++;
        } elseif ($c === '}' && --$depth === 0) {
            return $i + 1;
        }
    }

    return $n;
}

/**
 * A stylesheet's style rules: selector list and the @media (or @supports,
 * @layer, @container) it sits in. At-rules without style rules (@font-face,
 * @keyframes, @import, @page) give none.
 *
 * @param string     $css
 * @param string     $media the enclosing condition
 * @param array|null $rules the list a nested block adds to
 * @return array[] selector, media
 */
function pg_publish_weight_css_rules($css, $media = '', &$rules = null)
{
    $css = preg_replace('~/\*.*?\*/~s', '', (string)$css);

    if (!is_array($rules)) {
        $rules = array();
    }

    $n = strlen($css);
    $i = 0;

    while ($i < $n) {
        $open = strpos($css, '{', $i);
        $semi = strpos($css, ';', $i);

        if ($open === false) {
            break;
        }

        // A statement at-rule before the next block (@import, @charset).
        if ($semi !== false && $semi < $open && ltrim(substr($css, $i, $semi - $i)) !== '' && ltrim(substr($css, $i, $semi - $i))[0] === '@') {
            $i = $semi + 1;
            continue;
        }

        $prelude = trim(preg_replace('/\s+/', ' ', substr($css, $i, $open - $i)));
        $end = pg_publish_weight_block_end($css, $open);

        if ($prelude !== '' && $prelude[0] === '@') {
            if (preg_match('/^@(media|supports|layer|container)\b/i', $prelude)) {
                $inner = trim($media . ' ' . $prelude);
                pg_publish_weight_css_rules(substr($css, $open + 1, max(0, $end - $open - 2)), $inner, $rules);
            }
        } elseif ($prelude !== '') {
            $rules[] = array('selector' => $prelude, 'media' => $media);
        }

        $i = $end;
    }

    return $rules;
}

/**
 * Whether a class name is one scripts add while the page runs (open, shown,
 * validated states), so no stored page carries it.
 *
 * @param string $name without its dot
 * @return bool
 */
function pg_publish_weight_state_class($name)
{
    static $states = array('active', 'show', 'showing', 'hide', 'hiding', 'collapsing', 'fade', 'open', 'opened',
        'closed', 'disabled', 'focus', 'hover', 'visible', 'hidden', 'loaded', 'loading', 'scrolled', 'sticky', 'stuck',
        'in-view', 'animated');

    return in_array($name, $states, true) || preg_match('/^(is|has|js|was)-/', $name);
}

/**
 * Whether one selector can match the pages: every class and id it needs is
 * used (a selector without one, or needing only state classes, is kept).
 * Attribute selectors and the arguments of pseudo-classes such as :not()
 * are not read.
 *
 * @param string $selector
 * @param array  $used pg_publish_weight_used_names()
 * @return bool
 */
function pg_publish_weight_selector_used($selector, $used)
{
    $s = preg_replace('/\[[^\]]*\]/', '', $selector);

    do {
        $s = preg_replace('/\([^()]*\)/', '', $s, -1, $count);
    } while ($count);

    preg_match_all('/([.#])(-?[_a-zA-Z\x80-\xff][-_a-zA-Z0-9\x80-\xff]*)/', $s, $m, PREG_SET_ORDER);

    foreach ($m as $t) {
        if ($t[1] === '.' && pg_publish_weight_state_class($t[2])) {
            continue;
        }

        if (!isset($used[$t[1] . $t[2]])) {
            return false;
        }
    }

    return true;
}

/**
 * The rules of the design's own stylesheets that no page uses: none of a
 * rule's selectors can match. Listed for the person to look at; the check
 * never removes one.
 *
 * @param string $css  the design's stylesheets
 * @param array  $used the class and id names of every page (pg_publish_weight_used_names())
 * @return array[] selector, media
 */
function pg_publish_weight_unused_rules($css, $used)
{
    $used = is_array($used) ? $used : array();
    $out = array();

    foreach (pg_publish_weight_css_rules($css) as $rule) {
        $selectors = preg_split('/,(?![^(]*\))/', $rule['selector']);
        $any = false;

        foreach ($selectors as $selector) {
            if (pg_publish_weight_selector_used(trim($selector), $used)) {
                $any = true;
                break;
            }
        }

        if (!$any) {
            $out[] = $rule;
        }
    }

    return $out;
}

/**
 * One finding in the Publish check's shape (assets/js/pg_publish_findings.js):
 * source and group `weight`, never blocking.
 *
 * @param string $code
 * @param array  $f     node, index, text, detail, count, level
 * @return array
 */
function pg_publish_weight_finding($code, $f)
{
    $node = isset($f['node']) ? (string)$f['node'] : '';
    $index = isset($f['index']) ? (int)$f['index'] : 0;

    return array(
        'id' => 'weight:' . $code . ':' . $node . ':' . $index,
        'source' => 'weight',
        'code' => $code,
        'group' => 'weight',
        'level' => isset($f['level']) ? $f['level'] : 'look',
        'text' => isset($f['text']) ? (string)$f['text'] : '',
        'node' => $node,
        'shared' => 0,
        'where' => '',
        'count' => max(1, isset($f['count']) ? (int)$f['count'] : 1),
        'detail' => isset($f['detail']) ? (string)$f['detail'] : '',
        'seo' => '',
        'concept' => '',
    );
}

/**
 * A finding's sentence: the sheet's translated one, else English.
 *
 * @param string $code
 * @param array  $texts code => sentence
 * @return string
 */
function pg_publish_weight_text($code, $texts)
{
    static $english = array(
        'weight_markup' => 'The page markup is heavier than the budget',
        'weight_styles' => 'The styles are heavier than the budget',
        'weight_scripts' => 'The scripts are heavier than the budget',
        'weight_images' => 'The pictures of the first screen are heavier than the budget',
        'weight_total' => 'The page is heavier than the budget',
        'img_oversized' => 'The picture is more than twice the size it is drawn at',
        'img_no_size' => 'The picture has no width and height',
        'script_blocking' => 'The script holds the first paint',
        'css_unused' => 'Style rules no page uses',
        'weight_failed' => 'The page weight could not be measured',
    );

    if (is_array($texts) && isset($texts[$code]) && is_string($texts[$code]) && $texts[$code] !== '') {
        return $texts[$code];
    }

    return isset($english[$code]) ? $english[$code] : $code;
}

/**
 * The measure read into findings: a measure over its budget (detail
 * "bytes / budget"), each picture too large or without a size, each script
 * that holds the first paint, and one finding for the unused rules (count,
 * one rule a line). A page within its budget and without these has none.
 *
 * @param array      $measure pg_publish_weight_measure()
 * @param array      $unused  pg_publish_weight_unused_rules()
 * @param array      $texts   code => sentence (pg_publish_weight_texts())
 * @param int[]|null $budget  pg_publish_weight_budget() when null
 * @return array[]
 */
function pg_publish_weight_findings($measure, $unused, $texts, $budget = null)
{
    $budget = is_array($budget) ? $budget : pg_publish_weight_budget();
    $bytes = isset($measure['bytes']) ? $measure['bytes'] : array();
    $grade = pg_publish_weight_grade($bytes, $budget);
    $out = array();

    foreach ($grade['measures'] as $m => $g) {
        if ($g !== 'green') {
            $out[] = pg_publish_weight_finding('weight_' . $m, array('text' => pg_publish_weight_text('weight_' . $m, $texts),
                'detail' => (int)$bytes[$m] . ' / ' . (int)$budget[$m]));
        }
    }

    foreach (array('oversized' => 'img_oversized', 'unsized' => 'img_no_size') as $list => $code) {
        foreach (isset($measure[$list]) ? $measure[$list] : array() as $img) {
            $out[] = pg_publish_weight_finding($code, array('text' => pg_publish_weight_text($code, $texts),
                'node' => $img['node'], 'index' => ($img['node'] === '') ? $img['index'] : 0, 'detail' => $img['src']));
        }
    }

    foreach (isset($measure['blocking']) ? $measure['blocking'] : array() as $i => $script) {
        $out[] = pg_publish_weight_finding('script_blocking', array('text' => pg_publish_weight_text('script_blocking', $texts),
            'node' => $script['node'], 'index' => $i, 'detail' => $script['src']));
    }

    if (is_array($unused) && $unused) {
        $lines = array_map(function ($r) {
            return ($r['media'] !== '') ? $r['media'] . ' { ' . $r['selector'] . ' }' : $r['selector'];
        }, $unused);
        $out[] = pg_publish_weight_finding('css_unused', array('text' => pg_publish_weight_text('css_unused', $texts),
            'count' => count($unused), 'detail' => implode("\n", $lines)));
    }

    return $out;
}

/**
 * The finding of a weight measure that failed to run: said in its group,
 * blocks nothing.
 *
 * @param string $detail why
 * @param array  $texts  code => sentence
 * @return array
 */
function pg_publish_weight_failed($detail, $texts)
{
    return pg_publish_weight_finding('weight_failed', array('level' => 'failed',
        'text' => pg_publish_weight_text('weight_failed', $texts), 'detail' => $detail));
}

/**
 * The sizes of the site's own files among $urls: an address under the
 * site's path ($base) maps to a file under $root; one outside it, on another
 * host or missing is left out. A picture gets its pixel size.
 *
 * @param string[] $urls
 * @param string   $root the site's folder on disk
 * @param string   $base the site's path in addresses, such as '/'
 * @return array url => array(bytes, width, height)
 */
function pg_publish_weight_local_files($urls, $root, $base)
{
    $real_root = realpath((string)$root);
    $base = '/' . trim((string)$base, '/');
    $base = ($base === '/') ? '/' : $base . '/';
    $out = array();

    if ($real_root === false) {
        return $out;
    }

    foreach ((array)$urls as $url) {
        $path = pg_publish_weight_url($url);

        if ($path === '' || strpos($path, '//') === 0 || preg_match('/^[a-z][a-z0-9+.-]*:/i', $path) || strpos($path, $base) !== 0) {
            continue;
        }

        $file = realpath($real_root . '/' . rawurldecode(substr($path, strlen($base))));

        if ($file === false || strpos($file, $real_root . DIRECTORY_SEPARATOR) !== 0 || !is_file($file)) {
            continue;
        }

        $row = array('bytes' => (int)filesize($file), 'width' => 0, 'height' => 0);

        if (preg_match('/\.(jpe?g|png|gif|webp|avif|bmp)$/i', $file)) {
            $size = @getimagesize($file);

            if (is_array($size)) {
                $row['width'] = (int)$size[0];
                $row['height'] = (int)$size[1];
            }
        }

        $out[(string)$url] = $row;
    }

    return $out;
}

/**
 * The design's own stylesheet in a rendered page: the text of the
 * <style id="pg-user-styles"> the render prints last in the head (the
 * framework's and the look's sheets are not the design's own).
 *
 * @param string $html
 * @return string
 */
function pg_publish_weight_own_css($html)
{
    if (!preg_match('~<style\b[^>]*\bid\s*=\s*["\']?pg-user-styles["\']?[^>]*>(.*?)</style>~is', (string)$html, $m)) {
        return '';
    }

    return $m[1];
}

/**
 * The names a stored page tree may give its markup, as class and id keys:
 * every word of its JSON counts as both, so a rule is never listed for a
 * class another page draws (it errs towards "used").
 *
 * @param string|array $tree the tree or its JSON
 * @return array name => true
 */
function pg_publish_weight_tree_names($tree)
{
    // The words are read from the JSON text itself, keys included (more
    // names only keep more rules): decoding thirty large trees cost ten
    // times as much.
    $json = is_string($tree) ? ltrim($tree) : json_encode($tree);
    $names = array();

    if (!is_string($json) || $json === '' || $json[0] !== '{') {
        return $names;
    }

    if (preg_match_all('/-?[A-Za-z_][A-Za-z0-9_-]*/', $json, $m)) {
        foreach (array_unique($m[0]) as $word) {
            $names['.' . $word] = true;
            $names['#' . $word] = true;
        }
    }

    return $names;
}

/**
 * The Publish check's weight answer for one rendered page: its bytes with
 * the budget and grades, its findings, the rules of the design's own
 * stylesheet no page uses, and the pixel size of each picture without width
 * and height whose file is known (node => [width, height], what "Fix"
 * writes on the picture).
 *
 * @param string $html  the rendered draft
 * @param array  $files url => bytes or array(bytes, width, height)
 * @param string $css   the design's own stylesheet (pg_publish_weight_own_css())
 * @param array  $used  the other pages' names, merged with this page's
 * @param array  $texts code => sentence (pg_publish_weight_texts())
 * @return array bytes, budget, grades, findings, unused, sizes
 */
function pg_publish_weight_report($html, $files, $css, $used, $texts)
{
    $budget = pg_publish_weight_budget();
    $measure = pg_publish_weight_measure($html, $files);
    $used = (is_array($used) ? $used : array()) + pg_publish_weight_used_names($html);
    $unused = (trim((string)$css) !== '') ? pg_publish_weight_unused_rules($css, $used) : array();
    $sizes = array();

    foreach ($measure['unsized'] as $img) {
        if ($img['node'] !== '' && $img['width'] > 0 && $img['height'] > 0) {
            $sizes[$img['node']] = array((int)$img['width'], (int)$img['height']);
        }
    }

    return array(
        'bytes' => $measure['bytes'],
        'budget' => $budget,
        'grades' => pg_publish_weight_grade($measure['bytes'], $budget),
        'findings' => pg_publish_weight_findings($measure, $unused, $texts, $budget),
        'unused' => $unused,
        'sizes' => $sizes,
    );
}
