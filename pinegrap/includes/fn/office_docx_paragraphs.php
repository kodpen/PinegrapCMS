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
 * @return string '' when it may; section, picture (one that is not a plain picture), locked, cell, last or bookmark
 */
function pg_office_docx_delete_refusal($paragraph)
{
    // A section break is the paragraph's own: its pPr holds the layout of
    // the section it ends.
    $properties = pg_office_docx_child($paragraph, 'pPr');

    if (($properties !== null) && (pg_office_docx_child($properties, 'sectPr') !== null)) {
        return 'section';
    }

    // Plain pictures go with their paragraph; a text box, chart, shape,
    // legacy picture or embedded object keeps it
    // (includes/fn/office_docx_picture.php).
    $drawings = pg_office_docx_picture_drawings($paragraph);

    if ($drawings === null) {
        return 'picture';
    }

    if (pg_office_docx_picture_lock($paragraph, $drawings) !== '') {
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
 * A new, empty paragraph with the paragraph properties of another: its
 * style, numbering, alignment and the formatting of its mark. Not copied:
 * the section break, a page break before, and tracked changes of the
 * properties.
 *
 * @param DOMElement $anchor the paragraph whose kind it takes
 * @return DOMElement not yet placed
 */
function pg_office_docx_paragraph_new($anchor)
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

    return $paragraph;
}

/**
 * A new, empty paragraph placed right after another node, with the
 * paragraph properties of the paragraph it follows
 * (pg_office_docx_paragraph_new()).
 *
 * @param DOMElement $anchor the paragraph whose kind it takes
 * @param DOMElement $after  the node it is placed after (the anchor, or a paragraph or table added after it)
 * @return DOMElement
 */
function pg_office_docx_paragraph_add($anchor, $after)
{
    $paragraph = pg_office_docx_paragraph_new($anchor);
    $after->parentNode->insertBefore($paragraph, $after->nextSibling);

    return $paragraph;
}

/**
 * Gives a new paragraph its asked text, format and alignment, and queues
 * its kind.
 *
 * @param DOMElement $paragraph
 * @param array      $item  text, format, kind, align (pg_office_docx_change_parts())
 * @param array      $kinds list of [paragraph, kind], added to
 * @param array|null $links pg_office_docx_link_context(), for links in its format
 * @return string '' when written, else the refusal's code
 */
function pg_office_docx_paragraph_fill($paragraph, $item, &$kinds, &$links)
{
    if (($item['text'] !== null) && ($item['text'] !== '')) {
        pg_office_docx_paragraph_write($paragraph, pg_office_docx_paragraph_edit($paragraph), $item['text']);
    }

    if ($item['format'] !== null) {
        $code = pg_office_docx_paragraph_format($paragraph, $item['format'], $links);

        if ($code !== '') {
            return $code;
        }
    }

    if (isset($item['align'])) {
        pg_office_docx_paragraph_align_set($paragraph, $item['align']);
    }

    if ($item['kind'] !== null) {
        $kinds[] = array($paragraph, $item['kind']);
    }

    return '';
}

/**
 * Adds the asked paragraphs, tables and pictures into a document being
 * saved, each paragraph with its text, format, kind and alignment as a
 * changed paragraph has them, each table with its cells
 * (includes/fn/office_docx_table.php), each picture as a paragraph of its
 * own (includes/fn/office_docx_picture.php).
 *
 * @param DOMElement[] $paragraphs the document's paragraphs before anything was added
 * @param array        $added      list of [after, text, format, kind, align], of [after, table, style] for a table,
 *                                 or of [after, picture] for a picture, in the order they read after the same paragraph
 * @param array        $kinds      list of [paragraph, kind] the added ones' kinds are put on
 * @param array|null   $links      pg_office_docx_link_context(), for links in their formats and pictures' relationships
 * @param array|null   $pictures   pg_office_docx_picture_context(), when a picture is asked
 * @return array ok, code, paragraph (counted from 1), added (the new paragraphs and tables)
 */
function pg_office_docx_paragraphs_add($paragraphs, $added, &$kinds, &$links = null, &$pictures = null)
{
    $tails = array();
    $made = array();
    $tables = array();

    foreach ($added as $item) {
        $position = $item['after'];

        if (!isset($paragraphs[$position])) {
            return array('ok' => false, 'code' => 'missing', 'paragraph' => (string) ($position + 1), 'added' => $made);
        }

        $anchor = $paragraphs[$position];

        if (!pg_office_docx_add_allowed($anchor)) {
            return array('ok' => false, 'code' => 'add_locked', 'paragraph' => (string) ($position + 1), 'added' => $made);
        }

        $after = isset($tails[$position]) ? $tails[$position] : $anchor;

        if (isset($item['picture'])) {
            $element = pg_office_docx_picture_add($anchor, $after, $item['picture'], $pictures, $links);

            if (is_string($element)) {
                return array('ok' => false, 'code' => $element, 'paragraph' => (string) ($position + 1), 'added' => $made);
            }

            $tails[$position] = $element;
            $made[] = $element;
            continue;
        }

        if (isset($item['table'])) {
            $element = pg_office_docx_table_add($anchor, $after, $item['table'], $item['style'], $kinds, $links);

            if (is_string($element)) {
                return array('ok' => false, 'code' => $element, 'paragraph' => (string) ($position + 1), 'added' => $made);
            }

            $tails[$position] = $element;
            $made[] = $element;
            $tables[] = $element;
            continue;
        }

        $paragraph = pg_office_docx_paragraph_add($anchor, $after);
        $tails[$position] = $paragraph;
        $made[] = $paragraph;
        $code = pg_office_docx_paragraph_fill($paragraph, $item, $kinds, $links);

        if ($code !== '') {
            return array('ok' => false, 'code' => $code, 'paragraph' => (string) ($position + 1), 'added' => $made);
        }
    }

    // Once everything is in place, a paragraph follows each new table.
    foreach ($tables as $table) {
        pg_office_docx_table_close($table);
    }

    return array('ok' => true, 'code' => '', 'paragraph' => '', 'added' => $made);
}

/**
 * Deletes the asked paragraphs, from the last one up, each checked as the
 * document stands then: deleting a cell's last two paragraphs stops at the
 * last. A paragraph already gone with a deleted row, column or table is
 * passed over.
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

        // Gone already with a row, a column or a table deleted.
        if (!pg_office_docx_table_attached($paragraphs[$position])) {
            continue;
        }

        $refusal = pg_office_docx_delete_refusal($paragraphs[$position]);

        if ($refusal !== '') {
            return array('ok' => false, 'code' => ($refusal === 'locked') ? 'locked' : 'delete_' . $refusal, 'paragraph' => (string) ($position + 1));
        }

        $paragraphs[$position]->parentNode->removeChild($paragraphs[$position]);
    }

    return array('ok' => true, 'code' => '', 'paragraph' => '');
}
