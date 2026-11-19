<?php
/**
 * Kodpen platform (api.kodpen.com)
 *
 * The Pinegrap release package recipe: builds the two packages of one
 * Pinegrap commit with `git archive` and writes their unsigned manifest.
 *
 *   pinegrap_software_update.zip  kind "update": the tree minus UPDATE_EXCLUDES
 *   pinegrap_software.zip         kind "full":   the whole tree
 *   manifest.json                 schema 1, see below; signed later, offline
 *                                 (manifest.json.sig)
 *
 * The recipe was verified against the live kodpen.com packages of 2026.4.10:
 * both are the tag tree, file for file and byte for byte, the update package
 * without the paths in UPDATE_EXCLUDES. On the beta channel (a pre-release
 * of the development branch) the paths the release branch leaves out are
 * removed from both packages as well (BETA_EXCLUDES). docker/ goes into the
 * full package and not into the update package (DOCKER_IN_UPDATE).
 *
 * Every path is anchored at the root of the tree: ".github/" removes the
 * top-level folder only, never a vendored library's own .github/. A name
 * ending in "/" is a folder and everything under it; any other name is one
 * file.
 *
 * The packages are deterministic as far as git archive allows: entries in
 * git's tree order, every entry dated at the commit time, the commit id as
 * the archive comment, so the same commit built with the same git gives the
 * same bytes. git runs with core.autocrlf=false and core.eol=lf, so no line
 * ending is converted. After each archive is written, every file entry is
 * read back and compared with the tree: the set of names must equal the
 * expected set, no file entry may be marked as a symbolic link, and each
 * entry's bytes must hash to its git blob id. Anything
 * else (an export-ignore or export-subst attribute, a filter, a conversion)
 * fails the build and leaves no package behind.
 *
 * manifest.json:
 *   schema            1
 *   version           the last version of the commit's
 *                     pinegrap/includes/migrations/versions.php (three parts,
 *                     the open version on development)
 *   channel           stable | beta
 *   build             beta only: --build, or "<short sha>.<commit time UTC,
 *                     YYYYMMDDHHMM>"; never present on stable
 *   git_ref           the --ref as given (the tag)
 *   git_sha           the commit id
 *   min_from_version  --min-from, or null
 *   artifacts         [{kind, file, size, sha256}] for update and full
 *   key_id            --key-id (lower case), or KEY_ID_PLACEHOLDER (refused
 *                     by every verifier and by the signer until the right
 *                     key id is written)
 *
 * The file is self-contained (no include) so the Pinegrap repository's CI
 * can run the same file, and it parses on PHP 7.1 so that a web request on
 * any Pinegrap host gets the 403 below. It needs PHP 8.0 or later to run and
 * may shell out to git: it runs on development machines and CI only, never
 * on the host.
 *
 * Usage:  php build_packages.php --repo=<git checkout> --ref=<tag or commit>
 *             --channel=stable|beta --out=<folder> [--build=<id>] [--min-from=<version>]
 *             [--key-id=<kid>]
 * Exit:   0 built; 1 the build failed (the reason is printed); 2 wrong usage.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Forbidden');
}

/** Paths left out of the update package (verified against the live 2026.4.10 files). */
const BP_UPDATE_EXCLUDES = [
    '.gitattributes',
    '.gitignore',
    '.github/',
    '.htaccess',
    'web.config',
    'CODE_OF_CONDUCT.md',
    'CONTRIBUTING.md',
    'README.md',
    'README.tr.md',
    'SECURITY.md',
    'pinegrap/data/config.php',
    'pinegrap/data/backups/english_default/',
    'pinegrap/data/backups/turkish_default/',
];

/** Paths the release branch leaves out, removed from both beta packages. */
const BP_BETA_EXCLUDES = [
    'CLAUDE.md',
    '.claude/',
    'docs/',
    'tools/',
    'tests/',
];

/** docker/ (from 2026.11.0 on): in the full package, out of the update package. */
const BP_DOCKER_DIR = 'docker/';
const BP_DOCKER_IN_FULL = true;
const BP_DOCKER_IN_UPDATE = false;

