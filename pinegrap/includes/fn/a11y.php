<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Accessibility report: WCAG 2.2 checks over a page's rendered markup, and
 * the nightly pass (seo_a11y_job.php) that runs them over every published
 * page.
 *
 * The findings live in seo_issue beside the SEO structure findings, told
 * apart by their code: every accessibility code starts with a11y_. They are
 * not part of the SEO score; the SEO readers (pg_seo_load_issues(), the flag
 * map, GET /seo/issues) leave them out unless asked for them.
 *
 * Only what the server can answer from the markup is checked. Contrast is
 * computable only where both colours are written inline, and target size only
 * where the size is; the visual designer measures both on the canvas
 * (assets/js/pg_contrast.js).
 *
 * Loaded by the job and the screens that read the report, not by
 * functions.php.
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
 * Every check: its severity, the WCAG 2.2 success criterion it reads and that
 * criterion's level. 'document' rules need a whole page and are skipped for
 * output that is not one (seo-structure-non-html-output.md).
 *
 * @return array<string,array>
 */
function pg_a11y_rules()
{
    return array(
        'a11y_page_title'        => array('severity' => 'error',   'criterion' => '2.4.2', 'level' => 'A',  'document' => true),
        'a11y_html_lang'         => array('severity' => 'error',   'criterion' => '3.1.1', 'level' => 'A',  'document' => true),
        'a11y_main_missing'      => array('severity' => 'warning', 'criterion' => '2.4.1', 'level' => 'A',  'document' => true),
        'a11y_heading_order'     => array('severity' => 'warning', 'criterion' => '1.3.1', 'level' => 'A',  'document' => false),
        'a11y_img_alt'           => array('severity' => 'error',   'criterion' => '1.1.1', 'level' => 'A',  'document' => false),
        'a11y_form_label'        => array('severity' => 'error',   'criterion' => '1.3.1', 'level' => 'A',  'document' => false),
        'a11y_idref_broken'      => array('severity' => 'warning', 'criterion' => '1.3.1', 'level' => 'A',  'document' => false),
        'a11y_error_association' => array('severity' => 'warning', 'criterion' => '3.3.1', 'level' => 'A',  'document' => false),
        'a11y_link_name'         => array('severity' => 'error',   'criterion' => '2.4.4', 'level' => 'A',  'document' => false),
        'a11y_link_generic'      => array('severity' => 'notice',  'criterion' => '2.4.4', 'level' => 'A',  'document' => false),
        'a11y_button_name'       => array('severity' => 'error',   'criterion' => '4.1.2', 'level' => 'A',  'document' => false),
        'a11y_frame_title'       => array('severity' => 'error',   'criterion' => '4.1.2', 'level' => 'A',  'document' => false),
        'a11y_media_autoplay'    => array('severity' => 'warning', 'criterion' => '1.4.2', 'level' => 'A',  'document' => false),
        'a11y_contrast'          => array('severity' => 'warning', 'criterion' => '1.4.3', 'level' => 'AA', 'document' => false),
        'a11y_target_size'       => array('severity' => 'notice',  'criterion' => '2.5.8', 'level' => 'AA', 'document' => false),
    );
}

// WCAG 2.2 success criteria the checks read, by number, with their names.
function pg_a11y_criteria()
{
    return array(
        '1.1.1' => lang('Non-text Content'),
        '1.3.1' => lang('Info and Relationships'),
        '1.4.2' => lang('Audio Control'),
        '1.4.3' => lang('Contrast (Minimum)'),
        '2.4.1' => lang('Bypass Blocks'),
        '2.4.2' => lang('Page Titled'),
        '2.4.4' => lang('Link Purpose (In Context)'),
        '2.5.8' => lang('Target Size (Minimum)'),
        '3.1.1' => lang('Language of Page'),
        '3.3.1' => lang('Error Identification'),
        '4.1.2' => lang('Name, Role, Value'),
    );
}

