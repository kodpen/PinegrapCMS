<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * A picture put into a Word document after a paragraph, as a paragraph of
 * its own, in the file itself (the file window and a channel's preview
 * send it with a save's paragraphs, through pg_office_docx_save()).
 *
 * A PNG, JPEG or GIF goes into the package as a part of its own under
 * word/media, named by a relationship of the main part, its extension given
 * a content type when the package has none for it, and is drawn inline at
 * its own size (its pixels at 96 per inch), made smaller to the width of
 * the text where it is wider. A picture the preview would not show (over
 * PG_OFFICE_MAX_IMAGE, or the document's pictures together over
 * PG_OFFICE_MAX_IMAGES) is refused.
 *
 * A paragraph whose only drawings are plain pictures may be deleted like a
 * paragraph of text; a text box, a chart, a shape, a legacy (VML) picture
 * or an embedded object keeps its paragraph.
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
 * The widest or tallest picture taken, in pixels.
 */
define('PG_OFFICE_DOCX_PICTURE_MAX_SIDE', 20000);

/**
 * The longest description (alternative text) a picture keeps, in characters.
 */
define('PG_OFFICE_DOCX_PICTURE_MAX_NAME', 255);

/**
 * English Metric Units in a pixel at 96 per inch, and in a twip.
 */
define('PG_OFFICE_DOCX_PICTURE_EMU_PIXEL', 9525);
define('PG_OFFICE_DOCX_PICTURE_EMU_TWIP', 635);

/**
 * The kinds of picture taken: extension of the part => [content type, getimagesize() type].
 *
 * @return array
 */
function pg_office_docx_picture_kinds()
{
    return array(
        'png'  => array('image/png', IMAGETYPE_PNG),
        'jpeg' => array('image/jpeg', IMAGETYPE_JPEG),
        'gif'  => array('image/gif', IMAGETYPE_GIF),
    );
}

/**
 * The kind of a picture by its first bytes; '' when it is none taken.
 *
 * @param string $bytes
 * @return string png, jpeg, gif or ''
 */
function pg_office_docx_picture_sniff($bytes)
{
    if (strncmp($bytes, "\x89PNG\r\n\x1a\n", 8) === 0) {
        return 'png';
    }

    if (strncmp($bytes, "\xFF\xD8\xFF", 3) === 0) {
        return 'jpeg';
    }

    if ((strncmp($bytes, 'GIF87a', 6) === 0) || (strncmp($bytes, 'GIF89a', 6) === 0)) {
        return 'gif';
    }

    return '';
}

/**
 * A picture asked by a save, checked: [data => the picture's bytes in
 * base64, name => its description, optional].
 *
 * @param mixed $picture
 * @return array|string bytes, kind (png, jpeg, gif), width, height (pixels), name; or the refusal's code
 */
function pg_office_docx_picture_request($picture)
{
    if (!is_array($picture) || !isset($picture['data']) || !is_string($picture['data'])) {
        return 'none';
    }

    // Base64 is a third larger than what it holds.
    if (strlen($picture['data']) > (int) ceil(PG_OFFICE_MAX_IMAGE * 4 / 3) + 4) {
        return 'picture_large';
    }

    $bytes = base64_decode(preg_replace('/\s+/', '', $picture['data']), true);

    if (($bytes === false) || ($bytes === '')) {
        return 'picture_type';
    }

    if (strlen($bytes) > PG_OFFICE_MAX_IMAGE) {
        return 'picture_large';
    }

    $kind = pg_office_docx_picture_sniff($bytes);
    $kinds = pg_office_docx_picture_kinds();
    $size = ($kind !== '') ? @getimagesizefromstring($bytes) : false;

    if (($size === false) || ((int) $size[2] !== $kinds[$kind][1])) {
        return 'picture_type';
    }

    $width = (int) $size[0];
    $height = (int) $size[1];

    if (($width < 1) || ($height < 1) || ($width > PG_OFFICE_DOCX_PICTURE_MAX_SIDE) || ($height > PG_OFFICE_DOCX_PICTURE_MAX_SIDE)) {
        return 'picture_type';
    }

    $name = '';

    if (isset($picture['name'])) {
        if (!is_string($picture['name'])) {
            return 'none';
        }

        $name = pg_office_xlsx_clean_text($picture['name']);

        if ($name === null) {
            return 'text';
        }

        $name = mb_substr(trim(preg_replace('/\s+/u', ' ', $name)), 0, PG_OFFICE_DOCX_PICTURE_MAX_NAME, 'UTF-8');
    }

    return array('bytes' => $bytes, 'kind' => $kind, 'width' => $width, 'height' => $height, 'name' => $name);
}

/**
 * What a save putting pictures in works with: the media parts' names taken,
 * the size of the pictures the document holds, the next drawing id.
 *
 * @param ZipArchive  $zip
 * @param DOMDocument $document the main part
 * @return array strict, names (lower case => true), size, id, parts (part name => bytes), shared (digest => relationship id)
 */
function pg_office_docx_picture_context($zip, $document)
{
    $names = array();
    $size = 0;
    $media = array('png', 'jpg', 'jpeg', 'gif', 'bmp', 'webp');

    for ($index = 0; $index < $zip->numFiles; $index++) {
        $stat = $zip->statIndex($index);

        if ($stat === false) {
            continue;
        }

        $name = strtolower((string) $stat['name']);
        $names[$name] = true;

        // The pictures the preview shows count against its limit together.
        if ((strpos($name, 'word/media/') === 0) && in_array(pathinfo($name, PATHINFO_EXTENSION), $media, true)) {
            $size += (int) $stat['size'];
        }
    }

    // A drawing's id is unique in the document.
    $id = 0;

    foreach ($document->getElementsByTagNameNS('*', 'docPr') as $properties) {
        $id = max($id, (int) $properties->getAttribute('id'));
    }

    return array(
        'strict' => ($document->documentElement->namespaceURI === 'http://purl.oclc.org/ooxml/wordprocessingml/main'),
        'names' => $names,
        'size' => $size,
        'id' => $id,
        'parts' => array(),
        'shared' => array(),
    );
}

/**
 * The namespaces a picture is written in, in a transitional and in a strict
 * document.
 *
 * @param bool $strict
 * @return array wp, a, pic, r, uri (the graphic's kind), type (the relationship's)
 */
function pg_office_docx_picture_namespaces($strict)
{
    if ($strict) {
        return array(
            'wp' => 'http://purl.oclc.org/ooxml/drawingml/wordprocessingDrawing',
            'a' => 'http://purl.oclc.org/ooxml/drawingml/main',
            'pic' => 'http://purl.oclc.org/ooxml/drawingml/picture',
            'r' => pg_office_docx_link_namespace(true),
            'uri' => 'http://purl.oclc.org/ooxml/drawingml/picture',
            'type' => 'http://purl.oclc.org/ooxml/officeDocument/relationships/image',
        );
    }

    return array(
        'wp' => 'http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing',
        'a' => 'http://schemas.openxmlformats.org/drawingml/2006/main',
        'pic' => 'http://schemas.openxmlformats.org/drawingml/2006/picture',
        'r' => pg_office_docx_link_namespace(false),
        'uri' => 'http://schemas.openxmlformats.org/drawingml/2006/picture',
        'type' => 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/image',
    );
}

/**
 * The relationship a picture is written with: its part put among the ones
 * the save writes, under word/media with a name no entry has. The same
 * picture asked twice in a save is one part.
 *
 * @param array $context pg_office_docx_picture_context()
 * @param array $links   pg_office_docx_link_context(): the relationship goes there
 * @param array $picture pg_office_docx_picture_request()
 * @return string the relationship's id
 */
function pg_office_docx_picture_rel(&$context, &$links, $picture)
{
    $digest = sha1($picture['bytes']);

    if (isset($context['shared'][$digest])) {
        return $context['shared'][$digest];
    }

    $number = 1;

    while (isset($context['names']['word/media/image' . $number . '.' . $picture['kind']])) {
        $number++;
    }

    $part = 'word/media/image' . $number . '.' . $picture['kind'];
    $context['names'][$part] = true;
    $context['parts'][$part] = $picture['bytes'];

    // Listed with the links' so the relationships part is written once; a
    // link's address always has a scheme, so no link takes this one.
    $id = pg_office_docx_rel_add($links, pg_office_docx_picture_namespaces($context['strict'])['type'], substr($part, strlen('word/')));
    $context['shared'][$digest] = $id;

    return $id;
}

/**
 * The size a picture is drawn at, in EMU: its pixels at 96 per inch, made
 * smaller (keeping its shape) to the given width.
 *
 * @param int $width  pixels
 * @param int $height pixels
 * @param int $widest twips
 * @return int[] width, height
 */
function pg_office_docx_picture_extent($width, $height, $widest)
{
    $cx = $width * PG_OFFICE_DOCX_PICTURE_EMU_PIXEL;
    $cy = $height * PG_OFFICE_DOCX_PICTURE_EMU_PIXEL;
    $most = max(1, $widest) * PG_OFFICE_DOCX_PICTURE_EMU_TWIP;

    if ($cx > $most) {
        $cy = max(1, (int) round($cy * $most / $cx));
        $cx = $most;
    }

    return array($cx, $cy);
}

/**
 * A paragraph holding a picture inline, as Word writes one; the namespaces
 * the drawing needs are declared on the paragraph.
 *
 * @param DOMElement $near   an element of the main part
 * @param string     $align  the paragraph's alignment (jc), '' for none
 * @param string     $rel_id the picture part's relationship
 * @param int        $id     the drawing's id in the document
 * @param int[]      $extent width, height in EMU
 * @param string     $name   the picture's description
 * @param bool       $strict
 * @return DOMElement not yet placed
 */
function pg_office_docx_picture_paragraph($near, $align, $rel_id, $id, $extent, $name, $strict)
{
    $ns = pg_office_docx_picture_namespaces($strict);
    $text = function ($value) {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    };
    $title = 'Picture ' . (int) $id;
    $alt = ($name !== '') ? ' descr="' . $text($name) . '"' : '';
    $size = 'cx="' . (int) $extent[0] . '" cy="' . (int) $extent[1] . '"';

    $xml = '<w:p xmlns:w="' . $text($near->namespaceURI) . '" xmlns:wp="' . $ns['wp'] . '" xmlns:a="' . $ns['a'] . '" xmlns:pic="' . $ns['pic'] . '" xmlns:r="' . $ns['r'] . '">'
        . (($align !== '') ? '<w:pPr><w:jc w:val="' . $text($align) . '"/></w:pPr>' : '')
        . '<w:r><w:drawing><wp:inline distT="0" distB="0" distL="0" distR="0">'
        . '<wp:extent ' . $size . '/><wp:effectExtent l="0" t="0" r="0" b="0"/>'
        . '<wp:docPr id="' . (int) $id . '" name="' . $title . '"' . $alt . '/>'
        . '<wp:cNvGraphicFramePr><a:graphicFrameLocks noChangeAspect="1"/></wp:cNvGraphicFramePr>'
        . '<a:graphic><a:graphicData uri="' . $ns['uri'] . '"><pic:pic>'
        . '<pic:nvPicPr><pic:cNvPr id="0" name="' . $title . '"' . $alt . '/><pic:cNvPicPr/></pic:nvPicPr>'
        . '<pic:blipFill><a:blip r:embed="' . $text($rel_id) . '"/><a:stretch><a:fillRect/></a:stretch></pic:blipFill>'
        . '<pic:spPr><a:xfrm><a:off x="0" y="0"/><a:ext ' . $size . '/></a:xfrm><a:prstGeom prst="rect"><a:avLst/></a:prstGeom></pic:spPr>'
        . '</pic:pic></a:graphicData></a:graphic></wp:inline></w:drawing></w:r></w:p>';

    $fragment = new DOMDocument('1.0', 'UTF-8');
    $fragment->loadXML($xml, LIBXML_NONET);

    return $near->ownerDocument->importNode($fragment->documentElement, true);
}

/**
 * Puts a picture in after a paragraph (or what was added after it) as a
 * paragraph of its own, which takes only the alignment of the paragraph it
 * follows: a heading's or a list's look is not a picture's.
 *
 * @param DOMElement $anchor  the paragraph it goes after
 * @param DOMElement $after   the node it is placed after (the anchor, or what was added after it)
 * @param array      $picture pg_office_docx_picture_request()
 * @param array      $context pg_office_docx_picture_context()
 * @param array      $links   pg_office_docx_link_context()
 * @return DOMElement|string the new paragraph, or the refusal's code
 */
function pg_office_docx_picture_add($anchor, $after, $picture, &$context, &$links)
{
    // The same picture again is the same part: it adds nothing.
    if (!isset($context['shared'][sha1($picture['bytes'])])) {
        if ($context['size'] + strlen($picture['bytes']) > PG_OFFICE_MAX_IMAGES) {
            return 'picture_room';
        }

        $context['size'] += strlen($picture['bytes']);
    }

    $rel_id = pg_office_docx_picture_rel($context, $links, $picture);
    $context['id']++;

    $properties = pg_office_docx_child($anchor, 'pPr');
    $align = ($properties !== null) ? pg_office_docx_child($properties, 'jc') : null;
    $align = ($align !== null) ? pg_office_attr($align, 'val') : '';
    $extent = pg_office_docx_picture_extent($picture['width'], $picture['height'], pg_office_docx_table_text_width($anchor));
    $paragraph = pg_office_docx_picture_paragraph($anchor, $align, $rel_id, $context['id'], $extent, $picture['name'], $context['strict']);
    $after->parentNode->insertBefore($paragraph, $after->nextSibling);

    return $paragraph;
}

/**
 * The parts a save putting pictures in writes beside the main part and its
 * relationships: each picture, and the content types when an extension
 * had none. Null when the content types cannot be read.
 *
 * @param ZipArchive  $zip
 * @param array       $context pg_office_docx_picture_context()
 * @param string|null $types   the content types as the save already changed them (a part a paragraph's kind added); null to read the package's
 * @return array|null part name => its bytes
 */
function pg_office_docx_picture_parts($zip, $context, $types = null)
{
    if (empty($context['parts'])) {
        return array();
    }

    $xml = ($types !== null) ? $types : pg_zip_entry_contents($zip, '[Content_Types].xml', PG_OFFICE_EDIT_MAX_DOCUMENT, $unread);

    if (($xml === null) || (stripos($xml, '<!DOCTYPE') !== false)) {
        return null;
    }

    $types = new DOMDocument('1.0', 'UTF-8');
    $previous = libxml_use_internal_errors(true);
    $loaded = $types->loadXML($xml, LIBXML_NONET);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);

    if (!$loaded || ($types->documentElement === null) || ($types->documentElement->localName !== 'Types')) {
        return null;
    }

    $root = $types->documentElement;
    $defaults = array();

    foreach ($root->childNodes as $child) {
        if (($child instanceof DOMElement) && ($child->localName === 'Default')) {
            $defaults[strtolower($child->getAttribute('Extension'))] = strtolower($child->getAttribute('ContentType'));
        }
    }

    $kinds = pg_office_docx_picture_kinds();
    $changed = false;

    foreach (array_keys($context['parts']) as $part) {
        $extension = pathinfo($part, PATHINFO_EXTENSION);
        $type = $kinds[$extension][0];

        if (!isset($defaults[$extension])) {
            // Defaults come before overrides, as Word writes them.
            $default = $types->createElementNS((string) $root->namespaceURI, 'Default');
            $default->setAttribute('Extension', $extension);
            $default->setAttribute('ContentType', $type);
            $first = null;

            foreach ($root->childNodes as $child) {
                if (($child instanceof DOMElement) && ($child->localName === 'Override')) {
                    $first = $child;
                    break;
                }
            }

            $root->insertBefore($default, $first);
            $defaults[$extension] = $type;
            $changed = true;
        } elseif ($defaults[$extension] !== $type) {
            // An extension given another type keeps it; this part says its own.
            $override = $types->createElementNS((string) $root->namespaceURI, 'Override');
            $override->setAttribute('PartName', '/' . $part);
            $override->setAttribute('ContentType', $type);
            $root->appendChild($override);
            $changed = true;
        }
    }

    $parts = $context['parts'];

    if ($changed) {
        $xml = $types->saveXML();

        if (!is_string($xml)) {
            return null;
        }

        $parts['[Content_Types].xml'] = $xml;
    }

    return $parts;
}