const BP_VERSIONS_FILE = 'pinegrap/includes/migrations/versions.php';
const BP_MANIFEST_SCHEMA = 1;
const BP_KEY_ID_PLACEHOLDER = 'unset';

const BP_FILES = [
    'update' => 'pinegrap_software_update.zip',
    'full' => 'pinegrap_software.zip',
];

/**
 * Whether a tree path is in a list of root-anchored names ("dir/" = a folder
 * and everything under it).
 *
 * @param list<string> $names
 */
function bp_path_listed(string $path, array $names): bool
{
    foreach ($names as $name) {
        if (str_ends_with($name, '/')) {
            if (str_starts_with($path, $name)) {
                return true;
            }
        } elseif ($path === $name) {
            return true;
        }
    }

    return false;
}

/** The root-anchored names left out of a package of a kind on a channel. @return list<string> */
function bp_excludes(string $kind, string $channel): array
{
    $names = [];

    if ($kind === 'update') {
        $names = BP_UPDATE_EXCLUDES;
    }

    if ($channel === 'beta') {
        $names = array_merge($names, BP_BETA_EXCLUDES);
    }

    $dockerIn = $kind === 'update' ? BP_DOCKER_IN_UPDATE : BP_DOCKER_IN_FULL;

    if (!$dockerIn) {
        $names[] = BP_DOCKER_DIR;
    }

    return $names;
}

/** Whether a tree path goes into a package of a kind on a channel. */
function bp_path_included(string $path, string $kind, string $channel): bool
{
    return !bp_path_listed($path, bp_excludes($kind, $channel));
}

/**
 * The last version of a Pinegrap versions.php, read from its source (the
 * single-quoted numbers of the array it returns; the same reading as the
 * API's package check), or '' when there is none.
 */
function bp_last_version(string $source): string
{
    $start = strpos($source, 'return array(');

    if ($start === false) {
        return '';
    }

    $end = strpos($source, ');', $start);
    $body = $end === false ? substr($source, $start) : substr($source, $start, $end - $start);
    preg_match_all("/'(\\d{4}(?:\\.\\d+)*)'/", $body, $matches);

    return $matches[1] === [] ? '' : (string) end($matches[1]);
}

/** A three-part Pinegrap version, YYYY.M.PATCH (month without a leading zero). */
function bp_is_version(string $version): bool
{
    if (preg_match('/^(\d{4})\.([1-9]\d?)\.(0|[1-9]\d{0,3})\z/', $version, $match) !== 1) {
        return false;
    }

    return (int) $match[2] <= 12;
}

/** A beta build id: letters and digits in groups joined by "." or "-", at most 40 characters. */
function bp_is_build(string $build): bool
{
    return strlen($build) <= 40 && preg_match('/^[0-9A-Za-z]+(?:[.-][0-9A-Za-z]+)*\z/', $build) === 1;
}

/** The default beta build id: short sha and the commit time in UTC. */
function bp_default_build(string $sha, int $commitTime): string
{
    return substr($sha, 0, 7) . '.' . gmdate('YmdHi', $commitTime);
}

/** The git blob id of some bytes, SHA-1 or (a SHA-256 repository, 64 hex ids) SHA-256. */
function bp_blob_id(string $bytes, int $idLength = 40): string
{
    return hash($idLength === 64 ? 'sha256' : 'sha1', 'blob ' . strlen($bytes) . "\0" . $bytes);
}

/**
 * Runs git with an argument list (no shell); answers [exit code, stdout,
 * stderr].
 *
 * @param list<string> $args
 * @return array{0: int, 1: string, 2: string}
 */
function bp_git(string $repo, array $args): array
{
    $command = array_merge(['git', '-c', 'core.autocrlf=false', '-c', 'core.eol=lf', '-c', 'core.safecrlf=false', '-C', $repo], $args);
    $pipes = [];
    $process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);

    if (!is_resource($process)) {
        throw new RuntimeException('git could not be started');
    }

    fclose($pipes[0]);
    $stdout = (string) stream_get_contents($pipes[1]);
    $stderr = (string) stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);

    return [proc_close($process), $stdout, $stderr];
}

