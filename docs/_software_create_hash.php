<?php 
/**
 * Create Hash Referance. Only for generate hash_referance.json and development computer only function that will never publish with software.
 */



include ('pinegrap/init.php');

/**
 * Generates SHA-256 hashes for the shipped source files and stores them in
 * pinegrap/data/temp/hash_reference.json for integrity verification.
 *
 * Two things are covered: every file directly in /pinegrap, and everything under
 * the directories named in hashed_subdirectories(). Server-generated files are
 * ignored by name wherever they appear.
 */

// Subdirectories walked in full, relative to /pinegrap.
//
// includes/ holds code that decides who may do what - authentication, the
// external API's credential check, the payment libraries - and until now none of
// it was covered: the reference only ever listed the flat root, so a modified
// includes/authentication.php or a modified stripe library was invisible to the
// integrity check. Nothing writes into includes/ at runtime, so every file there
// is expected to match its shipped hash forever.
//
// data/ is deliberately absent: it holds the configuration, the cache and the
// backups, and all three change on a live site by design. install/ is absent
// too, because operators delete that directory after setup and a missing file
// would be reported as tampering.
function hashed_subdirectories()
{
    // The check's own list when it is loaded, so the two cannot drift apart.
    if (function_exists('pg_integrity_scope_directories')) {
        return pg_integrity_scope_directories();
    }

    return ['includes'];
}

// Every file under one directory, as paths relative to /pinegrap with forward
// slashes, so the reference reads the same on Windows and on Linux.
function collect_directory_hashes($base_path, $relative_directory, $ignored_files)
{
    $hashes = [];
    $directory = $base_path . DIRECTORY_SEPARATOR . $relative_directory;

    if (!is_dir($directory)) {
        return $hashes;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($iterator as $item) {
        if ($item->isDir()) {
            continue;
        }

        if (in_array($item->getFilename(), $ignored_files)) {
            continue;
        }

        $relative_path = $relative_directory . '/' . str_replace(
            '\\',
            '/',
            substr($item->getPathname(), strlen($directory) + 1)
        );

        $hashes[$relative_path] = pg_integrity_hash_file($item->getPathname());
    }

    ksort($hashes);

    return $hashes;
}

function generate_directory_file_integrity()
{
    // Define reference file path
    $reference_file = 'pinegrap/data/temp/hash_reference.json';
    // Define files to ignore, by name, wherever they appear
    $ignored_files = function_exists('pg_integrity_ignored_files')
        ? pg_integrity_ignored_files()
        : ['error_log', '.htaccess', '.user.ini', '.DS_Store', 'Thumbs.db'];
    $hashes = [];
    // Get current working directory
    $base_path =  getcwd() . '/pinegrap';
    if ($base_path === false) {
        log_activity(lang('Unable to resolve current working directory.'), $_SESSION['sessionusername']);
        return 'unable_to_resolve_directory';
    }
    // Scan current directory for files
    $files = scandir($base_path);
    // Generate hashes for each file
    foreach ($files as $file) {
        $full_path = $base_path . DIRECTORY_SEPARATOR . $file;
        // Skip directories and ignored files
        if (
            $file === '.' ||
            $file === '..' ||
            is_dir($full_path) ||
            in_array($file, $ignored_files) ||
            realpath($full_path) === realpath($reference_file)
        ) {
            continue;
        }

        $relative_path = str_replace('\\', '/', $file);

        // Line-ending-blind, like the check that reads it: this machine checks
        // text files out with CRLF, the package ships them with LF. See
        // pg_integrity_hash_file().
        $hashes[$relative_path] = pg_integrity_hash_file($full_path);
    }

    // The subdirectories that ship with the software, walked in full.
    foreach (hashed_subdirectories() as $subdirectory) {
        $hashes = array_merge($hashes, collect_directory_hashes($base_path, $subdirectory, $ignored_files));
    }

    // The stamp that makes this reference authoritative on the host it was made
    // on: check_directory_file_integrity() compares the files against a reference
    // generated here for the running version and never asks GitHub. On every
    // other host the stamp names a different host, the file is not used, and the
    // release's tag on GitHub is the reference.
    $hashes['_generated'] = array(
        'version' => defined('VERSION') ? VERSION : '',
        'host' => function_exists('pg_integrity_host') ? pg_integrity_host() : strtolower((string) (isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '')),
        'time' => time(),
    );

    // The reference lives in pinegrap/data/temp. Creating "data" relative to the
    // current directory made the folder next to this script instead, so on a
    // checkout without that directory the write failed and nothing said so.
    $reference_directory = dirname($reference_file);

    if (!is_dir($reference_directory) && !mkdir($reference_directory, 0755, true)) {
        return 'unable_to_resolve_directory';
    }

    // A failed write must not be reported as success: the operator would publish
    // the previous reference and every file changed since then would be reported
    // as tampered with on every site running this version.
    if (file_put_contents($reference_file, json_encode($hashes, JSON_PRETTY_PRINT)) === false) {
        return 'unable_to_write_reference';
    }

    // The check's bookkeeping (last result, last fetch from GitHub) belongs
    // to the previous reference; without this the next status read could answer
    // from the cached result of the old one for up to an hour.
    $state_file = 'pinegrap/data/temp/hash_reference_state.json';

    if (is_file($state_file)) {
        @unlink($state_file);
    }
    // Log activity
    log_activity(lang('File integrity reference generated for current directory.'), $_SESSION['sessionusername']);
    return 'success';
}

// What the reference covers and where it counts. It is used on this host only:
// it is hashed from this working tree, which holds files that were never pushed
// and files still being edited. Every other installation verifies against the
// release's tag on GitHub, so nothing is published from here.
function describe_reference($reference_file)
{
    $version = defined('VERSION') ? VERSION : '';

    $local = json_decode((string) @file_get_contents($reference_file), true);

    if (!is_array($local)) {
        return 'The reference was written but could not be read back.';
    }

    $stamp = isset($local['_generated']) && is_array($local['_generated']) ? $local['_generated'] : [];

    $files = function_exists('pg_integrity_reference_files') ? pg_integrity_reference_files($local) : $local;

    $lines = [];
    $lines[] = count($files) . ' files hashed for version ' . $version
        . ' on ' . (isset($stamp['host']) ? $stamp['host'] : '?') . '.';
    $lines[] = 'This reference is authoritative on this host only. Other installations verify against the GitHub tag v'
        . $version . ' once it is released; do not upload this file anywhere.';

    return implode("\n", $lines);
}

$result = generate_directory_file_integrity();

echo $result;

if ($result === 'success') {
    echo "\n\n" . describe_reference('pinegrap/data/temp/hash_reference.json');
}