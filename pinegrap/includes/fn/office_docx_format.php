<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Bold, italic, underline, a text colour, a highlight and a font size set
 * on or taken off a stretch of a Word paragraph's text, and a paragraph's
 * alignment, in the file itself (the file window and a channel's preview
 * send them with a save's paragraphs, through pg_office_docx_save()).
 *
 * The stretch is counted in the paragraph's text as the preview reads it
 * (pg_office_docx_paragraph_edit()). A run the stretch starts or ends inside
 * is split in two, each half keeping the run's attributes and properties;
 * the runs inside get the asked property in their own properties (rPr),
 * written in the order the format requires, every other property kept.
 * Afterwards neighbouring runs of the same parent that the change reached
 * and that now look the same are joined back into one. Runs the change does
 * not reach keep their XML.
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
 * The most stretches one paragraph's format change may hold.
 */
define('PG_OFFICE_DOCX_FORMAT_MAX_RANGES', 200);

/**
 * The bits a run's look is told by: bold 1, italic 2, underline 4.
 *
 * @return array property => bit
 */
function pg_office_docx_format_bits()
{
    return array('bold' => 1, 'italic' => 2, 'underline' => 4);
}

/**
 * The formats that take a value rather than on or off: a text colour
 * (RRGGBB), a highlight (one of pg_office_docx_highlight_names()) and a font
 * size in points; false takes each off.
 *
 * @return array property => the key the editor's marks give it in
 */
function pg_office_docx_format_values()
{
    return array('color' => 'c', 'highlight' => 'h', 'size' => 's');
}

/**
 * The highlight colours Word names (ST_HighlightColor, none left out): a
 * highlight is one of these, never a colour of its own.
 *
 * @return string[]
 */
function pg_office_docx_highlight_names()
{
    return array('yellow', 'green', 'cyan', 'magenta', 'blue', 'red', 'darkBlue', 'darkCyan', 'darkGreen', 'darkMagenta', 'darkRed',
        'darkYellow', 'darkGray', 'lightGray', 'black', 'white');
}

/**
 * The smallest and the largest font size the format entry writes, in points.
 */
define('PG_OFFICE_DOCX_SIZE_MIN', 8);
define('PG_OFFICE_DOCX_SIZE_MAX', 72);

/**
 * The run properties each format sets: Word sets the complex-script twin of
 * bold, italic and the size with them.
 *
 * @return array property => element local names
 */
function pg_office_docx_format_elements()
{
    return array('bold' => array('b', 'bCs'), 'italic' => array('i', 'iCs'), 'underline' => array('u'), 'color' => array('color'),
        'highlight' => array('highlight'), 'size' => array('sz', 'szCs'));
}

/**
 * The order the properties of a run stand in (CT_RPr); a reader may refuse
 * them in another order.
 *
 * @return string[]
 */
function pg_office_docx_rpr_order()
{
    return array('rStyle', 'rFonts', 'b', 'bCs', 'i', 'iCs', 'caps', 'smallCaps', 'strike', 'dstrike', 'outline', 'shadow', 'emboss', 'imprint',
        'noProof', 'snapToGrid', 'vanish', 'webHidden', 'color', 'spacing', 'w', 'kern', 'position', 'sz', 'szCs', 'highlight', 'u', 'effect',
        'bdr', 'shd', 'fitText', 'vertAlign', 'rtl', 'cs', 'em', 'lang', 'eastAsianLayout', 'specVanish', 'oMath', 'rPrChange');
}

/**
 * A child element of a node by its local name.
 *
 * @param DOMElement $node
 * @param string     $local
 * @return DOMElement|null
 */
function pg_office_docx_child($node, $local)
{
    foreach ($node->childNodes as $child) {
        if (($child instanceof DOMElement) && ($child->localName === $local)) {
            return $child;
        }
    }

    return null;
}

/**
 * Bold, italic and underline of a run as the preview draws them: its own
 * properties only, a property written off (w:val 0, false, off, none) not
 * counted.
 *
 * @param DOMElement $run
 * @return int bits of pg_office_docx_format_bits()
 */
function pg_office_docx_run_bits($run)
{
    $bits = 0;
    $properties = pg_office_docx_child($run, 'rPr');

    if ($properties === null) {
        return 0;
    }

    $map = array('b' => 1, 'i' => 2, 'u' => 4);

    foreach ($properties->childNodes as $property) {
        if (!($property instanceof DOMElement) || !isset($map[$property->localName])) {
            continue;
        }

        $val = strtolower(pg_office_attr($property, 'val'));

        if (in_array($val, array('0', 'false', 'off', 'none'), true)) {
            $bits &= ~$map[$property->localName];
        } else {
            $bits |= $map[$property->localName];
        }
    }

    return $bits;
}

/**
 * A run's text colour, highlight and font size as its own properties give
 * them: a colour as RRGGBB (auto left out), a highlight Word names (none
 * left out), a size in points.
 *
 * @param DOMElement $run
 * @return array c, h, s, each only when the run has it
 */
function pg_office_docx_run_look($run)
{
    $look = array();
    $properties = pg_office_docx_child($run, 'rPr');

    if ($properties === null) {
        return $look;
    }

    $color = pg_office_docx_child($properties, 'color');
    $value = ($color !== null) ? (string) pg_office_attr($color, 'val') : '';

    if (preg_match('/^[0-9A-Fa-f]{6}$/', $value)) {
        $look['c'] = strtoupper($value);
    }

    $highlight = pg_office_docx_child($properties, 'highlight');
    $value = ($highlight !== null) ? strtolower((string) pg_office_attr($highlight, 'val')) : '';

    foreach (pg_office_docx_highlight_names() as $name) {
        if (strtolower($name) === $value) {
            $look['h'] = $name;
        }
    }

    $size = pg_office_docx_child($properties, 'sz');
    $value = ($size !== null) ? (string) pg_office_attr($size, 'val') : '';

    if (ctype_digit($value) && ((int) $value > 0)) {
        $look['s'] = ((int) $value % 2 === 0) ? (int) $value / 2 : (int) $value / 2.0;
    }

    return $look;
}

/**
 * The colour each of Word's highlight names is drawn in.
 *
 * @return array name => RRGGBB
 */
function pg_office_docx_highlight_colors()
{
    return array('yellow' => 'FFFF00', 'green' => '00FF00', 'cyan' => '00FFFF', 'magenta' => 'FF00FF', 'blue' => '0000FF',
        'red' => 'FF0000', 'darkBlue' => '000080', 'darkCyan' => '008080', 'darkGreen' => '008000', 'darkMagenta' => '800080',
        'darkRed' => '800000', 'darkYellow' => '808000', 'darkGray' => '808080', 'lightGray' => 'C0C0C0', 'black' => '000000',
        'white' => 'FFFFFF');
}

/**
 * The inline style the preview draws a run's own colour, highlight and
 * size with (pg_office_docx_run_look()); the twin of lookStyle() in
 * pg_office_view.js. Anything else in the look is left out.
 *
 * @param array $look {c, h, s}
 * @return string
 */
function pg_office_docx_look_style($look)
{
    $style = array();
    $highlights = pg_office_docx_highlight_colors();

    if (isset($look['c']) && is_string($look['c']) && preg_match('/^[0-9A-F]{6}$/', $look['c'])) {
        $style[] = 'color:#' . $look['c'];
    }

    if (isset($look['h']) && is_string($look['h']) && isset($highlights[$look['h']])) {
        $style[] = 'background-color:#' . $highlights[$look['h']];
    }

    if (isset($look['s']) && (is_int($look['s']) || is_float($look['s'])) && ($look['s'] > 0)) {
        $style[] = 'font-size:' . $look['s'] . 'pt';
    }

    return implode(';', $style);
}

/**
 * The look of an editable paragraph's text, stretch by stretch: what the
 * editor starts from when it shows and changes the format.
 *
 * @param array      $edit pg_office_docx_paragraph_edit() of it
 * @param array|null $rels the main part's relationships (pg_office_rels()): a run in a link carries its address as l
 * @return array [[characters, bits], ...] covering the text in order; a
 *               stretch with a colour, highlight or size of its own, or in a
 *               link, carries pg_office_docx_run_look() third: [characters, bits, {c, h, s, l}]
 */
function pg_office_docx_paragraph_marks($edit, $rels = null)
{
    $marks = array();

    foreach ($edit['runs'] as $run) {
        $length = mb_strlen($run['text'], 'UTF-8');

        if ($length === 0) {
            continue;
        }

        $mark = array($length, pg_office_docx_run_bits($run['run']));
        $look = pg_office_docx_run_look($run['run']);
        $link = ($rels !== null) ? pg_office_docx_run_link($run['run'], $rels) : '';

        if ($link !== '') {
            $look['l'] = $link;
        }

        if (!empty($look)) {
            $mark[] = $look;
        }

        $last = count($marks) - 1;

        if (($last >= 0) && (array_slice($marks[$last], 1) === array_slice($mark, 1))) {
            $marks[$last][0] += $length;
        } else {
            $marks[] = $mark;
        }
    }

    return $marks;
}

/**
 * A paragraph's format change as a save sends it, checked: a list of
 * {start, end, set: {bold|italic|underline: true|false, color: RRGGBB|false,
 * highlight: a Word highlight name|false, size: points|false, link: an
 * address|false}}.
 *
 * @param mixed $format
 * @return array|null [[start, end, [property => bool]], ...]; null when it is not one
 */
function pg_office_docx_format_request($format)
{
    if (!is_array($format) || empty($format) || (count($format) > PG_OFFICE_DOCX_FORMAT_MAX_RANGES)) {
        return null;
    }

    $bits = pg_office_docx_format_bits();
    $values = pg_office_docx_format_values();
    $out = array();

    foreach ($format as $range) {
        if (!is_array($range) || !isset($range['start'], $range['end'], $range['set']) || !is_array($range['set']) || empty($range['set'])) {
            return null;
        }

        $start = $range['start'];
        $end = $range['end'];

        if (!is_int($start) && !(is_string($start) && ctype_digit($start))) {
            return null;
        }

        if (!is_int($end) && !(is_string($end) && ctype_digit($end))) {
            return null;
        }

        $start = (int) $start;
        $end = (int) $end;

        if (($start < 0) || ($end <= $start)) {
            return null;
        }

        $set = array();

        foreach ($range['set'] as $property => $on) {
            // A link's address (includes/fn/office_docx_link.php).
            if ($property === 'link') {
                $on = ($on === false) ? false : pg_office_docx_link_value($on);

                if ($on === null) {
                    return null;
                }

                $set[$property] = $on;
                continue;
            }

            if (isset($values[$property]) && ($on !== false)) {
                $on = pg_office_docx_format_value($property, $on);

                if ($on === null) {
                    return null;
                }
            } elseif (!(isset($bits[$property]) || isset($values[$property])) || !is_bool($on)) {
                return null;
            }

            $set[$property] = $on;
        }

        $out[] = array($start, $end, $set);
    }

    return $out;
}

/**
 * A colour, highlight or size a format change asks for, checked: a colour
 * as six hex digits, a highlight by a name Word has (any case), a size in
 * half points from PG_OFFICE_DOCX_SIZE_MIN to PG_OFFICE_DOCX_SIZE_MAX.
 *
 * @param string $property color, highlight or size
 * @param mixed  $value
 * @return string|int|float|null as written (RRGGBB, Word's name, points); null when it is not one
 */
function pg_office_docx_format_value($property, $value)
{
    if ($property === 'color') {
        return (is_string($value) && preg_match('/^[0-9A-Fa-f]{6}$/', $value)) ? strtoupper($value) : null;
    }

    if ($property === 'highlight') {
        if (!is_string($value)) {
            return null;
        }

        foreach (pg_office_docx_highlight_names() as $name) {
            if (strtolower($name) === strtolower($value)) {
                return $name;
            }
        }

        return null;
    }

    if (!is_int($value) && !is_float($value) && !(is_string($value) && is_numeric($value))) {
        return null;
    }

    $half = (float) $value * 2;

    if (($half != floor($half)) || ($half < PG_OFFICE_DOCX_SIZE_MIN * 2) || ($half > PG_OFFICE_DOCX_SIZE_MAX * 2)) {
        return null;
    }

    return (fmod($half, 2) == 0) ? (int) ($half / 2) : $half / 2;
}

/**
 * How many characters of the paragraph's text an element inside a run
 * stands for (pg_office_docx_run_edit() counts the same way).
 *
 * @param DOMElement $child
 * @return int
 */
function pg_office_docx_run_child_length($child)
{
    switch ($child->localName) {
        case 't':
            return mb_strlen(pg_office_docx_t_text($child), 'UTF-8');
        case 'tab':
        case 'br':
        case 'cr':
        case 'noBreakHyphen':
        case 'softHyphen':
            return 1;
    }

    return 0;
}

/**
 * A text element's text as the paragraph's text holds it: a line end
 * written inside it is a space.
 *
 * @param DOMElement $node
 * @return string
 */
function pg_office_docx_t_text($node)
{
    return str_replace(array("\r\n", "\r", "\n"), ' ', $node->textContent);
}

/**
 * Writes a text into a text element, saying its spaces belong to it when a
 * reader would drop them otherwise.
 *
 * @param DOMElement $node
 * @param string     $text
 */
function pg_office_docx_t_write($node, $text)
{
    while ($node->firstChild) {
        $node->removeChild($node->firstChild);
    }

    $node->appendChild($node->ownerDocument->createTextNode($text));

    if ((trim($text) !== $text) || (strpos($text, '  ') !== false)) {
        $node->setAttributeNS('http://www.w3.org/XML/1998/namespace', 'xml:space', 'preserve');
    } elseif ($node->hasAttributeNS('http://www.w3.org/XML/1998/namespace', 'space')) {
        $node->removeAttributeNS('http://www.w3.org/XML/1998/namespace', 'space');
    }
}

/**
 * Splits a run in two at a point of its text: the run keeps what comes
 * before, a copy of it (its attributes and properties) placed right after
 * it takes the rest.
 *
 * @param DOMElement $run
 * @param int        $at characters of the run's text, inside it
 * @return DOMElement the new run
 */
function pg_office_docx_run_split($run, $at)
{
    $after = $run->cloneNode(false);
    $properties = pg_office_docx_child($run, 'rPr');

    if ($properties !== null) {
        $after->appendChild($properties->cloneNode(true));
    }

    $count = 0;
    $moving = array();

    foreach ($run->childNodes as $child) {
        if (!($child instanceof DOMElement) || ($child->localName === 'rPr')) {
            continue;
        }

        if ($count >= $at) {
            $moving[] = $child;
            continue;
        }

        $length = pg_office_docx_run_child_length($child);

        if (($child->localName === 't') && ($count + $length > $at)) {
            // The point falls inside this text: its second half goes to
            // the new run as a text element of its own.
            $text = pg_office_docx_t_text($child);
            $second = $child->cloneNode(false);

            pg_office_docx_t_write($child, mb_substr($text, 0, $at - $count, 'UTF-8'));
            pg_office_docx_t_write($second, mb_substr($text, $at - $count, null, 'UTF-8'));
            $after->appendChild($second);
        }

        $count += $length;
    }

    foreach ($moving as $child) {
        $after->appendChild($child);
    }

    $run->parentNode->insertBefore($after, $run->nextSibling);

    return $after;
}

/**
 * Sets bold, italic or underline on a run, or takes it off, keeping every
 * other property. Taken off where a style may give it back (a character or
 * a paragraph style), it is written off rather than left out.
 *
 * @param DOMElement $run
 * @param string     $property bold, italic or underline
 * @param bool       $on
 * @param bool       $styled   whether a style may set it
 */
function pg_office_docx_rpr_set($run, $property, $on, $styled)
{
    $elements = pg_office_docx_format_elements();
    $document = $run->ownerDocument;
    $namespace = (string) $run->namespaceURI;
    $prefix = ((string) $run->prefix !== '') ? $run->prefix . ':' : '';
    $properties = pg_office_docx_child($run, 'rPr');
    $made = false;

    if ($properties === null) {
        if (!$on && !$styled) {
            return;
        }

        $properties = $document->createElementNS($namespace, $prefix . 'rPr');
        $run->insertBefore($properties, $run->firstChild);
        $made = true;
    }

    foreach ($elements[$property] as $local) {
        $node = pg_office_docx_child($properties, $local);
        $val = ($node !== null) ? strtolower(pg_office_attr($node, 'val')) : '';
        $off = in_array($val, array('0', 'false', 'off', 'none'), true);

        if (!$on && !$styled) {
            if ($node !== null) {
                $properties->removeChild($node);
            }
            continue;
        }

        if ($on && ($node !== null) && !$off) {
            // Already on: an underline keeps its kind (double, dotted...).
            continue;
        }

        if ($node === null) {
            $node = pg_office_docx_rpr_place($properties, $local);
        }

        $value = $on ? (($local === 'u') ? 'single' : '') : (($local === 'u') ? 'none' : '0');

        foreach (iterator_to_array($node->attributes) as $attribute) {
            if ($attribute->localName === 'val') {
                $node->removeAttributeNode($attribute);
            }
        }

        if ($value !== '') {
            $node->setAttributeNS($namespace, $prefix . 'val', $value);
        }
    }

    if ($made && !$properties->hasChildNodes()) {
        $run->removeChild($properties);
    }
}

/**
 * A new, empty run property put in its place among the others (CT_RPr
 * order).
 *
 * @param DOMElement $properties rPr
 * @param string     $local      its local name
 * @return DOMElement
 */
function pg_office_docx_rpr_place($properties, $local)
{
    $order = array_flip(pg_office_docx_rpr_order());
    $prefix = ((string) $properties->prefix !== '') ? $properties->prefix . ':' : '';
    $node = $properties->ownerDocument->createElementNS((string) $properties->namespaceURI, $prefix . $local);
    $before = null;

    foreach ($properties->childNodes as $child) {
        if (($child instanceof DOMElement) && isset($order[$child->localName]) && ($order[$child->localName] > $order[$local])) {
            $before = $child;
            break;
        }
    }

    $properties->insertBefore($node, $before);

    return $node;
}

/**
 * Sets a text colour, a highlight or a font size on a run, or takes it off
 * (false), keeping every other property. A colour is written as RRGGBB with
 * any theme colour it had left off, as the theme would win over it; a size
 * in half points with its complex-script twin. Taken off where a style may
 * give it back, a colour is written auto and a highlight none; a size has no
 * value that means none, so it goes and the style's size is the one meant.
 *
 * @param DOMElement            $run
 * @param string                $property color, highlight or size
 * @param string|int|float|bool $value    pg_office_docx_format_value(), or false
 * @param bool                  $styled   whether a style may set it
 */
function pg_office_docx_rpr_value_set($run, $property, $value, $styled)
{
    $elements = pg_office_docx_format_elements();
    $namespace = (string) $run->namespaceURI;
    $prefix = ((string) $run->prefix !== '') ? $run->prefix . ':' : '';
    $properties = pg_office_docx_child($run, 'rPr');

    if ($value === false) {
        $write = ($styled && ($property !== 'size')) ? (($property === 'color') ? 'auto' : 'none') : '';
    } else {
        $write = ($property === 'size') ? (string) (int) round($value * 2) : (string) $value;
    }

    if ($properties === null) {
        if ($write === '') {
            return;
        }

        $properties = $run->ownerDocument->createElementNS($namespace, $prefix . 'rPr');
        $run->insertBefore($properties, $run->firstChild);
    }

    foreach ($elements[$property] as $local) {
        $node = pg_office_docx_child($properties, $local);

        if ($write === '') {
            if ($node !== null) {
                $properties->removeChild($node);
            }
            continue;
        }

        if ($node === null) {
            $node = pg_office_docx_rpr_place($properties, $local);
        } elseif ((pg_office_attr($node, 'val') === $write) && !$node->hasAttributeNS($namespace, 'themeColor')) {
            continue;
        }

        foreach (iterator_to_array($node->attributes) as $attribute) {
            if (in_array($attribute->localName, array('val', 'themeColor', 'themeTint', 'themeShade'), true)) {
                $node->removeAttributeNode($attribute);
            }
        }

        $node->setAttributeNS($namespace, $prefix . 'val', $write);
    }

    if (!$properties->hasChildNodes()) {
        $run->removeChild($properties);
    }
}

/**
 * Whether a style may give a run bold, italic or underline: a character
 * style of its own or the paragraph's style.
 *
 * @param DOMElement $paragraph
 * @param DOMElement $run
 * @return bool
 */
function pg_office_docx_run_styled($paragraph, $run)
{
    $properties = pg_office_docx_child($run, 'rPr');

    if (($properties !== null) && (pg_office_docx_child($properties, 'rStyle') !== null)) {
        return true;
    }

    $paragraph_properties = pg_office_docx_child($paragraph, 'pPr');

    return ($paragraph_properties !== null) && (pg_office_docx_child($paragraph_properties, 'pStyle') !== null);
}

/**
 * The properties of a run as they compare to another's: '' for none.
 *
 * @param DOMElement $run
 * @return string
 */
function pg_office_docx_rpr_key($run)
{
    $properties = pg_office_docx_child($run, 'rPr');

    if (($properties === null) || !$properties->hasChildNodes()) {
        return '';
    }

    $canonical = $properties->C14N();

    return is_string($canonical) ? $canonical : '';
}

/**
 * Joins the second run into the first: its text after the first's, the
 * first's attributes and properties kept, text elements that end up side
 * by side made one.
 *
 * @param DOMElement $first
 * @param DOMElement $second
 */
function pg_office_docx_runs_join($first, $second)
{
    foreach (iterator_to_array($second->childNodes) as $child) {
        if (($child instanceof DOMElement) && ($child->localName === 'rPr')) {
            continue;
        }

        $first->appendChild($child);
    }

    $second->parentNode->removeChild($second);

    $previous = null;

    foreach (iterator_to_array($first->childNodes) as $child) {
        if (!($child instanceof DOMElement)) {
            continue;
        }

        if (($child->localName === 't') && ($previous !== null) && ($previous->localName === 't')) {
            pg_office_docx_t_write($previous, pg_office_docx_t_text($previous) . pg_office_docx_t_text($child));
            $first->removeChild($child);
            continue;
        }

        $previous = $child;
    }
}

/**
 * Sets bold, italic, underline, a colour, a highlight or a size on
 * stretches of an editable paragraph's text, splitting the runs a stretch starts or ends inside and joining the
 * reached runs that end up looking the same.
 *
 * A link stretch puts the text into a link or takes it out of its links
 * (pg_office_docx_paragraph_link()); a link leaving the document adds its
 * relationship to $links.
 *
 * @param DOMElement $paragraph
 * @param array      $ranges pg_office_docx_format_request()
 * @param array|null $links  pg_office_docx_link_context(); made when a link needs it and it is null
 * @return string '' when written; locked or format_range
 */
function pg_office_docx_paragraph_format($paragraph, $ranges, &$links = null)
{
    $reached = new SplObjectStorage();
    $values = pg_office_docx_format_values();

    foreach ($ranges as $range) {
        list($start, $end, $set) = $range;

        $edit = pg_office_docx_paragraph_edit($paragraph);

        if ($edit['lock'] !== '') {
            return 'locked';
        }

        if ($end > mb_strlen($edit['text'], 'UTF-8')) {
            return 'format_range';
        }

        // The runs a stretch's ends fall inside are split there first.
        foreach (array($start, $end) as $point) {
            $offset = 0;

            foreach ($edit['runs'] as $run) {
                $length = mb_strlen($run['text'], 'UTF-8');

                if (($point > $offset) && ($point < $offset + $length)) {
                    $reached[pg_office_docx_run_split($run['run'], $point - $offset)] = true;
                    $reached[$run['run']] = true;
                    break;
                }

                $offset += $length;
            }

            $edit = pg_office_docx_paragraph_edit($paragraph);
        }

        if (array_key_exists('link', $set)) {
            pg_office_docx_paragraph_link($paragraph, $start, $end, $set['link'], $links, $reached);
            unset($set['link']);
            $edit = pg_office_docx_paragraph_edit($paragraph);
        }

        $offset = 0;

        foreach ($edit['runs'] as $run) {
            $length = mb_strlen($run['text'], 'UTF-8');

            if (!empty($set) && ($length > 0) && ($offset >= $start) && ($offset + $length <= $end)) {
                $styled = pg_office_docx_run_styled($paragraph, $run['run']);

                foreach ($set as $property => $on) {
                    if (isset($values[$property])) {
                        pg_office_docx_rpr_value_set($run['run'], $property, $on, $styled);
                    } else {
                        pg_office_docx_rpr_set($run['run'], $property, $on, $styled);
                    }
                }

                $reached[$run['run']] = true;
            }

            $offset += $length;
        }
    }

    // Neighbours of one parent that now look the same become one run again,
    // when the change reached at least one of them.
    $edit = pg_office_docx_paragraph_edit($paragraph);
    $previous = null;

    foreach ($edit['runs'] as $run) {
        $node = $run['run'];

        if (($previous !== null) && ($previous->nextSibling === $node) && (isset($reached[$previous]) || isset($reached[$node]))
            && (pg_office_docx_rpr_key($previous) === pg_office_docx_rpr_key($node))) {
            pg_office_docx_runs_join($previous, $node);
            $reached[$previous] = true;
            continue;
        }

        $previous = $node;
    }

    return '';
}

/**
 * The alignments a paragraph entry may ask for, with the value each is
 * written as: left and right, which Word reads as the start and the end of
 * a right-to-left paragraph too, rather than start and end, which older
 * readers do not know.
 *
 * @return array alignment => jc value
 */
function pg_office_docx_aligns()
{
    return array('start' => 'left', 'center' => 'center', 'end' => 'right', 'justify' => 'both');
}

/**
 * A paragraph entry's alignment, checked.
 *
 * @param mixed $align
 * @return string|null start, center, end or justify; null when it is not one
 */
function pg_office_docx_align_request($align)
{
    return (is_string($align) && isset(pg_office_docx_aligns()[$align])) ? $align : null;
}

/**
 * A paragraph's alignment as the editor's bar shows it, from its own
 * properties: one the bar does not offer (distribute, thai) is start.
 *
 * @param DOMElement $paragraph
 * @return string start, center, end or justify
 */
function pg_office_docx_paragraph_align($paragraph)
{
    $properties = pg_office_docx_child($paragraph, 'pPr');
    $jc = ($properties !== null) ? pg_office_docx_child($properties, 'jc') : null;

    if ($jc === null) {
        return 'start';
    }

    $map = array('left' => 'start', 'start' => 'start', 'center' => 'center', 'right' => 'end', 'end' => 'end', 'both' => 'justify');
    $value = strtolower((string) pg_office_attr($jc, 'val'));

    return isset($map[$value]) ? $map[$value] : 'start';
}

/**
 * Aligns a paragraph, every other property kept. Start is written as left,
 * never by taking the alignment away: a paragraph without a style of its
 * own takes the document's default paragraph style, which may centre or
 * justify it. A paragraph with no alignment and no style is left as it
 * is. Nothing is written when the paragraph is aligned so already.
 *
 * @param DOMElement $paragraph
 * @param string     $align pg_office_docx_align_request()
 * @return bool whether it was changed
 */
function pg_office_docx_paragraph_align_set($paragraph, $align)
{
    $properties = pg_office_docx_child($paragraph, 'pPr');
    $jc = ($properties !== null) ? pg_office_docx_child($properties, 'jc') : null;
    $styled = ($properties !== null) && (pg_office_docx_child($properties, 'pStyle') !== null);

    if (($align === 'start') && !$styled && ($jc === null)) {
        return false;
    }

    if (($jc !== null) && (pg_office_docx_paragraph_align($paragraph) === $align)) {
        return false;
    }

    $namespace = (string) $paragraph->namespaceURI;
    $prefix = ((string) $paragraph->prefix !== '') ? $paragraph->prefix . ':' : '';

    if ($properties === null) {
        $properties = $paragraph->ownerDocument->createElementNS($namespace, $prefix . 'pPr');
        $paragraph->insertBefore($properties, $paragraph->firstChild);
    }

    $node = $paragraph->ownerDocument->createElementNS($namespace, $prefix . 'jc');
    $node->setAttributeNS($namespace, $prefix . 'val', pg_office_docx_aligns()[$align]);
    pg_office_docx_ppr_put($properties, $node);

    return true;
}
