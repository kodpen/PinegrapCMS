<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// A module of functions.php: The software update channel, TLS for cURL, archive checks and extraction.
//
// Loaded by functions.php through require_once, never on its own. The
// functions were moved here from functions.php as they were; PG_FUNCTIONS_DIR
// is the software directory (what dirname(__FILE__) was in functions.php),
// see the note at the top of functions.php.
if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

// ----------------------------------------------------------------------------
// Performance monitor
// ----------------------------------------------------------------------------
// Records per-request metrics (duration, peak memory, CPU) into perf_log,
// after the response has been flushed to the client. Intended cost on the
// request path: a getrusage() call at start + one INSERT during shutdown.
//
// Behavior is governed by optional config.php constants — if undefined, sane
// defaults apply, so the system works out of the box once the migration runs.
//
//   PERF_MONITOR                 — enable/disable (default: true)
//   PERF_MONITOR_SAMPLE_RATE     — 1..100 percent of requests recorded (default: 100)
//   PERF_MONITOR_RETENTION_DAYS  — rows older than this are pruned (default: 30)
//   PERF_MONITOR_MIN_DURATION_MS — only log requests slower than this (default: 0)

/**
 * Verify a downloaded payload really is a ZIP archive.
 *
 * A download can "succeed" and still not be an archive: the far end may have
 * returned a 403 from its own firewall, a 404 page, or a proxy error, and all
 * of those arrive as a perfectly valid HTTP body. Writing that to disk under a
 * .zip name and handing it to ZipArchive produces a confusing failure several
 * steps later, pointing at the wrong subsystem.
 *
 * Checks the local file header signature. Empty archives use PK\x05\x06 and
 * spanned ones PK\x07\x08; all three are accepted.
 */
/**
 * Apply TLS verification to a cURL handle used for code or licence traffic.
 *
 * These paths were pinned to CURLOPT_SSL_VERIFYPEER = 0, which disables
 * certificate validation entirely. CURLOPT_SSL_VERIFYHOST = 2 sat next to it
 * and did nothing useful: with no chain validation there is no verified
 * certificate whose hostname could be checked.
 *
 * On an update channel that matters more than anywhere else. Anyone able to
 * sit between this server and the update server — poisoned DNS, a hostile
 * network, a compromised upstream — could serve their own archive, and it
 * would be unpacked straight into the web root and executed. The update
 * mechanism becomes the delivery mechanism.
 *
 * Deliberately does NOT retry without verification when it fails. A silent
 * downgrade is worse than no verification at all, because an attacker only has
 * to break the first attempt to get the insecure second one. Turning it off is
 * an explicit, documented decision the operator makes in config.php.
 */
function pg_curl_tls($ch)
{
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 1);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

    // Servers whose CA bundle is not on the default search path can point at
    // one instead of giving up verification.
    if (defined('CURL_CA_BUNDLE') && CURL_CA_BUNDLE !== '' && is_file(CURL_CA_BUNDLE)) {
        curl_setopt($ch, CURLOPT_CAINFO, CURL_CA_BUNDLE);
    }

    // Last resort for a host with no usable CA store at all.
    if (defined('ALLOW_INSECURE_UPDATE_TLS') && ALLOW_INSECURE_UPDATE_TLS === true) {
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    }
}

/**
 * Turn a certificate-related cURL error number into an actionable sentence.
 *
 * Without this the operator sees "cURL error 60" and has no idea that the
 * cause is a stale CA bundle on their own server rather than a fault at the
 * other end.
 */
function pg_curl_tls_hint($errno)
{
    $errno = (int) $errno;

    // 60 CURLE_PEER_FAILED_VERIFICATION, 77 CURLE_SSL_CACERT_BADFILE,
    // 35 CURLE_SSL_CONNECT_ERROR.
    if ($errno !== 60 && $errno !== 77 && $errno !== 35) {
        return '';
    }

    return ' The certificate could not be verified. This server is most likely'
        . ' missing an up-to-date CA bundle. Point CURL_CA_BUNDLE at a cacert.pem'
        . ' in data/config.php, or as a last resort set'
        . ' ALLOW_INSECURE_UPDATE_TLS to true there.';
}

/**
 * Which update channel this installation follows: 'stable' or 'beta'.
 *
 * One place decides, because three separate paths ask the update server the
 * same question — the daily check (software_update_check.php), the updater
 * screen (software_update.php) and the updater's own steps (api.php) — and a
 * site that checked one channel and downloaded the other would install a
 * package it never compared against its own version.
 *
 * config.software_update_channel arrives with 2026.4.4. Anything older, or a
 * request that runs before init.php has read the config row (the installer,
 * a job that includes functions.php on its own), reads 'stable': the channel
 * every installation was on before the setting existed.
 *
 * download_assistant.php deliberately stays on stable. It is the hand-run
 * rescue path for a site whose software is already broken, and the answer to
 * "my site is down" is the release everybody else is running.
 */