// What a finding means, in a sentence for the report.
function pg_a11y_issue_label($code)
{
    switch ($code) {
        case 'a11y_page_title':
            return lang('The page has no title');
        case 'a11y_html_lang':
            return lang('The page does not state its language');
        case 'a11y_main_missing':
            return lang('No main content area to skip to');
        case 'a11y_heading_order':
            return lang('A heading level is skipped');
        case 'a11y_img_alt':
            return lang('Image without alternative text');
        case 'a11y_form_label':
            return lang('Form field without a label');
        case 'a11y_idref_broken':
            return lang('A label or description points to an element that is not on the page');
        case 'a11y_error_association':
            return lang('A field marked invalid does not point to its error message');
        case 'a11y_link_name':
            return lang('Link without text');
        case 'a11y_link_generic':
            return lang('Link text that does not say where it leads');
        case 'a11y_button_name':
            return lang('Button without a name');
        case 'a11y_frame_title':
            return lang('Frame without a title');
        case 'a11y_media_autoplay':
            return lang('Sound that plays by itself');
        case 'a11y_contrast':
            return lang('Text with too little contrast');
        case 'a11y_target_size':
            return lang('Link or button smaller than 24 by 24 pixels');
    }

    return $code;
}

/**
 * Every finding of the checks over one piece of markup, keyed by code: code,
 * severity, occurrences and the first example as detail.
 *
 * The document rules run only when the markup carries an <html> tag: a page
 * may answer JSON or a fragment, and DOMDocument wraps anything in html and
 * body, so the parsed tree cannot tell.
 *
 * @param string $html
 * @return array<string,array>
 */
function pg_a11y_check_html($html)
{
    $findings = array();
    $html = (string) $html;

    if (!class_exists('DOMDocument') || (strpos($html, '<') === false)) {
        return $findings;
    }

    $previous = libxml_use_internal_errors(true);
    $document = new DOMDocument();
    $loaded = $document->loadHTML('<?xml encoding="UTF-8">' . $html);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);

    if (!$loaded) {
        return $findings;
    }

    $xpath = new DOMXPath($document);
    $is_document = (stripos($html, '<html') !== false);

    foreach (pg_a11y_rules() as $code => $rule) {

        if ($rule['document'] && !$is_document) {
            continue;
        }

        $examples = call_user_func('pg_a11y_rule_' . substr($code, 5), $xpath);

        if ($examples) {
            $findings[$code] = array(
                'code'        => $code,
                'severity'    => $rule['severity'],
                'occurrences' => count($examples),
                'detail'      => mb_substr((string) $examples[0], 0, 250),
            );
        }
    }

    return $findings;
}

// ---- Shared readers -------------------------------------------------------

// Text of a node with script, style and template content left out, spaces
// collapsed.
function pg_a11y_text($node)
{
    $text = '';

    foreach ($node->childNodes as $child) {
        if ($child->nodeType === XML_TEXT_NODE) {
            $text .= $child->nodeValue;
        } elseif (($child->nodeType === XML_ELEMENT_NODE) && !in_array($child->nodeName, array('script', 'style', 'template', 'noscript'), true)) {
            $text .= ' ' . pg_a11y_text($child) . ' ';
        }
    }

    return trim(preg_replace('/\s+/u', ' ', $text));
}

// Whether the node or an ancestor is out of the accessibility tree.
function pg_a11y_hidden($node)
{
    for ($n = $node; $n && ($n->nodeType === XML_ELEMENT_NODE); $n = $n->parentNode) {
        if ($n->hasAttribute('hidden') || (strtolower($n->getAttribute('aria-hidden')) === 'true')) {
            return true;
        }

        if (preg_match('/(^|;)\s*display\s*:\s*none/i', $n->getAttribute('style'))) {
            return true;
        }
    }

    return false;
}

// The ids an idref attribute names, in order.
function pg_a11y_idrefs($value)
{
    return preg_split('/\s+/', trim((string) $value), -1, PREG_SPLIT_NO_EMPTY);
}