/**
 * Whether a drawing is a plain picture: an inline or anchored picture and
 * nothing else (no text box, chart, shape or group).
 *
 * @param DOMElement $drawing w:drawing
 * @return bool
 */
function pg_office_docx_picture_plain($drawing)
{
    $graphics = $drawing->getElementsByTagNameNS('*', 'graphicData');

    if ($graphics->length !== 1) {
        return false;
    }

    $count = 0;

    foreach ($graphics->item(0)->childNodes as $child) {
        if (!($child instanceof DOMElement)) {
            continue;
        }

        if ($child->localName !== 'pic') {
            return false;
        }

        $count++;
    }

    return ($count === 1) && ($drawing->getElementsByTagNameNS('*', 'txbxContent')->length === 0);
}

/**
 * The plain picture drawings of a paragraph, each directly in a run; null
 * when it holds a drawing of another kind, a legacy picture or an embedded
 * object, or a drawing outside a run.
 *
 * @param DOMElement $paragraph
 * @return DOMElement[]|null
 */
function pg_office_docx_picture_drawings($paragraph)
{
    foreach (array('pict', 'object') as $local) {
        if ($paragraph->getElementsByTagNameNS('*', $local)->length > 0) {
            return null;
        }
    }

    $drawings = array();

    foreach ($paragraph->getElementsByTagNameNS('*', 'drawing') as $drawing) {
        $run = $drawing->parentNode;

        if (!($run instanceof DOMElement) || ($run->localName !== 'r') || !pg_office_docx_picture_plain($drawing)) {
            return null;
        }

        $drawings[] = $drawing;
    }

    return $drawings;
}

/**
 * Why a paragraph keeps its content, its plain pictures set aside: the lock
 * pg_office_docx_paragraph_edit() gives the paragraph without them. It is
 * looked at on a copy, so the document is not touched.
 *
 * @param DOMElement   $paragraph
 * @param DOMElement[] $drawings  pg_office_docx_picture_drawings()
 * @return string '' when nothing else keeps it
 */
function pg_office_docx_picture_lock($paragraph, $drawings)
{
    if (empty($drawings)) {
        return pg_office_docx_paragraph_edit($paragraph)['lock'];
    }

    // Where it stands (a content control, a text box, a merged cell).
    $lock = pg_office_docx_place_lock($paragraph);

    if ($lock !== '') {
        return $lock;
    }

    $copy = $paragraph->cloneNode(true);

    foreach (iterator_to_array($copy->getElementsByTagNameNS('*', 'drawing')) as $drawing) {
        $drawing->parentNode->removeChild($drawing);
    }

    return pg_office_docx_paragraph_edit($copy)['lock'];
}
