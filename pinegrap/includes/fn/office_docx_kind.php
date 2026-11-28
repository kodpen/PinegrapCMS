<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * A Word paragraph's kind set in the file itself: normal text, heading 1 to
 * 3, a bulleted or a numbered list (the file window and a channel's preview
 * send it with a save's paragraphs, through pg_office_docx_save()).
 *
 * A heading takes the document's own heading style (found by its built-in
 * name, "heading 1", whatever its id or the language it shows in); a list
 * takes the numbering the paragraph beside it already uses, else one of the
 * document's own, else one added. Only when the document has none is a
 * style or a numbering added, appended to its part as it stands; every other
 * byte of the styles and the numbering is kept. A document whose styles or
 * numbering cannot be carried that way is refused with its reason.
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
 * The kinds a paragraph can be given.
 *
 * @return string[]
 */
function pg_office_docx_kinds()
{
    return array('normal', 'heading1', 'heading2', 'heading3', 'bullet', 'number');
}

/**
 * The heading level a paragraph style gives: by its built-in name, else by
 * an id that reads as a heading (pg_office_docx_block_tag()); 0 for none.
 *
 * @param array  $state pg_office_docx_state()
 * @param string $style the style's id
 * @return int
 */
function pg_office_docx_heading_level($state, $style)
{
    if (isset($state['heads'][$style])) {
        return (int) $state['heads'][$style];
    }

    $lower = strtolower((string) $style);

    if (!in_array($lower, array('title', 'balk', 'subtitle'), true) && preg_match('/^h([1-6])$/', pg_office_docx_block_tag($style), $match)) {
        return (int) $match[1];
    }

    return 0;
}

/**
 * A paragraph's kind as the preview draws it: its numbering (its own or its
 * style's) first, then its style. "other" for a style the kinds do not hold
 * (a title, a quote, heading 4 and below).
 *
 * @param array $state pg_office_docx_state()
 * @param array $props pg_office_docx_paragraph_props(), the style's numbering followed and numId 0 taken off
 * @return string
 */
function pg_office_docx_kind_of($state, $props)
{
    if ($props['list'] !== '') {
        $format = isset($state['lists'][$props['list']][$props['level']]) ? $state['lists'][$props['list']][$props['level']] : 'ul';

        return ($format === 'ul') ? 'bullet' : 'number';
    }

    $level = pg_office_docx_heading_level($state, $props['style']);

    if (($level >= 1) && ($level <= 3)) {
        return 'heading' . $level;
    }

    return (($level === 0) && (pg_office_docx_block_tag($props['style']) === 'p')) ? 'normal' : 'other';
}

/**
 * A paragraph's properties with its style's numbering followed and numId 0
 * ("no numbering") taken off, as the preview draws it.
 *
 * @param array      $state
 * @param DOMElement $paragraph
 * @return array style, list, level, align
 */
function pg_office_docx_kind_props($state, $paragraph)
{
    $props = pg_office_docx_paragraph_props($paragraph);

    if (($props['list'] === '') && isset($state['styles'][$props['style']])) {
        $props['list'] = $state['styles'][$props['style']]['list'];
        $props['level'] = $state['styles'][$props['style']]['level'];
    }

    if ($props['list'] === '0') {
        $props['list'] = '';
    }

    return $props;
}

/**
 * Notes the kind of an editable paragraph for the editor, beside its text.
 *
 * @param array      $state
 * @param DOMElement $paragraph
 * @param array      $props pg_office_docx_kind_props()
 */
function pg_office_docx_kind_mark(&$state, $paragraph, $props)
{
    if (!isset($state['edit'])) {
        return;
    }

    $path = $paragraph->getNodePath();

    if (!isset($state['edit']['index'][$path])) {
        return;
    }

    $position = (string) $state['edit']['index'][$path];

    if (isset($state['edit']['texts'][$position])) {
        $state['edit']['kinds'][$position] = pg_office_docx_kind_of($state, $props);
    }
}