// The element with an id, or null. Looked up by walking the id attributes
// rather than with an XPath literal, which cannot hold a quote.
function pg_a11y_by_id($xpath, $id)
{
    // The map of the last document asked about. The document itself is the
    // key: holding it keeps it alive, so a later document cannot reuse its
    // object id and be answered from the wrong map.
    static $document = null;
    static $map = array();

    if ($document !== $xpath->document) {
        $document = $xpath->document;
        $map = array();

        foreach ($xpath->query('//*[@id]') as $element) {
            if (!isset($map[$element->getAttribute('id')])) {
                $map[$element->getAttribute('id')] = $element;
            }
        }
    }

    return isset($map[$id]) ? $map[$id] : null;
}

// Accessible name of an element, close to the browser's computation for the
// cases a page carries: aria-labelledby, aria-label, content (text, image
// alt, svg title), then title.
function pg_a11y_name($xpath, $element)
{
    if ($element->hasAttribute('aria-labelledby')) {
        $parts = array();

        foreach (pg_a11y_idrefs($element->getAttribute('aria-labelledby')) as $id) {
            $target = pg_a11y_by_id($xpath, $id);

            if ($target) {
                $parts[] = pg_a11y_text($target);
            }
        }

        $name = trim(implode(' ', $parts));

        if ($name !== '') {
            return $name;
        }
    }

    if (trim($element->getAttribute('aria-label')) !== '') {
        return trim($element->getAttribute('aria-label'));
    }

    $name = pg_a11y_text($element);

    if ($name === '') {
        foreach ($xpath->query('.//img[@alt]|.//*[local-name()="svg"]/*[local-name()="title"]|.//*[@aria-label]', $element) as $inner) {
            $value = ($inner->nodeName === 'img') ? $inner->getAttribute('alt')
                : ($inner->hasAttribute('aria-label') ? $inner->getAttribute('aria-label') : $inner->textContent);

            if (trim($value) !== '') {
                $name = trim($value);
                break;
            }
        }
    }

    if ($name === '') {
        $name = trim($element->getAttribute('title'));
    }

    return $name;
}

// ---- Rules: one per code, each returns one example per occurrence ---------

// 2.4.2: the page has a title.
function pg_a11y_rule_page_title($xpath)
{
    $title = $xpath->query('//title')->item(0);

    return ($title && (trim($title->textContent) !== '')) ? array() : array('');
}

// 3.1.1: the page names its language, in the form BCP 47 gives it.
function pg_a11y_rule_html_lang($xpath)
{
    $html = $xpath->query('/html')->item(0);
    $lang = $html ? trim($html->getAttribute('lang')) : '';

    if (($lang !== '') && preg_match('/^[a-z]{2,3}(-[a-z0-9]{1,8})*$/i', $lang)) {
        return array();
    }

    return array($lang);
}

// 2.4.1: a main landmark lets a keyboard or screen reader user step over the
// header and menus to the content.
function pg_a11y_rule_main_missing($xpath)
{
    return ($xpath->query('//main|//*[@role="main"]')->length > 0) ? array() : array('');
}

// 1.3.1: headings go down one level at a time (h2 then h3, not h2 then h4).
function pg_a11y_rule_heading_order($xpath)
{
    $examples = array();
    $previous = 0;

    foreach ($xpath->query('//h1|//h2|//h3|//h4|//h5|//h6|//*[@role="heading"][@aria-level]') as $heading) {

        if (pg_a11y_hidden($heading)) {
            continue;
        }

        $level = ($heading->getAttribute('role') === 'heading') ? (int) $heading->getAttribute('aria-level') : (int) substr($heading->nodeName, 1);

        if (($level < 1) || ($level > 6)) {
            continue;
        }

        if ($previous && ($level > $previous + 1)) {
            $examples[] = 'h' . $previous . ' → h' . $level . ': ' . mb_substr(pg_a11y_text($heading), 0, 80);
        }

        $previous = $level;
    }

    return $examples;
}

// 1.1.1: every image says what it shows, or alt="" says it is decoration.
function pg_a11y_rule_img_alt($xpath)
{
    $examples = array();

    foreach ($xpath->query('//img|//input[translate(@type,"IMAGE","image")="image"]|//area[@href]') as $image) {

        if ($image->hasAttribute('alt') && (($image->nodeName === 'img') || (trim($image->getAttribute('alt')) !== ''))) {
            continue;
        }

        if (in_array(strtolower($image->getAttribute('role')), array('presentation', 'none'), true) || pg_a11y_hidden($image)) {
            continue;
        }

        if ((trim($image->getAttribute('aria-label')) !== '') || $image->hasAttribute('aria-labelledby')) {
            continue;
        }

        $examples[] = $image->hasAttribute('src') ? $image->getAttribute('src') : $image->getAttribute('href');
    }

    return $examples;
}