/** @param list<string> $args */
function bp_git_ok(string $repo, array $args): string
{
    [$code, $stdout, $stderr] = bp_git($repo, $args);

    if ($code !== 0) {
        throw new RuntimeException('git ' . $args[0] . ' failed: ' . trim($stderr));
    }

    return $stdout;
}

/**
 * The files of a commit's tree: path => blob id. Refuses submodules and
 * symbolic links, which a package cannot carry as plain files.
 *
 * @return array<string, string>
 */
function bp_tree(string $repo, string $sha): array
{
    $out = bp_git_ok($repo, ['ls-tree', '-r', '-z', '--full-tree', $sha]);
    $files = [];

    foreach (explode("\0", $out) as $line) {
        if ($line === '') {
            continue;
        }

        if (preg_match('/^(\d{6}) (\w+) ([0-9a-f]{40,64})\t(.+)\z/s', $line, $match) !== 1) {
            throw new RuntimeException('Unreadable ls-tree line: ' . $line);
        }

        if ($match[2] !== 'blob' || $match[1] === '120000') {
            throw new RuntimeException('The tree holds a ' . ($match[1] === '120000' ? 'symbolic link' : $match[2]) . ' at ' . $match[4] . '; a package carries plain files only');
        }

        $files[$match[4]] = $match[3];
    }

    ksort($files, SORT_STRING);

    return $files;
}

/**
 * Writes one package with git archive into $target (through a dot-prefixed
 * .part file) and proves it holds exactly $expected (path => blob id).
 *
 * @param array<string, string> $expected
 * @param list<string>          $excludes
 */
function bp_archive(string $repo, string $sha, array $excludes, array $expected, string $target): void
{
    $part = dirname($target) . '/.' . basename($target) . '.part';

    if (is_file($part)) {
        unlink($part);
    }

    $args = ['archive', '--format=zip', '-o', $part, $sha, '--', '.'];

    foreach ($excludes as $name) {
        $args[] = ':(top,exclude,literal)' . rtrim($name, '/');
    }

    try {
        bp_git_ok($repo, $args);
        bp_verify_zip($part, $expected);
    } catch (Throwable $error) {
        if (is_file($part)) {
            unlink($part);
        }

        throw $error;
    }

    if (is_file($target)) {
        unlink($target);
    }

    if (!rename($part, $target)) {
        throw new RuntimeException('Could not move the package into place: ' . $target);
    }
}

/**
 * Proves that the archive's file entries are exactly $expected, none a
 * symbolic link, and that each one's bytes hash to its blob id. Folder
 * entries are ignored.
 *
 * @param array<string, string> $expected path => blob id
 */
function bp_verify_zip(string $path, array $expected): void
{
    $zip = new ZipArchive();

    if ($zip->open($path, ZipArchive::CHECKCONS) !== true) {
        // Also the signer's check of a downloaded package, not only of an archive written here.
        throw new RuntimeException('The archive does not open as a consistent ZIP: ' . $path);
    }

    try {
        $seen = [];

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = (string) $zip->getNameIndex($i);

            if (str_ends_with($name, '/')) {
                continue;
            }

            if (!isset($expected[$name])) {
                throw new RuntimeException('The archive holds a file the recipe leaves out: ' . $name);
            }

            if (isset($seen[$name])) {
                throw new RuntimeException('The archive holds a file twice: ' . $name);
            }

            $opsys = 0;
            $attributes = 0;

            // A Unix symbolic link (the file type bits of the mode in the upper
            // half of the external attributes): its bytes would be extracted as
            // a link target, and a package carries plain files only.
            if ($zip->getExternalAttributesIndex($i, $opsys, $attributes) && $opsys === ZipArchive::OPSYS_UNIX && (($attributes >> 16) & 0170000) === 0120000) {
                throw new RuntimeException('The archive holds a symbolic link: ' . $name);
            }

            $bytes = $zip->getFromIndex($i);

            if (!is_string($bytes) || bp_blob_id($bytes, strlen($expected[$name])) !== $expected[$name]) {
                throw new RuntimeException('The archive entry is not the committed file: ' . $name);
            }

            $seen[$name] = true;
        }

        $missing = array_diff_key($expected, $seen);

        if ($missing !== []) {
            throw new RuntimeException(count($missing) . ' file(s) of the recipe are missing from the archive, first: ' . array_key_first($missing));
        }
    } finally {
        $zip->close();
    }
}