/**
 * A kind as a save sends it, checked.
 *
 * @param mixed $kind
 * @return string|null null when it is not one
 */
function pg_office_docx_kind_request($kind)
{
    return (is_string($kind) && in_array($kind, pg_office_docx_kinds(), true)) ? $kind : null;
}

/**
 * The main namespace's prefix of a part, read from its root, when the root
 * is in that namespace and has one: what an added element is written with.
 *
 * @param string $xml
 * @param string $root      the root's local name
 * @param string $namespace the document's main namespace
 * @return string|null the prefix without ':', null when it cannot be used
 */
function pg_office_docx_kind_prefix($xml, $root, $namespace)
{
    if (!preg_match('/<([\w.\-]+):' . $root . '(?=[\s>\/])((?:[^>"\']|"[^"]*"|\'[^\']*\')*)>/', $xml, $match)) {
        return null;
    }

    if (!preg_match('/\sxmlns:' . preg_quote($match[1], '/') . '\s*=\s*(["\'])(.*?)\1/', $match[2], $declared) || ($declared[2] !== $namespace)) {
        return null;
    }

    return $match[1];
}

/**
 * A part's text with a piece put before the first element of one of the
 * given local names, else before the root's end tag.
 *
 * @param string   $xml
 * @param string   $prefix
 * @param string[] $before local names
 * @param string   $root
 * @param string   $piece
 * @return string|null null when the root's end tag is not there
 */
function pg_office_docx_kind_insert($xml, $prefix, $before, $root, $piece)
{
    $at = -1;

    foreach ($before as $local) {
        if (preg_match('/<' . preg_quote($prefix, '/') . ':' . $local . '(?=[\s>\/])/', $xml, $match, PREG_OFFSET_CAPTURE) && (($at < 0) || ($match[0][1] < $at))) {
            $at = $match[0][1];
        }
    }

    if ($at < 0) {
        if (!preg_match('/<\/' . preg_quote($prefix, '/') . ':' . $root . '\s*>\s*$/', $xml, $match, PREG_OFFSET_CAPTURE)) {
            return null;
        }

        $at = $match[0][1];
    }

    return substr_replace($xml, $piece, $at, 0);
}

/**
 * What a save that sets kinds needs to know of a document's styles and
 * numbering, read from the package; or why they cannot be carried.
 *
 * @param ZipArchive  $zip
 * @param DOMDocument $document the main part being written
 * @return array|string the context, or the refusal's code
 */
