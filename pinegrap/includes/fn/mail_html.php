<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The allowlist cleaner for the HTML of incoming e-mail: a mail's HTML part
 * is rebuilt element by element from a fixed list of text and table
 * elements and their attributes, with inline styles and the mail's own
 * style sheets kept. Scripts, forms, frames, event attributes, links to
 * style sheets and anything a style could fetch never come through, and a
 * picture from outside keeps its address aside (data-pg-src) so nothing
 * loads by itself. Pictures the mail carries are named by their Content-ID
 * (data-pg-cid) and, once kept, by their file (data-pg-file). Pure
 * functions, no database and no files.
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
 * The largest HTML part read, and the largest cleaned HTML kept, in bytes.
 *
 * @return int
 */
function pg_mail_html_max_bytes()
{
    return 1048576;
}

/**
 * The elements kept, each with the attributes it may carry beyond the
 * common ones (pg_mail_html_common_attributes()). An element in neither
 * this list nor pg_mail_html_dropped_elements() is left out with its
 * children kept (an Outlook <o:p>, a <form>, a <picture>).
 *
 * @return array element name => list of its own attributes
 */
function pg_mail_html_elements()
{
    $cell = array('colspan', 'rowspan', 'nowrap', 'scope', 'abbr', 'headers', 'background');
    $text = array();

    $elements = array(
        'a'          => array('href', 'name'),
        'img'        => array('src', 'alt', 'border', 'hspace', 'vspace'),
        'table'      => array('border', 'cellpadding', 'cellspacing', 'summary', 'frame', 'rules', 'background'),
        'td'         => $cell,
        'th'         => $cell,
        'tr'         => array('background'),
        'col'        => array('span'),
        'colgroup'   => array('span'),
        'ol'         => array('start', 'type', 'reversed'),
        'ul'         => array('type'),
        'li'         => array('value', 'type'),
        'font'       => array('face', 'size', 'color'),
        'hr'         => array('size', 'noshade', 'color'),
        'time'       => array('datetime'),
        'details'    => array('open'),
        'blockquote' => $text,
        'q'          => $text,
    );

    foreach (array('abbr', 'address', 'article', 'aside', 'b', 'bdi', 'bdo', 'big', 'br', 'caption', 'center', 'cite',
        'code', 'dd', 'del', 'dfn', 'div', 'dl', 'dt', 'em', 'figcaption', 'figure', 'footer', 'h1', 'h2', 'h3', 'h4',
        'h5', 'h6', 'header', 'i', 'ins', 'kbd', 'main', 'mark', 'nav', 'p', 'pre', 's', 'samp', 'section', 'small',
        'span', 'strike', 'strong', 'sub', 'summary', 'sup', 'tbody', 'tfoot', 'thead', 'tt', 'u', 'var', 'wbr') as $name) {
        $elements[$name] = $text;
    }

    return $elements;
}

/**
 * The attributes any kept element may carry.
 *
 * @return array
 */
function pg_mail_html_common_attributes()
{
    return array('id', 'class', 'style', 'title', 'lang', 'dir', 'align', 'valign', 'width', 'height', 'bgcolor',
        'role', 'aria-label', 'aria-hidden');
}

/**
 * The elements left out together with everything inside them: what runs,
 * loads, asks for input or draws from outside, and the head's parts (its
 * style sheets are read on their own).
 *
 * @return array
 */
function pg_mail_html_dropped_elements()
{
    return array('script', 'style', 'head', 'title', 'meta', 'link', 'base', 'noscript', 'iframe', 'frame',
        'frameset', 'noframes', 'object', 'embed', 'applet', 'param', 'input', 'button', 'select', 'option',
        'optgroup', 'textarea', 'datalist', 'output', 'template', 'svg', 'math', 'canvas', 'video', 'audio',
        'source', 'track', 'map', 'area', 'dialog', 'slot', 'portal', 'xml');
}

/**
 * The elements HTML5 writes without an end tag. The parser follows HTML 4
 * and hands the text after an <embed>, <source> or <wbr> to it as its
 * children: these give their children back in place.
 *
 * @return array
 */
function pg_mail_html_void_elements()
{
    return array('area', 'base', 'br', 'col', 'embed', 'hr', 'img', 'input', 'link', 'meta', 'param', 'source', 'track', 'wbr');
}

/**
 * A link's address when it is a web, mail or telephone address or a place
 * in the mail itself; '' otherwise (javascript:, data:, relative paths).
 *
 * @param string $value
 * @return string
 */
