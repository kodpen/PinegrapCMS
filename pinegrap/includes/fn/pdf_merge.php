<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * PDF merging - the pages of several PDF documents put into one, for
 * printing many shipping labels at once (shipment_label.php). No library
 * bundled with the software imports existing PDFs, and the free FPDI parser
 * does not read the compressed cross-reference streams labels are often
 * written with, so this reads just what a merge needs:
 *
 * Every indirect object is found by scanning the file ("N G obj ... endobj";
 * a later copy of a number replaces an earlier one, as incremental updates
 * do), the objects packed in object streams (/Type /ObjStm, FlateDecode)
 * are unpacked, and the page tree is walked from the catalog the trailer
 * (or the cross-reference stream) names. Each document's objects are
 * renumbered after the previous one's, every page gets the one new page
 * tree as its parent and the attributes it inherited from its old tree
 * (Resources, MediaBox, CropBox, Rotate), and the file is written with a
 * classic cross-reference table. Stream data is copied byte for byte; only
 * dictionaries are rewritten. An encrypted document, or one whose pages
 * cannot be found, is left out.
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
 * The objects of a PDF and its catalog. Pure.
 *
 * @param string $pdf
 * @return array|null objects (number => body text between "obj" and
 *                    "endobj"), root (the catalog's number); null when the
 *                    file is not a PDF this can read
 */
function pg_pdf_parse($pdf)
{
    $pdf = (string) $pdf;

    if ((strncmp($pdf, '%PDF-', 5) !== 0) || (strpos($pdf, '/Encrypt') !== false)) {
        return null;
    }

    $objects = array();
    $offset = 0;

    while (preg_match('/(?<![0-9])(\d+)\s+\d+\s+obj\b/', $pdf, $match, PREG_OFFSET_CAPTURE, $offset)) {
        $start = $match[0][1] + strlen($match[0][0]);
        $stream = preg_match('/\bstream\r?\n/', $pdf, $stream_match, PREG_OFFSET_CAPTURE, $start) ? $stream_match[0][1] : -1;
        $end = strpos($pdf, 'endobj', $start);

        if ($end === false) {
            break;
        }

        // A stream may hold the bytes "endobj": its end is "endstream".
        if (($stream !== -1) && ($stream < $end)) {
            $stream_end = strpos($pdf, 'endstream', $stream);
            $end = ($stream_end === false) ? false : strpos($pdf, 'endobj', $stream_end);

            if ($end === false) {
                break;
            }
        }

        $objects[(int) $match[1][0]] = trim(substr($pdf, $start, $end - $start));
        $offset = $end + 6;
    }

    // Objects packed in object streams, where no plain copy exists.
    foreach ($objects as $number => $body) {
        if (!preg_match('#/Type\s*/ObjStm\b#', pg_pdf_dictionary($body))) {
            continue;
        }

        $data = pg_pdf_stream_data($body);
        unset($objects[$number]);

        if (($data === '') || !preg_match('#/First\s+(\d+)#', $body, $first) || !preg_match('#/N\s+(\d+)#', $body, $count)) {
            continue;
        }

        $pairs = preg_split('/\s+/', trim(substr($data, 0, (int) $first[1])));
        $packed = array();

        for ($index = 0; ($index + 1 < count($pairs)) && ($index < 2 * (int) $count[1]); $index += 2) {
            $packed[] = array((int) $pairs[$index], (int) $first[1] + (int) $pairs[$index + 1]);
        }

        foreach ($packed as $position => $entry) {
            $next = isset($packed[$position + 1]) ? $packed[$position + 1][1] : strlen($data);

            if (!isset($objects[$entry[0]])) {
                $objects[$entry[0]] = trim(substr($data, $entry[1], $next - $entry[1]));
            }
        }
    }

    foreach ($objects as $number => $body) {
        if (preg_match('#/Type\s*/XRef\b#', pg_pdf_dictionary($body))) {
            unset($objects[$number]);
        }
    }

    if (!preg_match_all('#/Root\s+(\d+)\s+\d+\s+R#', $pdf, $roots) || !isset($objects[(int) end($roots[1])])) {
        return null;
    }

    $document = array('objects' => $objects, 'root' => (int) end($roots[1]));

    return pg_pdf_page_numbers($document) ? $document : null;
}

/**
 * The dictionary part of an object's body: all of it but a stream's data.
 *
 * @param string $body
 * @return string
 */
function pg_pdf_dictionary($body)
{
    return preg_match('/\bstream\r?\n/', $body, $match, PREG_OFFSET_CAPTURE) ? substr($body, 0, $match[0][1]) : $body;
}

/**
 * A stream's data, inflated when it is FlateDecode; '' when it cannot be.
 *
 * @param string $body
 * @return string
 */
function pg_pdf_stream_data($body)
{
    if (!preg_match('/\bstream\r?\n/', $body, $match, PREG_OFFSET_CAPTURE)) {
        return '';
    }

    $start = $match[0][1] + strlen($match[0][0]);
    $end = strrpos($body, 'endstream');
    $data = rtrim(substr($body, $start, ($end === false ? strlen($body) : $end) - $start), "\r\n");
    $dictionary = substr($body, 0, $match[0][1]);

    if (strpos($dictionary, '/FlateDecode') === false) {
        return $data;
    }

    if (strpos($dictionary, '/DecodeParms') !== false) {
        return '';
    }

    $inflated = function_exists('gzuncompress') ? @gzuncompress($data) : false;

    return is_string($inflated) ? $inflated : '';
}

/**
 * A key's value in a dictionary: a reference, an array, a name, a number or
 * a nested dictionary; '' when absent.
 *
 * @param string $dictionary
 * @param string $key without the slash
 * @return string
 */
function pg_pdf_value($dictionary, $key)
{
    if (!preg_match('#/' . preg_quote($key, '#') . '(?![A-Za-z0-9])\s*#', $dictionary, $match, PREG_OFFSET_CAPTURE)) {
        return '';
    }

    $start = $match[0][1] + strlen($match[0][0]);
    $rest = substr($dictionary, $start);

    if (preg_match('/^\d+\s+\d+\s+R\b/', $rest, $reference)) {
        return $reference[0];
    }

    $open = substr($rest, 0, 2) === '<<' ? '<<' : (substr($rest, 0, 1) === '[' ? '[' : '');

    if ($open === '') {
        return preg_match('#^(/?[^\s/<>\[\]()]+)#', $rest, $plain) ? $plain[1] : '';
    }

    // A nested array or dictionary, to its matching end.
    $close = ($open === '<<') ? '>>' : ']';
    $depth = 0;

    for ($index = 0; $index < strlen($rest); $index++) {
        if (substr($rest, $index, strlen($open)) === $open) {
            $depth++;
            $index += strlen($open) - 1;
        } elseif (substr($rest, $index, strlen($close)) === $close) {
            $depth--;
            $index += strlen($close) - 1;

            if ($depth === 0) {
                return substr($rest, 0, $index + 1);
            }
        }
    }

    return '';
}

/**
 * The object numbers of a document's pages, in reading order. Pure.
 *
 * @param array $document pg_pdf_parse()
 * @param array $inherited the attributes each page inherits, filled in
 * @return array
 */
function pg_pdf_page_numbers($document, &$inherited = array())
{
    $pages = array();
    $catalog = isset($document['objects'][$document['root']]) ? pg_pdf_dictionary($document['objects'][$document['root']]) : '';
    $stack = preg_match('#/Pages\s+(\d+)\s+\d+\s+R#', $catalog, $match) ? array(array((int) $match[1], array())) : array();
    $seen = array();

    while ($stack) {
        list($number, $from_parent) = array_shift($stack);

        if (isset($seen[$number]) || !isset($document['objects'][$number]) || (count($seen) > 10000)) {
            continue;
        }

        $seen[$number] = true;
        $dictionary = pg_pdf_dictionary($document['objects'][$number]);

        foreach (array('Resources', 'MediaBox', 'CropBox', 'Rotate') as $key) {
            $value = pg_pdf_value($dictionary, $key);

            if ($value !== '') {
                $from_parent[$key] = $value;
            }
        }

        if (preg_match('#/Type\s*/Pages\b#', $dictionary)) {
            preg_match_all('#(\d+)\s+\d+\s+R#', pg_pdf_value($dictionary, 'Kids'), $kids);
            $children = array();

            foreach ($kids[1] as $kid) {
                $children[] = array((int) $kid, $from_parent);
            }

            $stack = array_merge($children, $stack);
        } elseif (preg_match('#/Type\s*/Page\b#', $dictionary)) {
            $pages[] = $number;
            $inherited[$number] = $from_parent;
        }
    }

    return $pages;
}

/**
 * The pages of several PDFs in one PDF, in the order given. Pure.
 *
 * @param array $documents list of PDF bytes
 * @param array $skipped   filled with the positions of the documents left out
 * @return string the PDF, '' when no document could be read
 */
function pg_pdf_merge($documents, &$skipped = array())
{
    $skipped = array();
    $bodies = array();
    $kids = array();
    $next = 1;

    foreach (array_values((array) $documents) as $position => $pdf) {
        $document = pg_pdf_parse($pdf);
        $inherited = array();
        $pages = $document ? pg_pdf_page_numbers($document, $inherited) : array();

        if (!$pages) {
            $skipped[] = $position;
            continue;
        }

        $map = array();

        foreach (array_keys($document['objects']) as $number) {
            $map[$number] = $next++;
        }

        $renumber = function ($text) use ($map) {
            return preg_replace_callback('/(?<![0-9])(\d+)\s+(\d+)\s+R\b/', function ($reference) use ($map) {
                return (isset($map[(int) $reference[1]]) ? $map[(int) $reference[1]] : 0) . ' 0 R';
            }, $text);
        };

        foreach ($document['objects'] as $number => $body) {
            $dictionary = pg_pdf_dictionary($body);
            $data = substr($body, strlen($dictionary));

            if (isset($inherited[$number]) && (substr(ltrim($dictionary), 0, 2) === '<<')) {
                // The page's own values stay; what it inherited is written on.
                $add = '';

                foreach ($inherited[$number] as $key => $value) {
                    if (pg_pdf_value($dictionary, $key) === '') {
                        $add .= ' /' . $key . ' ' . $value;
                    }
                }

                $dictionary = preg_replace('#/Parent\s+\d+\s+\d+\s+R#', '', $dictionary);
                $dictionary = preg_replace('/^\s*<</', '<< /Parent PG_PDF_PAGES' . $add, $dictionary, 1);
                $kids[] = $map[$number];
            }

            $bodies[$map[$number]] = $renumber($dictionary) . $data;
        }
    }

    if (!$kids) {
        return '';
    }

    $pages = $next;
    $catalog = $next + 1;

    foreach ($bodies as $number => $body) {
        $bodies[$number] = str_replace('PG_PDF_PAGES', $pages . ' 0 R', $body);
    }

    $bodies[$pages] = '<< /Type /Pages /Kids [' . implode(' 0 R ', $kids) . ' 0 R] /Count ' . count($kids) . ' >>';
    $bodies[$catalog] = '<< /Type /Catalog /Pages ' . $pages . ' 0 R >>';

    $out = "%PDF-1.7\n%\xE2\xE3\xCF\xD3\n";
    $offsets = array();

    for ($number = 1; $number <= $catalog; $number++) {
        $offsets[$number] = strlen($out);
        $out .= $number . " 0 obj\n" . $bodies[$number] . "\nendobj\n";
    }

    $xref = strlen($out);
    $out .= "xref\n0 " . ($catalog + 1) . "\n0000000000 65535 f \n";

    foreach ($offsets as $offset) {
        $out .= sprintf('%010d', $offset) . " 00000 n \n";
    }

    return $out . "trailer\n<< /Size " . ($catalog + 1) . ' /Root ' . $catalog . " 0 R >>\nstartxref\n" . $xref . "\n%%EOF\n";
}
