<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Word tables put in, and their rows and columns put in or deleted, in the
 * file itself (the file window and a channel's preview send them with a
 * save's paragraphs, through pg_office_docx_save()).
 *
 * A new table goes right after a paragraph: up to 20 rows by 10 columns,
 * its columns sharing the width of the text, drawn with the document's
 * table style when it has one, else with thin borders. A row or a column is
 * put in beside the cell of a named paragraph, taking that row's or
 * column's look, or deleted with what it holds. A table whose cells are
 * merged or uneven keeps its rows and columns: a row or column of it is
 * not the same cells from one end to the other. A table in a content
 * control or holding tracked changes is not changed at all.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

define('PG_OFFICE_DOCX_TABLE_MAX_ROWS', 20);
define('PG_OFFICE_DOCX_TABLE_MAX_COLUMNS', 10);

// Word's own limit of columns in a table.
define('PG_OFFICE_DOCX_TABLE_WIDEST', 63);

/**
 * The changes a save can make to a table a paragraph is in.
 *
 * @return string[]
 */
function pg_office_docx_table_actions()
{
    return array('row_before', 'row_after', 'column_before', 'column_after', 'row_delete', 'column_delete', 'delete');
}

/**
 * The paragraphs asked for in cells, checked: each cell a list of
 * paragraph entries as an added paragraph takes them (text, format, kind,
 * align), or a plain text.
 *
 * @param mixed $cells list of cells
 * @param int   $count how many cells there are
 * @param int   $total paragraphs counted so far, against the save's limit
 * @return array|string list of $count cells, each a list of paragraph items (empty: the cell left empty); or the refusal's code
 */
function pg_office_docx_table_cells_request($cells, $count, &$total)
{
    if ($cells === null) {
        $cells = array();
    }

    if (!is_array($cells) || (count($cells) > $count) || (array_values($cells) !== $cells)) {
        return 'none';
    }

    $out = array();

    for ($index = 0; $index < $count; $index++) {
        $cell = isset($cells[$index]) ? $cells[$index] : array();

        if (is_string($cell)) {
            $cell = array(array('text' => $cell));
        }

        if (!is_array($cell) || (array_values($cell) !== $cell)) {
            return 'none';
        }

        $items = array();

        foreach ($cell as $change) {
            if (!is_array($change)) {
                return 'none';
            }

            if (++$total > PG_OFFICE_EDIT_MAX_PARAGRAPHS) {
                return 'many';
            }

            $item = pg_office_docx_change_parts($change, true);

            if (is_string($item)) {
                return $item;
            }

            $items[] = $item;
        }

        $out[] = $items;
    }

    return $out;
}

/**
 * A new table a save asks for, checked: {rows, columns, cells?}, cells
 * being rows of cells (pg_office_docx_table_cells_request()).
 *
 * @param mixed $table
 * @return array|string rows, columns, cells ([row][column] => items); or the refusal's code
 */
function pg_office_docx_table_request($table)
{
    if (!is_array($table) || !isset($table['rows'], $table['columns']) || !is_scalar($table['rows']) || !is_scalar($table['columns'])
        || !ctype_digit((string) $table['rows']) || !ctype_digit((string) $table['columns'])) {
        return 'none';
    }

    $rows = (int) $table['rows'];
    $columns = (int) $table['columns'];

    if (($rows < 1) || ($rows > PG_OFFICE_DOCX_TABLE_MAX_ROWS) || ($columns < 1) || ($columns > PG_OFFICE_DOCX_TABLE_MAX_COLUMNS)) {
        return 'table_size';
    }

    $given = isset($table['cells']) ? $table['cells'] : array();

    if (!is_array($given) || (count($given) > $rows) || (array_values($given) !== $given)) {
        return 'none';
    }

    $cells = array();
    $total = 0;

    for ($row = 0; $row < $rows; $row++) {
        $line = pg_office_docx_table_cells_request(isset($given[$row]) ? $given[$row] : array(), $columns, $total);

        if (is_string($line)) {
            return $line;
        }

        $cells[] = $line;
    }

    return array('rows' => $rows, 'columns' => $columns, 'cells' => $cells);
}

/**
 * A change to the table a paragraph is in, checked: {cell: the paragraph's
 * position, table: an action, cells?: the new row's cells by column, or the
 * new column's by row}.
 *
 * @param array $change
 * @return array|string cell, action, cells (null when the action puts nothing in); or the refusal's code
 */
function pg_office_docx_table_change_request($change)
{
    if (!is_scalar($change['cell']) || !ctype_digit((string) $change['cell']) || !is_string($change['table'])
        || !in_array($change['table'], pg_office_docx_table_actions(), true)) {
        return 'none';
    }

    $cells = null;

    if (in_array($change['table'], array('row_before', 'row_after', 'column_before', 'column_after'), true)) {
        $given = isset($change['cells']) ? $change['cells'] : array();
        $total = 0;
        $cells = pg_office_docx_table_cells_request($given, is_array($given) ? count($given) : 0, $total);

        if (is_string($cells)) {
            return $cells;
        }
    }

    return array('cell' => (int) $change['cell'], 'action' => $change['table'], 'cells' => $cells);
}

/**
 * The element children of a node with a local name.
 *
 * @param DOMElement $node
 * @param string     $local
 * @return DOMElement[]
 */
function pg_office_docx_table_children($node, $local)
{
    $out = array();

    foreach ($node->childNodes as $child) {
        if (($child instanceof DOMElement) && ($child->localName === $local)) {
            $out[] = $child;
        }
    }

    return $out;
}

/**
 * A new element of the main part's namespace, with attributes of it.
 *
 * @param DOMElement $near  any element of the main part
 * @param string     $local
 * @param array      $attributes local name => value
 * @return DOMElement
 */
function pg_office_docx_table_element($near, $local, $attributes = array())
{
    $namespace = (string) $near->namespaceURI;
    $prefix = ((string) $near->prefix !== '') ? $near->prefix . ':' : '';
    $element = $near->ownerDocument->createElementNS($namespace, $prefix . $local);

    foreach ($attributes as $name => $value) {
        $element->setAttributeNS($namespace, $prefix . $name, (string) $value);
    }

    return $element;
}

/**
 * The table style a new table takes: the document's "Table Grid" when its
 * styles hold it, else the style the document's first styled table uses;
 * '' when there is none (the table then gets thin borders of its own).
 *
 * @param ZipArchive  $zip
 * @param DOMDocument $document the main part
 * @return string the style's id, or ''
 */
function pg_office_docx_table_style($zip, $document)
{
    $part = '';

    foreach (pg_office_rels($zip, 'word/_rels/document.xml.rels') as $relationship) {
        if (!$relationship['external'] && ($relationship['type'] === 'styles')) {
            $part = pg_office_part_name('word/', $relationship['target']);
        }
    }

    $styles = ($part !== '') ? pg_office_xml($zip, $part, $status) : null;

    if ($styles === null) {
        return '';
    }

    // Table styles by id, the document's default table style left out: it
    // is what a table without a style already has.
    $tables = array();

    foreach ($styles->getElementsByTagNameNS('*', 'style') as $style) {
        if ((pg_office_attr($style, 'type') !== 'table') || in_array(strtolower(pg_office_attr($style, 'default')), array('1', 'true', 'on'), true)) {
            continue;
        }

        $id = pg_office_attr($style, 'styleId');
        $name = pg_office_docx_child($style, 'name');

        if ($id !== '') {
            $tables[$id] = ($name !== null) ? strtolower(pg_office_attr($name, 'val')) : '';
        }
    }

    foreach ($tables as $id => $name) {
        if ($name === 'table grid') {
            return (string) $id;
        }
    }

    foreach ($document->getElementsByTagNameNS('*', 'tblStyle') as $used) {
        $id = pg_office_attr($used, 'val');

        if (isset($tables[$id])) {
            return $id;
        }
    }

    return '';
}

/**
 * How wide the text is where a table goes after a paragraph, in twips:
 * the cell's own width inside a table cell, else the page's width less its
 * side margins in the section the paragraph is in.
 *
 * @param DOMElement $paragraph
 * @return int
 */
function pg_office_docx_table_text_width($paragraph)
{
    // A cell's width less Word's default cell margins (108 twips a side).
    $parent = $paragraph->parentNode;

    if (($parent instanceof DOMElement) && ($parent->localName === 'tc')) {
        $properties = pg_office_docx_child($parent, 'tcPr');
        $width = ($properties !== null) ? pg_office_docx_child($properties, 'tcW') : null;

        if (($width !== null) && in_array(pg_office_attr($width, 'type'), array('', 'dxa'), true) && ((int) pg_office_attr($width, 'w') > 1000)) {
            return (int) pg_office_attr($width, 'w') - 216;
        }

        return 4000;
    }

    // The section a paragraph is in ends at the next paragraph holding a
    // section break, or with the body's own.
    $section = null;
    $paragraphs = $paragraph->ownerDocument->getElementsByTagNameNS((string) $paragraph->namespaceURI, 'p');
    $past = false;

    foreach ($paragraphs as $node) {
        $past = $past || $node->isSameNode($paragraph);

        if ($past && (($properties = pg_office_docx_child($node, 'pPr')) !== null) && (($found = pg_office_docx_child($properties, 'sectPr')) !== null)) {
            $section = $found;
            break;
        }
    }

    if ($section === null) {
        $body = $paragraph->ownerDocument->documentElement ? pg_office_docx_child($paragraph->ownerDocument->documentElement, 'body') : null;
        $section = ($body !== null) ? pg_office_docx_child($body, 'sectPr') : null;
    }

    $size = ($section !== null) ? pg_office_docx_child($section, 'pgSz') : null;
    $margins = ($section !== null) ? pg_office_docx_child($section, 'pgMar') : null;

    if (($size === null) || ($margins === null)) {
        return 9026;
    }

    $side = function ($names) use ($margins) {
        foreach ($names as $name) {
            if (pg_office_attr($margins, $name) !== '') {
                return (int) pg_office_attr($margins, $name);
            }
        }

        return 1440;
    };

    $width = (int) pg_office_attr($size, 'w') - $side(array('left', 'start')) - $side(array('right', 'end')) - (int) pg_office_attr($margins, 'gutter');

    return ($width > 1000) ? $width : 9026;
}

/**
 * A new, empty table: its columns sharing the width, each cell one empty
 * paragraph, the style given or else thin single borders.
 *
 * @param DOMElement $near    an element of the main part
 * @param int        $rows
 * @param int        $columns
 * @param string     $style   a table style's id, or ''
 * @param int        $width   the width the columns share, in twips
 * @return DOMElement not yet placed
 */
function pg_office_docx_table_new($near, $rows, $columns, $style, $width)
{
    $strict = ((string) $near->namespaceURI === 'http://purl.oclc.org/ooxml/wordprocessingml/main');
    $table = pg_office_docx_table_element($near, 'tbl');
    $properties = $table->appendChild(pg_office_docx_table_element($near, 'tblPr'));

    // In CT_TblPr order: style, width, borders, look.
    if ($style !== '') {
        $properties->appendChild(pg_office_docx_table_element($near, 'tblStyle', array('val' => $style)));
    }

    $properties->appendChild(pg_office_docx_table_element($near, 'tblW', array('w' => 0, 'type' => 'auto')));

    if ($style === '') {
        $borders = $properties->appendChild(pg_office_docx_table_element($near, 'tblBorders'));

        foreach (array('top', $strict ? 'start' : 'left', 'bottom', $strict ? 'end' : 'right', 'insideH', 'insideV') as $side) {
            $borders->appendChild(pg_office_docx_table_element($near, $side, array('val' => 'single', 'sz' => 4, 'space' => 0, 'color' => 'auto')));
        }
    } else {
        // The style's header row and first column, banded rows: what Word
        // gives a table it puts in.
        $look = array('firstRow' => 1, 'lastRow' => 0, 'firstColumn' => 1, 'lastColumn' => 0, 'noHBand' => 0, 'noVBand' => 1);
        $properties->appendChild(pg_office_docx_table_element($near, 'tblLook', ($strict ? array() : array('val' => '04A0')) + $look));
    }

    $column = max(1, (int) floor($width / $columns));
    $grid = $table->appendChild(pg_office_docx_table_element($near, 'tblGrid'));

    for ($index = 0; $index < $columns; $index++) {
        $grid->appendChild(pg_office_docx_table_element($near, 'gridCol', array('w' => $column)));
    }

    for ($row = 0; $row < $rows; $row++) {
        $line = $table->appendChild(pg_office_docx_table_element($near, 'tr'));

        for ($index = 0; $index < $columns; $index++) {
            $cell = $line->appendChild(pg_office_docx_table_element($near, 'tc'));
            $cell_properties = $cell->appendChild(pg_office_docx_table_element($near, 'tcPr'));
            $cell_properties->appendChild(pg_office_docx_table_element($near, 'tcW', array('w' => $column, 'type' => 'dxa')));
            $cell->appendChild(pg_office_docx_table_element($near, 'p'));
        }
    }

    return $table;
}

/**
 * Makes sure a paragraph follows a table: a table cell and the body each
 * end with a paragraph, and two tables one right after the other are read
 * by a word processor as one.
 *
 * @param DOMElement $table
 * @return bool whether a paragraph was added
 */
function pg_office_docx_table_close($table)
{
    for ($node = $table->nextSibling; $node !== null; $node = $node->nextSibling) {
        if (($node instanceof DOMElement) && in_array($node->localName, array('p', 'tbl', 'sdt', 'customXml', 'altChunk', 'sectPr'), true)) {
            if ($node->localName === 'p') {
                return false;
            }

            break;
        }
    }

    $table->parentNode->insertBefore(pg_office_docx_table_element($table, 'p'), $table->nextSibling);

    return true;
}

/**
 * Puts asked paragraphs into a new cell: the first into the cell's own
 * paragraph, the others after it, each with its text, format, alignment
 * and kind (pg_office_docx_paragraph_fill()).
 *
 * @param DOMElement $cell
 * @param array      $items pg_office_docx_change_parts() of each
 * @param array      $kinds list of [paragraph, kind], added to
 * @param array|null $links pg_office_docx_link_context()
 * @return string '' when written, else the refusal's code
 */
function pg_office_docx_table_cell_fill($cell, $items, &$kinds, &$links)
{
    $paragraph = null;

    foreach (pg_office_docx_table_children($cell, 'p') as $paragraph) {
        break;
    }

    foreach ($items as $index => $item) {
        if ($index > 0) {
            $paragraph = pg_office_docx_paragraph_add($paragraph, $paragraph);
        }

        $code = pg_office_docx_paragraph_fill($paragraph, $item, $kinds, $links);

        if ($code !== '') {
            return $code;
        }
    }

    return '';
}

/**
 * Puts a new table after a node, filled with the cells asked for.
 *
 * @param DOMElement $anchor the paragraph it is put after (its width)
 * @param DOMElement $after  the node it is placed right after
 * @param array      $table  pg_office_docx_table_request()
 * @param string     $style  pg_office_docx_table_style()
 * @param array      $kinds
 * @param array|null $links
 * @return DOMElement|string the table, or the refusal's code
 */
function pg_office_docx_table_add($anchor, $after, $table, $style, &$kinds, &$links)
{
    $element = pg_office_docx_table_new($anchor, $table['rows'], $table['columns'], $style, pg_office_docx_table_text_width($anchor));
    $after->parentNode->insertBefore($element, $after->nextSibling);

    foreach (pg_office_docx_table_children($element, 'tr') as $row => $line) {
        foreach (pg_office_docx_table_children($line, 'tc') as $column => $cell) {
            $code = pg_office_docx_table_cell_fill($cell, $table['cells'][$row][$column], $kinds, $links);

            if ($code !== '') {
                return $code;
            }
        }
    }

    return $element;
}

/**
 * The table, row and cell a paragraph is in: the nearest cell around it.
 *
 * @param DOMElement $paragraph
 * @return array|null [table, row, cell], or null outside a table; a cell not
 *                    held right in a row in a table gives a null table
 */
function pg_office_docx_table_of($paragraph)
{
    for ($node = $paragraph->parentNode; $node instanceof DOMElement; $node = $node->parentNode) {
        if ($node->localName !== 'tc') {
            continue;
        }

        $row = $node->parentNode;
        $table = ($row instanceof DOMElement) ? $row->parentNode : null;

        if (!($row instanceof DOMElement) || ($row->localName !== 'tr') || !($table instanceof DOMElement) || ($table->localName !== 'tbl')) {
            return array(null, null, $node);
        }

        return array($table, $row, $node);
    }

    return null;
}

/**
 * Why the rows and columns of a table cannot be changed here.
 *
 * @param DOMElement $table
 * @param bool       $whole only the table deleted: merged cells do not matter then
 * @return string '' when they may; locked (a tracked change, a row or cell
 *                held in a content control) or merged (merged or uneven cells)
 */
function pg_office_docx_table_refusal($table, $whole = false)
{
    foreach ($table->getElementsByTagNameNS('*', '*') as $node) {
        if (in_array($node->localName, array('ins', 'del', 'moveFrom', 'moveTo', 'cellIns', 'cellDel', 'cellMerge'), true)
            || (substr($node->localName, -6) === 'Change')) {
            return 'locked';
        }
    }

    $grid = pg_office_docx_child($table, 'tblGrid');
    $columns = ($grid !== null) ? count(pg_office_docx_table_children($grid, 'gridCol')) : 0;

    // Markers may stand between rows and between cells; anything else
    // (a content control or custom XML around a row or a cell) is not
    // changed here.
    $between = array('bookmarkStart', 'bookmarkEnd', 'proofErr', 'permStart', 'permEnd');

    foreach ($table->childNodes as $row) {
        if (!($row instanceof DOMElement) || in_array($row->localName, array_merge(array('tblPr', 'tblGrid'), $between), true)) {
            continue;
        }

        if ($row->localName !== 'tr') {
            return 'locked';
        }

        $cells = 0;

        foreach ($row->childNodes as $cell) {
            if (!($cell instanceof DOMElement) || in_array($cell->localName, array_merge(array('trPr', 'tblPrEx'), $between), true)) {
                continue;
            }

            if ($cell->localName !== 'tc') {
                return 'locked';
            }

            $cells++;
            $properties = pg_office_docx_child($cell, 'tcPr');

            if ($properties === null) {
                continue;
            }

            foreach ($properties->childNodes as $merge) {
                if (($merge instanceof DOMElement) && (in_array($merge->localName, array('vMerge', 'hMerge'), true)
                    || (($merge->localName === 'gridSpan') && ((int) pg_office_attr($merge, 'val') > 1)))) {
                    $cells = -1000;
                }
            }
        }

        $row_properties = pg_office_docx_child($row, 'trPr');

        if ($row_properties !== null) {
            foreach (array('gridBefore', 'gridAfter') as $local) {
                $skip = pg_office_docx_child($row_properties, $local);

                if (($skip !== null) && ((int) pg_office_attr($skip, 'val') > 0)) {
                    $cells = -1000;
                }
            }
        }

        if (!$whole && ($cells !== $columns)) {
            return 'merged';
        }
    }

    return (!$whole && ($columns === 0)) ? 'merged' : '';
}

/**
 * The cells of a table's row, in order.
 *
 * @param DOMElement $row
 * @return DOMElement[]
 */
function pg_office_docx_table_cells($row)
{
    return pg_office_docx_table_children($row, 'tc');
}

/**
 * A copy of a cell's properties for a new cell beside it or under it: the
 * width, shading, borders and margins kept; the cached look of the table
 * style's parts left out.
 *
 * @param DOMElement $cell
 * @return DOMElement the new cell, holding one paragraph with the kind of the cell's first one
 */
function pg_office_docx_table_cell_copy($cell)
{
    $copy = pg_office_docx_table_element($cell, 'tc');
    $properties = pg_office_docx_child($cell, 'tcPr');

    if ($properties !== null) {
        $properties = $copy->appendChild($properties->cloneNode(true));

        foreach (iterator_to_array($properties->childNodes) as $child) {
            if (($child instanceof DOMElement) && ($child->localName === 'cnfStyle')) {
                $properties->removeChild($child);
            }
        }
    }

    $first = null;

    foreach (pg_office_docx_table_children($cell, 'p') as $first) {
        break;
    }

    $paragraph = ($first !== null) ? pg_office_docx_paragraph_new($first) : pg_office_docx_table_element($cell, 'p');
    $paragraph_properties = pg_office_docx_child($paragraph, 'pPr');

    if ($paragraph_properties !== null) {
        foreach (iterator_to_array($paragraph_properties->childNodes) as $child) {
            if (($child instanceof DOMElement) && ($child->localName === 'cnfStyle')) {
                $paragraph_properties->removeChild($child);
            }
        }
    }

    $copy->appendChild($paragraph);

    return $copy;
}

/**
 * A new row with the look of another: its row properties (a header row's
 * mark and the cached style look left out) and a copy of each of its cells.
 *
 * @param DOMElement $row
 * @return DOMElement not yet placed
 */
function pg_office_docx_table_row_copy($row)
{
    $copy = pg_office_docx_table_element($row, 'tr');

    foreach ($row->childNodes as $child) {
        if (!($child instanceof DOMElement)) {
            continue;
        }

        if (in_array($child->localName, array('tblPrEx', 'trPr'), true)) {
            $properties = $copy->appendChild($child->cloneNode(true));

            foreach (iterator_to_array($properties->childNodes) as $setting) {
                if (($setting instanceof DOMElement) && in_array($setting->localName, array('tblHeader', 'cnfStyle'), true)) {
                    $properties->removeChild($setting);
                }
            }

            if (($child->localName === 'trPr') && !$properties->hasChildNodes()) {
                $copy->removeChild($properties);
            }
        } elseif ($child->localName === 'tc') {
            $copy->appendChild(pg_office_docx_table_cell_copy($child));
        }
    }

    return $copy;
}

/**
 * A width setting halved, or set, when it is written in twips.
 *
 * @param DOMElement|null $setting tcW or gridCol
 * @param int|null        $set     the new width; null to halve it
 * @return int the width it had (0 when not in twips)
 */
function pg_office_docx_table_width($setting, $set = null)
{
    if (($setting === null) || !in_array(pg_office_attr($setting, 'type'), array('', 'dxa'), true)) {
        return 0;
    }

    $width = (int) pg_office_attr($setting, 'w');
    $namespace = (string) $setting->namespaceURI;
    $prefix = ((string) $setting->prefix !== '') ? $setting->prefix . ':' : '';

    $setting->setAttributeNS($namespace, $prefix . 'w', (string) (($set === null) ? (int) floor($width / 2) : $set));

    return $width;
}

/**
 * The width setting of a cell (tcPr/tcW), when it has one.
 *
 * @param DOMElement $cell
 * @return DOMElement|null
 */
function pg_office_docx_table_cell_width($cell)
{
    $properties = pg_office_docx_child($cell, 'tcPr');

    return ($properties !== null) ? pg_office_docx_child($properties, 'tcW') : null;
}

/**
 * Puts a new column into a table beside a column, the two sharing that
 * column's width; each new cell a copy of the cell of its row it is put
 * beside.
 *
 * @param DOMElement $table
 * @param int        $source the column it is put beside, counted from 0
 * @param int        $at     where it goes: $source or $source + 1
 * @return DOMElement[] the new cells, by row
 */
function pg_office_docx_table_column_insert($table, $source, $at)
{
    $grid = pg_office_docx_child($table, 'tblGrid');
    $columns = pg_office_docx_table_children($grid, 'gridCol');
    $column = $columns[$source]->cloneNode(true);
    $width = pg_office_docx_table_width($columns[$source]);

    if ($width > 0) {
        pg_office_docx_table_width($column, $width - (int) floor($width / 2));
    }

    $grid->insertBefore($column, isset($columns[$at]) ? $columns[$at] : null);
    $made = array();

    foreach (pg_office_docx_table_children($table, 'tr') as $row) {
        $cells = pg_office_docx_table_cells($row);
        $cell = pg_office_docx_table_cell_copy($cells[$source]);
        $width = pg_office_docx_table_width(pg_office_docx_table_cell_width($cells[$source]));

        if ($width > 0) {
            pg_office_docx_table_width(pg_office_docx_table_cell_width($cell), $width - (int) floor($width / 2));
        }

        // Before the next cell, or after the row's last.
        $next = isset($cells[$at]) ? $cells[$at] : $cells[count($cells) - 1]->nextSibling;
        $row->insertBefore($cell, $next);
        $made[] = $cell;
    }

    return $made;
}

/**
 * Takes columns out of a table, each one's width given to the column
 * before it (the next one for the first column).
 *
 * @param DOMElement $table
 * @param int[]      $indexes columns counted from 0
 */
function pg_office_docx_table_columns_delete($table, $indexes)
{
    $indexes = array_values(array_unique($indexes));
    rsort($indexes);

    $grid = pg_office_docx_child($table, 'tblGrid');
    $rows = pg_office_docx_table_children($table, 'tr');

    foreach ($indexes as $index) {
        $columns = pg_office_docx_table_children($grid, 'gridCol');
        $neighbour = ($index > 0) ? $index - 1 : $index + 1;

        if (isset($columns[$neighbour])) {
            $width = (int) pg_office_attr($columns[$index], 'w');
            pg_office_docx_table_width($columns[$neighbour], (int) pg_office_attr($columns[$neighbour], 'w') + $width);
        }

        $grid->removeChild($columns[$index]);

        foreach ($rows as $row) {
            $cells = pg_office_docx_table_cells($row);
            $own = pg_office_docx_table_cell_width($cells[$index]);
            $other = isset($cells[$neighbour]) ? pg_office_docx_table_cell_width($cells[$neighbour]) : null;

            if (($own !== null) && ($other !== null) && (pg_office_attr($own, 'type') === pg_office_attr($other, 'type'))) {
                pg_office_docx_table_width($other, (int) pg_office_attr($other, 'w') + (int) pg_office_attr($own, 'w'));
            }

            $row->removeChild($cells[$index]);
        }
    }
}

/**
 * Why what is about to be deleted with a row, a column or a table may not
 * go: a picture, a field, a comment, a note, a content control or a
 * tracked change in it, as deleting a paragraph refuses them
 * (pg_office_docx_delete_refusal()).
 *
 * @param DOMElement[] $nodes
 * @return bool whether they may all go
 */
function pg_office_docx_table_removable($nodes)
{
    foreach ($nodes as $node) {
        foreach ($node->getElementsByTagNameNS('*', '*') as $inner) {
            if (($inner->localName === 'sectPr') || in_array(pg_office_docx_lock_reason($inner->localName), array('tracked', 'field', 'comment', 'note', 'control', 'object'), true)) {
                return false;
            }
        }
    }

    return true;
}

/**
 * The ends of the bookmarks and editing permissions in a document, by kind
 * and id.
 *
 * @param DOMDocument $document
 * @return array key => number of ends found (1 or 2)
 */
function pg_office_docx_table_ranges($document)
{
    $ends = array();

    foreach (array('bookmark', 'perm') as $marker) {
        foreach (array('Start', 'End') as $end) {
            foreach ($document->getElementsByTagNameNS('*', $marker . $end) as $node) {
                $ends[$marker . ':' . pg_office_attr($node, 'id')][$end] = true;
            }
        }
    }

    return array_map('count', $ends);
}

/**
 * Whether a node is still in its document.
 *
 * @param DOMNode $node
 * @return bool
 */
function pg_office_docx_table_attached($node)
{
    for (; $node !== null; $node = $node->parentNode) {
        if ($node instanceof DOMDocument) {
            return true;
        }
    }

    return false;
}

/**
 * Makes the asked changes to existing tables: rows and columns put in, in
 * the order asked, then rows, columns and tables deleted. Every table is
 * named by a paragraph of one of its cells, as the file was opened.
 *
 * @param DOMElement[] $paragraphs the document's paragraphs before anything was added
 * @param array        $changes    pg_office_docx_table_change_request() of each
 * @param array        $kinds      list of [paragraph, kind], added to
 * @param array|null   $links      pg_office_docx_link_context()
 * @return array ok, code, paragraph (counted from 1), changed (how many changes were made)
 */
function pg_office_docx_tables_change($paragraphs, $changes, &$kinds, &$links)
{
    $fail = function ($code, $position) {
        return array('ok' => false, 'code' => $code, 'paragraph' => (string) ($position + 1), 'changed' => 0);
    };

    // Every table named is found and checked before any is changed.
    $found = array();

    foreach ($changes as $index => $change) {
        $position = $change['cell'];

        if (!isset($paragraphs[$position])) {
            return $fail('missing', $position);
        }

        $place = pg_office_docx_table_of($paragraphs[$position]);

        if ($place === null) {
            return $fail('table_missing', $position);
        }

        $lock = pg_office_docx_place_lock($paragraphs[$position]);

        if (($place[0] === null) || ($lock === 'control') || ($lock === 'object')) {
            return $fail('table_locked', $position);
        }

        $refusal = pg_office_docx_table_refusal($place[0], $change['action'] === 'delete');

        if ($refusal === '' && ($lock === 'merged') && ($change['action'] !== 'delete')) {
            $refusal = 'merged';
        }

        if ($refusal !== '') {
            return $fail('table_' . $refusal, $position);
        }

        $found[$index] = $place;
    }

    $tails = new SplObjectStorage();
    $deletes = array();
    $changed = 0;

    foreach ($changes as $index => $change) {
        list($table, $row, $cell) = $found[$index];
        $action = $change['action'];

        if (in_array($action, array('row_delete', 'column_delete', 'delete'), true)) {
            $deletes[] = array($action, $table, $row, $cell, $change['cell']);
            continue;
        }

        if (!pg_office_docx_table_attached($table)) {
            continue;
        }

        if (($action === 'row_before') || ($action === 'row_after')) {
            $new = pg_office_docx_table_row_copy($row);

            // Several rows after one go in the order asked.
            $after = isset($tails[$row]) ? $tails[$row] : $row;
            $row->parentNode->insertBefore($new, ($action === 'row_after') ? $after->nextSibling : $row);

            if ($action === 'row_after') {
                $tails[$row] = $new;
            }

            $new_cells = pg_office_docx_table_cells($new);
        } else {
            $cells = pg_office_docx_table_cells($row);
            $after = isset($tails[$cell]) ? $tails[$cell] : $cell;
            $source = array_search($cell, $cells, true);
            $at = ($action === 'column_after') ? array_search($after, $cells, true) + 1 : $source;

            if (count($cells) + 1 > PG_OFFICE_DOCX_TABLE_WIDEST) {
                return $fail('table_wide', $change['cell']);
            }

            $new_cells = pg_office_docx_table_column_insert($table, $source, $at);

            if ($action === 'column_after') {
                $index_row = array_search($row, pg_office_docx_table_children($table, 'tr'), true);
                $tails[$cell] = $new_cells[$index_row];
            }
        }

        if (count($change['cells']) > count($new_cells)) {
            return $fail('none', $change['cell']);
        }

        foreach ($change['cells'] as $at_cell => $items) {
            $code = pg_office_docx_table_cell_fill($new_cells[$at_cell], $items, $kinds, $links);

            if ($code !== '') {
                return $fail($code, $change['cell']);
            }
        }

        $changed++;
    }

    if (empty($deletes)) {
        return array('ok' => true, 'code' => '', 'paragraph' => '', 'changed' => $changed);
    }

    // What goes is checked before anything goes; the columns of a table
    // are counted before any of them is taken out.
    $document = $paragraphs[$deletes[0][4]]->ownerDocument;
    $ranges = pg_office_docx_table_ranges($document);
    $plan = new SplObjectStorage();

    foreach ($deletes as $delete) {
        list($action, $table, $row, $cell, $position) = $delete;

        if ($action === 'delete') {
            $nodes = array($table);
        } elseif ($action === 'row_delete') {
            $nodes = array($row);
        } else {
            $column = array_search($cell, pg_office_docx_table_cells($row), true);
            $nodes = array();

            foreach (pg_office_docx_table_children($table, 'tr') as $line) {
                $cells = pg_office_docx_table_cells($line);
                $nodes[] = $cells[$column];
            }
        }

        if (!pg_office_docx_table_removable($nodes)) {
            return $fail('table_delete', $position);
        }

        $entry = isset($plan[$table]) ? $plan[$table] : array('whole' => false, 'rows' => array(), 'columns' => array());
        $entry['whole'] = $entry['whole'] || ($action === 'delete');

        if ($action === 'row_delete') {
            $entry['rows'][] = $row;
        } elseif ($action === 'column_delete') {
            $entry['columns'][] = $column;
        }

        $plan[$table] = $entry;
        $changed++;
    }

    foreach ($plan as $table) {
        $entry = $plan[$table];
        $rows = pg_office_docx_table_children($table, 'tr');
        $columns = count(pg_office_docx_table_cells($rows[0]));
        $rows_left = count($rows) - count(array_unique(array_map('spl_object_hash', $entry['rows'])));

        // A table left with no row or no column goes whole.
        if ($entry['whole'] || ($rows_left < 1) || (count(array_unique($entry['columns'])) >= $columns)) {
            if ($table->parentNode !== null) {
                $table->parentNode->removeChild($table);
            }

            continue;
        }

        foreach ($entry['rows'] as $row) {
            if ($row->parentNode !== null) {
                $row->parentNode->removeChild($row);
            }
        }

        if (!empty($entry['columns'])) {
            pg_office_docx_table_columns_delete($table, $entry['columns']);
        }
    }

    // A bookmark or permission range may go whole, never lose one end.
    foreach (pg_office_docx_table_ranges($document) as $key => $ends) {
        if (($ends === 1) && isset($ranges[$key]) && ($ranges[$key] === 2)) {
            return $fail('table_delete', $deletes[0][4]);
        }
    }

    return array('ok' => true, 'code' => '', 'paragraph' => '', 'changed' => $changed);
}