function pg_mail_html_href($value)
{
    $value = trim((string) $value);

    if (($value === '') || preg_match('/[\x00-\x1F\x7F]/', $value)) {
        return '';
    }

    if (($value[0] === '#') || preg_match('#^(https?://[^/?\#\s]|mailto:|tel:)#i', $value)) {
        return $value;
    }

    return '';
}

/**
 * Whether a CSS value or selector can neither fetch anything nor break out
 * of its <style> element: no url() or other function that loads a
 * picture or an attribute, no escapes, no comments, no at-rules, no "<",
 * no expression() or bindings. Any url is refused, a data: one too: a
 * declaration split at the ";" of a data: address must not leave an
 * unclosed url( behind.
 *
 * @param string $value
 * @return bool
 */
function pg_mail_html_css_ok($value)
{
    return !preg_match('#[\\\\<@{}]|/\*|url|image-set|cross-fade|\b(element|image|src|attr)\s*\(|expression|javascript|vbscript|behavior|binding#i', (string) $value);
}

/**
 * A style attribute, or the inside of one rule, with only the declarations
 * whose name is plain and whose value passes pg_mail_html_css_ok(); ''
 * when none is left.
 *
 * @param string $value
 * @return string
 */
function pg_mail_html_style($value)
{
    $kept = array();

    foreach (explode(';', (string) $value) as $declaration) {
        $parts = explode(':', $declaration, 2);

        if (count($parts) !== 2) {
            continue;
        }

        $name = strtolower(trim($parts[0]));
        $item = trim(preg_replace('/\s+/', ' ', $parts[1]));

        if (preg_match('/^-{0,2}[a-z][a-z0-9-]{0,63}$/', $name) && ($item !== '') && pg_mail_html_css_ok($item)) {
            $kept[] = $name . ': ' . $item;
        }
    }

    return implode('; ', $kept);
}

/**
 * The position of the "}" closing the block whose "{" is at $open, or -1.
 *
 * @param string $text
 * @param int    $open
 * @return int
 */
function pg_mail_html_block_end($text, $open)
{
    $depth = 0;
    $length = strlen($text);

    for ($i = $open; $i < $length; $i++) {
        if ($text[$i] === '{') {
            $depth++;
        } elseif (($text[$i] === '}') && (--$depth === 0)) {
            return $i;
        }
    }

    return -1;
}

/**
 * A mail's style sheet with only plain rules and @media / @supports blocks
 * of them, every declaration through pg_mail_html_style(); @import,
 * @font-face and every other at-rule are left out. '' when nothing is left.
 *
 * @param string $text
 * @param int    $depth nesting of @media / @supports, at most 3
 * @return string
 */
function pg_mail_html_sheet($text, $depth = 0)
{
    $text = (string) $text;

    if ($depth === 0) {
        $text = preg_replace('#/\*.*?(\*/|$)#s', '', str_replace(array('<!--', '-->'), '', $text));
    }

    $out = '';
    $length = strlen($text);
    $i = 0;

    while ($i < $length) {
        $open = strpos($text, '{', $i);
        $semicolon = strpos($text, ';', $i);

        // A statement before the next block (@import, @charset, a stray
        // declaration) is left out up to its ";".
        if (($semicolon !== false) && (($open === false) || ($semicolon < $open))) {
            $i = $semicolon + 1;

            continue;
        }

        if ($open === false) {
            break;
        }

        $close = pg_mail_html_block_end($text, $open);

        if ($close < 0) {
            break;
        }

        $prelude = trim(preg_replace('/\s+/', ' ', substr($text, $i, $open - $i)));
        $body = (string) substr($text, $open + 1, $close - $open - 1);
        $i = $close + 1;

        if (preg_match('/^@(media|supports)\s+(.+)$/i', $prelude, $at)) {
            $inner = ($depth < 3) ? pg_mail_html_sheet($body, $depth + 1) : '';

            if (($inner !== '') && pg_mail_html_css_ok($at[2])) {
                $out .= '@' . strtolower($at[1]) . ' ' . $at[2] . ' {' . $inner . '}';
            }

            continue;
        }

        if (($prelude === '') || (strlen($prelude) > 1000) || !pg_mail_html_css_ok($prelude) || (strpos($body, '{') !== false)) {
            continue;
        }

        $declarations = pg_mail_html_style($body);

        if ($declarations !== '') {
            $out .= $prelude . ' {' . $declarations . '}';
        }
    }

    return $out;
}

/**
 * A Content-ID as the cleaned HTML names it: decoded from its cid: form,
 * small letters, angle brackets taken off; '' when it holds a character
 * outside the plain set, so it can be written and matched as it is.
 *
 * @param string $value the part after "cid:", or a part's Content-ID
 * @return string
 */
