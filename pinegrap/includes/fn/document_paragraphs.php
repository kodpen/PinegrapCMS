<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * A document turned into the paragraphs the designer's document reader
 * takes (assets/js/pg_document_reader.js, PGDocumentReader.read()): each
 * {text, style, level, lead, rows, src, alt}.
 *
 * A .docx is read here through the product's own Word reader
 * (includes/fn/office.php): its main part within PG_OFFICE_MAX_DOM, its
 * numbering, styles and relationships, headings by their built-in name.
 * Pictures are taken out into a list of their own; a picture paragraph's
 * src is "picture:<n>" until the picture is stored (pg_document_picture()
 * gives its bytes). A .md or .txt comes back as UTF-8 text, which the
 * browser turns into paragraphs with PGDocumentReader.paragraphs(), as it
 * does a paste, so the Markdown rules live in one place.
 *
 * Loaded by the places that need it, not by functions.php.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

// The designer's reader reads this many paragraphs; more are not sent.
define('PG_DOCUMENT_MAX_PARAGRAPHS', 2000);
// The bytes of a .md or .txt read, cut back to a line's end.
define('PG_DOCUMENT_MAX_TEXT', 1024 * 1024);
// The characters of a document's text read, as the preview's HTML limit.
define('PG_DOCUMENT_MAX_CHARS', 600000);

/**
 * The kind of document a name is: 'docx' for a Word document (and its
 * macro and template forms), 'text' for .md, .markdown and .txt, else ''.
 *
 * @param string $name
 * @return string docx | text | ''
 */
function pg_document_kind($name)
{
    $extension = strtolower((string) pathinfo((string) $name, PATHINFO_EXTENSION));

    if (in_array($extension, array('md', 'markdown', 'txt'), true)) {
        return 'text';
    }

    return (pg_office_kind($name) === 'docx') ? 'docx' : '';
}

/**
 * A document's paragraphs, or its text for a .md or .txt, with what was
 * left: `cut` '' (all read), 'size' (the file or its main part past its
 * limit) or 'paragraphs' (past PG_DOCUMENT_MAX_PARAGRAPHS or
 * PG_DOCUMENT_MAX_CHARS); `read` the paragraphs given and about how much
 * of the document they are, in percent.
 *
 * @param string $path the file on disk
 * @param string $name its name, for the kind
 * @return array ok, error, paragraphs, text, pictures, pictures_lost, cut, read
 */
function pg_document_paragraphs($path, $name)
{
    $out = array('ok' => false, 'error' => '', 'paragraphs' => array(), 'text' => '', 'pictures' => array(), 'pictures_lost' => 0, 'cut' => '', 'read' => array('paragraphs' => 0, 'percent' => 0));
    $kind = pg_document_kind($name);

    if ($kind === 'text') {
        return pg_document_text($path, $out);
    }

    if ($kind !== 'docx') {
        $out['error'] = lang('This kind of file cannot be read.');

        return $out;
    }

    $zip = pg_office_open($path, $error);

    if ($zip === null) {
        $out['error'] = $error;

        return $out;
    }

    $document = pg_office_xml($zip, 'word/document.xml', $status, true);
    $state = ($document !== null) ? pg_office_docx_state($zip, $refused) : null;

    if (($document === null) || ($refused !== '')) {
        $zip->close();
        $out['error'] = ($document === null) ? lang('The file could not be read.') : pg_office_refusal_error($refused);

        return $out;
    }

    $stat = $zip->statName('word/document.xml');
    $size = max(1, (int) $stat['size']);
    $state += array('out' => array(), 'chars' => 0, 'stop' => '', 'pictures' => array());
    $share = 1.0;

    foreach ($document->getElementsByTagNameNS('*', 'body') as $body) {
        $share = pg_document_docx_blocks($state, $body);
        break;
    }

    $zip->close();

    $out['ok'] = true;
    $out['paragraphs'] = $state['out'];
    $out['pictures'] = $state['pictures'];
    $out['pictures_lost'] = $state['images_lost'];
    $out['cut'] = ($status === 'cut') ? 'size' : $state['stop'];
    $out['read'] = array(
        'paragraphs' => count($state['out']),
        'percent'    => (int) floor(min(1.0, (($status === 'cut') ? PG_OFFICE_MAX_DOM / $size : 1.0)) * $share * 100),
    );

    return $out;
}

/**
 * A .md or .txt as UTF-8 text (a byte order mark left out, Windows-1254
 * converted), read to PG_DOCUMENT_MAX_TEXT and then back to a line's end.
 *
 * @param string $path
 * @param array  $out the answer to fill
 * @return array
 */