// 1.3.1: every data entry control has a label. A placeholder is no label: it
// is gone as soon as the visitor types.
function pg_a11y_rule_form_label($xpath)
{
    $labelled = array();

    foreach ($xpath->query('//label[@for]') as $label) {
        $labelled[$label->getAttribute('for')] = true;
    }

    $examples = array();

    foreach ($xpath->query('//input|//select|//textarea') as $control) {

        if (($control->nodeName === 'input') && in_array(strtolower($control->getAttribute('type')), array('hidden', 'submit', 'button', 'reset', 'image'), true)) {
            continue;
        }

        if (pg_a11y_hidden($control)) {
            continue;
        }

        if (($control->getAttribute('id') !== '') && isset($labelled[$control->getAttribute('id')])) {
            continue;
        }

        if (($xpath->query('ancestor::label', $control)->length > 0) || (pg_a11y_name($xpath, $control) !== '')) {
            continue;
        }

        $examples[] = ($control->getAttribute('name') !== '') ? $control->getAttribute('name') : $control->nodeName;
    }

    return $examples;
}

// 1.3.1: a label or description that points at an id nobody has ties
// nothing to the control.
function pg_a11y_rule_idref_broken($xpath)
{
    $examples = array();

    foreach ($xpath->query('//label[@for]') as $label) {
        if ((trim($label->getAttribute('for')) !== '') && !pg_a11y_by_id($xpath, $label->getAttribute('for'))) {
            $examples[] = 'for="' . $label->getAttribute('for') . '"';
        }
    }

    foreach ($xpath->query('//*[@aria-labelledby or @aria-describedby or @aria-errormessage]') as $element) {
        foreach (array('aria-labelledby', 'aria-describedby', 'aria-errormessage') as $attribute) {
            foreach (pg_a11y_idrefs($element->getAttribute($attribute)) as $id) {
                if (!pg_a11y_by_id($xpath, $id)) {
                    $examples[] = $attribute . '="' . $id . '"';
                }
            }
        }
    }

    return $examples;
}

// 3.3.1: a field marked invalid points at the text that says what is wrong.
function pg_a11y_rule_error_association($xpath)
{
    $examples = array();

    foreach ($xpath->query('//*[translate(@aria-invalid,"TRUE","true")="true"]') as $control) {

        $described = false;

        foreach (array('aria-describedby', 'aria-errormessage') as $attribute) {
            foreach (pg_a11y_idrefs($control->getAttribute($attribute)) as $id) {
                $target = pg_a11y_by_id($xpath, $id);

                if ($target && (pg_a11y_text($target) !== '')) {
                    $described = true;
                }
            }
        }

        if (!$described) {
            $examples[] = ($control->getAttribute('name') !== '') ? $control->getAttribute('name') : $control->nodeName;
        }
    }

    return $examples;
}

// 2.4.4: every link has a name: text, an image's alt, a label.
function pg_a11y_rule_link_name($xpath)
{
    $examples = array();

    foreach ($xpath->query('//a[@href]') as $link) {
        if (!pg_a11y_hidden($link) && (pg_a11y_name($xpath, $link) === '')) {
            $examples[] = $link->getAttribute('href');
        }
    }

    return $examples;
}

// 2.4.4: "click here" and "read more" say nothing out of context, which is how
// a screen reader's list of links reads them. The word list is the SEO
// check's (pg_seo_generic_anchor_texts()), when it is loaded.
function pg_a11y_rule_link_generic($xpath)
{
    if (!function_exists('pg_seo_generic_anchor_texts')) {
        return array();
    }

    $generic = array_flip(pg_seo_generic_anchor_texts());
    $examples = array();

    foreach ($xpath->query('//a[@href]') as $link) {

        $name = mb_strtolower(trim(pg_a11y_name($xpath, $link), " \t\n.…:»›>→"));

        if (($name !== '') && isset($generic[$name]) && !pg_a11y_hidden($link)) {
            $examples[] = $name;
        }
    }

    return $examples;
}