function pg_office_docx_kind_context($zip, $document)
{
    $root = $document->documentElement;
    $namespace = ($root !== null) ? (string) $root->namespaceURI : '';

    if ($namespace === '') {
        return 'kind_unusual';
    }

    $state = pg_office_docx_state($zip, $refused);

    if ($refused !== '') {
        return 'kind_unusual';
    }

    $rels = (string) pg_zip_entry_contents($zip, 'word/_rels/document.xml.rels', PG_OFFICE_MAX_DOM, $unread);
    $types = (string) pg_zip_entry_contents($zip, '[Content_Types].xml', PG_OFFICE_MAX_DOM, $unread);

    if (($rels === '') || ($types === '')) {
        return 'kind_unusual';
    }

    // The standard relationship types the document already writes,
    // transitional or strict, are the ones an added part is named with.
    // Word lists its own extension parts (stylesWithEffects, people,
    // commentsExtended) under Microsoft's address among them; a numbering
    // named under that address is not found by Word.
    $base = '';
    $targets = array();

    foreach (pg_office_rels($zip, 'word/_rels/document.xml.rels') as $id => $relationship) {
        $targets[$relationship['type']] = $relationship['external'] ? '' : pg_office_part_name('word/', $relationship['target']);
    }

    if (preg_match('~\sType\s*=\s*"(http://schemas\.openxmlformats\.org/officeDocument/2006/relationships/|http://purl\.oclc\.org/ooxml/officeDocument/relationships/)[^"/]*"~', $rels, $type)) {
        $base = $type[1];
    } elseif ($namespace === 'http://schemas.openxmlformats.org/wordprocessingml/2006/main') {
        $base = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/';
    } elseif ($namespace === 'http://purl.oclc.org/ooxml/wordprocessingml/main') {
        $base = 'http://purl.oclc.org/ooxml/officeDocument/relationships/';
    }

    $context = array(
        'state'     => $state,
        'namespace' => $namespace,
        'rels'      => $rels,
        'types'     => $types,
        'base'      => $base,
        'parts'     => array(),
        'changed'   => array(),
    );

    // The styles and the numbering are each read where the document's
    // relationships say, which must be where the preview reads them.
    foreach (array('styles' => 'styles', 'numbering' => 'numbering') as $kind => $root_name) {
        $part = 'word/' . $kind . '.xml';
        $named = isset($targets[$kind]) ? $targets[$kind] : null;
        $there = ($zip->locateName($part) !== false);

        if ((($named !== null) && ($named !== $part)) || (($named === null) && $there)) {
            return 'kind_unusual';
        }

        $context[$kind] = array('xml' => null, 'prefix' => '', 'added' => '');

        if (!$there) {
            continue;
        }

        $xml = pg_zip_entry_contents($zip, $part, PG_OFFICE_MAX_DOM, $unread);

        if (($xml === null) || !pg_office_xlsx_utf8_part(substr($xml, 0, 200)) || (stripos($xml, '<!DOCTYPE') !== false)) {
            return 'kind_unusual';
        }

        $prefix = pg_office_docx_kind_prefix($xml, $root_name, $namespace);

        if ($prefix === null) {
            return 'kind_unusual';
        }

        $context[$kind] = array('xml' => $xml, 'prefix' => $prefix, 'added' => '');
    }

    // Styles by id: their type, name, whether they are the default
    // paragraph style; numbering: the abstract numberings by their first
    // level's format, the nums by their abstract numbering.
    $context['style_ids'] = array();
    $context['paragraph_ids'] = array();
    $context['by_name'] = array();
    $context['default'] = '';

    if ($context['styles']['xml'] !== null) {
        $dom = pg_office_xml($zip, 'word/styles.xml', $status);

        if ($dom === null) {
            return 'kind_unusual';
        }

        foreach ($dom->getElementsByTagNameNS('*', 'style') as $style) {
            $id = pg_office_attr($style, 'styleId');
            $context['style_ids'][strtolower($id)] = true;

            if (pg_office_attr($style, 'type') !== 'paragraph') {
                continue;
            }

            $context['paragraph_ids'][] = $id;

            if (in_array(strtolower(pg_office_attr($style, 'default')), array('1', 'true', 'on'), true) && ($context['default'] === '')) {
                $context['default'] = $id;
            }

            $name = pg_office_docx_child($style, 'name');
            $name = ($name !== null) ? strtolower(trim(pg_office_attr($name, 'val'))) : '';

            if (($name !== '') && !isset($context['by_name'][$name])) {
                $context['by_name'][$name] = $id;
            }
        }
    }

    $context['abstract'] = array();
    $context['nums'] = array();

    if ($context['numbering']['xml'] !== null) {
        $dom = pg_office_xml($zip, 'word/numbering.xml', $status);

        if ($dom === null) {
            return 'kind_unusual';
        }

        foreach ($dom->getElementsByTagNameNS('*', 'abstractNum') as $node) {
            $first = '';

            foreach ($node->getElementsByTagNameNS('*', 'lvl') as $level) {
                if ((int) pg_office_attr($level, 'ilvl') === 0) {
                    $format = pg_office_docx_child($level, 'numFmt');
                    $first = ($format !== null) ? pg_office_attr($format, 'val') : '';
                }
            }

            $context['abstract'][pg_office_attr($node, 'abstractNumId')] = $first;
        }

        foreach ($dom->getElementsByTagNameNS('*', 'num') as $node) {
            $ref = pg_office_docx_child($node, 'abstractNumId');
            $context['nums'][pg_office_attr($node, 'numId')] = ($ref !== null) ? pg_office_attr($ref, 'val') : '';
        }
    }

    return $context;
}

