<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The "From a document" start of the designer (designer_document.php): the
 * form's texts, a file's refusal in the form's words, the answer the rows
 * are drawn from, and the pictures of a Word document kept until the page
 * is built, then stored in the file manager, each to its place.
 *
 * Loaded by designer_document.php only, not by functions.php.
 *
 * Memory: docs/memory/designer/document-start.md
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/document_paragraphs.php');

// The largest file the form takes (the own reader's main part limit).
define('PG_DOCUMENT_START_MAX_BYTES', 8 * 1024 * 1024);
// How long a read document is kept for its pictures, and how many a session keeps.
define('PG_DOCUMENT_START_KEEP_SECONDS', 6 * 3600);
define('PG_DOCUMENT_START_KEEP_MAX', 3);

/**
 * The texts of the form and the rows, for the New design window and the
 * designer's own window alike. {var:n} is filled in the browser.
 *
 * @return array key => translated text
 */
function pg_document_start_texts()
{
    return array(
        'head' => lang('Start from a document'),
        'lead' => lang('The headings, lists, questions and figures of a text become the sections of a page.'),
        'text' => lang('The text'),
        'placeholder' => lang('Paste the text here'),
        'help' => lang('Plain text or Markdown. A text copied from Word or a web page keeps its headings and lists. Up to 600,000 characters.'),
        'drop' => lang('Or drop a file here'),
        'dropLimits' => lang('.docx, .md or .txt · up to 8 MB'),
        'choose' => lang('Choose a file'),
        'manager' => lang('From the file manager'),
        'pasteInstead' => lang('Paste text instead'),
        'remove' => lang('Remove'),
        'local' => lang('The text is read on this site. Nothing is sent anywhere.'),
        'cancel' => lang('Cancel'),
        'read' => lang('Read'),
        'reading' => lang('Reading…'),
        'readingFile' => lang('Reading {var:1}…'),
        'sections' => lang('{var:1} sections'),
        'sectionsOf' => lang('{var:1} of {var:2} sections'),
        'from' => lang('read from {var:1} · {var:2} words'),
        'fromPaste' => lang('read from the pasted text · {var:1} words'),
        'toCheck' => lang('{var:1} to check'),
        'couldBe' => lang('It could be'),
        'other' => lang('Something else'),
        'mixed' => lang('It could be {var:1} or {var:2}: both fit the words.'),
        'words' => lang('The heading says {var:2}, but the words read as {var:1}.'),
        'open' => lang('Show the words'),
        'close' => lang('Hide the words'),
        'leave' => lang('Leave out'),
        'putBack' => lang('Put back'),
        'move' => lang('Move'),
        'moved' => lang('Moved to place {var:1} of {var:2}'),
        'heading' => lang('Heading'),
        'back' => lang('Back'),
        'backAsk' => lang('Reading again throws away the changes made to the sections.'),
        'readAgain' => lang('Read again'),
        'build' => lang('Build the page'),
        'building' => lang('Building…'),
        'buildingPage' => lang('Building the page…'),
        'cut' => lang('The first {var:1} paragraphs, about {var:2}%, were read'),
        'pictures' => lang('{var:1} pictures go to the file manager'),
        'picturesLost' => lang('{var:1} could not be taken (each larger than 2 MB)'),
        'noHeadings' => lang('No headings were found'),
        'noHeadingsText' => lang('The text is one section of the kind Text and can be built as it is.'),
        'tooLong' => lang('The text is longer than 600,000 characters.'),
        'network' => lang('Network error.'),
        'built' => lang('The page was built from the document.'),
        'kinds' => array(
            'intro' => lang('Intro'), 'text' => lang('Text'), 'cards' => lang('Cards'),
            'questions' => lang('Questions'), 'figures' => lang('Figures'), 'testimonial' => lang('Testimonial'),
            'table' => lang('Table'), 'prices' => lang('Prices'), 'contact' => lang('Contact'),
            'picture' => lang('Picture'), 'gallery' => lang('Gallery'),
        ),
        'counts' => array(
            'paragraphs' => lang('{var:1} paragraphs'), 'words' => lang('{var:1} words'), 'cards' => lang('{var:1} cards'),
            'questions' => lang('{var:1} questions'), 'figures' => lang('{var:1} figures'), 'quotes' => lang('{var:1} quotes'),
            'rows' => lang('{var:1} rows'), 'lines' => lang('{var:1} lines'), 'pictures' => lang('{var:1} pictures'),
        ),
        'fields' => array(
            'title' => lang('Title'), 'text' => lang('Text'), 'question' => lang('Question'), 'answer' => lang('Answer'),
            'value' => lang('Figure'), 'label' => lang('Label'), 'quote' => lang('Quote'), 'name' => lang('Name'),
            'role' => lang('Role'), 'alt' => lang('Alternative text'), 'cell' => lang('Cell'),
        ),
    );
}

/**
 * Why a file is not read, in the form's words: null when it can be. Pure.
 *
 * @param string     $name its name
 * @param int        $size its bytes
 * @param array|null $read pg_document_paragraphs(), null before it ran
 * @return array|null title, text
 */