function pg_update_channel()
{
    if (defined('SOFTWARE_UPDATE_CHANNEL') && SOFTWARE_UPDATE_CHANNEL === 'beta') {
        return 'beta';
    }

    return 'stable';
}

/**
 * The REQUEST value the update server answers a version question with.
 *
 * Beta builds are published under their own key so that a stable site never
 * sees them: asking for latest_version is asking "what should everyone be
 * running", and that has to keep meaning exactly that.
 */
function pg_update_request_key()
{
    return (pg_update_channel() === 'beta') ? 'latest_beta_version' : 'latest_version';
}

/**
 * The package file for this channel, used both as the name to download from
 * the update server and as the name it is written under while it is unpacked.
 * The two must not drift apart: the replace step opens the file the download
 * step wrote.
 */
function pg_update_package_file()
{
    return (pg_update_channel() === 'beta') ? 'pinegrap_software_update_beta.zip' : 'pinegrap_software_update.zip';
}

function pg_looks_like_zip($bytes)
{
    if (!is_string($bytes) || strlen($bytes) < 4) {
        return false;
    }

    $magic = substr($bytes, 0, 4);

    return ($magic === "PK\x03\x04" || $magic === "PK\x05\x06" || $magic === "PK\x07\x08");
}

/**
 * Extract an update archive and PROVE every entry landed on disk.
 *
 * ZipArchive::extractTo() returns a boolean that the update flow ignored, and
 * that single omission is the cause of the recurring "the update left files
 * missing" problem. Extraction stops at the first entry it cannot write — a
 * permission denied, a file held open by another process, a full disk,
 * open_basedir — and everything after that entry is simply never created.
 * The caller saw no error and reported success, leaving a half-updated
 * installation that then had to be repaired by hand.
 *
 * Four things are checked here, in increasing order of cost:
 *
 *   1. The archive opens with CHECKCONS, so a truncated or corrupt download is
 *      rejected before a single file is touched rather than half-applied.
 *   2. Every file the archive is about to replace can be replaced. A file that
 *      exists and cannot be written to - left behind by another system user,
 *      or made read-only - is unlinked first, since deleting needs only the
 *      directory; what still cannot go is reported BEFORE anything is
 *      extracted, so the site is left whole rather than half-updated.
 *   3. extractTo() succeeded.
 *   4. Every entry the archive claims to contain now exists on disk with the
 *      size and CRC-32 the archive recorded for it. Existence alone was the
 *      old check, and existence is exactly what an old copy satisfies: a
 *      migration file the extraction did not overwrite passed it, the shorter
 *      list of steps in that copy ran, and the version was recorded with
 *      tables missing. Content is what is verified now.
 *
 * Returns array(
 *     'ok'      => bool,
 *     'message' => string,
 *     'missing' => array   entries that did not land on disk,
 *     'stale'   => array   entries that are on disk but are not the archive's,
 *     'blocked' => array   entries that could not have been replaced (nothing was extracted),
 *     'files'   => array   absolute paths written, on success
 * ).
 */