/**
 * The id of a heading style for a level: the document's own, or one added.
 *
 * @param array $context
 * @param int   $level 1 to 3
 * @return string|null null when one cannot be added
 */
function pg_office_docx_kind_heading_style(&$context, $level)
{
    $name = 'heading ' . $level;

    if (isset($context['by_name'][$name])) {
        return $context['by_name'][$name];
    }

    // A style whose id reads as the heading, without its built-in name.
    foreach ($context['paragraph_ids'] as $id) {
        if (!in_array(strtolower($id), array('title', 'balk', 'subtitle'), true) && (pg_office_docx_block_tag($id) === 'h' . $level)) {
            return $id;
        }
    }

    $id = 'Heading' . $level;

    for ($number = 2; isset($context['style_ids'][strtolower($id)]); $number++) {
        $id = 'Heading' . $level . 'Pg' . $number;
    }

    $prefix = ($context['styles']['xml'] !== null) ? $context['styles']['prefix'] : 'w';
    $p = $prefix . ':';
    $size = array(1 => 32, 2 => 28, 3 => 24);
    $default = ($context['default'] !== '') ? $context['default'] : '';
    $attr = function ($value) {
        return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    };

    $context['styles']['added'] .= '<' . $p . 'style ' . $p . 'type="paragraph" ' . $p . 'styleId="' . $attr($id) . '">'
        . '<' . $p . 'name ' . $p . 'val="' . $name . '"/>'
        . (($default !== '') ? '<' . $p . 'basedOn ' . $p . 'val="' . $attr($default) . '"/><' . $p . 'next ' . $p . 'val="' . $attr($default) . '"/>' : '')
        . '<' . $p . 'uiPriority ' . $p . 'val="9"/><' . $p . 'qFormat/>'
        . '<' . $p . 'pPr><' . $p . 'keepNext/><' . $p . 'keepLines/><' . $p . 'spacing ' . $p . 'before="240" ' . $p . 'after="80"/><' . $p . 'outlineLvl ' . $p . 'val="' . ($level - 1) . '"/></' . $p . 'pPr>'
        . '<' . $p . 'rPr><' . $p . 'b/><' . $p . 'bCs/><' . $p . 'sz ' . $p . 'val="' . $size[$level] . '"/><' . $p . 'szCs ' . $p . 'val="' . $size[$level] . '"/></' . $p . 'rPr>'
        . '</' . $p . 'style>';
    $context['style_ids'][strtolower($id)] = true;
    $context['by_name'][$name] = $id;
    $context['state']['heads'][$id] = $level;

    return $id;
}

/**
 * The numbering levels the product adds for a list, nine of them, each
 * indented a step further.
 *
 * @param string $prefix
 * @param bool   $bullet
 * @return string
 */
function pg_office_docx_kind_levels($prefix, $bullet)
{
    $p = $prefix . ':';
    $out = '';
    $bullets = array("\u{2022}", "\u{25E6}", "\u{25AA}");
    $formats = array('decimal', 'lowerLetter', 'lowerRoman');

    for ($level = 0; $level < 9; $level++) {
        $out .= '<' . $p . 'lvl ' . $p . 'ilvl="' . $level . '"><' . $p . 'start ' . $p . 'val="1"/>'
            . '<' . $p . 'numFmt ' . $p . 'val="' . ($bullet ? 'bullet' : $formats[$level % 3]) . '"/>'
            . '<' . $p . 'lvlText ' . $p . 'val="' . ($bullet ? $bullets[$level % 3] : '%' . ($level + 1) . '.') . '"/>'
            . '<' . $p . 'lvlJc ' . $p . 'val="left"/>'
            . '<' . $p . 'pPr><' . $p . 'ind ' . $p . 'left="' . (720 * ($level + 1)) . '" ' . $p . 'hanging="360"/></' . $p . 'pPr></' . $p . 'lvl>';
    }

    return $out;
}

