<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Word paragraphs added and deleted in the file itself (the file window and
 * a channel's preview send them with a save's paragraphs, through
 * pg_office_docx_save()).
 *
 * A new paragraph goes right after the one it is added after and takes its
 * paragraph properties, so it has the same kind: a list item continues its
 * list, a heading stays a heading. A section break, a page break before and
 * a tracked change of those properties are not copied. A paragraph is
 * deleted whole; one that holds a picture, ends a section, is the last of a
 * table cell or of the document, holds more than text or one end of a
 * bookmark reaching elsewhere is refused with its reason, as deleting it
 * would lose what it holds or leave the file damaged.
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
 * Why a paragraph cannot be deleted here, as it stands in the document.
 *
 * @param DOMElement $paragraph
 * @return string '' when it may; section, picture, locked, cell, last or bookmark
 */
function pg_office_docx_delete_refusal($paragraph)
{
    // A section break is the paragraph's own: its pPr holds the layout of
    // the section it ends.
    $properties = pg_office_docx_child($paragraph, 'pPr');

    if (($properties !== null) && (pg_office_docx_child($properties, 'sectPr') !== null)) {
        return 'section';
    }

    foreach (array('drawing', 'pict', 'object') as $local) {
        if ($paragraph->getElementsByTagNameNS('*', $local)->length > 0) {
            return 'picture';
        }
    }

    $edit = pg_office_docx_paragraph_edit($paragraph);

    if ($edit['lock'] !== '') {
        return 'locked';
    }

    // A table cell and the body each end with a paragraph (a cell's end
    // mark, the document's last one); none may be left without it.
    $parent = $paragraph->parentNode;
    $last = true;

    for ($node = $paragraph->nextSibling; $node !== null; $node = $node->nextSibling) {
        if (($node instanceof DOMElement) && in_array($node->localName, array('p', 'tbl', 'sdt', 'customXml', 'altChunk'), true)) {
            $last = false;
            break;
        }
    }

    if ($last && ($parent instanceof DOMElement)) {
        return ($parent->localName === 'tc') ? 'cell' : 'last';
    }

    // A bookmark or an editing permission that starts here and ends in
    // another paragraph, or the other way round, would lose one end.
    $open = array();

    foreach (array('bookmark', 'perm') as $marker) {
        foreach (array('Start' => 1, 'End' => -1) as $end => $step) {
            foreach ($paragraph->getElementsByTagNameNS('*', $marker . $end) as $node) {
                $key = $marker . ':' . pg_office_attr($node, 'id');
                $open[$key] = (isset($open[$key]) ? $open[$key] : 0) + $step;
            }
        }
    }

    foreach ($open as $balance) {
        if ($balance !== 0) {
            return 'bookmark';
        }
    }

    return '';
}

/**
 * Whether a paragraph may be added after this one: not inside a content
 * control or custom XML, a text box or a cell hidden under merged cells,
 * and not after a paragraph mark that is a tracked change.
 *
 * @param DOMElement $paragraph
 * @return bool
 */
function pg_office_docx_add_allowed($paragraph)
{
    if (pg_office_docx_place_lock($paragraph) !== '') {
        return false;
    }

    $properties = pg_office_docx_child($paragraph, 'pPr');

    return ($properties === null) || !pg_office_docx_tracked_props($properties);
}

/**
 * A new, empty paragraph placed right after another node, with the
 * paragraph properties of the paragraph it follows: its style, numbering,
 * alignment and the formatting of its mark. Not copied: the section break,
 * a page break before, and tracked changes of the properties.
 *
 * @param DOMElement $anchor the paragraph whose kind it takes
 * @param DOMElement $after  the node it is placed after (the anchor, or a paragraph added after it)
 * @return DOMElement
 */