// 4.1.2: every button has a name. An icon alone is no name.
function pg_a11y_rule_button_name($xpath)
{
    $examples = array();

    foreach ($xpath->query('//button|//*[@role="button"]|//input[translate(@type,"BUTON","buton")="button"]') as $button) {

        if (pg_a11y_hidden($button)) {
            continue;
        }

        $name = ($button->nodeName === 'input') ? trim($button->getAttribute('value')) : '';

        if (($name === '') && (pg_a11y_name($xpath, $button) === '')) {
            $examples[] = ($button->getAttribute('class') !== '') ? $button->nodeName . '.' . preg_replace('/\s+/', '.', trim($button->getAttribute('class'))) : $button->nodeName;
        }
    }

    return $examples;
}

// 4.1.2: a frame says what it holds.
function pg_a11y_rule_frame_title($xpath)
{
    $examples = array();

    foreach ($xpath->query('//iframe|//frame') as $frame) {

        // Only a label or a title names a frame; text inside the tag is
        // fallback content for browsers without frames.
        $named = (trim($frame->getAttribute('title')) !== '') || (trim($frame->getAttribute('aria-label')) !== '') || $frame->hasAttribute('aria-labelledby');

        if (!$named && !pg_a11y_hidden($frame)) {
            $examples[] = $frame->getAttribute('src');
        }
    }

    return $examples;
}

// 1.4.2: sound that starts by itself drowns out the screen reader. A muted
// video is fine.
function pg_a11y_rule_media_autoplay($xpath)
{
    $examples = array();

    foreach ($xpath->query('//audio[@autoplay]|//video[@autoplay][not(@muted)]') as $media) {
        $source = $media->getAttribute('src');

        if ($source === '') {
            $inner = $xpath->query('.//source[@src]', $media)->item(0);
            $source = $inner ? $inner->getAttribute('src') : $media->nodeName;
        }

        $examples[] = $source;
    }

    return $examples;
}

// One declaration of an inline style attribute, lower case, or ''.
function pg_a11y_style_value($element, $property)
{
    if (preg_match_all('/(?:^|;)\s*' . preg_quote($property, '/') . '\s*:\s*([^;]+)/i', $element->getAttribute('style'), $m)) {
        return strtolower(trim(str_ireplace('!important', '', end($m[1]))));
    }

    return '';
}

// A CSS colour as array(r, g, b), or null for anything that is not an opaque
// colour this check can be sure of (a variable, a gradient, transparency).
function pg_a11y_color($value)
{
    $value = strtolower(trim((string) $value));
    $named = array('white' => '#ffffff', 'black' => '#000000');

    if (isset($named[$value])) {
        $value = $named[$value];
    }

    if (preg_match('/^#([0-9a-f]{3}|[0-9a-f]{6})$/', $value)) {
        return _pg_theme_rgb($value);
    }

    if (preg_match('/^rgba?\(\s*(\d+)[\s,]+(\d+)[\s,]+(\d+)\s*(?:[,\/]\s*([\d.]+%?)\s*)?\)$/', $value, $m)) {
        if (isset($m[4]) && ($m[4] !== '') && ($m[4] !== '1') && ($m[4] !== '100%')) {
            return null;
        }

        return array(min(255, (int) $m[1]), min(255, (int) $m[2]), min(255, (int) $m[3]));
    }

    return null;
}

