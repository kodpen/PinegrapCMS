<?php
/**
 * PineGrap - Enterprise Website Platform
 *
 * Originally developed as LiveSite by Camelback Web Architects.
 * Since 2017, maintained and evolved by Erdal Güral (Kodpen) under the name PineGrap.
 * The final LiveSite update (2019) has been integrated into PineGrap.
 * LiveSite remains available as a separate downloadable legacy version.
 *
 * @author      Camelback Web Architects
 *              Erdal Güral (Kodpen)
 * @link        https://livesite.com
 *              https://kodpen.com
 * @copyright   2001–2019 Camelback Consulting, Inc.
 *              2016–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// HTML structure analysis: the second half of the SEO score.
//
// The meta half (seo.php) reads database fields. This half reads the page as
// it is actually rendered and checks how the markup is built - heading
// hierarchy, image alt text, invalid nesting, structured data, the tags that
// only exist if the page template emitted them.
//
// The source of the HTML is get_page_content(), called in process. It is an
// ordinary function that takes a page id and returns the assembled document
// as a string; update_search_index.php has been calling it that way for
// years. There is no crawler here, no HTTP request, no rate limiting and
// nothing for the firewall to block.
//
// This file is separate from seo.php because the list screens never need it:
// they read stored scores, and loading a few hundred lines of DOM rules on
// every page of the control panel would be waste.

// Severity to deduction. Structure scoring is subtractive rather than a
// weighted sum, because the number of findings is unbounded - a page can
// have one missing alt attribute or forty.
//
// The per-rule cap is the important half. Without it forty missing alt
// attributes would deduct 320 points, the score would floor at zero, and
// every other finding on that page would become invisible. With it, one
// class of problem can cost at most one band of the score and the rest still
// shows through.
function pg_seo_severity_weights()
{
    return array(
        'error'   => array('each' => 8, 'cap' => 24),
        'warning' => array('each' => 4, 'cap' => 12),
        'notice'  => array('each' => 1.5, 'cap' => 6),
    );
}

// Severity per rule code. Kept in one place so the score, the filters and
// the detail panel cannot disagree about how serious a finding is.
function pg_seo_structure_severities()
{
    return array(
        // Document level tags. These fire when the page template never
        // emitted the tag at all, which is a different failure from leaving
        // the corresponding field empty and is invisible from the database.
        'title_tag_missing'         => 'error',
        'title_tag_multiple'        => 'warning',
        'meta_description_tag_missing' => 'error',
        'canonical_missing'         => 'warning',
        'canonical_multiple'        => 'warning',
        'noindex_but_in_sitemap'    => 'error',
        'viewport_missing'          => 'notice',
        'og_incomplete'             => 'notice',

        // Headings
        'h1_missing'                => 'error',
        'h1_multiple'               => 'warning',
        'h1_empty'                  => 'error',
        'h1_equals_title'           => 'notice',
        'h1_too_long'               => 'notice',
        'heading_starts_at_h2'      => 'warning',
        'heading_level_skip'        => 'warning',
        'heading_empty'             => 'warning',
        'heading_is_paragraph'      => 'notice',
        'heading_duplicate_text'    => 'notice',

        // Text
        'p_contains_block'          => 'error',
        'p_empty'                   => 'notice',
        'br_as_paragraph'           => 'notice',
        'presentational_tag'        => 'warning',
        'bold_italic_tag'           => 'notice',
        'inline_style_heavy'        => 'notice',

        // Lists and tables
        'list_invalid_child'        => 'error',
        'table_no_header'           => 'notice',

        // Images
        'img_no_alt'                => 'error',
        'img_alt_is_filename'       => 'warning',
        'img_no_dimensions'         => 'notice',
        'img_no_lazy'               => 'notice',

        // Links
        'link_empty'                => 'error',
        'link_empty_href'           => 'warning',
        'link_generic_anchor'       => 'notice',
        'link_blank_no_noopener'    => 'warning',

        // Semantics and accessibility
        'html_lang_missing'         => 'warning',
        'main_missing'              => 'notice',
        'main_multiple'             => 'warning',
        'landmark_missing'          => 'notice',
        'input_no_label'            => 'warning',
        'iframe_no_title'           => 'notice',
        'button_empty'              => 'warning',

        // Content volume
        'thin_content'              => 'warning',
        'low_text_ratio'            => 'notice',

        // Structured data
        'jsonld_invalid'            => 'error',
        'jsonld_missing_type'       => 'warning',
        'jsonld_missing'            => 'notice',
        'product_schema_incomplete' => 'notice',

        // Set by the page analyzer rather than by the rule pass, because
        // answering it needs to know which layer the heading came from.
        'h1_only_in_template'       => 'warning',
    );
}

// Anchor texts that describe the click instead of the destination. Turkish
// first because that is what these sites are written in; the English ones
// appear in imported and third-party content.
function pg_seo_generic_anchor_texts()
{
    return array(
        'tıkla', 'tıklayın', 'tıklayınız', 'buraya', 'buraya tıklayın',
        'buradan', 'devamı', 'devamını oku', 'daha fazla', 'daha fazlası',
        'detay', 'detaylar', 'incele', 'göster', 'link', 'bağlantı',
        'click here', 'click', 'read more', 'more', 'here', 'this link',
        'download', 'learn more',
    );
}

// Parse a string of HTML into a DOMXPath, or NULL when the extension is
// missing or the input has no markup worth walking.
//
// The <?xml encoding> prefix is what makes UTF-8 survive: without it
// loadHTML() assumes ISO-8859-1 and every Turkish character in the document
// is mangled before a single rule runs. It leaves a processing instruction
// node at the root, which no query below matches.
function pg_seo_html_xpath($html)
{
    if (!class_exists('DOMDocument')) {
        return NULL;
    }

    $html = trim((string) $html);

    if (($html === '') || (strpos($html, '<') === FALSE)) {
        return NULL;
    }

    $previous_state = libxml_use_internal_errors(TRUE);

    $document = new DOMDocument();
    $loaded = $document->loadHTML('<?xml encoding="UTF-8">' . $html);

    libxml_clear_errors();
    libxml_use_internal_errors($previous_state);

    if (!$loaded) {
        return NULL;
    }

    return new DOMXPath($document);
}

// Visible text of a node with script, style and noscript removed. Used for
// word counts and for deciding whether an element is empty.
//
// The fast path matters: this is called once per heading, link, paragraph
// and button on the page. Almost none of them contain a script, so the
// expensive branch - clone the subtree into a scratch document, strip, read
// back - only runs for the handful that do.
function pg_seo_visible_text($xpath, $node = NULL)
{
    $context = $node ? $node : $xpath->document->documentElement;

    if (!$context) {
        return '';
    }

    if ($xpath->query('.//script|.//style|.//noscript', $context)->length === 0) {
        return trim(preg_replace('/\s+/u', ' ', $context->textContent));
    }

    $scratch = new DOMDocument();
    $scratch->appendChild($scratch->importNode($context->cloneNode(TRUE), TRUE));
    $scratch_xpath = new DOMXPath($scratch);

    foreach ($scratch_xpath->query('//script|//style|//noscript') as $removable) {
        if ($removable->parentNode) {
            $removable->parentNode->removeChild($removable);
        }
    }

    return trim(preg_replace('/\s+/u', ' ', $scratch->textContent));
}

// Word count of a text string. Turkish is space separated like English, so
// splitting on whitespace is the right measure here.
function pg_seo_count_words($text)
{
    $text = (string) $text;
    $normalized = preg_replace('/\s+/u', ' ', $text);

    // The /u modifier makes preg_replace return NULL on invalid UTF-8, and a
    // single stray byte in an imported description is enough. Falling back to
    // the byte-wise pattern keeps the count honest instead of reporting a
    // fully written product as having no words at all.
    if ($normalized === NULL) {
        $normalized = preg_replace('/\s+/', ' ', $text);
    }

    $normalized = trim((string) $normalized);

    if ($normalized === '') {
        return 0;
    }

    return count(explode(' ', $normalized));
}

// Analyze one piece of HTML.
//
// $mode 'document' runs the whole rule set. 'fragment' skips everything that
// only makes sense for a complete page - a product description is not
// missing a <title> tag or an <html lang> attribute, and it is allowed to
// have no <h1> because the catalog template supplies one.
//
// $context carries what the markup cannot answer for itself: the page title
// (for h1_equals_title), whether the record is in the sitemap (for
// noindex_but_in_sitemap), and whether the installation has Open Graph and
// structured data turned on - a feature that is switched off produces no
// output by design and must not be reported as missing.
//
// Returns code => array(code, severity, occurrences, detail).
function pg_seo_analyze_html($html, $mode = 'document', $context = array(), &$links = null)
{
    $findings = array();
    $severities = pg_seo_structure_severities();

    // Filled in for the caller when it asked for links. Collected here
    // rather than by a second parse: the document is already in a DOM by the
    // time the link rules run, and re-reading a full page to find the same
    // anchors again would double the most expensive step of the pass.
    $collect_links = is_array($links);

    $add = function ($code, $occurrences = 1, $detail = '') use (&$findings, $severities) {
        if (!isset($severities[$code])) {
            return;
        }

        if (!isset($findings[$code])) {
            $findings[$code] = array(
                'code' => $code,
                'severity' => $severities[$code],
                'occurrences' => 0,
                'detail' => '',
            );
        }

        $findings[$code]['occurrences'] += $occurrences;

        // First example wins. A list of every offending element would not
        // fit the column and the operator only needs somewhere to start.
        if (($detail !== '') && ($findings[$code]['detail'] === '')) {
            $findings[$code]['detail'] = mb_substr($detail, 0, 250);
        }
    };

    $xpath = pg_seo_html_xpath($html);

    if (!$xpath) {
        return $findings;
    }

    $is_document = ($mode === 'document');

    // ---- Document level tags ----------------------------------------------

    if ($is_document) {
        $titles = $xpath->query('//title');

        if ($titles->length === 0) {
            $add('title_tag_missing');
        } elseif ($titles->length > 1) {
            $add('title_tag_multiple', $titles->length - 1);
        }

        if ($xpath->query('//meta[translate(@name,"DESCRIPTION","description")="description"]')->length === 0) {
            $add('meta_description_tag_missing');
        }

        $canonicals = $xpath->query('//link[contains(translate(@rel,"CANONICAL","canonical"),"canonical")]');

        if ($canonicals->length === 0) {
            $add('canonical_missing');
        } elseif ($canonicals->length > 1) {
            $add('canonical_multiple', $canonicals->length - 1);
        }

        if ($xpath->query('//meta[translate(@name,"VIEWPORT","viewport")="viewport"]')->length === 0) {
            $add('viewport_missing');
        }

        // A page the operator put in the sitemap while the markup tells
        // search engines to stay away. Both statements are deliberate on
        // their own and contradictory together.
        if (!empty($context['in_sitemap'])) {
            foreach ($xpath->query('//meta[translate(@name,"ROBTS","robots")="robots"]') as $robots_meta) {
                if (strpos(strtolower($robots_meta->getAttribute('content')), 'noindex') !== FALSE) {
                    $add('noindex_but_in_sitemap', 1, $robots_meta->getAttribute('content'));
                    break;
                }
            }
        }

        // Only when the feature is on. Open Graph output is switchable and a
        // switched-off feature produces no output by design.
        if (!empty($context['open_graph'])) {
            $missing_og = array();

            foreach (array('og:title', 'og:description') as $og_property) {
                if ($xpath->query('//meta[@property="' . $og_property . '"]')->length === 0) {
                    $missing_og[] = $og_property;
                }
            }

            if ($missing_og) {
                $add('og_incomplete', 1, implode(', ', $missing_og));
            }
        }
    }

    // ---- Headings ----------------------------------------------------------

    $h1_nodes = $xpath->query('//h1');

    if ($is_document && ($h1_nodes->length === 0)) {
        $add('h1_missing');
    }

    if ($h1_nodes->length > 1) {
        $add('h1_multiple', $h1_nodes->length - 1);
    }

    $context_title = isset($context['title']) ? trim((string) $context['title']) : '';

    foreach ($h1_nodes as $h1_node) {
        $h1_text = pg_seo_visible_text($xpath, $h1_node);

        // An h1 holding only a logo image is empty as far as a search engine
        // reading the outline is concerned.
        if ($h1_text === '') {
            $add('h1_empty');
            continue;
        }

        if (mb_strlen($h1_text, 'UTF-8') > 70) {
            $add('h1_too_long', 1, $h1_text);
        }

        if (($context_title !== '') && (mb_strtolower($h1_text, 'UTF-8') === mb_strtolower($context_title, 'UTF-8'))) {
            $add('h1_equals_title', 1, $h1_text);
        }
    }

    $headings = $xpath->query('//h1|//h2|//h3|//h4|//h5|//h6');
    $previous_level = 0;
    $heading_texts = array();

    foreach ($headings as $heading) {
        $level = (int) substr($heading->nodeName, 1);
        $heading_text = pg_seo_visible_text($xpath, $heading);

        if ($heading_text === '') {
            // h1_empty already covers the h1 case; reporting both would
            // charge the same markup twice.
            if ($level > 1) {
                $add('heading_empty', 1, $heading->nodeName);
            }
        } else {
            if (mb_strlen($heading_text, 'UTF-8') > 200) {
                $add('heading_is_paragraph', 1, mb_substr($heading_text, 0, 80));
            }

            $heading_key = mb_strtolower($heading_text, 'UTF-8');
            $heading_texts[$heading_key] = isset($heading_texts[$heading_key]) ? $heading_texts[$heading_key] + 1 : 1;
        }

        if ($previous_level === 0) {
            if ($is_document && ($level > 1)) {
                $add('heading_starts_at_h2', 1, $heading->nodeName);
            }
        } elseif ($level > ($previous_level + 1)) {
            $add('heading_level_skip', 1, 'h' . $previous_level . ' -> ' . $heading->nodeName);
        }

        $previous_level = $level;
    }

    foreach ($heading_texts as $heading_text => $heading_count) {
        if ($heading_count > 1) {
            $add('heading_duplicate_text', $heading_count - 1, $heading_text);
        }
    }

    // ---- Text --------------------------------------------------------------

    // A block element inside a paragraph is not a style question: the parser
    // closes the paragraph early and rebuilds the tree, so the document the
    // browser works with is not the one that was authored.
    //
    // This one rule reads the source text rather than the tree, and it has
    // to: by the time loadHTML() is done the offending nesting is gone,
    // because the parser already performed exactly the repair that makes the
    // markup a problem. Querying the tree for it can only ever return
    // nothing. Scripts, styles and comments are removed first so that a
    // paragraph tag mentioned inside them is not mistaken for markup.
    $source_without_code = preg_replace(
        array('/<!--.*?-->/s', '/<script\b[^>]*>.*?<\/script\s*>/is', '/<style\b[^>]*>.*?<\/style\s*>/is'),
        '',
        $html);

    if (preg_match_all('/<p\b[^>]*>(.*?)<\/p\s*>/is', $source_without_code, $paragraph_matches)) {
        $nested_blocks = 0;
        $nested_example = '';

        foreach ($paragraph_matches[1] as $paragraph_source) {
            if (preg_match('/<(div|p|ul|ol|table|h[1-6])\b/i', $paragraph_source, $block_match)) {
                $nested_blocks++;

                if ($nested_example === '') {
                    $nested_example = strtolower($block_match[1]);
                }
            }
        }

        if ($nested_blocks > 0) {
            $add('p_contains_block', $nested_blocks, $nested_example);
        }
    }

    $empty_paragraphs = 0;

    foreach ($xpath->query('//p') as $paragraph) {
        if (pg_seo_visible_text($xpath, $paragraph) !== '') {
            continue;
        }

        // A paragraph wrapping only an image or an embed is a layout
        // paragraph, not a leftover.
        if ($xpath->query('.//img|.//iframe|.//video|.//svg|.//input', $paragraph)->length > 0) {
            continue;
        }

        $empty_paragraphs++;
    }

    // A couple of spacer paragraphs is how people type; a page full of them
    // is an editor that has been fought with.
    if ($empty_paragraphs > 3) {
        $add('p_empty', $empty_paragraphs);
    }

    $stacked_breaks = $xpath->query('//br[following-sibling::*[1][self::br]]');

    if ($stacked_breaks->length > 0) {
        $add('br_as_paragraph', $stacked_breaks->length);
    }

    $presentational = $xpath->query('//font|//center|//marquee|//big|//strike|//tt|//blink');

    if ($presentational->length > 0) {
        $add('presentational_tag', $presentational->length, $presentational->item(0)->nodeName);
    }

    $bold_italic = $xpath->query('//b|//i[not(@class)]');

    if ($bold_italic->length > 0) {
        // <i> without a class is text; <i class="bi bi-..."> is an icon and
        // every Bootstrap Icon in the control panel and the themes is one.
        $add('bold_italic_tag', $bold_italic->length);
    }

    $inline_styles = $xpath->query('//*[@style]');

    if ($inline_styles->length > 20) {
        $add('inline_style_heavy', 1, (string) $inline_styles->length);
    }

    // ---- Lists and tables --------------------------------------------------

    $invalid_list_children = $xpath->query('//ul/*[not(self::li) and not(self::script) and not(self::template)]|//ol/*[not(self::li) and not(self::script) and not(self::template)]');

    if ($invalid_list_children->length > 0) {
        $add('list_invalid_child', $invalid_list_children->length, $invalid_list_children->item(0)->nodeName);
    }

    foreach ($xpath->query('//table') as $table) {
        if (strtolower($table->getAttribute('role')) === 'presentation') {
            continue;
        }

        if ($xpath->query('.//th|.//caption', $table)->length > 0) {
            continue;
        }

        if ($xpath->query('.//td', $table)->length < 3) {
            continue;
        }

        $add('table_no_header');
    }

    // ---- Images ------------------------------------------------------------

    foreach ($xpath->query('//img') as $image) {
        $source = $image->getAttribute('src');
        $classes = strtolower($image->getAttribute('class'));
        $is_lazy = ($image->hasAttribute('data-src') || (strpos($classes, 'lazy') !== FALSE));

        if (!$image->hasAttribute('alt')) {
            $add('img_no_alt', 1, $source);

        // alt="" is a deliberate statement that the image is decorative and
        // is what the accessibility guidance asks for. It is not a finding.
        } elseif (trim($image->getAttribute('alt')) !== '') {
            $alt = trim($image->getAttribute('alt'));
            $file_name = basename($is_lazy ? $image->getAttribute('data-src') : $source);

            if (($file_name !== '') && (mb_strtolower($alt, 'UTF-8') === mb_strtolower($file_name, 'UTF-8'))) {
                $add('img_alt_is_filename', 1, $alt);
            }
        }

        if (!$image->hasAttribute('width') || !$image->hasAttribute('height')) {
            $add('img_no_dimensions', 1, $source);
        }

        // A lazily loaded image already defers; asking it for the native
        // attribute as well would report the theme's own mechanism as a
        // defect.
        if (!$image->hasAttribute('loading') && !$is_lazy) {
            $add('img_no_lazy', 1, $source);
        }
    }

    // ---- Links -------------------------------------------------------------

    $generic_anchors = pg_seo_generic_anchor_texts();

    foreach ($xpath->query('//a') as $link) {
        $href = trim($link->getAttribute('href'));
        $anchor_text = pg_seo_visible_text($xpath, $link);

        // Anchors with no href are in-page targets, not links.
        if ($collect_links && $link->hasAttribute('href') && ($href !== '') && (strpos($href, '#') !== 0)) {
            $links[] = array(
                'href' => $href,
                'anchor' => $anchor_text,
                'rel' => $link->getAttribute('rel'),
            );
        }

        // An <a> without an href attribute is not a link at all - it is an
        // in-page target, the <a id="top"> a back-to-top control scrolls to.
        // It has no text because it is not meant to be seen, and nothing an
        // operator can do makes it "not empty": the only way to clear the
        // finding is to delete the anchor and break their own button. The
        // link collection above already draws this line; the check has to
        // draw it too.
        if (($anchor_text === '') && $link->hasAttribute('href')) {
            $has_alternative_label = $link->hasAttribute('aria-label')
                || $link->hasAttribute('title')
                || ($xpath->query('.//img|.//svg|.//i|.//span[@class]', $link)->length > 0);

            if (!$has_alternative_label) {
                $add('link_empty', 1, $href);
            }
        } elseif ($anchor_text !== '') {
            if (in_array(mb_strtolower($anchor_text, 'UTF-8'), $generic_anchors)) {
                $add('link_generic_anchor', 1, $anchor_text);
            }
        }

        // A link with a placeholder href is either dead or driven by
        // JavaScript that a search engine will not run.
        if ($link->hasAttribute('href') && (($href === '') || ($href === '#'))) {
            $add('link_empty_href', 1, $anchor_text);
        }

        if (strtolower($link->getAttribute('target')) === '_blank') {
            if (strpos(strtolower($link->getAttribute('rel')), 'noopener') === FALSE) {
                $add('link_blank_no_noopener', 1, $href);
            }
        }
    }

    // ---- Semantics and accessibility ---------------------------------------

    if ($is_document) {
        $html_elements = $xpath->query('//html');

        if ($html_elements->length > 0) {
            $language = trim($html_elements->item(0)->getAttribute('lang'));

            if ($language === '') {
                $add('html_lang_missing');
            }
        }

        $main_elements = $xpath->query('//main|//*[@role="main"]');

        if ($main_elements->length === 0) {
            $add('main_missing');
        } elseif ($main_elements->length > 1) {
            $add('main_multiple', $main_elements->length - 1);
        }

        if ($xpath->query('//nav|//header|//footer')->length === 0) {
            $add('landmark_missing');
        }
    }

    // Labelless inputs. Hidden fields, buttons and submits carry their own
    // accessible name, so only data entry controls are checked.
    //
    // The label targets are collected once. Building an XPath predicate out
    // of each control id instead would mean interpolating attribute values
    // into a query string, and XPath has no escape for a quote inside a
    // literal - one id containing an apostrophe would turn a lookup into a
    // syntax error.
    $labelled_ids = array();

    foreach ($xpath->query('//label[@for]') as $label) {
        $labelled_ids[$label->getAttribute('for')] = TRUE;
    }

    foreach ($xpath->query('//input|//select|//textarea') as $control) {
        if ($control->nodeName === 'input') {
            $control_type = strtolower($control->getAttribute('type'));

            if (in_array($control_type, array('hidden', 'submit', 'button', 'reset', 'image'))) {
                continue;
            }
        }

        if ($control->hasAttribute('aria-label') || $control->hasAttribute('aria-labelledby') || $control->hasAttribute('title')) {
            continue;
        }

        $control_id = $control->getAttribute('id');

        if (($control_id !== '') && isset($labelled_ids[$control_id])) {
            continue;
        }

        // A control wrapped in its own label needs no for attribute.
        $ancestor = $control->parentNode;
        $wrapped = FALSE;

        while ($ancestor && ($ancestor->nodeType === XML_ELEMENT_NODE)) {
            if ($ancestor->nodeName === 'label') {
                $wrapped = TRUE;
                break;
            }

            $ancestor = $ancestor->parentNode;
        }

        if (!$wrapped) {
            $add('input_no_label', 1, $control->getAttribute('name'));
        }
    }

    foreach ($xpath->query('//iframe') as $frame) {
        if (trim($frame->getAttribute('title')) === '') {
            $add('iframe_no_title', 1, $frame->getAttribute('src'));
        }
    }

    foreach ($xpath->query('//button') as $button) {
        if (pg_seo_visible_text($xpath, $button) !== '') {
            continue;
        }

        if ($button->hasAttribute('aria-label') || $button->hasAttribute('title')) {
            continue;
        }

        if ($xpath->query('.//img|.//svg|.//i|.//span[@class]', $button)->length > 0) {
            continue;
        }

        $add('button_empty');
    }

    // ---- Content volume ----------------------------------------------------

    if ($is_document) {
        $body_nodes = $xpath->query('//body');
        $body_text = $body_nodes->length ? pg_seo_visible_text($xpath, $body_nodes->item(0)) : '';
        $word_count = pg_seo_count_words($body_text);

        if ($word_count < 300) {
            $add('thin_content', 1, (string) $word_count);
        }

        $html_length = strlen($html);

        if ($html_length > 0) {
            $ratio = strlen($body_text) / $html_length;

            if ($ratio < 0.10) {
                $add('low_text_ratio', 1, round($ratio * 100, 1) . '%');
            }
        }
    }

    // ---- Structured data ---------------------------------------------------

    $jsonld_nodes = $xpath->query('//script[translate(@type,"ABCDEFGHIJKLMNOPQRSTUVWXYZ","abcdefghijklmnopqrstuvwxyz")="application/ld+json"]');

    foreach ($jsonld_nodes as $jsonld_node) {
        $decoded = json_decode($jsonld_node->textContent, TRUE);

        if (!is_array($decoded)) {
            $add('jsonld_invalid', 1, trim(mb_substr($jsonld_node->textContent, 0, 80)));
            continue;
        }

        // Three shapes are legal at the root and all three appear in the
        // wild: a single object, a @graph wrapper, and a bare list of
        // entities. Reading a list as one object would charge a valid
        // document with having no @type, and would stop the Product check
        // below from ever seeing the product inside it.
        if (isset($decoded['@graph']) && is_array($decoded['@graph'])) {
            $entries = $decoded['@graph'];
        } elseif (isset($decoded[0]) && is_array($decoded[0])) {
            $entries = $decoded;
        } else {
            $entries = array($decoded);
        }

        foreach ($entries as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            if (!isset($entry['@type'])) {
                $add('jsonld_missing_type');
                continue;
            }

            // A Product entry without an offer is the half that shopping
            // results actually read: no price and no availability means the
            // listing carries a name and nothing to act on.
            if (strtolower((string) $entry['@type']) !== 'product') {
                continue;
            }

            $offers = isset($entry['offers']) ? $entry['offers'] : NULL;

            if (!is_array($offers)) {
                $add('product_schema_incomplete', 1, 'offers');
                continue;
            }

            // One offer or a list of them. Decided by shape, not by the
            // presence of @type: an offer object that omits @type is common,
            // and reading it as a list would iterate its scalar values, skip
            // every one of them and silently pass a document with no price.
            $offer_entries = (isset($offers[0]) && is_array($offers[0])) ? $offers : array($offers);
            $missing_offer_fields = array();

            foreach ($offer_entries as $offer) {
                if (!is_array($offer)) {
                    continue;
                }

                foreach (array('price', 'availability') as $offer_field) {
                    if (!isset($offer[$offer_field]) || ($offer[$offer_field] === '')) {
                        $missing_offer_fields[$offer_field] = TRUE;
                    }
                }
            }

            if ($missing_offer_fields) {
                $add('product_schema_incomplete', 1, implode(', ', array_keys($missing_offer_fields)));
            }
        }
    }

    if ($is_document && !empty($context['expect_jsonld']) && ($jsonld_nodes->length === 0)) {
        $add('jsonld_missing');
    }

    return $findings;
}

// Turn findings into a 0-100 structure score.
function pg_seo_structure_score($findings)
{
    $weights = pg_seo_severity_weights();
    $score = 100.0;

    foreach ($findings as $finding) {
        $weight = isset($weights[$finding['severity']]) ? $weights[$finding['severity']] : $weights['notice'];
        $score -= min($weight['cap'], $weight['each'] * max(1, (int) $finding['occurrences']));
    }

    return (int) max(0, round($score));
}

// The editable layers a page is assembled from, so a finding can say where
// it came from. "The page has two h1 elements" is not actionable until the
// operator knows whether the second one is in their page region or in the
// style template every page shares.
//
// Each layer is stored separately already, so this is a read rather than a
// reconstruction.
function pg_seo_page_layers($page_id, $page_row)
{
    $layers = array();

    foreach (db_items(
        "SELECT pregion_content AS content
        FROM pregion
        WHERE (pregion_page = '" . (int) $page_id . "') AND (collection = 'a')
        ORDER BY pregion_order ASC") as $region
    ) {
        $layers['page_region'] = ($layers['page_region'] ?? '') . $region['content'];
    }

    if (trim((string) ($page_row['system_region_header'] ?? '')) !== '') {
        $layers['system_region'] = $page_row['system_region_header'];
    }

    if (trim((string) ($page_row['system_region_footer'] ?? '')) !== '') {
        $layers['system_region'] = ($layers['system_region'] ?? '') . $page_row['system_region_footer'];
    }

    $style_id = (int) ($page_row['page_style'] ?? 0);

    if ($style_id) {
        $style = db_item("SELECT style_code, style_head FROM style WHERE style_id = '" . $style_id . "'");

        if ($style) {
            $layers['style'] = $style['style_code'] . $style['style_head'];
        }
    }

    return $layers;
}

// Codes that describe the document envelope rather than anything inside a
// single editable layer. None of them can be reproduced by analyzing a
// fragment, so failing to find them in a layer says nothing - they belong to
// the page template as a whole.
function pg_seo_document_level_codes()
{
    return array(
        'title_tag_missing', 'title_tag_multiple', 'meta_description_tag_missing',
        'canonical_missing', 'canonical_multiple', 'noindex_but_in_sitemap',
        'viewport_missing', 'og_incomplete', 'html_lang_missing',
        'main_missing', 'landmark_missing', 'thin_content', 'low_text_ratio',
        'jsonld_missing',
    );
}

// Codes that count or sequence elements across the whole page. Two layers
// can each be clean on their own and still produce one of these together -
// one h1 in the style and one in the page region is two h1 elements - so
// "not found in any layer" means the combination, not the generator.
function pg_seo_cross_layer_codes()
{
    return array(
        'h1_multiple', 'main_multiple', 'heading_duplicate_text',
        'heading_level_skip', 'heading_starts_at_h2', 'h1_equals_title',
        'h1_only_in_template',
    );
}

// Attribute each finding of the assembled document to the layer that
// produced it.
//
// A code found in exactly one layer is attributed to it and a code found in
// several is marked as such. A code found in none is not simply "generated":
// the honest answer depends on what kind of check it is, and saying
// "generated content" for a heading conflict that arises from combining a
// style with a page region would send the operator looking in the wrong
// place.
function pg_seo_attribute_sources($findings, $layers, $context)
{
    if (!$findings) {
        return $findings;
    }

    $codes_by_layer = array();

    foreach ($layers as $layer_name => $layer_html) {
        if (trim((string) $layer_html) === '') {
            continue;
        }

        foreach (pg_seo_analyze_html($layer_html, 'fragment', $context) as $code => $finding) {
            $codes_by_layer[$code][] = $layer_name;
        }
    }

    $document_codes = pg_seo_document_level_codes();
    $cross_layer_codes = pg_seo_cross_layer_codes();

    foreach ($findings as $code => $finding) {
        if (isset($codes_by_layer[$code])) {
            $sources = array_unique($codes_by_layer[$code]);
            $findings[$code]['source'] = (count($sources) === 1) ? reset($sources) : 'multiple';
            continue;
        }

        if (in_array($code, $document_codes)) {
            $findings[$code]['source'] = 'document';
        } elseif (in_array($code, $cross_layer_codes)) {
            $findings[$code]['source'] = 'combined';
        } else {
            $findings[$code]['source'] = 'generated';
        }
    }

    return $findings;
}

// Human label for a source value.
function pg_seo_source_label($source)
{
    switch ($source) {
        case 'page_region':
            return lang('Page Region');

        case 'system_region':
            return lang('System Region');

        case 'style':
            return lang('Page Style');

        case 'multiple':
            return lang('Several places');

        case 'document':
            return lang('Page template');

        case 'combined':
            return lang('Layers combined');

        case 'generated':
            return lang('Generated content');

        case 'product_description':
            return lang('Full Description');

        case 'product_details':
            return lang('Details');
    }

    return '';
}

// Replace the stored findings of one record.
//
// Delete then insert, in that order and unconditionally: findings are
// derived from the current content, so anything already stored is a previous
// answer to the same question and merging the two would keep resolved
// problems alive forever.
// $scope says which family of codes this call owns. The markup pass and the
// link-graph pass both write findings for the same record and run at
// different times, so a blanket delete would mean whichever ran last erased
// the other's work.
function pg_seo_store_issues($type, $id, $findings, $scope = 'structure')
{
    $sql_scope = '';

    if (function_exists('pg_seo_link_codes')) {
        if ($scope === 'graph') {
            $sql_scope = " AND (code IN ('" . implode("','", pg_seo_graph_codes()) . "'))";
        } elseif ($scope === 'links') {
            $sql_scope = " AND (code IN ('" . implode("','", pg_seo_page_link_codes()) . "'))";
        } else {
            $sql_scope = " AND (code NOT IN ('" . implode("','", pg_seo_link_codes()) . "'))";
        }
    }

    db("DELETE FROM seo_issue WHERE (entity_type = '" . e($type) . "') AND (entity_id = '" . (int) $id . "')" . $sql_scope);

    foreach ($findings as $finding) {
        db(
            "INSERT INTO seo_issue (entity_type, entity_id, code, severity, occurrences, source, detail)
            VALUES (
                '" . e($type) . "',
                '" . (int) $id . "',
                '" . e($finding['code']) . "',
                '" . e($finding['severity']) . "',
                '" . min(65535, max(1, (int) $finding['occurrences'])) . "',
                '" . e($finding['source'] ?? '') . "',
                '" . e($finding['detail'] ?? '') . "')");
    }
}

// Page types the structure pass renders.
//
// The check reads a page's markup, so it has to build that markup - and not
// every page type can be built without a request behind it. A confirmation
// screen wants the reference code of a form somebody just submitted; an order
// receipt wants an order; an account screen wants a session. Handed none of
// those, the renderer calls output_error(), which echoes and exits, and the
// cron run dies on the spot.
//
// Chasing those one at a time is the wrong shape. What the list below says
// instead is: analyze the pages whose markup a search engine could ever see.
// That is the sitemap-eligible types, plus the three that render one item and
// are reachable by their own URL. The rest are transactional or account
// screens - their structure is not an SEO surface, and their score being
// absent leaves the group out of the denominator exactly as an unanalyzed
// record does.
function pg_seo_renderable_page_types()
{
    $types = array('calendar event view', 'catalog detail', 'form item view');

    if (function_exists('pg_seo_sitemap_eligible_types')) {
        $types = array_merge(pg_seo_sitemap_eligible_types(), $types);
    }

    return $types;
}

// What a dependent page type needs before it can render.
//
// Some page types do not render themselves - they render one item, and the
// renderer resolves that item from the request. A calendar event view reads
// $_GET['id'], a form item view reads a reference code, a catalog detail page
// reads the product slug out of the path. In a cron run there is none of that.
//
// update_search_index.php has always handed the item over explicitly for the
// same reason, through get_page_content()'s dynamic properties. This picks a
// representative one the same way, and the page type's own rules decide what
// is eligible: a calendar event view only accepts an event published on a
// calendar that view is bound to, so an event it would reject is no use here.
//
// Returns FALSE when the type needs an item and the site has none, and the
// caller then skips the page rather than rendering it into an error.
//
// The catalog detail case is different in kind: it takes the item from the
// address rather than from a property, and it is written to cope with not
// having one. Without a product it renders the bare template, which is a
// weaker thing to score than the page a visitor sees - so a representative
// product is put in the address when one exists, and its absence is not a
// reason to skip.
function pg_seo_render_context($page_type, $page_id, $page_name)
{
    $context = array('properties' => array(), 'page' => $page_name);

    if ($page_type == 'calendar event view') {

        $event = db_item(
            "SELECT DISTINCT calendar_events.id
            FROM calendar_events
            INNER JOIN calendar_events_calendars_xref
                ON calendar_events_calendars_xref.calendar_event_id = calendar_events.id
            INNER JOIN calendar_event_views_calendars_xref
                ON calendar_event_views_calendars_xref.calendar_id = calendar_events_calendars_xref.calendar_id
            WHERE
                (calendar_events.published = '1')
                AND (calendar_event_views_calendars_xref.page_id = '" . (int) $page_id . "')
            ORDER BY calendar_events.id DESC
            LIMIT 1");

        if (!$event) {
            return FALSE;
        }

        $context['properties'] = array('calendar_event_id' => (int) $event['id']);

        return $context;
    }

    if ($page_type == 'form item view') {

        // submitter_security as well as the link. update_search_index.php -
        // the precedent this mechanism comes from - refuses to render a form
        // item view at all when that switch is on, because the page is then
        // showing one person's submission to whoever asks. Rendering it here
        // anonymously would be the same disclosure, and the finding would be
        // about markup nobody is allowed to see.
        $view = db_item(
            "SELECT custom_form_page_id, submitter_security
            FROM form_item_view_pages
            WHERE (page_id = '" . (int) $page_id . "') AND (collection = 'a')");

        if (!$view || !((int) $view['custom_form_page_id']) || ((int) $view['submitter_security'])) {
            return FALSE;
        }

        $submitted_form = db_item(
            "SELECT id
            FROM forms
            WHERE page_id = '" . (int) $view['custom_form_page_id'] . "'
            ORDER BY id DESC
            LIMIT 1");

        if (!$submitted_form) {
            return FALSE;
        }

        $context['properties'] = array('form_id' => (int) $submitted_form['id']);

        return $context;
    }

    if ($page_type == 'catalog detail') {

        // The group this detail page belongs to, through the catalog page
        // that points at it.
        $catalog_page = db_item(
            "SELECT product_group_id
            FROM catalog_pages
            WHERE catalog_detail_page_id = '" . (int) $page_id . "'
            LIMIT 1");

        $product = NULL;

        if ($catalog_page && ((int) $catalog_page['product_group_id'])) {
            $product = db_item(
                "SELECT products.address_name
                FROM products
                INNER JOIN products_groups_xref ON products_groups_xref.product = products.id
                WHERE
                    (products_groups_xref.product_group = '" . (int) $catalog_page['product_group_id'] . "')
                    AND (products.enabled = '1')
                    AND (products.address_name != '')
                ORDER BY products.id ASC
                LIMIT 1");
        }

        // No representative means no analysis. Rendering the bare template
        // looks harmless and is not: with no product resolved the Product
        // JSON-LD block emits an offers object with a trailing comma, which is
        // invalid JSON, and the markup rules then record jsonld_invalid - an
        // error-severity finding caused entirely by how the analyzer chose to
        // render the page. A product group of 0 is an ordinary configuration,
        // so this is not a rare state.
        if (!$product) {
            return FALSE;
        }

        $context['page'] = $page_name . '/' . $product['address_name'];

        return $context;
    }

    return $context;
}

// Drop everything a record was previously told, when it is no longer being
// examined.
//
// pg_seo_store_issues() and pg_seo_store_links() are the only writers that
// delete, and they only run when a record is actually analyzed. A page that
// used to be analyzed and is now excluded - its type is not rendered any more,
// or the item it needs no longer exists - keeps its old rows forever: the
// detail panel goes on listing findings, pg_seo_structure_flag_map() goes on
// rebuilding the badge and the filters from them, and its stale seo_link rows
// go on counting as inbound links, which hides a genuinely orphaned page.
function pg_seo_forget_findings($type, $id)
{
    db("DELETE FROM seo_issue WHERE (entity_type = '" . e($type) . "') AND (entity_id = '" . (int) $id . "')");

    if (db_item("SHOW TABLES LIKE 'seo_link'")) {
        db("DELETE FROM seo_link WHERE (from_type = '" . e($type) . "') AND (from_id = '" . (int) $id . "')");
    }
}

// Analyze one page: render it in process, run the rules, attribute the
// findings and store them. Returns the structure score.
function pg_seo_analyze_page($page_id)
{
    require_once(dirname(__FILE__) . '/get_page_content.php');

    $page_row = db_item(
        "SELECT
            page.page_id,
            page.page_name,
            page.page_title,
            page.page_type,
            page.page_style,
            page.sitemap,
            page.system_region_header,
            page.system_region_footer,
            page.page_folder,
            folder.folder_archived
        FROM page
        LEFT JOIN folder ON page.page_folder = folder.folder_id
        WHERE page.page_id = '" . (int) $page_id . "'");

    if (!$page_row) {
        return NULL;
    }

    $in_sitemap = (((string) $page_row['sitemap']) === '1')
        && (get_access_control_type($page_row['page_folder']) === 'public')
        && (((string) $page_row['folder_archived']) !== '1');

    $context = array(
        'title' => $page_row['page_title'],
        'in_sitemap' => $in_sitemap,
        'open_graph' => (!defined('OPEN_GRAPH') || OPEN_GRAPH),
        'expect_jsonld' => in_array($page_row['page_type'], array('catalog', 'catalog detail', 'order form', 'express order')),
    );

    // Page types that cannot be built without a request behind them are not
    // rendered at all. Chasing them one at a time meant the cron run died on
    // whichever one happened to come first in id order.
    //
    // A dependent type additionally needs the item it is going to render
    // handed to it; pg_seo_render_context() declines when the site has none.
    $render_context = in_array($page_row['page_type'], pg_seo_renderable_page_types())
        ? pg_seo_render_context($page_row['page_type'], $page_id, $page_row['page_name'])
        : FALSE;

    if ($render_context === FALSE) {
        pg_seo_forget_findings('page', $page_id);
        return NULL;
    }

    $dynamic_properties = $render_context['properties'];

    // Preview mode, no toolbar, not an e-mail: the same call the search
    // indexer makes. In a cron context there is no session user, so what is
    // rendered is the anonymous visitor's view - which is the one search
    // engines see and the one worth scoring.
    //
    // The renderer reads two things a real request would have supplied: the
    // requested page name (catalog pages resolve their item from it) and the
    // session token it writes into the inline script block. update_search_
    // index.php gets away without setting them because it runs from a
    // browser window; a cron run does not, and an undefined index there
    // would fill the error log on every page. Both are restored afterwards
    // so nothing else in the request sees a value this function invented.
    $had_page_parameter = isset($_GET['page']);
    $previous_page_parameter = $had_page_parameter ? $_GET['page'] : NULL;
    $_GET['page'] = $render_context['page'];

    // The catalog item resolver memoises for the life of the process, which is
    // right for a request and wrong for a pass that renders hundreds of pages:
    // without this every catalog detail page after the first is scored against
    // the first one's product - its title, its description, its JSON-LD.
    if (function_exists('get_catalog_item_from_url')) {
        get_catalog_item_from_url(true);
    }

    // The token has to be restored by UNSETTING it, not by blanking it.
    // initialize_token() only generates a new one when the key is absent, and
    // validate_token_field() rejects outright when the stored token is the
    // empty string - so leaving '' behind would break every form and every
    // token-checked link for the rest of that session, permanently.
    $had_token = isset($_SESSION['software']['token']);

    if (!$had_token) {
        $_SESSION['software']['token'] = '';
    }

    $html = get_page_content($page_id, '', '', 'preview', FALSE, $dynamic_properties, FALSE, 'desktop');

    if ($had_page_parameter) {
        $_GET['page'] = $previous_page_parameter;
    } else {
        unset($_GET['page']);
    }

    if (!$had_token) {
        unset($_SESSION['software']['token']);
    }

    if (trim((string) $html) === '') {
        pg_seo_forget_findings('page', $page_id);
        return NULL;
    }

    $layers = pg_seo_page_layers($page_id, $page_row);
    $raw_links = array();
    $findings = pg_seo_analyze_html($html, 'document', $context, $raw_links);

    // The page has a heading, but only because the style template wraps the
    // logo in one. Every page sharing that style then claims to be about the
    // same thing, and the actual content of this one announces nothing -
    // which the h1_missing rule cannot see, because technically an h1 is
    // present. Only the layer split can tell the two apart.
    if (!isset($findings['h1_missing']) && isset($layers['style'])) {
        $content_layers = $layers;
        unset($content_layers['style']);

        $style_has_h1 = (strpos(strtolower($layers['style']), '<h1') !== FALSE);
        $content_has_h1 = FALSE;

        foreach ($content_layers as $layer_html) {
            if (strpos(strtolower((string) $layer_html), '<h1') !== FALSE) {
                $content_has_h1 = TRUE;
                break;
            }
        }

        if ($style_has_h1 && !$content_has_h1) {
            $findings['h1_only_in_template'] = array(
                'code' => 'h1_only_in_template',
                'severity' => 'warning',
                'occurrences' => 1,
                'detail' => '',
            );
        }
    }

    $findings = pg_seo_attribute_sources($findings, $layers, $context);

    pg_seo_store_issues('page', $page_id, $findings, 'structure');

    // Links are stored under their own scope: the graph pass adds findings
    // of its own to this record later and neither pass may erase the other.
    if (function_exists('pg_seo_process_links') && pg_seo_link_schema_ready()) {
        pg_seo_store_issues('page', $page_id, pg_seo_process_links('page', $page_id, $raw_links, array(
            'source_public' => (get_access_control_type($page_row['page_folder']) === 'public'),
        )), 'links');

        // Written now so the link half of the score exists from the first
        // pass. The graph walk revises it later, once it can add the
        // findings that need every page collected.
        db(
            "UPDATE page
            SET seo_link_score = '" . (int) pg_seo_link_score('page', $page_id) . "'
            WHERE page_id = '" . (int) $page_id . "'");
    }

    return pg_seo_structure_score($findings);
}

// Analyze one product or product group. No render: what the operator writes
// for these records are HTML fragments, and the template around them belongs
// to the catalog page that displays them and is scored there.
function pg_seo_analyze_catalog_record($type, $id)
{
    $table = ($type === 'product') ? 'products' : 'product_groups';

    $record = db_item(
        "SELECT title, full_description, details
        FROM `" . $table . "`
        WHERE id = '" . (int) $id . "'");

    if (!$record) {
        return NULL;
    }

    $context = array('title' => $record['title']);

    $fragments = array(
        'product_description' => (string) $record['full_description'],
        'product_details' => (string) $record['details'],
    );

    $findings = array();
    $fragments_analyzed = 0;
    $raw_links = array();

    foreach ($fragments as $source => $fragment) {
        if (trim($fragment) === '') {
            continue;
        }

        // Plain text with no markup in it is not markup that passed. The
        // parser declines it, every rule finds nothing, and the record would
        // be handed a perfect structure score for having written no HTML at
        // all - outscoring one that is properly marked up and has a single
        // missing alt. It happens on any product created through the API or an
        // import, where nothing put tags around the text.
        if (strpos($fragment, '<') === false) {
            continue;
        }

        $fragments_analyzed++;

        foreach (pg_seo_analyze_html($fragment, 'fragment', $context, $raw_links) as $code => $finding) {
            if (isset($findings[$code])) {
                $findings[$code]['occurrences'] += $finding['occurrences'];
                $findings[$code]['source'] = 'multiple';
                continue;
            }

            $finding['source'] = $source;
            $findings[$code] = $finding;
        }
    }

    pg_seo_store_issues($type, $id, $findings, 'structure');

    // A description can link to a page that no longer exists just as a page
    // can. The private-folder check does not apply: these fragments are
    // displayed by whichever catalog page shows them, and that page's own
    // access control is what decides who sees the link.
    if (function_exists('pg_seo_process_links') && pg_seo_link_schema_ready()) {
        pg_seo_store_issues($type, $id, pg_seo_process_links($type, $id, $raw_links), 'links');

        db(
            "UPDATE `" . $table . "`
            SET seo_link_score = '" . (int) pg_seo_link_score($type, $id) . "'
            WHERE id = '" . (int) $id . "'");
    }

    // Nothing to analyze is not the same as analyzed and faultless. A record
    // with no description at all would otherwise be handed a perfect 100 for
    // structure and outscore one that has content with a couple of notices -
    // the model would be paying points for writing less. NULL keeps it out
    // of the composition entirely, the same way an unrendered page is.
    if (!$fragments_analyzed) {
        return NULL;
    }

    return pg_seo_structure_score($findings);
}

/**
 * Run one bounded pass of the HTML structure analysis.
 *
 * Shared by the nightly job and the button on the Pages screen, which differ
 * only in how many seconds they are willing to spend. Everything about the
 * work is the same: the queue, the order, the graph gate, and the score
 * recomposition at the end.
 *
 * The queue is the state. A pass that runs out of budget leaves the rest of
 * the records queued and the next pass - job or button - continues exactly
 * where this one stopped, so a caller with twenty seconds and a caller with
 * two minutes are the same thing at different speeds.
 *
 * Nothing is logged here. The two callers describe the same work differently
 * to the operator, so each writes its own line.
 *
 * @param int $time_budget Seconds of work before the pass stops.
 * @return array analyzed, remaining, ready
 */