function pg_office_docx_paragraph_add($anchor, $after)
{
    $document = $anchor->ownerDocument;
    $prefix = ((string) $anchor->prefix !== '') ? $anchor->prefix . ':' : '';
    $paragraph = $document->createElementNS((string) $anchor->namespaceURI, $prefix . 'p');
    $properties = pg_office_docx_child($anchor, 'pPr');

    if ($properties !== null) {
        $copy = $properties->cloneNode(true);

        foreach (iterator_to_array($copy->childNodes) as $child) {
            if (($child instanceof DOMElement) && in_array($child->localName, array('sectPr', 'pPrChange', 'pageBreakBefore'), true)) {
                $copy->removeChild($child);
            }
        }

        $mark = pg_office_docx_child($copy, 'rPr');

        if ($mark !== null) {
            foreach (iterator_to_array($mark->childNodes) as $child) {
                if (($child instanceof DOMElement) && in_array($child->localName, array('ins', 'del', 'moveFrom', 'moveTo', 'rPrChange'), true)) {
                    $mark->removeChild($child);
                }
            }
        }

        if ($copy->hasChildNodes()) {
            $paragraph->appendChild($copy);
        }
    }

    $after->parentNode->insertBefore($paragraph, $after->nextSibling);

    return $paragraph;
}

/**
 * Adds the asked paragraphs into a document being saved, each with its
 * text, format, kind and alignment as a changed paragraph has them.
 *
 * @param DOMElement[] $paragraphs the document's paragraphs before anything was added
 * @param array        $added      list of [after, text, format, kind, align], in the order they read after the same paragraph
 * @param array        $kinds      list of [paragraph, kind] the added ones' kinds are put on
 * @param array|null   $links      pg_office_docx_link_context(), for links in their formats
 * @return array ok, code, paragraph (counted from 1), added (the new paragraphs)
 */
function pg_office_docx_paragraphs_add($paragraphs, $added, &$kinds, &$links = null)
{
    $tails = array();
    $made = array();

    foreach ($added as $item) {
        $position = $item['after'];

        if (!isset($paragraphs[$position])) {
            return array('ok' => false, 'code' => 'missing', 'paragraph' => (string) ($position + 1), 'added' => $made);
        }

        $anchor = $paragraphs[$position];

        if (!pg_office_docx_add_allowed($anchor)) {
            return array('ok' => false, 'code' => 'add_locked', 'paragraph' => (string) ($position + 1), 'added' => $made);
        }

        $paragraph = pg_office_docx_paragraph_add($anchor, isset($tails[$position]) ? $tails[$position] : $anchor);
        $tails[$position] = $paragraph;
        $made[] = $paragraph;

        if (($item['text'] !== null) && ($item['text'] !== '')) {
            pg_office_docx_paragraph_write($paragraph, pg_office_docx_paragraph_edit($paragraph), $item['text']);
        }

        if ($item['format'] !== null) {
            $code = pg_office_docx_paragraph_format($paragraph, $item['format'], $links);

            if ($code !== '') {
                return array('ok' => false, 'code' => $code, 'paragraph' => (string) ($position + 1), 'added' => $made);
            }
        }

        if (isset($item['align'])) {
            pg_office_docx_paragraph_align_set($paragraph, $item['align']);
        }

        if ($item['kind'] !== null) {
            $kinds[] = array($paragraph, $item['kind']);
        }
    }

    return array('ok' => true, 'code' => '', 'paragraph' => '', 'added' => $made);
}

/**
 * Deletes the asked paragraphs, from the last one up, each checked as the
 * document stands then: deleting a cell's last two paragraphs stops at the
 * last.
 *
 * @param DOMElement[] $paragraphs the document's paragraphs before anything was added
 * @param int[]        $positions
 * @return array ok, code, paragraph (counted from 1)
 */
function pg_office_docx_paragraphs_delete($paragraphs, $positions)
{
    rsort($positions);

    foreach ($positions as $position) {
        if (!isset($paragraphs[$position])) {
            return array('ok' => false, 'code' => 'missing', 'paragraph' => (string) ($position + 1));
        }

        $refusal = pg_office_docx_delete_refusal($paragraphs[$position]);

        if ($refusal !== '') {
            return array('ok' => false, 'code' => ($refusal === 'locked') ? 'locked' : 'delete_' . $refusal, 'paragraph' => (string) ($position + 1));
        }

        $paragraphs[$position]->parentNode->removeChild($paragraphs[$position]);
    }

    return array('ok' => true, 'code' => '', 'paragraph' => '');
}
