<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - an Evernote export (.enex) brought in as notes.
 *
 * An .enex file is one notebook: every note with its title, its dates and
 * tags, its text in ENML (Evernote's XHTML) and its attachments inside the
 * file as base64. ws_note_import_stage() hands uploaded ones to
 * ws_note_enex_stage(), which lays them out the way an upload of Markdown
 * files would have been: a Markdown file a note, its dates and tags in the
 * front matter, and its attachments under attachments/<n>/, linked from
 * where the note shows them. The import then goes on as for any upload: the
 * preview, the batches and the finish of notes_import.php.
 *
 * The file is read as a stream, 64 KB at a time (xml_parse()), and an
 * attachment is decoded into its file while it is read, so a notebook of
 * large pictures is never held in memory whole. A file whose document type
 * declares anything of its own is refused before it is parsed, and ENML is
 * loaded without its DTD: the entities it uses are spelt out first.
 *
 * ENML comes in the way the workspace writes a note: a <div> is a line,
 * bold, italic, struck and code text keep their marks, links to web and mail
 * addresses stay links, a to-do box (en-todo, or a checklist of the newer
 * editor) is a checklist item, a table a Markdown table, a code block a
 * fenced block and an attachment (en-media) a link to its file. Headings and
 * lists are written as Markdown and left to ws_note_import_convert(), as for
 * a Markdown file.
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
 * How many bytes of the file one xml_parse() call reads.
 */
define('WS_NOTE_ENEX_CHUNK', 65536);

/**
 * How much ENML one note may hold before it is too long to bring in.
 */
define('WS_NOTE_ENEX_CONTENT_MAX', 16777216);

/**
 * A moment an export writes (20231015T123456Z, UTC) as a timestamp; 0 when
 * it is none, or not when a note can have been written.
 *
 * @param string $value
 * @return int
 */
function ws_note_enex_time($value)
{
    $value = trim((string) $value);

    if (preg_match('/^(\d{4})(\d{2})(\d{2})T(\d{2})(\d{2})(\d{2})Z?$/', $value, $match)) {
        $time = gmmktime((int) $match[4], (int) $match[5], (int) $match[6], (int) $match[2], (int) $match[3], (int) $match[1]);

        return ws_note_import_time((string) $time);
    }

    return ws_note_import_time($value);
}

/**
 * The extension of a file the export only gives a type for.
 *
 * @param string $mime
 * @return string
 */
function ws_note_enex_extension($mime)
{
    $map = array(
        'image/jpeg' => 'jpg', 'image/jpg' => 'jpg', 'image/pjpeg' => 'jpg', 'image/png' => 'png',
        'image/gif' => 'gif', 'image/webp' => 'webp', 'image/bmp' => 'bmp', 'image/tiff' => 'tif',
        'image/heic' => 'heic', 'image/heif' => 'heif', 'image/svg+xml' => 'svg',
        'application/pdf' => 'pdf', 'text/plain' => 'txt', 'text/html' => 'html', 'text/csv' => 'csv',
        'audio/mpeg' => 'mp3', 'audio/mp3' => 'mp3', 'audio/wav' => 'wav', 'audio/x-wav' => 'wav',
        'audio/mp4' => 'm4a', 'audio/x-m4a' => 'm4a', 'audio/amr' => 'amr', 'audio/ogg' => 'ogg',
        'video/mp4' => 'mp4', 'video/quicktime' => 'mov', 'video/webm' => 'webm',
        'application/zip' => 'zip', 'application/msword' => 'doc', 'application/vnd.ms-excel' => 'xls',
        'application/vnd.ms-powerpoint' => 'ppt',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',
    );

    $mime = strtolower(trim(preg_replace('/;.*$/', '', (string) $mime)));

    return $map[$mime] ?? 'bin';
}

/**
 * The name an attachment is kept under in its note's folder: the name the
 * export gives it, with what a path or a link could not hold replaced, an
 * extension from its type when it has none, and a number when the note has
 * another file of that name.
 *
 * @param string $name  the export's file-name, '' when it gives none
 * @param string $mime
 * @param int    $number the attachment's place in its note, for a name it lacks
 * @param array  $taken lower-cased names the note's folder holds already, as keys
 * @return string
 */
function ws_note_enex_file_name($name, $mime, $number, &$taken)
{
    $name = basename(str_replace('\\', '/', (string) $name));
    $name = preg_replace('/[\x00-\x1F\x7F]+/u', '', $name);
    $name = preg_replace('/\s*[\/:*?"<>|#%\[\]]+\s*/u', '-', $name);
    $name = trim(preg_replace('/\s+/u', ' ', $name), ' .-');

    $extension = strtolower((string) pathinfo($name, PATHINFO_EXTENSION));

    if (($extension === '') || ($extension === $name) || !preg_match('/^[a-z0-9]{1,10}$/', $extension)) {
        $extension = ws_note_enex_extension($mime);
        $stem = $name;
    } else {
        $stem = mb_substr($name, 0, mb_strlen($name) - strlen($extension) - 1);
    }

    $stem = trim($stem, ' .-');

    if ($stem === '') {
        $stem = 'attachment-' . (int) $number;
    }

    if (mb_strlen($stem) > 100) {
        $stem = rtrim(mb_substr($stem, 0, 100), ' .-');
    }

    $candidate = $stem . '.' . $extension;
    $count = 1;

    while (isset($taken[mb_strtolower($candidate)])) {
        $count++;
        $candidate = $stem . ' (' . $count . ').' . $extension;
    }

    $taken[mb_strtolower($candidate)] = true;

    return $candidate;
}

/* ---------------------------------------------------------------------------
   ENML to the workspace's Markdown.
   --------------------------------------------------------------------------- */

/**
 * The ENML of a note as a document: without its declaration and document
 * type, the HTML entities it uses spelt out (its DTD is not read), loaded
 * with LIBXML_NONET; read by the HTML parser when it is not well-formed.
 *
 * @param string $enml
 * @return DOMElement|null the en-note element (or what stands for it)
 */
function ws_note_enex_root($enml)
{
    $enml = preg_replace('/^\s*<\?xml[^>]*\?>/i', '', (string) $enml);
    $enml = preg_replace('/<!DOCTYPE[^>\[]*(?:\[[^\]]*\])?\s*>/i', '', $enml);

    $enml = preg_replace_callback('/&([A-Za-z][A-Za-z0-9]{1,31});/', function ($match) {
        if (in_array($match[1], array('amp', 'lt', 'gt', 'quot', 'apos'), true)) {
            return $match[0];
        }

        $character = html_entity_decode($match[0], ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return ($character === $match[0]) ? '&amp;' . $match[1] . ';' : htmlspecialchars($character, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }, $enml);

    $previous = libxml_use_internal_errors(true);

    $document = new DOMDocument();
    $loaded = (trim($enml) !== '') && $document->loadXML($enml, LIBXML_NONET);

    if (!$loaded) {
        $document = new DOMDocument();
        $loaded = (trim($enml) !== '') && $document->loadHTML('<?xml encoding="UTF-8"?><html><body>' . $enml . '</body></html>', LIBXML_NONET);
    }

    libxml_clear_errors();
    libxml_use_internal_errors($previous);

    if (!$loaded) {
        return null;
    }

    $notes = $document->getElementsByTagName('en-note');

    if ($notes->length > 0) {
        return $notes->item(0);
    }

    $bodies = $document->getElementsByTagName('body');

    return ($bodies->length > 0) ? $bodies->item(0) : $document->documentElement;
}

/**
 * The value of one property of an element's style attribute, lower-cased.
 *
 * @param DOMNode $node
 * @param string  $property
 * @return string '' when it has none
 */
function ws_note_enex_style($node, $property)
{
    if (!($node instanceof DOMElement) || !$node->hasAttribute('style')) {
        return '';
    }

    foreach (explode(';', $node->getAttribute('style')) as $declaration) {
        $pair = explode(':', $declaration, 2);

        if ((count($pair) === 2) && (strtolower(trim($pair[0])) === strtolower($property))) {
            return strtolower(trim($pair[1]));
        }
    }

    return '';
}

/**
 * Is this element a block of its own (a line or more) rather than words
 * within a line?
 *
 * @param DOMNode $node
 * @return bool
 */
function ws_note_enex_is_block($node)
{
    static $blocks = array(
        'div', 'p', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'ul', 'ol', 'li', 'table', 'thead', 'tbody',
        'tfoot', 'tr', 'td', 'th', 'blockquote', 'pre', 'hr', 'en-note', 'center', 'section', 'article',
        'header', 'footer', 'aside', 'nav', 'figure', 'figcaption', 'address', 'dl', 'dt', 'dd', 'body',
    );

    return ($node instanceof DOMElement) && in_array(strtolower($node->nodeName), $blocks, true);
}

/**
 * The lines of code an element holds, spaces kept: a line a block (one that
 * holds only a <br> is an empty line), a line break where the text or a
 * <br> has one, no marks.
 *
 * @param DOMNode $node
 * @return string[]
 */
function ws_note_enex_code_lines($node)
{
    $lines = array();
    $current = null;

    $add = function ($text) use (&$lines, &$current) {
        $parts = explode("\n", str_replace(array("\r\n", "\r", "\xC2\xA0"), array("\n", "\n", ' '), (string) $text));
        $current = (string) $current . array_shift($parts);

        foreach ($parts as $part) {
            $lines[] = $current;
            $current = $part;
        }
    };

    foreach ($node->childNodes as $child) {
        if (($child->nodeType === XML_TEXT_NODE) || ($child->nodeType === XML_CDATA_SECTION_NODE)) {
            $add($child->nodeValue);
            continue;
        }

        if (!($child instanceof DOMElement)) {
            continue;
        }

        if (strtolower($child->nodeName) === 'br') {
            $lines[] = (string) $current;
            $current = null;
            continue;
        }

        $inner = ws_note_enex_code_lines($child);

        if (ws_note_enex_is_block($child)) {
            if ($current !== null) {
                $lines[] = $current;
                $current = null;
            }

            foreach ($inner as $line) {
                $lines[] = $line;
            }

            continue;
        }

        $add(implode("\n", $inner));
    }

    if ($current !== null) {
        $lines[] = $current;
    }

    return $lines;
}

/**
 * Marks put around words: around each line of them, inside the spaces that
 * surround them, never twice.
 *
 * @param string $mark
 * @param string $inner
 * @return string
 */
function ws_note_enex_wrap($mark, $inner)
{
    $lines = explode("\n", (string) $inner);

    foreach ($lines as $index => $line) {
        if (!preg_match('/^(\s*)(.*?)(\s*)$/us', $line, $parts) || ($parts[2] === '') || (strpos($parts[2], "\x02") !== false)) {
            continue;
        }

        $words = $parts[2];

        if ((strlen($words) > 2 * strlen($mark)) && (strpos($words, $mark) === 0) && (substr($words, -strlen($mark)) === $mark)) {
            continue;
        }

        $lines[$index] = $parts[1] . $mark . $words . $mark . $parts[3];
    }

    return implode("\n", $lines);
}

/**
 * An address as a Markdown link holds it: what would end the link encoded.
 *
 * @param string $url
 * @return string
 */
function ws_note_enex_url($url)
{
    return str_replace(array(' ', '(', ')', '<', '>', '"'), array('%20', '%28', '%29', '%3C', '%3E', '%22'), trim((string) $url));
}

/**
 * A to-do box in the middle of words: it reads as [ ] or [x]. One that opens
 * a line makes the line a checklist item (ws_note_enex_lines()).
 *
 * @param string $text
 * @return string
 */
function ws_note_enex_unmark($text)
{
    return str_replace(array("\x02x\x02", "\x02 \x02"), array('[x] ', '[ ] '), (string) $text);
}

/**
 * The words a run of inline content leaves, as lines: a <br> ends a line, a
 * break that ends the run adds none (a run that is only a break is one empty
 * line), spaces are single and a to-do box at the start makes a checklist
 * item.
 *
 * @param string $buffer
 * @return string[]
 */
function ws_note_enex_lines($buffer)
{
    $buffer = (string) $buffer;

    if ($buffer === '') {
        return array();
    }

    $broken = (strpos($buffer, "\n") !== false);
    $buffer = preg_replace('/\n[ \t]*$/', '', $buffer, 1);
    $lines = array();

    foreach (explode("\n", $buffer) as $piece) {
        $piece = trim($piece);
        $item = '';

        if (preg_match('/^\x02([ x])\x02\s*(.*)$/su', $piece, $match)) {
            $item = '- [' . $match[1] . '] ';
            $piece = $match[2];
        }

        $lines[] = rtrim($item . trim(preg_replace('/ {2,}/', ' ', ws_note_enex_unmark($piece))));
    }

    if ((count($lines) === 1) && ($lines[0] === '')) {
        return $broken ? array('') : array();
    }

    return $lines;
}

/**
 * The words of a node, with their marks.
 *
 * @param DOMNode $node
 * @param array   $state media, used, crypt
 * @return string "\n" where a line ends
 */
function ws_note_enex_inline($node, &$state)
{
    if (($node->nodeType === XML_TEXT_NODE) || ($node->nodeType === XML_CDATA_SECTION_NODE)) {
        return preg_replace('/[\s\x{00A0}]+/u', ' ', $node->nodeValue);
    }

    if (!($node instanceof DOMElement)) {
        return '';
    }

    $tag = strtolower($node->nodeName);
    $inner = function () use ($node, &$state) {
        $text = '';

        foreach ($node->childNodes as $child) {
            $text .= ws_note_enex_inline($child, $state);
        }

        return $text;
    };

    switch ($tag) {
        case 'br':
            return "\n";

        case 'en-todo':
            return "\x02" . ((strtolower($node->getAttribute('checked')) === 'true') ? 'x' : ' ') . "\x02";

        case 'en-media':
            return ws_note_enex_media($node, $state);

        case 'en-crypt':
            return ' ' . $state['crypt'] . ' ';

        case 'img':
            $source = trim($node->getAttribute('src'));

            if (!preg_match('#^https?://#i', $source)) {
                return '';
            }

            $label = trim(str_replace(array('[', ']'), '', ($node->getAttribute('alt') !== '') ? $node->getAttribute('alt') : $node->getAttribute('title')));

            return ' [' . (($label !== '') ? $label : 'image') . '](' . ws_note_enex_url($source) . ') ';

        case 'a':
            $href = trim($node->getAttribute('href'));
            $label = trim(preg_replace('/\s+/u', ' ', $inner()));

            if (!preg_match('#^(?:https?://|mailto:)#i', $href)) {
                return $label;
            }

            // A picture or a file inside the link stays itself; the link is
            // written after it.
            if (strpos($label, '](') !== false) {
                return $label . ' [' . $href . '](' . ws_note_enex_url($href) . ')';
            }

            $label = str_replace(array('[', ']'), array('(', ')'), $label);

            return '[' . (($label !== '') ? $label : preg_replace('/^mailto:/i', '', $href)) . '](' . ws_note_enex_url($href) . ')';

        case 'b':
        case 'strong':
            return ws_note_enex_wrap('**', $inner());

        case 'i':
        case 'em':
            return ws_note_enex_wrap('*', $inner());

        case 's':
        case 'strike':
        case 'del':
            return ws_note_enex_wrap('~~', $inner());

        case 'code':
        case 'tt':
        case 'kbd':
        case 'samp':
            $code = trim(preg_replace('/[\s\x{00A0}]+/u', ' ', $node->textContent));

            if ($code === '') {
                return '';
            }

            return (strpos($code, '`') === false) ? '`' . $code . '`' : $code;

        case 'script':
        case 'style':
        case 'title':
        case 'head':
        case 'object':
        case 'embed':
        case 'iframe':
        case 'noscript':
            return '';
    }

    // A block inside words still starts a line of its own.
    if (ws_note_enex_is_block($node)) {
        return "\n" . $inner() . "\n";
    }

    // Spans of the newer editor and of web clips carry their marks in style.
    $text = $inner();
    $weight = ws_note_enex_style($node, 'font-weight');

    if (($weight === 'bold') || ($weight === 'bolder') || (ctype_digit($weight) && ((int) $weight >= 600))) {
        $text = ws_note_enex_wrap('**', $text);
    }

    if (ws_note_enex_style($node, 'font-style') === 'italic') {
        $text = ws_note_enex_wrap('*', $text);
    }

    if (strpos(ws_note_enex_style($node, 'text-decoration'), 'line-through') !== false) {
        $text = ws_note_enex_wrap('~~', $text);
    }

    return $text;
}

/**
 * An attachment where the note shows it, as a link to its file: found by
 * its hash (the MD5 of its bytes), or, when the box names none, the next one
 * not shown yet.
 *
 * @param DOMElement $node
 * @param array      $state
 * @return string
 */
function ws_note_enex_media($node, &$state)
{
    $hash = strtolower(trim($node->getAttribute('hash')));
    $found = -1;

    foreach ($state['media'] as $index => $file) {
        if (($hash !== '') && ($file['hash'] === $hash)) {
            $found = $index;
            break;
        }
    }

    if (($found < 0) && ($hash === '')) {
        foreach ($state['media'] as $index => $file) {
            if (!isset($state['used'][$index])) {
                $found = $index;
                break;
            }
        }
    }

    if ($found < 0) {
        return '';
    }

    $state['used'][$found] = true;

    return ' [' . $state['media'][$found]['name'] . '](' . $state['media'][$found]['link'] . ') ';
}

/**
 * The lines of an element's content, blocks and words alike.
 *
 * @param DOMNode $parent
 * @param array   $state
 * @return string[]
 */
function ws_note_enex_flow($parent, &$state)
{
    $lines = array();
    $buffer = '';

    foreach ($parent->childNodes as $child) {
        if (!ws_note_enex_is_block($child)) {
            $buffer .= ws_note_enex_inline($child, $state);
            continue;
        }

        foreach (ws_note_enex_lines($buffer) as $line) {
            $lines[] = $line;
        }

        $buffer = '';

        foreach (ws_note_enex_block($child, $state) as $line) {
            $lines[] = $line;
        }
    }

    foreach (ws_note_enex_lines($buffer) as $line) {
        $lines[] = $line;
    }

    return $lines;
}

/**
 * A block of code: its lines between fences.
 *
 * @param string[] $lines ws_note_enex_code_lines()
 * @return string[]
 */
function ws_note_enex_fence($lines)
{
    $lines = array_values((array) $lines);

    while (!empty($lines) && (trim($lines[0]) === '')) {
        array_shift($lines);
    }

    while (!empty($lines) && (trim(end($lines)) === '')) {
        array_pop($lines);
    }

    if (empty($lines)) {
        return array();
    }

    return array_merge(array('', '```'), array_map('rtrim', $lines), array('```', ''));
}

/**
 * One block element as lines.
 *
 * @param DOMElement $node
 * @param array      $state
 * @return string[]
 */
function ws_note_enex_block($node, &$state)
{
    $tag = strtolower($node->nodeName);

    if (preg_match('/^h([1-6])$/', $tag, $level)) {
        $words = trim(implode(' ', ws_note_enex_flow($node, $state)));

        return ($words === '') ? array() : array('', str_repeat('#', (int) $level[1]) . ' ' . $words, '');
    }

    if (($tag === 'ul') || ($tag === 'ol')) {
        return array_merge(array(''), ws_note_enex_list($node, $state), array(''));
    }

    if ($tag === 'table') {
        return ws_note_enex_table($node, $state);
    }

    if ($tag === 'pre') {
        return ws_note_enex_fence(ws_note_enex_code_lines($node));
    }

    if ($tag === 'hr') {
        return array('', '---', '');
    }

    // A code block of the newer editor: a div that says so in its style.
    if (strpos((string) ($node->hasAttribute('style') ? $node->getAttribute('style') : ''), 'en-codeblock:true') !== false) {
        return ws_note_enex_fence(ws_note_enex_code_lines($node));
    }

    $lines = ws_note_enex_flow($node, $state);

    if ($tag === 'blockquote') {
        return array_merge(array(''), array_map(function ($line) {
            return ($line === '') ? '>' : '> ' . $line;
        }, $lines), array(''));
    }

    if ($tag === 'p') {
        $lines[] = '';
    }

    return $lines;
}

/**
 * A list as Markdown items: "- ", "1. ", or a checklist item where the list
 * is a checklist of the newer editor (--en-todo in its style) or the item
 * opens with a to-do box. The lines of an item after its first, and a list
 * inside it, are indented under it.
 *
 * @param DOMElement $list
 * @param array      $state
 * @return string[]
 */
function ws_note_enex_list($list, &$state)
{
    $ordered = (strtolower($list->nodeName) === 'ol');
    $number = ($ordered && ctype_digit((string) $list->getAttribute('start'))) ? max(1, (int) $list->getAttribute('start')) : 1;
    $checklist = (ws_note_enex_style($list, '--en-todo') === 'true');
    $out = array();

    foreach ($list->childNodes as $item) {
        if (!($item instanceof DOMElement)) {
            continue;
        }

        $tag = strtolower($item->nodeName);

        // A list put straight inside a list belongs to the item before it.
        if (($tag === 'ul') || ($tag === 'ol')) {
            foreach (ws_note_enex_list($item, $state) as $line) {
                $out[] = '  ' . $line;
            }

            continue;
        }

        if ($tag !== 'li') {
            continue;
        }

        // An item reads as one block: the empty lines its paragraphs or a
        // list inside it leave are dropped.
        $lines = array_values(array_filter(ws_note_enex_flow($item, $state), function ($line) {
            return $line !== '';
        }));

        if ($checklist) {
            $marker = '- [' . ((ws_note_enex_style($item, '--en-checked') === 'true') ? 'x' : ' ') . '] ';
        } elseif ($ordered) {
            $marker = $number . '. ';
            $number++;
        } else {
            $marker = '- ';
        }

        if (empty($lines)) {
            continue;
        }

        $first = array_shift($lines);
        $out[] = preg_match('/^- \[[ x]\]( |$)/', $first) ? $first : rtrim($marker . $first);

        foreach ($lines as $line) {
            $out[] = ($line === '') ? '' : str_repeat(' ', min(4, strlen($marker))) . $line;
        }
    }

    return $out;
}

/**
 * A table as a Markdown table: its first row the header, a cell's lines on
 * one line, a | inside a cell escaped, a cell spanning columns followed by
 * empty ones.
 *
 * @param DOMElement $table
 * @param array      $state
 * @return string[]
 */
function ws_note_enex_table($table, &$state)
{
    $rows = array();
    $groups = array($table);

    foreach ($table->childNodes as $child) {
        if (($child instanceof DOMElement) && in_array(strtolower($child->nodeName), array('thead', 'tbody', 'tfoot'), true)) {
            $groups[] = $child;
        }
    }

    foreach ($groups as $group) {
        foreach ($group->childNodes as $row) {
            if (!($row instanceof DOMElement) || (strtolower($row->nodeName) !== 'tr')) {
                continue;
            }

            $cells = array();

            foreach ($row->childNodes as $cell) {
                if (!($cell instanceof DOMElement) || !in_array(strtolower($cell->nodeName), array('td', 'th'), true)) {
                    continue;
                }

                $words = array();

                foreach (ws_note_enex_flow($cell, $state) as $line) {
                    $line = trim(preg_replace('/^- (\[[ x]\])/', '$1', $line));

                    if (($line !== '') && ($line !== '```')) {
                        $words[] = $line;
                    }
                }

                $cells[] = str_replace('|', '\\|', implode(' ', $words));

                for ($span = (int) $cell->getAttribute('colspan'); $span > 1; $span--) {
                    $cells[] = '';
                }
            }

            if (!empty($cells)) {
                $rows[] = $cells;
            }
        }
    }

    if (empty($rows)) {
        return array();
    }

    $width = max(array_map('count', $rows));
    $lines = array('');

    foreach ($rows as $index => $cells) {
        $cells = array_pad($cells, $width, '');
        $lines[] = rtrim('| ' . implode(' | ', $cells) . ' |');

        if ($index === 0) {
            $lines[] = '|' . str_repeat(' --- |', $width);
        }
    }

    $lines[] = '';

    return $lines;
}

/**
 * A note's ENML as the workspace's Markdown.
 *
 * @param string  $enml
 * @param array[] $media the note's attachments, in the export's order: hash
 *                       (MD5 of the bytes, hex), name (the label), link (the
 *                       path a link to the file holds)
 * @return array text, used (indexes of $media the note shows)
 */
function ws_note_enex_markdown($enml, $media = array())
{
    $state = array(
        'media' => array_values((array) $media),
        'used'  => array(),
        'crypt' => lang('[encrypted text]'),
    );

    $root = ws_note_enex_root($enml);

    if ($root) {
        $lines = ws_note_enex_flow($root, $state);
    } else {
        $plain = preg_replace('#<br\s*/?>|</(?:div|p|li|h[1-6]|tr)>#i', "\n", (string) $enml);
        $lines = explode("\n", html_entity_decode(strip_tags($plain), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    // No empty line at either end, never two in a row; a code block keeps
    // its own.
    $out = array();
    $blank = true;
    $fence = false;

    foreach ($lines as $line) {
        $line = rtrim((string) $line);

        if ($line === '```') {
            $fence = !$fence;
        } elseif (!$fence && ($line === '')) {
            if (!$blank) {
                $out[] = '';
            }

            $blank = true;
            continue;
        }

        $out[] = $line;
        $blank = false;
    }

    while (!empty($out) && (end($out) === '')) {
        array_pop($out);
    }

    return array('text' => implode("\n", $out), 'used' => array_keys($state['used']));
}

/* ---------------------------------------------------------------------------
   The export read as a stream, written into the stage.
   --------------------------------------------------------------------------- */

/**
 * The Markdown file a note becomes: title, dates and tags in the front
 * matter, as an export of ours writes them, then the text.
 *
 * @param array  $note title, created, updated, tags
 * @param string $body
 * @return string
 */
function ws_note_enex_file($note, $body)
{
    $front = array('---');

    if (trim((string) $note['title']) !== '') {
        $front[] = 'title: ' . ws_note_export_yaml(trim((string) $note['title']));
    }

    foreach (array('created', 'updated') as $key) {
        if ((int) ($note[$key] ?? 0) > 0) {
            $front[] = $key . ': ' . gmdate('Y-m-d\TH:i:s\Z', (int) $note[$key]);
        }
    }

    $tags = array_values(array_filter(array_map('trim', (array) ($note['tags'] ?? array())), 'strlen'));

    if (!empty($tags)) {
        $front[] = 'tags:';

        foreach ($tags as $tag) {
            $front[] = '  - ' . ws_note_export_yaml($tag);
        }
    }

    $front[] = '---';

    return implode("\n", $front) . "\n\n" . rtrim((string) $body) . "\n";
}

/**
 * Lays out the notes of uploaded exports in a stage: every note a Markdown
 * file, every attachment a file of its own, as the files of an upload.
 *
 * @param string   $dir     the stage folder (its files/ is made here)
 * @param string[] $sources the .enex files, moved into the stage; removed once read
 * @return array ok, error, entries (as ws_note_import_stage() keeps them; an
 *               attachment's row carries a fifth value, 1: never read as a note)
 */
function ws_note_enex_stage($dir, $sources)
{
    $out = array('ok' => false, 'error' => '', 'entries' => array());

    if (!is_dir($dir . '/files') && !@mkdir($dir . '/files', 0755)) {
        ws_note_import_log_failure('creating the files folder of an Evernote import');
        $out['error'] = lang('The file could not be saved.');

        return $out;
    }

    $context = array(
        'dir'      => $dir,
        'next'     => 0,
        'notes'    => 0,
        'taken'    => array(),
        'entries'  => array(),
        'file_max' => function_exists('ws_upload_max_bytes') ? ws_upload_max_bytes() : 20971520,
        'error'    => '',
    );

    foreach ((array) $sources as $source) {
        $ok = ws_note_enex_read($source, $context);
        @unlink($source);

        if (!$ok) {
            $out['error'] = $context['error'];

            return $out;
        }
    }

    if ($context['notes'] === 0) {
        $out['error'] = lang('The Evernote export holds no notes.');

        return $out;
    }

    $out['ok'] = true;
    $out['entries'] = $context['entries'];

    return $out;
}

/**
 * Reads one export into the stage of $context, a note at a time.
 *
 * @param string $path
 * @param array  $context ws_note_enex_stage()'s; error is set when false is returned
 * @return bool
 */
function ws_note_enex_read($path, &$context)
{
    $not_enex = lang('This is not an Evernote export (.enex).');
    $handle = @fopen($path, 'rb');

    if ($handle === false) {
        $context['error'] = lang('The file could not be read.');

        return false;
    }

    // The export's root, and no document type of its own: an internal subset
    // could declare entities that expand into gigabytes.
    $head = (string) fread($handle, 8192);

    if (!preg_match('/<en-export[\s>]/', $head) || preg_match('/<!DOCTYPE[^>]*\[/i', $head) || (stripos($head, '<!ENTITY') !== false)) {
        fclose($handle);
        $context['error'] = $not_enex;

        return false;
    }

    rewind($handle);

    $stack = array();
    $text = '';
    $note = null;
    $file = null;
    $task = null;
    $failed = '';

    $parser = xml_parser_create('UTF-8');
    xml_parser_set_option($parser, XML_OPTION_CASE_FOLDING, 0);
    xml_parser_set_option($parser, XML_OPTION_TARGET_ENCODING, 'UTF-8');

    $start = function ($parser, $name, $attributes) use (&$stack, &$text, &$note, &$file, &$task, &$context, &$failed) {
        $name = strtolower($name);
        $parent = empty($stack) ? '' : end($stack);
        $stack[] = $name;
        $text = '';

        if (($name === 'note') && ($parent === 'en-export')) {
            $note = array('title' => '', 'created' => 0, 'updated' => 0, 'tags' => array(), 'content' => '', 'long' => false, 'files' => array(), 'tasks' => array());
        } elseif (($name === 'resource') && ($parent === 'note') && ($note !== null)) {
            $number = $context['next']++;
            $file = array(
                'number' => $number,
                'handle' => @fopen($context['dir'] . '/files/' . $number . '.txt', 'wb'),
                'hash'   => hash_init('md5'),
                'size'   => 0,
                'rest'   => '',
                'mime'   => '',
                'name'   => '',
            );

            if ($file['handle'] === false) {
                ws_note_import_log_failure('writing an attachment of an Evernote import');
                $failed = lang('The file could not be saved.');
            }
        } elseif (($name === 'task') && ($parent === 'note') && ($note !== null)) {
            $task = array('title' => '', 'status' => '');
        }
    };

    // The bytes of an attachment, decoded four characters at a time into its
    // file; past the largest file a note keeps, only counted and hashed.
    $decode = function ($data, $last) use (&$file, &$context) {
        $file['rest'] .= preg_replace('/[^A-Za-z0-9+\/]/', '', $data);
        $usable = $last ? strlen($file['rest']) : (strlen($file['rest']) - (strlen($file['rest']) % 4));

        if ($usable <= 0) {
            return;
        }

        $bytes = (string) base64_decode(substr($file['rest'], 0, $usable));
        $file['rest'] = (string) substr($file['rest'], $usable);

        hash_update($file['hash'], $bytes);

        if (($file['handle'] !== false) && (($file['size'] + strlen($bytes)) <= $context['file_max'])) {
            fwrite($file['handle'], $bytes);
        }

        $file['size'] += strlen($bytes);
    };

    $characters = function ($parser, $data) use (&$stack, &$text, &$note, &$file, $decode) {
        $count = count($stack);
        $name = ($count > 0) ? $stack[$count - 1] : '';
        $parent = ($count > 1) ? $stack[$count - 2] : '';

        if (($file !== null) && ($name === 'data') && ($parent === 'resource')) {
            $decode($data, false);

            return;
        }

        if (($note !== null) && ($name === 'content') && ($parent === 'note')) {
            if ((strlen($note['content']) + strlen($data)) > WS_NOTE_ENEX_CONTENT_MAX) {
                $note['long'] = true;
            } else {
                $note['content'] .= $data;
            }

            return;
        }

        if (strlen($text) < 65536) {
            $text .= $data;
        }
    };

    $end = function ($parser, $name) use (&$stack, &$text, &$note, &$file, &$task, &$context, &$failed, $decode) {
        $name = strtolower($name);
        array_pop($stack);
        $parent = empty($stack) ? '' : end($stack);
        $value = trim(preg_replace('/\s+/u', ' ', $text));

        if (($note !== null) && ($parent === 'note')) {
            if ($name === 'title') {
                $note['title'] = mb_substr($value, 0, 200);
            } elseif (($name === 'created') || ($name === 'updated')) {
                $note[$name] = ws_note_enex_time($value);
            } elseif (($name === 'tag') && ($value !== '')) {
                $note['tags'][] = mb_substr($value, 0, 100);
            }
        }

        if (($file !== null) && ($parent === 'resource') && ($name === 'mime')) {
            $file['mime'] = $value;
        }

        if (($file !== null) && ($parent === 'resource-attributes') && ($name === 'file-name')) {
            $file['name'] = $value;
        }

        if (($task !== null) && ($parent === 'task')) {
            if ($name === 'title') {
                $task['title'] = mb_substr($value, 0, 500);
            } elseif ($name === 'taskstatus') {
                $task['status'] = strtolower($value);
            }
        }

        if (($file !== null) && ($name === 'data') && ($parent === 'resource')) {
            $decode('', true);
        }

        if (($file !== null) && ($name === 'resource') && ($note !== null)) {
            if ($file['handle'] !== false) {
                fclose($file['handle']);
            }

            $note['files'][] = array(
                'number' => $file['number'],
                'hash'   => hash_final($file['hash']),
                'size'   => $file['size'],
                'mime'   => $file['mime'],
                'name'   => $file['name'],
            );
            $file = null;
        }

        if (($task !== null) && ($name === 'task')) {
            if ($task['title'] !== '') {
                $note['tasks'][] = $task;
            }

            $task = null;
        }

        if (($note !== null) && ($name === 'note') && ($parent === 'en-export')) {
            if (($failed === '') && !ws_note_enex_add($note, $context)) {
                $failed = $context['error'];
            }

            $note = null;
        }

        $text = '';
    };

    xml_set_element_handler($parser, $start, $end);
    xml_set_character_data_handler($parser, $characters);

    $ok = true;

    while ($ok && ($failed === '') && (($chunk = fread($handle, WS_NOTE_ENEX_CHUNK)) !== false) && ($chunk !== '')) {
        $ok = (xml_parse($parser, $chunk, false) === 1);
    }

    if ($ok && ($failed === '')) {
        $ok = (xml_parse($parser, '', true) === 1);
    }

    if (!$ok) {
        $failed = $not_enex . ' ' . lang(array('string' => 'Line {var:1}: {var:2}', 'vars' => array(xml_get_current_line_number($parser), xml_error_string(xml_get_error_code($parser)))));
    }

    if (($file !== null) && ($file['handle'] !== false)) {
        fclose($file['handle']);
    }

    // A no-op since PHP 8.0 and deprecated in 8.5: there the parser goes with
    // its variable.
    if (PHP_VERSION_ID < 80000) {
        xml_parser_free($parser); // phpcs:ignore PHPCompatibility.FunctionUse.RemovedFunctions.xml_parser_freeDeprecated -- guarded by PHP_VERSION_ID < 80000 above
    }

    fclose($handle);

    if ($failed !== '') {
        $context['error'] = $failed;

        return false;
    }

    return true;
}

/**
 * One note of an export added to the stage: its attachments named into
 * attachments/<n>/, its text with links to them, the Markdown file.
 *
 * @param array $note    as ws_note_enex_read() collects it
 * @param array $context
 * @return bool false (with the error set) when the stage holds too many files
 */
function ws_note_enex_add($note, &$context)
{
    if ((count($context['entries']) + 1 + count($note['files'])) > WS_NOTE_IMPORT_ENTRIES_MAX) {
        $context['error'] = lang(array('string' => 'The export holds more than {var:1} notes and attachments.', 'vars' => WS_NOTE_IMPORT_ENTRIES_MAX));

        return false;
    }

    $number = ++$context['notes'];
    $name = ws_note_export_name($note['title'], $number, $context['taken']) . '.md';
    $time = ($note['updated'] > 0) ? $note['updated'] : $note['created'];

    // A note too long to read stays in the list as one, without its file.
    if ($note['long']) {
        $context['entries'][] = array($name, WS_NOTE_IMPORT_TEXT_MAX + 1, $time, $context['next']++);

        return true;
    }

    $folder = 'attachments/' . $number . '/';
    $taken = array();
    $media = array();
    $rows = array();

    foreach ($note['files'] as $index => $file) {
        $file_name = ws_note_enex_file_name($file['name'], $file['mime'], $index + 1, $taken);
        $media[] = array('hash' => $file['hash'], 'name' => $file_name, 'link' => ws_note_import_link_path($folder . $file_name));
        $rows[] = array($folder . $file_name, (int) $file['size'], $time, (int) $file['number'], 1);
    }

    $markdown = ws_note_enex_markdown($note['content'], $media);
    $body = $markdown['text'];

    // Attachments the text does not show still come along, at its end.
    $shown = array_flip($markdown['used']);
    $rest = array();

    foreach ($media as $index => $file) {
        if (!isset($shown[$index])) {
            $rest[] = '[' . $file['name'] . '](' . $file['link'] . ')';
        }
    }

    // Tasks of the newer editor, as checklist items.
    foreach ($note['tasks'] as $task) {
        $rest[] = '- [' . (in_array($task['status'], array('completed', 'complete', 'done'), true) ? 'x' : ' ') . '] ' . $task['title'];
    }

    if (!empty($rest)) {
        $body = rtrim($body . "\n\n" . implode("\n", $rest));
    }

    $text = ws_note_enex_file($note, $body);
    $file_number = $context['next']++;

    if (@file_put_contents($context['dir'] . '/files/' . $file_number . '.txt', $text) === false) {
        ws_note_import_log_failure('writing a note of an Evernote import');
        $context['error'] = lang('The file could not be saved.');

        return false;
    }

    $context['entries'][] = array($name, strlen($text), $time, $file_number);

    foreach ($rows as $row) {
        $context['entries'][] = $row;
    }

    return true;
}