function pg_document_text($path, $out)
{
    $handle = @fopen($path, 'rb');
    $stat = ($handle === false) ? false : fstat($handle);
    $size = ($stat === false) ? 0 : (int) $stat['size'];

    if ($handle === false) {
        $out['error'] = lang('The file could not be read.');

        return $out;
    }

    $bytes = (string) fread($handle, PG_DOCUMENT_MAX_TEXT + 1);
    fclose($handle);

    if (strlen($bytes) > PG_DOCUMENT_MAX_TEXT) {
        $end = strrpos(substr($bytes, 0, PG_DOCUMENT_MAX_TEXT), "\n");
        $bytes = (string) substr($bytes, 0, ($end === false) ? PG_DOCUMENT_MAX_TEXT : $end + 1);
        $out['cut'] = 'size';
    }

    $out['ok'] = true;
    $out['text'] = pg_office_csv_text($bytes, pg_office_csv_encoding($bytes));
    $out['read']['percent'] = ($out['cut'] === '') ? 100 : (int) floor(strlen($bytes) * 100 / max(1, $size));

    return $out;
}

/**
 * The blocks of the body or a content control into $state['out'] until a
 * limit is met; the share of the container's blocks read (0 to 1).
 *
 * @param array   $state the Word reader's state, with out, chars, stop, pictures
 * @param DOMNode $container
 * @return float
 */
function pg_document_docx_blocks(&$state, $container)
{
    $blocks = array();

    foreach ($container->childNodes as $child) {
        if (($child instanceof DOMElement) && in_array($child->localName, array('p', 'tbl', 'sdt', 'sdtContent', 'customXml'), true)) {
            $blocks[] = $child;
        }
    }

    foreach ($blocks as $done => $child) {
        // The share of a block read when the limit stopped inside it: none
        // for a paragraph or a table (refused whole), the control's own
        // share for a content control.
        $inner = 0.0;

        if ($child->localName === 'p') {
            pg_document_docx_paragraph($state, $child);
        } elseif ($child->localName === 'tbl') {
            $rows = array();

            foreach (pg_office_docx_wrapped($child, 'tr') as $row) {
                $cells = array();

                foreach (pg_office_docx_wrapped($row, 'tc') as $cell) {
                    $texts = array();

                    foreach ($cell->getElementsByTagNameNS((string) $cell->namespaceURI, 'p') as $paragraph) {
                        $texts[] = pg_document_docx_text($state, $paragraph, $lead, false);
                    }

                    $cells[] = trim(implode(' ', $texts));
                }

                $rows[] = $cells;
            }

            pg_document_push($state, array('text' => '', 'style' => 'table', 'rows' => $rows), strlen(implode(' ', array_map('implode', $rows))));
        } else {
            $inner = pg_document_docx_blocks($state, $child);
        }

        if ($state['stop'] !== '') {
            return ($done + (($inner < 1.0) ? $inner : 0.0)) / count($blocks);
        }
    }

    return 1.0;
}

/**
 * One paragraph: its text with its style (heading and level, list item,
 * quote, else paragraph) and bold lead; its pictures after it, one
 * picture paragraph each. An empty paragraph gives nothing.
 *
 * @param array      $state
 * @param DOMElement $paragraph
 */
function pg_document_docx_paragraph(&$state, $paragraph)
{
    $pictures = array();
    $text = pg_document_docx_text($state, $paragraph, $lead, true, $pictures);

    if ($text !== '') {
        $props = pg_office_docx_paragraph_props($paragraph);
        $list = ($props['list'] === '') ? ($state['styles'][$props['style']]['list'] ?? '') : $props['list'];
        $tag = isset($state['heads'][$props['style']]) ? 'h' . min(6, $state['heads'][$props['style']]) : pg_office_docx_block_tag($props['style']);
        $entry = array('text' => $text, 'style' => 'paragraph');

        if (($list !== '') && ($list !== '0')) {
            $entry['style'] = 'item';
        } elseif ($tag[0] === 'h') {
            $entry['style'] = 'heading';
            $entry['level'] = (int) substr($tag, 1);
        } elseif ($tag === 'blockquote') {
            $entry['style'] = 'quote';
        }

        if (($lead !== '') && ($lead !== $text) && ($entry['style'] !== 'heading')) {
            $entry['lead'] = $lead;
        }

        pg_document_push($state, $entry, strlen($text));
    }

    foreach ($pictures as $picture) {
        pg_document_push($state, $picture, 0);
    }
}

/**
 * The plain text of a paragraph (deleted tracked text left out, breaks and
 * tabs as spaces, white space folded), the bold words it starts with in
 * $lead, and, when asked, its pictures as picture paragraphs.
 *
 * @param array      $state
 * @param DOMElement $paragraph
 * @param string     $lead set to the bold words the text starts with
 * @param bool       $take  take the pictures out
 * @param array      $pictures the picture paragraphs found
 * @return string
 */