/**
 * A new num added to the numbering, on an abstract numbering of the
 * document's, numbered from 1 again; or a new abstract numbering of the
 * product's and a num on it.
 *
 * @param array       $context
 * @param bool        $bullet
 * @param string|null $abstract an abstract numbering's id to use, null to add one
 * @return string the new num's id
 */
function pg_office_docx_kind_num_add(&$context, $bullet, $abstract)
{
    $prefix = ($context['numbering']['xml'] !== null) ? $context['numbering']['prefix'] : 'w';
    $p = $prefix . ':';

    if ($abstract === null) {
        $abstract = (string) (empty($context['abstract']) ? 0 : max(array_map('intval', array_keys($context['abstract']))) + 1);
        $context['numbering']['abstracts'] = (isset($context['numbering']['abstracts']) ? $context['numbering']['abstracts'] : '')
            . '<' . $p . 'abstractNum ' . $p . 'abstractNumId="' . $abstract . '"><' . $p . 'multiLevelType ' . $p . 'val="hybridMultilevel"/>'
            . pg_office_docx_kind_levels($prefix, $bullet) . '</' . $p . 'abstractNum>';
        $context['abstract'][$abstract] = $bullet ? 'bullet' : 'decimal';
        $override = '';
    } else {
        $override = '<' . $p . 'lvlOverride ' . $p . 'ilvl="0"><' . $p . 'startOverride ' . $p . 'val="1"/></' . $p . 'lvlOverride>';
    }

    $num = (string) (empty($context['nums']) ? 1 : max(array_map('intval', array_keys($context['nums']))) + 1);
    $context['numbering']['added'] .= '<' . $p . 'num ' . $p . 'numId="' . $num . '"><' . $p . 'abstractNumId ' . $p . 'val="' . $abstract . '"/>' . $override . '</' . $p . 'num>';
    $context['nums'][$num] = $abstract;

    // The preview's reading of the numbering, for the paragraphs after.
    $levels = array();

    for ($level = 0; $level < 9; $level++) {
        $levels[$level] = $bullet ? 'ul' : 'ol';
    }

    $context['state']['lists'][$num] = $levels;

    return $num;
}

/**
 * The num a paragraph made a list of a kind is numbered with: the one of
 * the paragraph just before it of that kind, else of the one just after it,
 * else a bulleted list's of the document; a numbered list starts again on
 * the document's own numbering, or on the product's.
 *
 * @param array      $context
 * @param DOMElement $paragraph
 * @param string     $kind bullet or number
 * @return string
 */
function pg_office_docx_kind_num(&$context, $paragraph, $kind)
{
    foreach (array('previousSibling', 'nextSibling') as $side) {
        $near = $paragraph->$side;

        while (($near !== null) && !($near instanceof DOMElement)) {
            $near = $near->$side;
        }

        if (($near !== null) && ($near->localName === 'p')) {
            $props = pg_office_docx_kind_props($context['state'], $near);

            if (($props['list'] !== '') && (pg_office_docx_kind_of($context['state'], $props) === $kind)) {
                return $props['list'];
            }
        }
    }

    $bullet = ($kind === 'bullet');

    foreach ($context['nums'] as $num => $abstract) {
        $first = isset($context['abstract'][$abstract]) ? $context['abstract'][$abstract] : '';

        if ($bullet && ($first === 'bullet')) {
            return (string) $num;
        }
    }

    if (!$bullet) {
        foreach ($context['nums'] as $num => $abstract) {
            if (isset($context['abstract'][$abstract]) && ($context['abstract'][$abstract] === 'decimal')) {
                return pg_office_docx_kind_num_add($context, false, (string) $abstract);
            }
        }
    }

    return pg_office_docx_kind_num_add($context, $bullet, null);
}

/**
 * Puts a paragraph property in its place (CT_PPr order) or replaces it.
 *
 * @param DOMElement $properties pPr
 * @param DOMElement $node
 */