function pg_seo_analyze_batch($time_budget)
{
    // Both guards also live in the job, which needs to record its run before
    // leaving. Repeated here because the button is a second entry point and
    // must not render anything without them either.
    if (!pg_seo_structure_schema_ready() || !class_exists('DOMDocument')) {
        return array('analyzed' => 0, 'remaining' => 0, 'ready' => FALSE);
    }

    require_once(dirname(__FILE__) . '/seo_links.php');

    $time_budget = (int) $time_budget;

    if ($time_budget < 1) {
        $time_budget = 1;
    }

    // Days between full refreshes. Shared markup - styles, common regions - can
    // change without touching any page timestamp, and only a full pass sees it.
    $full_refresh_days = defined('SEO_ANALYZE_FULL_REFRESH_DAYS') ? (int) SEO_ANALYZE_FULL_REFRESH_DAYS : 7;

    $started = microtime(true);
    $total_analyzed = 0;

    // If the oldest analysis is older than the refresh interval and nothing is
    // currently queued, start a new full pass.
    $queued = (int) db_value("SELECT COUNT(*) FROM page WHERE seo_struct_current = 0");

    if (!$queued) {
        $oldest = (int) db_value("SELECT MIN(seo_struct_checked_at) FROM page");

        if ($oldest < (time() - ($full_refresh_days * 86400))) {
            db("UPDATE page SET seo_struct_current = 0");
        }
    }

    // ---- Pages -----------------------------------------------------------------

    $pages = db_items(
        "SELECT page_id
        FROM page
        WHERE (seo_struct_current = 0) OR (seo_struct_checked_at < page_timestamp)
        ORDER BY page_id ASC");

    foreach ($pages as $page) {

        if ((microtime(true) - $started) > $time_budget) {
            break;
        }

        // Marked as attempted BEFORE the render, not after.
        //
        // The intent was always that a page which cannot be rendered is recorded
        // as analyzed with no score rather than left queued, so that a later run
        // does not start on the same broken record and never reach the rest of
        // the site. Writing that after the render does not achieve it: a render
        // that fails does not return, it calls output_error(), which echoes and
        // exits. The update never runs, the page stays queued, and every run from
        // then on dies on the same page - the whole feature stops at whichever
        // page happens to be first in id order that cannot render standalone.
        //
        // The score is nulled here and written again below. The window where a
        // page shows no structure score is one render long, and if the process
        // does die inside it, no score is the honest answer.
        db(
            "UPDATE page
            SET
                seo_struct_score = NULL,
                seo_struct_current = '1',
                seo_struct_checked_at = UNIX_TIMESTAMP()
            WHERE page_id = '" . (int) $page['page_id'] . "'");

        $structure_score = pg_seo_analyze_page($page['page_id']);

        db(
            "UPDATE page
            SET
                seo_struct_score = " . (($structure_score === NULL) ? "NULL" : "'" . (int) $structure_score . "'") . ",
                seo_analysis_current = '0'
            WHERE page_id = '" . (int) $page['page_id'] . "'");

        $total_analyzed++;
    }

    // ---- Products and product groups -------------------------------------------

    // No render for these: what the operator writes is an HTML fragment and the
    // template around it belongs to the catalog page, which is scored on its
    // own. That makes them cheap, so they run after the pages rather than
    // competing with them for the budget.
    if (defined('ECOMMERCE') && (ECOMMERCE == true)) {

        foreach (array('product' => 'products', 'product_group' => 'product_groups') as $type => $table) {

            // Checked before the refresh sweep below, not only before the record
            // loop: queuing every product and then processing none of them would
            // leave the queue full and the work undone.
            if ((microtime(true) - $started) > $time_budget) {
                break;
            }

            $queued = (int) db_value("SELECT COUNT(*) FROM `" . $table . "` WHERE seo_struct_current = 0");

            if (!$queued) {
                $oldest = (int) db_value("SELECT MIN(seo_struct_checked_at) FROM `" . $table . "`");

                if ($oldest < (time() - ($full_refresh_days * 86400))) {
                    db("UPDATE `" . $table . "` SET seo_struct_current = 0");
                }
            }

            $records = db_items(
                "SELECT id
                FROM `" . $table . "`
                WHERE (seo_struct_current = 0) OR (seo_struct_checked_at < timestamp)
                ORDER BY id ASC");

            foreach ($records as $record) {

                if ((microtime(true) - $started) > $time_budget) {
                    break 2;
                }

                // Marked before the analysis, for the same reason the page loop
                // does it. No render happens here, but the operator's own HTML is
                // still parsed through DOM and walked node by node, so a large
                // enough description can exhaust memory - and a fatal there would
                // leave the row queued and every later run would start on it.
                db(
                    "UPDATE `" . $table . "`
                    SET
                        seo_struct_score = NULL,
                        seo_struct_current = '1',
                        seo_struct_checked_at = UNIX_TIMESTAMP()
                    WHERE id = '" . (int) $record['id'] . "'");

                $structure_score = pg_seo_analyze_catalog_record($type, $record['id']);

                db(
                    "UPDATE `" . $table . "`
                    SET
                        seo_struct_score = " . (($structure_score === NULL) ? "NULL" : "'" . (int) $structure_score . "'") . ",
                        seo_analysis_current = '0'
                    WHERE id = '" . (int) $record['id'] . "'");

                $total_analyzed++;
            }
        }
    }

    // Graph-wide findings, once the queue is empty.
    //
    // Deliberately not run on a partial pass: "nothing links to this page" is
    // only true when every page's links have been collected, and answering it
    // halfway through would mark most of the site as orphaned and then quietly
    // unmark it on the following run. A wrong finding that disappears by itself
    // is worse than a finding that arrives a day late.
    if (db_item("SHOW TABLES LIKE 'seo_link'")) {

        $pages_left = (int) db_value(
            "SELECT COUNT(*)
            FROM page
            WHERE (seo_struct_current = 0) OR (seo_struct_checked_at < page_timestamp)");

        // The gate above only says the per-page analysis has caught up. It says
        // nothing about whether the graph has been rebuilt since, because the
        // graph marks pages stale for the meta pass (seo_analysis_current) and
        // never touches the column the gate reads (seo_struct_current). Left at
        // that, the graph rebuilt itself on every single invocation: a full scan
        // of five tables and a per-page write, against a graph that had not
        // changed.
        //
        // Its own marker in cron_runs is what closes that. Anything that could
        // change the graph goes through the analysis pass first and bumps
        // seo_struct_checked_at, so comparing the newest of those against the last
        // graph run is the exact question.
        $last_graph_run = (int) db_value("SELECT last_run_at FROM cron_runs WHERE job_name = 'seo_link_graph'");
        $newest_analysis = (int) db_value("SELECT MAX(seo_struct_checked_at) FROM page");

        if (defined('ECOMMERCE') && (ECOMMERCE == true)) {
            $newest_analysis = max(
                $newest_analysis,
                (int) db_value("SELECT MAX(seo_struct_checked_at) FROM products"),
                (int) db_value("SELECT MAX(seo_struct_checked_at) FROM product_groups"));
        }

        if (!$pages_left && ($newest_analysis > $last_graph_run)) {
            $graph_pages = pg_seo_build_link_graph();
            pg_cron_ran('seo_link_graph');

            // The graph pass writes link scores and marks those pages stale for
            // the meta pass, so its work has to reach the recomposition below
            // the same way the analysis does.
            $total_analyzed += $graph_pages;
        }
    }

    // The composed score is written by the meta pass, which the updates above
    // marked stale on purpose: a new structure score changes the number the
    // screens show, and that recomposition is the meta pass's job. Running it
    // here closes the loop in one cron cycle instead of leaving every analyzed
    // record showing yesterday's total until tomorrow.
    if ($total_analyzed) {

        pg_seo_recalculate('page', NULL, 30);

        if (defined('ECOMMERCE') && (ECOMMERCE == true)) {
            pg_seo_recalculate('product', NULL, 30);
            pg_seo_recalculate('product_group', NULL, 30);
        }

    }

    // What is still queued once this pass has stopped. The caller shows it as
    // "click again to continue"; the job ignores it and picks the rest up on
    // its next run.
    $remaining = (int) db_value(
        "SELECT COUNT(*)
        FROM page
        WHERE (seo_struct_current = 0) OR (seo_struct_checked_at < page_timestamp)");

    if (defined('ECOMMERCE') && (ECOMMERCE == true)) {

        foreach (array('products', 'product_groups') as $remaining_table) {

            $remaining += (int) db_value(
                "SELECT COUNT(*)
                FROM `" . $remaining_table . "`
                WHERE (seo_struct_current = 0) OR (seo_struct_checked_at < timestamp)");
        }
    }

    return array('analyzed' => $total_analyzed, 'remaining' => $remaining, 'ready' => TRUE);
}
