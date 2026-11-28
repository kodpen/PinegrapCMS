<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * A link put on a stretch of a Word paragraph's text, changed or taken off,
 * in the file itself. The file window and a channel's preview send it as a
 * format stretch of a save's paragraphs ({start, end, set: {link: address
 * or false}}), written by pg_office_docx_paragraph_format().
 *
 * A link is a hyperlink element around the stretch's runs; one leaving the
 * document names a relationship of the main part (an external target), one
 * to a bookmark of the document its anchor. Only http, https, mailto and tel
 * addresses are written, and a bookmark's name. A stretch is first taken out
 * of every link it reaches (the rest of each link stays a link), then put
 * into its own link when an address is asked.
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
 * The longest address a link may have.
 */
define('PG_OFFICE_DOCX_LINK_MAX', 2048);

/**
 * The relationship type of a link that leaves the document, in a
 * transitional and in a strict document.
 *
 * @param bool $strict
 * @return string
 */
function pg_office_docx_link_type($strict)
{
    return $strict ? 'http://purl.oclc.org/ooxml/officeDocument/relationships/hyperlink'
        : 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink';
}

/**
 * The namespace a relationship id is written in, in a transitional and in
 * a strict document.
 *
 * @param bool $strict
 * @return string
 */
function pg_office_docx_link_namespace($strict)
{
    return $strict ? 'http://purl.oclc.org/ooxml/officeDocument/relationships'
        : 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';
}

/**
 * An address a link may have, checked: http or https with a host, mailto,
 * tel, or # and a bookmark's name; no white space or control character.
 *
 * @param mixed $value
 * @return string|null the address as written; null when it is not one
 */
function pg_office_docx_link_value($value)
{
    if (!is_string($value) || ($value === '') || (strlen($value) > PG_OFFICE_DOCX_LINK_MAX)) {
        return null;
    }

    if (!mb_check_encoding($value, 'UTF-8') || preg_match('/[\x00-\x20\x7F]|\xC2[\x80-\xA0]|\xE2\x80[\xA8\xA9]/', $value)) {
        return null;
    }

    if (preg_match('~^https?://([^/?#@]*@)?[^/?#@:]+(:[0-9]{1,5})?([/?#]|$)~i', $value)
        || preg_match('~^mailto:[^@?]+@.+$~i', $value)
        || preg_match('~^tel:\+?[0-9A-Za-z().\-]+$~i', $value)
        || preg_match('~^#[\p{L}_][\p{L}\p{N}_]{0,39}$~u', $value)) {
        return $value;
    }

    return null;
}

/**
 * The address of the link a run stands in, as the editor's marks give it:
 * an external target (with its anchor) or # and a bookmark's name; '' when
 * the run is in no link, or in one whose address is not one a link may
 * have here.
 *
 * @param DOMElement $run
 * @param array      $rels the main part's relationships (pg_office_rels())
 * @return string
 */
function pg_office_docx_run_link($run, $rels)
{
    $link = $run->parentNode;

    if (!($link instanceof DOMElement) || ($link->localName !== 'hyperlink')) {
        return '';
    }

    $id = pg_office_attr($link, 'id');
    $anchor = pg_office_attr($link, 'anchor');
    $address = '';

    if ($id !== '') {
        $rel = isset($rels[$id]) ? $rels[$id] : null;

        if (!$rel || !$rel['external'] || ($rel['type'] !== 'hyperlink')) {
            return '';
        }

        $address = $rel['target'] . (($anchor !== '') ? '#' . $anchor : '');
    } elseif ($anchor !== '') {
        $address = '#' . $anchor;
    }

    return (pg_office_docx_link_value($address) !== null) ? $address : '';
}

/**
 * What a save needs to write links: the main part's relationships and the
 * ones added.
 *
 * @param string|null $xml    word/_rels/document.xml.rels; null when the file has none
 * @param bool        $strict whether the document is a strict one
 * @return array|null strict, dom (the relationships read), ids (taken), added (id => target);
 *                    null when the part cannot be read
 */