function pg_office_docx_ppr_put($properties, $node)
{
    $order = array_flip(array('pStyle', 'keepNext', 'keepLines', 'pageBreakBefore', 'framePr', 'widowControl', 'numPr', 'suppressLineNumbers',
        'pBdr', 'shd', 'tabs', 'suppressAutoHyphens', 'kinsoku', 'wordWrap', 'overflowPunct', 'topLinePunct', 'autoSpaceDE', 'autoSpaceDN', 'bidi',
        'adjustRightInd', 'snapToGrid', 'spacing', 'ind', 'contextualSpacing', 'mirrorIndents', 'suppressOverlap', 'jc', 'textDirection',
        'textAlignment', 'textboxTightWrap', 'outlineLvl', 'divId', 'cnfStyle', 'rPr', 'sectPr', 'pPrChange'));
    $old = pg_office_docx_child($properties, $node->localName);

    if ($old !== null) {
        $properties->replaceChild($node, $old);
        return;
    }

    $before = null;

    foreach ($properties->childNodes as $child) {
        if (($child instanceof DOMElement) && (!isset($order[$child->localName]) || ($order[$child->localName] > $order[$node->localName]))) {
            $before = $child;
            break;
        }
    }

    $properties->insertBefore($node, $before);
}

/**
 * Gives a paragraph a kind: its style and numbering set, every other
 * property kept. Nothing is written when it has that kind already.
 *
 * @param array      $context pg_office_docx_kind_context()
 * @param DOMElement $paragraph
 * @param string     $kind
 * @return bool whether it was changed
 */
function pg_office_docx_paragraph_kind(&$context, $paragraph, $kind)
{
    $state = $context['state'];
    $props = pg_office_docx_kind_props($state, $paragraph);

    if (pg_office_docx_kind_of($state, $props) === $kind) {
        return false;
    }

    $namespace = (string) $paragraph->namespaceURI;
    $prefix = ((string) $paragraph->prefix !== '') ? $paragraph->prefix . ':' : '';
    $document = $paragraph->ownerDocument;
    $properties = pg_office_docx_child($paragraph, 'pPr');

    if ($properties === null) {
        $properties = $document->createElementNS($namespace, $prefix . 'pPr');
        $paragraph->insertBefore($properties, $paragraph->firstChild);
    }

    $element = function ($local, $attributes) use ($document, $namespace, $prefix) {
        $node = $document->createElementNS($namespace, $prefix . $local);

        foreach ($attributes as $name => $value) {
            $node->setAttributeNS($namespace, $prefix . $name, $value);
        }

        return $node;
    };

    $style = pg_office_docx_paragraph_props($paragraph)['style'];

    // A style that is itself a heading, a title, a quote or a list gives
    // way; a plain style of the document's own stays under a list or
    // normal text.
    $plain = ($style === '') || ((pg_office_docx_heading_level($state, $style) === 0) && (pg_office_docx_block_tag($style) === 'p') && !isset($state['styles'][$style]));
    $new_style = $plain ? $style : '';
    $num = '';

    if (strpos($kind, 'heading') === 0) {
        $new_style = pg_office_docx_kind_heading_style($context, (int) substr($kind, 7));
    } elseif (($kind === 'bullet') || ($kind === 'number')) {
        if (($style === '') || !$plain) {
            $new_style = isset($context['by_name']['list paragraph']) ? $context['by_name']['list paragraph'] : '';
        }

        $num = pg_office_docx_kind_num($context, $paragraph, $kind);
    }

    if ($new_style !== '') {
        pg_office_docx_ppr_put($properties, $element('pStyle', array('val' => $new_style)));
    } elseif (($old = pg_office_docx_child($properties, 'pStyle')) !== null) {
        $properties->removeChild($old);
    }

    $numbering = pg_office_docx_child($properties, 'numPr');

    if ($num !== '') {
        $level = 0;

        if ($numbering !== null) {
            $ilvl = pg_office_docx_child($numbering, 'ilvl');
            $level = ($ilvl !== null) ? min(8, max(0, (int) pg_office_attr($ilvl, 'val'))) : 0;
        }

        $node = $element('numPr', array());
        $node->appendChild($element('ilvl', array('val' => (string) $level)));
        $node->appendChild($element('numId', array('val' => $num)));
        pg_office_docx_ppr_put($properties, $node);
    } elseif ($numbering !== null) {
        $properties->removeChild($numbering);
    }

    return true;
}