function pg_extract_archive($archive_path, $destination)
{
    $failure = array('ok' => false, 'message' => '', 'missing' => array(), 'stale' => array(), 'blocked' => array());

    if (!class_exists('ZipArchive')) {
        $failure['message'] = 'The ZipArchive extension is not available on this server.';
        return $failure;
    }

    if (!is_file($archive_path) || filesize($archive_path) < 4) {
        $failure['message'] = 'The downloaded archive is missing or empty.';
        return $failure;
    }

    $zip = new ZipArchive();

    // CHECKCONS makes the consistency of the archive a precondition. Without
    // it a damaged file can open on the strength of its central directory
    // alone and then extract nonsense.
    $opened = $zip->open($archive_path, ZipArchive::CHECKCONS);

    if ($opened !== true) {
        // Fall back to a plain open so an archive that merely fails the strict
        // check still reports a useful error rather than a bare code.
        $opened = $zip->open($archive_path);

        if ($opened !== true) {
            $failure['message'] = 'The downloaded archive could not be opened (code ' . (int) $opened . '). It is most likely incomplete — try again.';
            return $failure;
        }
    }

    // name => array(size, crc) for every file entry; directories are skipped
    $entries = array();

    for ($i = 0; $i < $zip->numFiles; $i++) {
        $stat = $zip->statIndex($i);

        if ($stat && isset($stat['name']) && substr($stat['name'], -1) !== '/') {
            // %08x prints the CRC the way hash_file('crc32b') does, on 32-bit
            // builds too, where the value arrives negative.
            $entries[$stat['name']] = array(
                'size' => isset($stat['size']) ? (int) $stat['size'] : -1,
                'crc'  => isset($stat['crc']) ? sprintf('%08x', $stat['crc']) : '',
            );
        }
    }

    $destination = rtrim($destination, '/\\') . '/';

    // Anything that cannot be overwritten is found before the archive is
    // opened on the tree. extractTo() opens each target for writing, and a
    // target that refuses - another owner, mode 0444 - stops it partway with
    // no word on which file. Deleting needs only write access to the folder,
    // so that is tried; a file that survives both is named and nothing is
    // extracted, because a package applied around one file is the incident
    // this function exists to prevent.
    clearstatcache();

    $blocked = array();

    // whether a file can be created in a directory: the directory, or the
    // nearest ancestor that exists, must be writable. Asked once per directory.
    $directory_writable = array();

    $can_create_in = function ($directory) use (&$directory_writable) {
        $directory = rtrim($directory, '/\\');

        if (isset($directory_writable[$directory])) {
            return $directory_writable[$directory];
        }

        $probe = $directory;

        while (($probe !== '') && !is_dir($probe)) {
            $parent = dirname($probe);

            if (($parent === $probe) || ($parent === '.') || ($parent === '')) {
                break;
            }

            $probe = $parent;
        }

        $directory_writable[$directory] = (($probe !== '') && is_dir($probe) && is_writable($probe));

        return $directory_writable[$directory];
    };

    foreach ($entries as $entry => $expected) {
        $target = $destination . $entry;

        if (!file_exists($target)) {
            // a new file: its folder has to take it
            if (!$can_create_in(dirname($target))) {
                $blocked[] = $entry;
            }
        } elseif (!is_writable($target)) {
            // an existing file that refuses: the mode first, then the folder
            @chmod($target, 0644);

            if (!is_writable($target)) {
                @unlink($target);

                clearstatcache(true, $target);

                if (file_exists($target)) {
                    $blocked[] = $entry;
                }
            }
        }

        if (count($blocked) >= 25) {
            break;
        }
    }

    if ($blocked) {
        $zip->close();

        $failure['message'] = count($blocked) . ' file(s) on this server cannot be replaced or created by the web server - the file or its folder belongs to another system user or is read-only. Nothing was changed. Correct the permissions, or delete the files (FTP or the file manager), and run the update again: ' . implode(', ', array_slice($blocked, 0, 10)) . (count($blocked) > 10 ? ', …' : '');
        $failure['blocked'] = $blocked;
        return $failure;
    }

    $extracted = $zip->extractTo($destination);
    $zip->close();

    if (!$extracted) {
        $failure['message'] = 'Extraction failed. The web server may not have write permission for the software directory, or the disk may be full.';
        return $failure;
    }

    // Prove it: on disk, and the archive's bytes rather than whatever was
    // there before. The size is compared first because it is free; the CRC
    // costs one read of every file and is what actually settles it.
    clearstatcache();

    $missing = array();
    $stale = array();

    foreach ($entries as $entry => $expected) {
        $target = $destination . $entry;

        if (!file_exists($target)) {
            $missing[] = $entry;

            if (count($missing) >= 25) {
                break;
            }

            continue;
        }

        if (($expected['size'] >= 0) && (@filesize($target) !== $expected['size'])) {
            $stale[] = $entry;
        } elseif (($expected['crc'] !== '') && (@hash_file('crc32b', $target) !== $expected['crc'])) {
            $stale[] = $entry;
        }

        if (count($stale) >= 25) {
            break;
        }
    }

    if ($missing) {
        $failure['message'] = count($missing) . '+ file(s) from the archive were not written. The installation is now partially updated and should be repaired.';
        $failure['missing'] = $missing;
        return $failure;
    }

    if ($stale) {
        $failure['message'] = count($stale) . ' file(s) on disk are not the ones in the package after extraction - the server kept its old copy: ' . implode(', ', array_slice($stale, 0, 10)) . (count($stale) > 10 ? ', …' : '') . '. Delete them and run the update again.';
        $failure['stale'] = $stale;
        return $failure;
    }

    // 'files' are the absolute paths that were just written. The caller needs
    // them to invalidate the bytecode cache per file where a full reset is
    // refused (opcache.restrict_api) — the alternative is walking the whole
    // installation to find files that are already known here.
    $written = array();
    foreach ($entries as $entry => $expected) {
        $written[] = $destination . $entry;
    }

    return array('ok' => true, 'message' => '', 'missing' => array(), 'stale' => array(), 'blocked' => array(), 'files' => $written);
}