// 1.4.3: text reads at 4.5:1 against its background, 3:1 when large (24px, or
// 18.66px bold). Only text whose colour and background are both written
// inline, on it or on an ancestor, can be measured here.
function pg_a11y_rule_contrast($xpath)
{
    $examples = array();

    foreach ($xpath->query('//body//*[text()[normalize-space()]][ancestor-or-self::*[@style]]') as $element) {

        if (in_array($element->nodeName, array('script', 'style', 'noscript', 'template'), true) || pg_a11y_hidden($element)) {
            continue;
        }

        $foreground = null;
        $background = null;
        $size = '';
        $weight = '';

        for ($n = $element; $n && ($n->nodeType === XML_ELEMENT_NODE); $n = $n->parentNode) {

            if (!$n->hasAttribute('style')) {
                continue;
            }

            if (($foreground === null) && (($value = pg_a11y_style_value($n, 'color')) !== '')) {
                $foreground = pg_a11y_color($value);

                if ($foreground === null) {
                    break;
                }
            }

            if ($background === null) {
                $value = pg_a11y_style_value($n, 'background-color');

                if ($value === '') {
                    $value = pg_a11y_style_value($n, 'background');
                }

                if ($value !== '') {
                    $background = pg_a11y_color($value);

                    // A background image or gradient under the text: no
                    // single colour to measure against.
                    if ($background === null) {
                        break;
                    }
                }
            }

            if ($size === '') {
                $size = pg_a11y_style_value($n, 'font-size');
            }

            if ($weight === '') {
                $weight = pg_a11y_style_value($n, 'font-weight');
            }

            if (($foreground !== null) && ($background !== null)) {
                break;
            }
        }

        if (($foreground === null) || ($background === null)) {
            continue;
        }

        $pixels = 0;

        if (preg_match('/^([\d.]+)(px|pt)$/', $size, $m)) {
            $pixels = ($m[2] === 'pt') ? (float) $m[1] * 4 / 3 : (float) $m[1];
        }

        $bold = in_array($weight, array('bold', 'bolder'), true) || ((int) $weight >= 700)
            || in_array($element->nodeName, array('b', 'strong', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'th'), true);
        $large = ($pixels >= 24) || ($bold && ($pixels >= 18.66));
        $ratio = _pg_theme_contrast($foreground, $background);

        if ($ratio < ($large ? 3 : 4.5)) {
            $examples[] = sprintf('%.2f:1 %s / %s', floor($ratio * 100) / 100, _pg_theme_hex($foreground), _pg_theme_hex($background));
        }
    }

    return $examples;
}

// 2.5.8: a target is at least 24 by 24 CSS pixels. Only links and buttons
// whose width and height are written inline in pixels can be measured here,
// and the spacing exception cannot be: this is a notice to look at, not a
// failure.
function pg_a11y_rule_target_size($xpath)
{
    $examples = array();

    foreach ($xpath->query('//a[@href][@style]|//button[@style]|//*[@role="button"][@style]') as $target) {

        $width = pg_a11y_style_value($target, 'width');
        $height = pg_a11y_style_value($target, 'height');

        if (!preg_match('/^([\d.]+)px$/', $width, $w) || !preg_match('/^([\d.]+)px$/', $height, $h)) {
            continue;
        }

        if ((((float) $w[1] < 24) || ((float) $h[1] < 24)) && !pg_a11y_hidden($target)) {
            $examples[] = $w[1] . '×' . $h[1];
        }
    }

    return $examples;
}

// ---- The nightly pass -----------------------------------------------------

// Where an unfinished pass keeps its place. Losing the file (the clean up
// tool empties data/temp) only starts the pass from the first page again.
function pg_a11y_state_path()
{
    return PG_FUNCTIONS_DIR . '/data/temp/seo_a11y.json';
}

/**
 * The pages the report covers, page_id => row: published (public folder, not
 * archived) and of a type the structure pass can render. Classic pages are
 * read like visual ones; nothing in their markup is changed.
 *
 * @return array<int,array>
 */