/**
 * The manifest as written to manifest.json: fixed key order, pretty-printed,
 * unescaped slashes, a final newline.
 *
 * @param array<string, mixed> $manifest
 */
function bp_manifest_json(array $manifest): string
{
    return json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n";
}

/**
 * Builds both packages and the manifest. Answers the manifest.
 *
 * @param array{repo: string, ref: string, channel: string, out: string, build: ?string, min_from: ?string, key_id: ?string} $options
 * @return array<string, mixed>
 */
function bp_build(array $options): array
{
    $repo = $options['repo'];
    $ref = $options['ref'];
    $channel = $options['channel'];
    $out = rtrim(str_replace('\\', '/', $options['out']), '/');

    $sha = trim(bp_git_ok($repo, ['rev-parse', '--verify', '--quiet', $ref . '^{commit}']));

    if (preg_match('/^[0-9a-f]{40}(?:[0-9a-f]{24})?\z/', $sha) !== 1) {
        throw new RuntimeException('The ref does not name a commit: ' . $ref);
    }

    $commitTime = (int) trim(bp_git_ok($repo, ['show', '-s', '--format=%ct', $sha]));
    $version = bp_last_version(bp_git_ok($repo, ['show', $sha . ':' . BP_VERSIONS_FILE]));

    if (!bp_is_version($version)) {
        throw new RuntimeException(BP_VERSIONS_FILE . ' at ' . $ref . ' does not end on a three-part version (read: "' . $version . '")');
    }

    $minFrom = $options['min_from'];

    if ($minFrom !== null && (!bp_is_version($minFrom) || version_compare($minFrom, $version, '>='))) {
        throw new RuntimeException('--min-from must be a three-part version below ' . $version);
    }

    $build = null;

    if ($channel === 'beta') {
        $build = $options['build'] ?? bp_default_build($sha, $commitTime);

        if (!bp_is_build($build)) {
            throw new RuntimeException('The build id may hold letters and digits joined by "." or "-", 40 characters at most');
        }
    }

    $tree = bp_tree($repo, $sha);

    if (!is_dir($out) && !mkdir($out, 0775, true) && !is_dir($out)) {
        throw new RuntimeException('The output folder cannot be created: ' . $out);
    }

    $artifacts = [];

    foreach (BP_FILES as $kind => $file) {
        $expected = array_filter($tree, static function (string $path) use ($kind, $channel): bool {
            return bp_path_included($path, $kind, $channel);
        }, ARRAY_FILTER_USE_KEY);
        $target = $out . '/' . $file;

        bp_archive($repo, $sha, bp_excludes($kind, $channel), $expected, $target);

        clearstatcache(true, $target);
        $artifacts[] = [
            'kind' => $kind,
            'file' => $file,
            'size' => (int) filesize($target),
            'sha256' => (string) hash_file('sha256', $target),
            'files' => count($expected),
        ];
    }

    $manifest = [
        'schema' => BP_MANIFEST_SCHEMA,
        'version' => $version,
        'channel' => $channel,
    ];

    if ($build !== null) {
        $manifest['build'] = $build;
    }

    $manifest += [
        'git_ref' => $ref,
        'git_sha' => $sha,
        'min_from_version' => $minFrom,
        'artifacts' => array_map(static function (array $a): array {
            return [
                'kind' => $a['kind'],
                'file' => $a['file'],
                'size' => $a['size'],
                'sha256' => $a['sha256'],
            ];
        }, $artifacts),
        'key_id' => $options['key_id'] ?? BP_KEY_ID_PLACEHOLDER,
    ];

    $manifestPath = $out . '/manifest.json';
    $partPath = $out . '/.manifest.json.part';

    if (file_put_contents($partPath, bp_manifest_json($manifest)) === false || !rename($partPath, $manifestPath)) {
        throw new RuntimeException('manifest.json could not be written');
    }

    $manifest['_files'] = array_column($artifacts, 'files', 'kind');

    return $manifest;
}