function pg_document_start_refusal($name, $size, $read = null)
{
    if (pg_document_kind($name) === '') {
        return array('title' => lang('This file cannot be read'), 'text' => lang('Only .docx, .md and .txt files are read. An older .doc can be saved as .docx in Word.'));
    }

    if ((int) $size > PG_DOCUMENT_START_MAX_BYTES) {
        return array('title' => lang('The file is larger than 8 MB'), 'text' => lang('Save a shorter part of it, or paste its text.'));
    }

    if (is_array($read) && empty($read['ok'])) {
        return array('title' => lang('The file could not be opened'), 'text' => lang('It may be damaged or protected with a password.'));
    }

    if (is_array($read) && !$read['paragraphs'] && (trim((string) $read['text']) === '')) {
        return array('title' => lang('No text was found'), 'text' => lang('The file holds no text to make sections from.'));
    }

    return null;
}

/**
 * What the browser draws the rows from: the paragraphs or the text, how
 * much was read and the pictures without their place in the package. Pure.
 *
 * @param array  $read pg_document_paragraphs()
 * @param string $name
 * @param int    $size
 * @param string $keep the token the pictures are kept under, '' for none
 * @return array
 */
function pg_document_start_answer($read, $name, $size, $keep)
{
    $pictures = array();

    foreach ($read['pictures'] as $n => $picture) {
        $pictures[] = array('n' => $n, 'name' => $picture['name'], 'alt' => $picture['alt'], 'size' => (int) $picture['size']);
    }

    return array(
        'status' => 'success',
        'name' => (string) $name,
        'size' => (int) $size,
        'paragraphs' => $read['paragraphs'],
        'text' => $read['text'],
        'cut' => $read['cut'],
        'read' => $read['read'],
        'pictures' => $pictures,
        'pictures_lost' => (int) $read['pictures_lost'],
        'keep' => (string) $keep,
    );
}

/**
 * Where read documents wait for their pictures to be stored.
 *
 * @return string
 */
function pg_document_start_keep_dir()
{
    return PG_FUNCTIONS_DIR . '/data/temp/documents';
}

/**
 * Keeps a read Word document for its pictures, under a token of this
 * session; documents past PG_DOCUMENT_START_KEEP_SECONDS go first.
 *
 * @param string $path     the file read
 * @param array  $pictures pg_document_paragraphs()'s pictures
 * @return string the token, '' when it could not be kept
 */
function pg_document_start_keep($path, $pictures)
{
    $dir = pg_document_start_keep_dir();

    if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
        return '';
    }

    foreach ((array) glob($dir . '/*.docx') as $old) {
        if (is_file($old) && (filemtime($old) < time() - PG_DOCUMENT_START_KEEP_SECONDS)) {
            @unlink($old);
        }
    }

    $token = bin2hex(random_bytes(16));

    if (!@copy($path, $dir . '/' . $token . '.docx')) {
        return '';
    }

    $kept = isset($_SESSION['software']['document_keep']) && is_array($_SESSION['software']['document_keep']) ? $_SESSION['software']['document_keep'] : array();
    $kept[$token] = array('pictures' => $pictures, 'at' => time());

    while (count($kept) > PG_DOCUMENT_START_KEEP_MAX) {
        reset($kept);
        $first = key($kept);
        @unlink($dir . '/' . $first . '.docx');
        unset($kept[$first]);
    }

    $_SESSION['software']['document_keep'] = $kept;

    return $token;
}

/**
 * Stores the asked pictures of a kept document in the file manager's top
 * folder and answers the address of each by its number; the document is
 * let go after. A picture that cannot be stored is left out.
 *
 * @param string $token   pg_document_start_keep()
 * @param array  $numbers the pictures' numbers
 * @param string $stem    the document's name without extension, for the file names
 * @param int    $user_id
 * @return array n => address
 */
function pg_document_start_store($token, $numbers, $stem, $user_id)
{
    $kept = isset($_SESSION['software']['document_keep'][$token]) ? $_SESSION['software']['document_keep'][$token] : null;
    $path = pg_document_start_keep_dir() . '/' . $token . '.docx';
    $out = array();

    if (!preg_match('/^[0-9a-f]{32}$/', (string) $token) || !is_array($kept) || !is_file($path)) {
        return $out;
    }

    foreach (array_slice(array_unique(array_map('intval', (array) $numbers)), 0, 200) as $n) {
        if (!isset($kept['pictures'][$n])) {
            continue;
        }

        $picture = pg_document_picture($path, $kept['pictures'][$n]['part']);

        if ($picture === null) {
            continue;
        }

        $name = get_unique_name(array('name' => prepare_file_name(mb_substr((string) $stem, 0, 40) . '-' . $picture['name']), 'type' => 'file'));
        $ext = strtolower((string) pathinfo($name, PATHINFO_EXTENSION));

        if (!pg_file_write_whole(FILE_DIRECTORY_PATH . '/' . $name, $picture['bytes'])) {
            continue;
        }

        db_q("INSERT INTO files (name, folder, description, type, size, user, design, timestamp) VALUES (?, '0', ?, ?, ?, ?, '0', UNIX_TIMESTAMP())",
            array($name, (string) $kept['pictures'][$n]['alt'], substr($ext, 0, 4), (string) strlen($picture['bytes']), (string) (int) $user_id));
        $out[$n] = OUTPUT_PATH . $name;
    }

    @unlink($path);
    unset($_SESSION['software']['document_keep'][$token]);

    return $out;
}