function pg_a11y_pages()
{
    $types = pg_seo_renderable_page_types();
    $pages = array();

    $rows = db_items(
        "SELECT page.page_id, page.page_name, page.page_type, page.page_folder
        FROM page
        LEFT JOIN folder ON page.page_folder = folder.folder_id
        WHERE (folder.folder_archived IS NULL) OR (folder.folder_archived <> '1')
        ORDER BY page.page_id ASC");

    // Asked once per folder: every run lists the whole site again.
    $public = array();

    foreach ($rows as $row) {

        if (!in_array($row['page_type'], $types, true)) {
            continue;
        }

        if (!isset($public[$row['page_folder']])) {
            $public[$row['page_folder']] = (get_access_control_type($row['page_folder']) === 'public');
        }

        if ($public[$row['page_folder']]) {
            $pages[(int) $row['page_id']] = $row;
        }
    }

    return $pages;
}

/**
 * Check one page and store what was found. False when the page could not be
 * rendered: its earlier findings are then kept rather than cleared, since a
 * render error says nothing about the markup.
 *
 * @param int $page_id
 * @param array $page_row page_name, page_type
 * @return bool
 */
function pg_a11y_check_page($page_id, $page_row)
{
    $render = pg_seo_render_page($page_id, $page_row);

    if (($render === null) || ($render['error'] !== '')) {
        return false;
    }

    pg_seo_store_issues('page', $page_id, pg_a11y_check_html($render['html']), 'a11y');

    return true;
}

/**
 * One bounded run of the pass. The pass walks the pages in id order and
 * carries on from where the last run stopped; when it reaches the end it
 * removes the findings of pages that left the report (made private, folder
 * archived) and reports done, which is when the job records its run.
 *
 * @param int $time_budget Seconds of work before the run stops.
 * @return array checked, failed, done, ready
 */
function pg_a11y_run($time_budget)
{
    if (!pg_seo_structure_schema_ready() || !class_exists('DOMDocument')) {
        return array('checked' => 0, 'failed' => 0, 'done' => true, 'ready' => false);
    }

    $started = microtime(true);
    $state = json_decode((string) @file_get_contents(pg_a11y_state_path()), true);
    $after = (is_array($state) && isset($state['after'])) ? (int) $state['after'] : 0;
    $pages = pg_a11y_pages();
    $checked = 0;
    $failed = 0;

    foreach ($pages as $page_id => $row) {

        if ($page_id <= $after) {
            continue;
        }

        if ((microtime(true) - $started) > max(1, (int) $time_budget)) {
            @file_put_contents(pg_a11y_state_path(), json_encode(array('after' => $after)), LOCK_EX);

            return array('checked' => $checked, 'failed' => $failed, 'done' => false, 'ready' => true);
        }

        if (pg_a11y_check_page($page_id, $row)) {
            $checked++;
        } else {
            $failed++;
        }

        $after = $page_id;
    }

    db(
        "DELETE FROM seo_issue
        WHERE
            (entity_type = 'page')
            AND (LEFT(code, 5) = 'a11y_')
            AND (entity_id NOT IN (" . ($pages ? implode(',', array_keys($pages)) : '0') . "))");

    @unlink(pg_a11y_state_path());

    return array('checked' => $checked, 'failed' => $failed, 'done' => true, 'ready' => true);
}

// ---- The report -----------------------------------------------------------

/**
 * Pages and occurrences per code across the site, for the checklist.
 *
 * @return array<string,array> code => array('pages' => int, 'occurrences' => int)
 */
function pg_a11y_report_summary()
{
    $summary = array();

    foreach (db_items(
        "SELECT code, COUNT(DISTINCT entity_id) AS pages, SUM(occurrences) AS occurrences
        FROM seo_issue
        WHERE (entity_type = 'page') AND (LEFT(code, 5) = 'a11y_')
        GROUP BY code") as $row) {

        $summary[$row['code']] = array('pages' => (int) $row['pages'], 'occurrences' => (int) $row['occurrences']);
    }

    return $summary;
}

/**
 * Findings with their page, worst first, at most $limit of them.
 *
 * @param string $code One check, or '' for all.
 * @param int $limit
 * @return array
 */
function pg_a11y_report_rows($code, $limit)
{
    $rules = pg_a11y_rules();
    $sql_code = isset($rules[$code]) ? " AND (seo_issue.code = '" . e($code) . "')" : " AND (LEFT(seo_issue.code, 5) = 'a11y_')";

    return db_items(
        "SELECT seo_issue.entity_id AS page_id, page.page_name, seo_issue.code, seo_issue.severity, seo_issue.occurrences, seo_issue.detail
        FROM seo_issue
        JOIN page ON page.page_id = seo_issue.entity_id
        WHERE (seo_issue.entity_type = 'page')" . $sql_code . "
        ORDER BY FIELD(seo_issue.severity, 'error', 'warning', 'notice'), seo_issue.code, page.page_name
        LIMIT " . (int) $limit);
}

/**
 * The checklist report as a printable HTML document, for the PDF export:
 * one row per criterion with the checks under it, then the findings.
 *
 * @param array $summary pg_a11y_report_summary()
 * @param array $rows pg_a11y_report_rows()
 * @param int $pages Pages the report covers.
 * @param int $last_run Time the last full pass ended, 0 for never.
 * @param string $site The site's host name.
 * @return string
 */
function pg_a11y_report_html($summary, $rows, $pages, $last_run, $site)
{
    $criteria = pg_a11y_criteria();
    $by_criterion = array();

    foreach (pg_a11y_rules() as $code => $rule) {
        $by_criterion[$rule['criterion']][$code] = $rule;
    }

    ksort($by_criterion, SORT_NATURAL);

    // A comment or text before the charset meta makes dompdf read the page
    // as Latin-1 (core/dompdf-embedding.md).
    $html = '<!DOCTYPE html><html><head><meta charset="utf-8"><title>' . h(lang('Accessibility report')) . '</title><style>'
        . 'body{font-family:"DejaVu Sans",sans-serif;font-size:9pt;color:#212529}h1{font-size:15pt;margin:0 0 4pt}h2{font-size:11pt;margin:14pt 0 4pt}'
        . 'table{width:100%;border-collapse:collapse}th,td{border:0.5pt solid #adb5bd;padding:3pt 4pt;text-align:left;vertical-align:top}th{background:#e9ecef}'
        . '.muted{color:#6c757d}.ok{color:#146c43}.fail{color:#b02a37}</style></head><body>'
        . '<h1>' . h(lang('Accessibility report')) . '</h1>'
        . '<p>' . h($site) . ' · ' . h(lang(array('string' => '{var:1} published pages checked', 'vars' => array($pages)))) . ' · '
        . h($last_run ? lang(array('string' => 'last full check {var:1}', 'vars' => array(date('Y-m-d H:i', $last_run)))) : lang('no full check has finished yet')) . '</p>'
        . '<p class="muted">' . h(lang('The checks read the published markup of each page against WCAG 2.2. They cover only what can be checked automatically; every criterion still needs a person to review the rest.')) . '</p>'
        . '<h2>' . h(lang('Checklist')) . '</h2><table><tr><th>' . h(lang('Criterion')) . '</th><th>' . h(lang('Level')) . '</th><th>' . h(lang('Check')) . '</th><th>' . h(lang('Result')) . '</th></tr>';

    foreach ($by_criterion as $number => $codes) {
        foreach ($codes as $code => $rule) {
            $found = isset($summary[$code]) ? $summary[$code] : null;

            $html .= '<tr><td>' . h($number . ' ' . (isset($criteria[$number]) ? $criteria[$number] : '')) . '</td><td>' . h($rule['level']) . '</td><td>' . h(pg_a11y_issue_label($code)) . '</td><td>'
                . ($found
                    ? '<span class="fail">' . h(lang(array('string' => '{var:1} page(s), {var:2} time(s)', 'vars' => array($found['pages'], $found['occurrences'])))) . '</span>'
                    : '<span class="ok">' . h(lang('No problem found')) . '</span>')
                . '</td></tr>';
        }
    }

    $html .= '</table>';

    if ($rows) {
        $html .= '<h2>' . h(lang('Findings')) . '</h2><table><tr><th>' . h(lang('Page')) . '</th><th>' . h(lang('Problem')) . '</th><th>' . h(lang('Times')) . '</th><th>' . h(lang('Example')) . '</th></tr>';

        foreach ($rows as $row) {
            $html .= '<tr><td>' . h($row['page_name']) . '</td><td>' . h(pg_a11y_issue_label($row['code'])) . '</td><td>' . (int) $row['occurrences'] . '</td><td>' . h($row['detail']) . '</td></tr>';
        }

        $html .= '</table>';
    }

    return $html . '</body></html>';
}