function pg_mail_html_cid($value)
{
    $value = strtolower(trim(rawurldecode((string) $value), " \t<>"));

    return preg_match('/^[a-z0-9._@+=$-]{1,200}$/', $value) ? $value : '';
}

/**
 * An attribute that names a picture (src, background): the attributes it
 * becomes. Outside web addresses are set aside under data-pg-<name>, a
 * picture of the mail is named by data-pg-cid, an embedded raster picture
 * keeps its src; anything else gives nothing.
 *
 * @param string $name src or background
 * @param string $value
 * @return array attribute => value
 */
function pg_mail_html_picture($name, $value)
{
    $value = trim((string) $value);

    if (preg_match('/[\x00-\x1F\x7F]/', $value)) {
        return array();
    }

    if (preg_match('#^https?://[^/?\#\s]#i', $value)) {
        return array('data-pg-' . $name => $value);
    }

    if (($name === 'src') && (stripos($value, 'cid:') === 0)) {
        $cid = pg_mail_html_cid(substr($value, 4));

        return ($cid !== '') ? array('data-pg-cid' => $cid) : array();
    }

    if (($name === 'src') && preg_match('#^data:image/(png|jpeg|gif|webp);base64,[A-Za-z0-9+/=\s]+$#i', $value)) {
        return array('src' => preg_replace('/\s+/', '', $value));
    }

    return array();
}

/**
 * The attributes a kept element is written with.
 *
 * @param DOMElement $node
 * @param array      $own the element's own attributes (pg_mail_html_elements())
 * @param array      $common
 * @return array name => value
 */
function pg_mail_html_attributes($node, $own, $common)
{
    $kept = array();

    foreach ($node->attributes as $attribute) {
        $name = strtolower($attribute->nodeName);
        $value = (string) $attribute->nodeValue;

        if (!in_array($name, $own, true) && !in_array($name, $common, true)) {
            continue;
        }

        if ($name === 'style') {
            $value = pg_mail_html_style($value);
        } elseif ($name === 'href') {
            $value = pg_mail_html_href($value);
        } elseif (($name === 'src') || ($name === 'background')) {
            $kept = array_merge($kept, pg_mail_html_picture($name, $value));

            continue;
        } elseif (($name === 'dir') && !in_array(strtolower($value), array('ltr', 'rtl', 'auto'), true)) {
            continue;
        }

        if (($value !== '') || in_array($name, array('nowrap', 'reversed', 'noshade', 'open'), true)) {
            $kept[$name] = $value;
        }
    }

    return $kept;
}

/**
 * Writes the kept children of a node, depth first.
 *
 * @param DOMNode $node
 * @param int     $depth
 * @param array   $state elements, common, dropped, void, sheets (the style
 *                       sheets met, filled here), seen (a picture or text)
 * @return string
 */
