<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Bold, italic and underline set on or taken off a stretch of a Word
 * paragraph's text, in the file itself (the file window and a channel's
 * preview send it with a save's paragraphs, through pg_office_docx_save()).
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
 * The run properties each format sets: Word sets the complex-script twin of
 * bold and italic with them.
 *
 * @return array property => element local names
 */
function pg_office_docx_format_elements()
{
    return array('bold' => array('b', 'bCs'), 'italic' => array('i', 'iCs'), 'underline' => array('u'));
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
 * The look of an editable paragraph's text, stretch by stretch: what the
 * editor starts from when it shows and changes bold, italic and underline.
 *
 * @param array $edit pg_office_docx_paragraph_edit() of it
 * @return array [[characters, bits], ...] covering the text in order
 */
function pg_office_docx_paragraph_marks($edit)
{
    $marks = array();

    foreach ($edit['runs'] as $run) {
        $length = mb_strlen($run['text'], 'UTF-8');

        if ($length === 0) {
            continue;
        }

        $bits = pg_office_docx_run_bits($run['run']);
        $last = count($marks) - 1;

        if (($last >= 0) && ($marks[$last][1] === $bits)) {
            $marks[$last][0] += $length;
        } else {
            $marks[] = array($length, $bits);
        }
    }

    return $marks;
}

/**
 * A paragraph's format change as a save sends it, checked: a list of
 * {start, end, set: {bold|italic|underline: true|false}}.
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
            if (!isset($bits[$property]) || !is_bool($on)) {
                return null;
            }

            $set[$property] = $on;
        }

        $out[] = array($start, $end, $set);
    }

    return $out;
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

    $order = array_flip(pg_office_docx_rpr_order());

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
            $node = $document->createElementNS($namespace, $prefix . $local);
            $before = null;

            foreach ($properties->childNodes as $child) {
                if (($child instanceof DOMElement) && isset($order[$child->localName]) && ($order[$child->localName] > $order[$local])) {
                    $before = $child;
                    break;
                }
            }

            $properties->insertBefore($node, $before);
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
 * Sets bold, italic or underline on stretches of an editable paragraph's
 * text, splitting the runs a stretch starts or ends inside and joining the
 * reached runs that end up looking the same.
 *
 * @param DOMElement $paragraph
 * @param array      $ranges pg_office_docx_format_request()
 * @return string '' when written; locked or format_range
 */
function pg_office_docx_paragraph_format($paragraph, $ranges)
{
    $reached = new SplObjectStorage();

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
                    $reached->attach(pg_office_docx_run_split($run['run'], $point - $offset));
                    $reached->attach($run['run']);
                    break;
                }

                $offset += $length;
            }

            $edit = pg_office_docx_paragraph_edit($paragraph);
        }

        $offset = 0;

        foreach ($edit['runs'] as $run) {
            $length = mb_strlen($run['text'], 'UTF-8');

            if (($length > 0) && ($offset >= $start) && ($offset + $length <= $end)) {
                $styled = pg_office_docx_run_styled($paragraph, $run['run']);

                foreach ($set as $property => $on) {
                    pg_office_docx_rpr_set($run['run'], $property, $on, $styled);
                }

                $reached->attach($run['run']);
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

        if (($previous !== null) && ($previous->nextSibling === $node) && ($reached->contains($previous) || $reached->contains($node))
            && (pg_office_docx_rpr_key($previous) === pg_office_docx_rpr_key($node))) {
            pg_office_docx_runs_join($previous, $node);
            $reached->attach($previous);
            continue;
        }

        $previous = $node;
    }

    return '';
}