function pg_document_docx_text(&$state, $paragraph, &$lead, $take, &$pictures = array())
{
    $raw = '';
    $bold_end = 0;
    $leading = true;

    foreach ($paragraph->getElementsByTagNameNS((string) $paragraph->namespaceURI, 'r') as $run) {
        // Runs of deleted or moved-away text stay out, as in the preview,
        // and so do those of a text box inside a drawing.
        for ($up = $run->parentNode; ($up !== null) && ($up !== $paragraph); $up = $up->parentNode) {
            if (in_array($up->localName, array('del', 'moveFrom', 'drawing', 'pict', 'object'), true)) {
                continue 2;
            }
        }

        $piece = '';
        $bold = false;

        foreach ($run->childNodes as $child) {
            if (!($child instanceof DOMElement)) {
                continue;
            }

            if ($child->localName === 't') {
                $piece .= $child->textContent;
            } elseif (in_array($child->localName, array('tab', 'br', 'cr'), true)) {
                $piece .= ' ';
            } elseif ($child->localName === 'rPr') {
                foreach ($child->childNodes as $property) {
                    if (($property instanceof DOMElement) && ($property->localName === 'b')) {
                        $bold = !in_array(strtolower(pg_office_attr($property, 'val')), array('0', 'false', 'off', 'none'), true);
                    }
                }
            } elseif ($take && in_array($child->localName, array('drawing', 'pict', 'object'), true)) {
                $picture = pg_document_docx_picture($state, $child);

                if ($picture !== null) {
                    $pictures[] = $picture;
                }
            }
        }

        if ($leading && (trim($piece) !== '')) {
            $leading = $bold;
        }

        $raw .= $piece;

        if ($leading) {
            $bold_end = strlen($raw);
        }
    }

    $fold = function ($text) {
        return trim((string) preg_replace('/\s+/u', ' ', $text));
    };
    $lead = $fold(substr($raw, 0, $bold_end));

    return $fold($raw);
}

/**
 * A drawing's picture taken out: listed in $state['pictures'] with its part,
 * name, type, size and description, and given back as a picture paragraph;
 * null for none, or for one of a kind a browser does not show or past the
 * preview's picture limits (counted in images_lost).
 *
 * @param array      $state
 * @param DOMElement $node
 * @return array|null
 */
function pg_document_docx_picture(&$state, $node)
{
    $rel_id = '';

    foreach ($node->getElementsByTagNameNS('*', 'blip') as $blip) {
        $rel_id = pg_office_attr($blip, 'embed');
    }

    foreach ($node->getElementsByTagNameNS('*', 'imagedata') as $image) {
        $rel_id = ($rel_id === '') ? pg_office_attr($image, 'id') : $rel_id;
    }

    $rel = $state['rels'][$rel_id] ?? null;

    if (($rel === null) || $rel['external']) {
        return null;
    }

    $part = pg_office_part_name('word/', $rel['target']);
    $mime = pg_document_picture_mime($part);
    $stat = ($mime !== '') ? $state['zip']->statName($part) : false;

    if (($stat === false) || ((int) $stat['size'] > PG_OFFICE_MAX_IMAGE) || (($state['images'] + (int) $stat['size']) > PG_OFFICE_MAX_IMAGES)) {
        $state['images_lost']++;

        return null;
    }

    $alt = '';

    foreach ($node->getElementsByTagNameNS('*', 'docPr') as $pr) {
        $alt = trim((string) $pr->getAttribute('descr'));
    }

    $state['images'] += (int) $stat['size'];
    $index = count($state['pictures']);
    $state['pictures'][] = array('part' => $part, 'name' => pg_basename($part), 'mime' => $mime, 'size' => (int) $stat['size'], 'alt' => $alt);

    return array('text' => '', 'style' => 'picture', 'src' => 'picture:' . $index, 'alt' => $alt, 'picture' => $index);
}

/**
 * The type of a picture part a browser shows, by its extension; '' else.
 *
 * @param string $part
 * @return string
 */
function pg_document_picture_mime($part)
{
    $mimes = array('png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'gif' => 'image/gif', 'webp' => 'image/webp');

    return $mimes[strtolower((string) pathinfo((string) $part, PATHINFO_EXTENSION))] ?? '';
}

/**
 * One paragraph into the answer, or the stop reason once the paragraph or
 * character limit is met.
 *
 * @param array $state
 * @param array $entry
 * @param int   $chars
 */
function pg_document_push(&$state, $entry, $chars)
{
    if ((count($state['out']) >= PG_DOCUMENT_MAX_PARAGRAPHS) || (($state['chars'] + $chars) > PG_DOCUMENT_MAX_CHARS)) {
        $state['stop'] = 'paragraphs';

        return;
    }

    $state['out'][] = $entry;
    $state['chars'] += $chars;
}

/**
 * A picture taken out of a document: its bytes, type and name, read from
 * the package's word/media folder within PG_OFFICE_MAX_IMAGE; null when
 * the part is elsewhere, missing, too large or not a picture.
 *
 * @param string $path the document on disk
 * @param string $part a part named in pg_document_paragraphs()'s pictures
 * @return array|null bytes, mime, name
 */
function pg_document_picture($path, $part)
{
    $part = (string) $part;
    $mime = pg_document_picture_mime($part);

    if (($mime === '') || !preg_match('#^word/media/[^/\\\\]+$#', $part) || (strpos($part, '..') !== false)) {
        return null;
    }

    $zip = pg_office_open($path, $error);

    if ($zip === null) {
        return null;
    }

    $bytes = pg_zip_entry_contents($zip, $part, PG_OFFICE_MAX_IMAGE, $unread);
    $zip->close();

    if (($bytes === null) || ($bytes === '') || (@getimagesizefromstring($bytes) === false)) {
        return null;
    }

    return array('bytes' => $bytes, 'mime' => $mime, 'name' => pg_basename($part));
}