function pg_mail_html_walk($node, $depth, &$state)
{
    $out = '';

    foreach ($node->childNodes as $child) {
        if ($child->nodeType === XML_TEXT_NODE) {
            if (trim($child->nodeValue) !== '') {
                $state['seen'] = true;
            }

            $out .= htmlspecialchars($child->nodeValue, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

            continue;
        }

        if ($child->nodeType !== XML_ELEMENT_NODE) {
            continue;
        }

        $name = strtolower($child->nodeName);

        if ($name === 'style') {
            $state['sheets'][] = $child->textContent;

            continue;
        }

        if ($depth >= 200) {
            continue;
        }

        // The head's own parts are left out; its style sheets are read.
        if ($name === 'head') {
            pg_mail_html_walk($child, $depth + 1, $state);

            continue;
        }

        if (isset($state['dropped'][$name])) {
            if (isset($state['void'][$name])) {
                $out .= pg_mail_html_walk($child, $depth + 1, $state);
            }

            continue;
        }

        if (!isset($state['elements'][$name])) {
            $out .= pg_mail_html_walk($child, $depth + 1, $state);

            continue;
        }

        $attributes = pg_mail_html_attributes($child, $state['elements'][$name], $state['common']);

        // A picture with nothing left to show is left out.
        if (($name === 'img') && !isset($attributes['src']) && !isset($attributes['data-pg-src']) && !isset($attributes['data-pg-cid'])) {
            continue;
        }

        if ($name === 'img') {
            $state['seen'] = true;
        }

        $tag = '<' . $name;

        foreach ($attributes as $attribute => $value) {
            $tag .= ' ' . $attribute . '="' . htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
        }

        $inner = pg_mail_html_walk($child, $depth + 1, $state);
        $out .= $tag . '>' . (isset($state['void'][$name]) ? $inner : $inner . '</' . $name . '>');
    }

    return $out;
}

/**
 * Cleans a mail's HTML part. The result is a fragment: the mail's style
 * sheets (cleaned) in one <style> element, then the body's kept content,
 * inside a <div> carrying the body's own look when it had one. '' when
 * there is no HTML, it is larger than pg_mail_html_max_bytes() before or
 * after cleaning, nothing to read is left, or PHP has no DOM extension.
 *
 * @param string $html UTF-8, as pg_mail_parse() gives it
 * @return string
 */
function pg_mail_html_clean($html)
{
    $html = (string) $html;

    if ((trim($html) === '') || (strlen($html) > pg_mail_html_max_bytes()) || !class_exists('DOMDocument')) {
        return '';
    }

    if (function_exists('mb_convert_encoding')) {
        $html = (string) mb_convert_encoding($html, 'UTF-8', 'UTF-8');
    }

    // The parser follows the first character set it is told; the mail's own
    // meta tags name the one it was sent in, before the parser made it UTF-8.
    $html = '<meta http-equiv="Content-Type" content="text/html; charset=utf-8">' . preg_replace('/<meta\b[^>]*>/i', '', $html);

    $document = new DOMDocument();
    $errors = libxml_use_internal_errors(true);
    $loaded = $document->loadHTML($html, LIBXML_NONET | LIBXML_COMPACT);
    libxml_clear_errors();
    libxml_use_internal_errors($errors);

    if (!$loaded) {
        return '';
    }

    $state = array(
        'elements' => pg_mail_html_elements(),
        'common'   => pg_mail_html_common_attributes(),
        'dropped'  => array_flip(pg_mail_html_dropped_elements()),
        'void'     => array_flip(pg_mail_html_void_elements()),
        'sheets'   => array(),
        'seen'     => false,
    );

    $body = $document->getElementsByTagName('body')->item(0);
    $content = '';

    foreach ($document->getElementsByTagName('head') as $head) {
        pg_mail_html_walk($head, 1, $state);
    }

    if ($body) {
        $content = pg_mail_html_walk($body, 1, $state);
        $look = pg_mail_html_attributes($body, array('background'), array('style', 'class', 'dir', 'lang', 'bgcolor'));

        // bgcolor means nothing on a <div>: it becomes the first declaration.
        if (isset($look['bgcolor'])) {
            $color = pg_mail_html_style('background-color: ' . $look['bgcolor']);
            $style = trim($color . (isset($look['style']) ? '; ' . $look['style'] : ''), '; ');
            unset($look['bgcolor'], $look['style']);

            if ($style !== '') {
                $look['style'] = $style;
            }
        }

        if ($look) {
            $tag = '<div';

            foreach ($look as $attribute => $value) {
                $tag .= ' ' . $attribute . '="' . htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
            }

            $content = $tag . '>' . $content . '</div>';
        }
    }

    if (!$state['seen']) {
        return '';
    }

    $sheet = pg_mail_html_sheet(implode("\n", $state['sheets']));
    $out = (($sheet !== '') ? '<style>' . $sheet . '</style>' : '') . $content;

    return (strlen($out) > pg_mail_html_max_bytes()) ? '' : $out;
}

/**
 * The Content-IDs of the pictures a cleaned HTML shows from the mail
 * itself, each once.
 *
 * @param string $clean pg_mail_html_clean()
 * @return array
 */
function pg_mail_html_cids($clean)
{
    preg_match_all('/ data-pg-cid="([^"]+)"/', (string) $clean, $found);

    return array_values(array_unique($found[1]));
}

/**
 * A cleaned HTML whose pictures of the mail name their kept file: each
 * data-pg-cid with a file in $files gains data-pg-file. '' when the result
 * is over pg_mail_html_max_bytes().
 *
 * @param string $clean pg_mail_html_clean()
 * @param array  $files Content-ID (pg_mail_html_cid()) => file id
 * @return string
 */
function pg_mail_html_link_files($clean, $files)
{
    $clean = preg_replace_callback('/ data-pg-cid="([^"]+)"/', function ($m) use ($files) {
        return isset($files[$m[1]]) && ((int) $files[$m[1]] > 0) ? $m[0] . ' data-pg-file="' . (int) $files[$m[1]] . '"' : $m[0];
    }, (string) $clean);

    return (strlen($clean) > pg_mail_html_max_bytes()) ? '' : $clean;
}