/**
 * The parts a save that set kinds adds to or changes beside the main part:
 * the styles and the numbering with what was added, and when one of them
 * was not in the package, its content type and relationship. When the
 * save writes the relationships for links or pictures too, the new one is
 * added to theirs, so neither writing undoes the other.
 *
 * @param ZipArchive $zip
 * @param array      $context
 * @param array|null $links   pg_office_docx_link_context() of the save, when it has one
 * @return array|null part name => text; null when they cannot be written
 */
function pg_office_docx_kind_parts($zip, $context, &$links = null)
{
    $texts = array();
    $rels = $context['rels'];
    $types = $context['types'];
    $attr = function ($value) {
        return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    };
    $content = array(
        'styles'    => 'application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml',
        'numbering' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.numbering+xml',
    );

    foreach (array('styles', 'numbering') as $kind) {
        $part = $context[$kind];
        $pieces = ($kind === 'numbering') ? (isset($part['abstracts']) ? $part['abstracts'] : '') : '';

        if (($part['added'] === '') && ($pieces === '')) {
            continue;
        }

        if ($part['xml'] !== null) {
            $xml = $part['xml'];

            if ($kind === 'styles') {
                $xml = pg_office_docx_kind_insert($xml, $part['prefix'], array(), 'styles', $part['added']);
            } else {
                // Abstract numberings stand before every num (CT_Numbering).
                $xml = ($pieces !== '') ? pg_office_docx_kind_insert($xml, $part['prefix'], array('num', 'numIdMacAtCleanup'), 'numbering', $pieces) : $xml;
                $xml = ($xml !== null) ? pg_office_docx_kind_insert($xml, $part['prefix'], array('numIdMacAtCleanup'), 'numbering', $part['added']) : null;
            }

            if ($xml === null) {
                return null;
            }

            $texts['word/' . $kind . '.xml'] = $xml;
            continue;
        }

        // A part the document did not have: written new, named in the
        // relationships and the content types.
        if ($context['base'] === '') {
            return null;
        }

        $texts['word/' . $kind . '.xml'] = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n"
            . '<w:' . $kind . ' xmlns:w="' . $attr($context['namespace']) . '">' . $pieces . $part['added'] . '</w:' . $kind . '>';

        if (is_array($links)) {
            pg_office_docx_rel_add($links, $context['base'] . $kind, $kind . '.xml');
        } else {
            preg_match_all('/\sId\s*=\s*"rId(\d+)"/', $rels, $ids);
            $id = 'rId' . (empty($ids[1]) ? 1 : max(array_map('intval', $ids[1])) + 1);
            $rels = pg_office_sheets_append($rels, 'Relationships', '<Relationship Id="' . $id . '" Type="' . $attr($context['base'] . $kind) . '" Target="' . $kind . '.xml"/>');
        }

        if (($rels === null) || ($types === null)) {
            return null;
        }

        if (stripos($types, 'PartName="/word/' . $kind . '.xml"') === false) {
            $types = pg_office_sheets_append($types, 'Types', '<Override PartName="/word/' . $kind . '.xml" ContentType="' . $content[$kind] . '"/>');
        }
    }

    if (($rels === null) || ($types === null)) {
        return null;
    }

    if ($rels !== $context['rels']) {
        $texts['word/_rels/document.xml.rels'] = $rels;
    }

    if ($types !== $context['types']) {
        $texts['[Content_Types].xml'] = $types;
    }

    foreach ($texts as $xml) {
        if (!pg_office_xml_well_formed($xml)) {
            return null;
        }
    }

    return $texts;
}