/**
 * Parses --name=value arguments.
 *
 * @param list<string> $argv
 * @return array<string, string>
 */
function bp_arguments(array $argv): array
{
    $values = [];

    foreach ($argv as $argument) {
        if (preg_match('/^--([a-z][a-z-]*)=(.*)\z/s', $argument, $match) !== 1) {
            throw new InvalidArgumentException('Unknown argument: ' . $argument);
        }

        $values[$match[1]] = $match[2];
    }

    $unknown = array_diff(array_keys($values), ['repo', 'ref', 'channel', 'out', 'build', 'min-from', 'key-id']);

    if ($unknown !== []) {
        throw new InvalidArgumentException('Unknown option: --' . implode(', --', $unknown));
    }

    foreach (['repo', 'ref', 'channel', 'out'] as $required) {
        if (($values[$required] ?? '') === '') {
            throw new InvalidArgumentException('--' . $required . ' is required');
        }
    }

    if (!in_array($values['channel'], ['stable', 'beta'], true)) {
        throw new InvalidArgumentException('--channel is stable or beta');
    }

    if (isset($values['build']) && $values['channel'] !== 'beta') {
        throw new InvalidArgumentException('--build is for the beta channel only');
    }

    // A ref that git could read as an option, or that is not one name.
    if (preg_match('/^[A-Za-z0-9][A-Za-z0-9._\/+-]{0,199}\z/', $values['ref']) !== 1 || str_contains($values['ref'], '..')) {
        throw new InvalidArgumentException('--ref must be one tag, branch or commit name');
    }

    // The key-id pattern of the key and signing tools: a key id they refuse
    // would build a manifest that can never be signed.
    if (isset($values['key-id']) && preg_match('/^[a-z0-9][a-z0-9._-]{0,15}\z/', $values['key-id']) !== 1) {
        throw new InvalidArgumentException('--key-id is 1-16 lower-case letters, digits, ".", "_" or "-"');
    }

    if (!is_dir($values['repo'])) {
        throw new InvalidArgumentException('--repo is not a folder: ' . $values['repo']);
    }

    return $values;
}

// Loaded by a test for its functions only.
if (defined('KP_BUILD_PACKAGES_LIBRARY')) {
    return;
}

try {
    $values = bp_arguments(array_slice($argv, 1));
} catch (InvalidArgumentException $error) {
    fwrite(STDERR, $error->getMessage() . "\n");
    fwrite(STDERR, "Usage: php build_packages.php --repo=<git checkout> --ref=<tag or commit> --channel=stable|beta --out=<folder> [--build=<id>] [--min-from=<version>] [--key-id=<kid>]\n");
    exit(2);
}

if (!class_exists('ZipArchive')) {
    fwrite(STDERR, "The zip extension is required.\n");
    exit(1);
}

try {
    $manifest = bp_build([
        'repo' => $values['repo'],
        'ref' => $values['ref'],
        'channel' => $values['channel'],
        'out' => $values['out'],
        'build' => $values['build'] ?? null,
        'min_from' => $values['min-from'] ?? null,
        'key_id' => $values['key-id'] ?? null,
    ]);
} catch (Throwable $error) {
    fwrite(STDERR, 'Build failed: ' . $error->getMessage() . "\n");
    exit(1);
}

echo 'Pinegrap ' . $manifest['version'] . ' (' . $manifest['channel'] . (isset($manifest['build']) ? ', build ' . $manifest['build'] : '') . ') from ' . $manifest['git_ref'] . ' @ ' . substr((string) $manifest['git_sha'], 0, 12) . "\n";

foreach ($manifest['artifacts'] as $artifact) {
    echo '  ' . str_pad($artifact['kind'], 7) . ' ' . $artifact['file'] . '  ' . $manifest['_files'][$artifact['kind']] . ' files, ' . $artifact['size'] . ' bytes, sha256 ' . $artifact['sha256'] . "\n";
}

echo '  manifest.json (key_id ' . $manifest['key_id'] . ', unsigned)' . "\n";
exit(0);