function pg_office_docx_link_context($xml, $strict = false)
{
    $dom = new DOMDocument('1.0', 'UTF-8');

    if ($xml === null) {
        $dom->loadXML('<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"/>', LIBXML_NONET);
    } else {
        if (stripos($xml, '<!DOCTYPE') !== false) {
            return null;
        }

        $previous = libxml_use_internal_errors(true);
        $loaded = $dom->loadXML($xml, LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (!$loaded || ($dom->documentElement === null) || ($dom->documentElement->localName !== 'Relationships')) {
            return null;
        }
    }

    $ids = array();

    foreach ($dom->documentElement->childNodes as $relationship) {
        if (($relationship instanceof DOMElement) && ($relationship->localName === 'Relationship')) {
            $ids[(string) $relationship->getAttribute('Id')] = true;
        }
    }

    return array('strict' => (bool) $strict, 'dom' => $dom, 'ids' => $ids, 'added' => array());
}

/**
 * The relationship id a link to an external address is written with: one
 * this save added for the same address, else a new one.
 *
 * @param array  $context pg_office_docx_link_context()
 * @param string $target
 * @return string
 */
function pg_office_docx_link_rel(&$context, $target)
{
    $found = array_search($target, $context['added'], true);

    if ($found !== false) {
        return (string) $found;
    }

    return pg_office_docx_rel_add($context, pg_office_docx_link_type($context['strict']), $target, true);
}

/**
 * Adds a relationship to the main part's, under an id none has, and lists
 * it among the added ones so the part is written. A link, a picture and a
 * part a paragraph's kind adds (numbering, styles) all go through here, so
 * one save never gives two of them one id or writes the part twice.
 *
 * @param array  $context  pg_office_docx_link_context()
 * @param string $type     the relationship's type
 * @param string $target
 * @param bool   $external whether it leaves the package
 * @return string the new relationship's id
 */
function pg_office_docx_rel_add(&$context, $type, $target, $external = false)
{
    $number = count($context['ids']) + 1;

    while (isset($context['ids']['rId' . $number])) {
        $number++;
    }

    $id = 'rId' . $number;
    $root = $context['dom']->documentElement;
    $relationship = $context['dom']->createElementNS((string) $root->namespaceURI, 'Relationship');
    $relationship->setAttribute('Id', $id);
    $relationship->setAttribute('Type', $type);
    $relationship->setAttribute('Target', $target);

    if ($external) {
        $relationship->setAttribute('TargetMode', 'External');
    }

    $root->appendChild($relationship);

    $context['ids'][$id] = true;
    $context['added'][$id] = $target;

    return $id;
}

/**
 * The main part's relationships with the ones a save added, as text; null
 * when none was added.
 *
 * @param array|null $context
 * @return string|null
 */
function pg_office_docx_link_rels_xml($context)
{
    if (!is_array($context) || empty($context['added'])) {
        return null;
    }

    $xml = $context['dom']->saveXML();

    return is_string($xml) ? $xml : null;
}

/**
 * Takes the look of a link's character style (Hyperlink, FollowedHyperlink)
 * off a run that leaves its link, as a word processor's "Remove link" does.
 *
 * @param DOMElement $run
 */
function pg_office_docx_link_style_off($run)
{
    $properties = pg_office_docx_child($run, 'rPr');

    if ($properties === null) {
        return;
    }

    $style = pg_office_docx_child($properties, 'rStyle');

    if (($style !== null) && in_array(strtolower(pg_office_attr($style, 'val')), array('hyperlink', 'followedhyperlink'), true)) {
        $properties->removeChild($style);

        if (!$properties->hasChildNodes()) {
            $run->removeChild($properties);
        }
    }
}

/**
 * The runs of an editable paragraph with text and the point of the text
 * each starts at.
 *
 * @param DOMElement $paragraph
 * @return array list of [run, start, end]
 */
function pg_office_docx_link_runs($paragraph)
{
    $out = array();
    $offset = 0;

    foreach (pg_office_docx_paragraph_edit($paragraph)['runs'] as $run) {
        $length = mb_strlen($run['text'], 'UTF-8');

        if ($length > 0) {
            $out[] = array($run['run'], $offset, $offset + $length);
        }

        $offset += $length;
    }

    return $out;
}

/**
 * Takes a stretch of a paragraph's text out of every link it reaches: the
 * part of a link before the stretch and the part after it stay links (the
 * second a copy of the link), the runs inside go back into the paragraph.
 * The runs are split at the stretch's ends already.
 *
 * @param DOMElement       $paragraph
 * @param int              $start
 * @param int              $end
 * @param SplObjectStorage $reached the runs taken out are added
 */
function pg_office_docx_link_unwrap($paragraph, $start, $end, $reached)
{
    $inside = new SplObjectStorage();

    foreach (pg_office_docx_link_runs($paragraph) as $item) {
        if (($item[1] >= $start) && ($item[2] <= $end)) {
            $inside[$item[0]] = true;
        }
    }

    foreach (iterator_to_array($paragraph->childNodes) as $link) {
        if (!($link instanceof DOMElement) || ($link->localName !== 'hyperlink')) {
            continue;
        }

        $groups = array('before' => array(), 'inside' => array(), 'after' => array());
        $state = 'before';

        foreach (iterator_to_array($link->childNodes) as $child) {
            if (($child instanceof DOMElement) && ($child->localName === 'r') && (pg_office_docx_run_child_text_length($child) > 0)) {
                if (isset($inside[$child])) {
                    $state = 'inside';
                } elseif ($state === 'inside') {
                    $state = 'after';
                }
            }

            $groups[$state][] = $child;
        }

        if (empty($groups['inside'])) {
            continue;
        }

        foreach (array('before', 'inside', 'after') as $name) {
            $nodes = $groups[$name];
            $has_run = false;

            foreach ($nodes as $node) {
                $has_run = $has_run || (($node instanceof DOMElement) && ($node->localName === 'r'));
            }

            if (($name !== 'inside') && $has_run) {
                $copy = $link->cloneNode(false);

                foreach ($nodes as $node) {
                    $copy->appendChild($node);
                }

                $paragraph->insertBefore($copy, $link);
                continue;
            }

            foreach ($nodes as $node) {
                $paragraph->insertBefore($node, $link);

                if (($node instanceof DOMElement) && ($node->localName === 'r')) {
                    pg_office_docx_link_style_off($node);
                    $reached[$node] = true;
                }
            }
        }

        $paragraph->removeChild($link);
    }
}

/**
 * How many characters of the paragraph's text a run holds.
 *
 * @param DOMElement $run
 * @return int
 */
function pg_office_docx_run_child_text_length($run)
{
    $length = 0;

    foreach ($run->childNodes as $child) {
        if ($child instanceof DOMElement) {
            $length += pg_office_docx_run_child_length($child);
        }
    }

    return $length;
}

/**
 * Puts a stretch of an editable paragraph's text into a link to an address,
 * or takes it out of its links when the address is false. The runs are
 * split at the stretch's ends already (pg_office_docx_paragraph_format()).
 *
 * @param DOMElement       $paragraph
 * @param int              $start
 * @param int              $end
 * @param string|false     $address pg_office_docx_link_value(); false takes the links off
 * @param array|null       $context pg_office_docx_link_context(); made empty when null
 * @param SplObjectStorage $reached the runs the change reached are added
 */
function pg_office_docx_paragraph_link($paragraph, $start, $end, $address, &$context, $reached)
{
    pg_office_docx_link_unwrap($paragraph, $start, $end, $reached);

    if ($address === false) {
        return;
    }

    $first = null;
    $last = null;

    foreach (pg_office_docx_link_runs($paragraph) as $item) {
        if (($item[1] >= $start) && ($item[2] <= $end)) {
            $first = ($first === null) ? $item[0] : $first;
            $last = $item[0];
        }
    }

    if (($first === null) || ($first->parentNode !== $paragraph) || ($last->parentNode !== $paragraph)) {
        return;
    }

    $document = $paragraph->ownerDocument;
    $namespace = (string) $paragraph->namespaceURI;
    $prefix = ((string) $paragraph->prefix !== '') ? $paragraph->prefix . ':' : '';
    $link = $document->createElementNS($namespace, $prefix . 'hyperlink');

    if ($address[0] === '#') {
        $link->setAttributeNS($namespace, $prefix . 'anchor', substr($address, 1));
    } else {
        if ($context === null) {
            $context = pg_office_docx_link_context(null, $namespace === 'http://purl.oclc.org/ooxml/wordprocessingml/main');
        }

        $relationships = pg_office_docx_link_namespace($context['strict']);
        $root = $document->documentElement;
        $bound = $root->lookupPrefix($relationships);
        $link->setAttributeNS($relationships, (($bound !== null) && ($bound !== '') ? $bound : 'r') . ':id', pg_office_docx_link_rel($context, $address));
        $link->setAttributeNS($namespace, $prefix . 'history', '1');
    }

    $paragraph->insertBefore($link, $first);

    // The runs and marks from the first run to the last go into the link;
    // a link between them holding no text gives up what it holds.
    $node = $first;

    while ($node !== null) {
        $next = $node->nextSibling;

        if (($node instanceof DOMElement) && ($node->localName === 'hyperlink')) {
            foreach (iterator_to_array($node->childNodes) as $child) {
                $link->appendChild($child);
            }

            $paragraph->removeChild($node);
        } else {
            $link->appendChild($node);
        }

        if (($node instanceof DOMElement) && ($node->localName === 'r')) {
            $reached[$node] = true;
        }

        if ($node === $last) {
            break;
        }

        $node = $next;
    }
}
