<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The self-test behind WebP at a picture's own address through a CDN or
 * proxy: whether the edge keeps a copy of a picture per format (then the
 * confirmation, bit 8 of config.image_derivative_formats, is set and
 * pictures go out public again), and, when it keeps one copy for everyone,
 * whether it at least honours Cache-Control: private (then pictures stay
 * negotiated and private; otherwise the original goes until the next test).
 *
 * The confirmation needs proof: both copies must come back out of the edge's
 * cache in their own format. The right format alone proves nothing, because
 * the server's requests to its own address may skip the edge's cache while a
 * visitor's do not; then the confirmation is not given, or is taken back.
 *
 * It needs no CDN account: one public picture is fetched through the site's
 * own address (HOSTNAME_SETTING) at fresh one-off ?pg_vary_check= addresses,
 * which get_file.php negotiates whatever the last test left
 * (pg_image_vary_check_asked()). The general job runs it once after the
 * switch or the confirmation changes, then daily while the switch is on,
 * confirmed or not, so a confirmation ticked by hand or outlived by the
 * CDN's setup is taken back; Cloudflare Tools' check button runs the same
 * test. What it found is kept in same_address.json beside the derivatives,
 * which get_file.php reads (pg_image_same_address_mode()).
 *
 * Loaded by job.php, cloudflare.php and the System Status report.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/image_negotiation.php');

/**
 * Whether an answer came back through a CDN or proxy: one of the headers
 * such a hop adds to the answer (Cloudflare's CF-Ray, CloudFront's
 * X-Amz-Cf-Id, the standard Via, X-Cache of Varnish, Fastly and others) in
 * the last header block (after any redirect).
 *
 * @param string $headers the raw header block(s)
 * @return bool
 */
function pg_image_vary_answer_proxied($headers)
{
    $found = false;

    foreach (preg_split('/\r?\n/', (string) $headers) as $line) {

        if (stripos($line, 'HTTP/') === 0) {
            $found = false;
            continue;
        }

        $colon = strpos($line, ':');

        if (
            ($colon !== false)
            && in_array(strtolower(trim(substr($line, 0, $colon))), array('cf-ray', 'x-amz-cf-id', 'via', 'x-cache'), true)
            && (trim(substr($line, $colon + 1)) !== '')
        ) {
            $found = true;
        }
    }

    return $found;
}

/**
 * Whether an answer came out of a cache rather than from the server, read
 * from the last header block: Cloudflare's CF-Cache-Status HIT, STALE,
 * UPDATING or REVALIDATED, an X-Cache naming a hit (Varnish, Fastly,
 * CloudFront's "Hit from cloudfront"), or an Age header, which only a cache
 * adds (get_file.php sends none).
 *
 * @param string $headers the raw header block(s)
 * @return bool
 */
function pg_image_vary_answer_hit($headers)
{
    $hit = false;

    foreach (preg_split('/\r?\n/', (string) $headers) as $line) {

        if (stripos($line, 'HTTP/') === 0) {
            $hit = false;
            continue;
        }

        $colon = strpos($line, ':');

        if ($colon === false) {
            continue;
        }

        $name = strtolower(trim(substr($line, 0, $colon)));
        $value = trim(substr($line, $colon + 1));

        if (
            (($name === 'cf-cache-status') && in_array(strtoupper($value), array('HIT', 'STALE', 'UPDATING', 'REVALIDATED'), true))
            || (($name === 'x-cache') && (stripos($value, 'hit') !== false))
            || (($name === 'age') && (preg_match('/^\d+$/', $value) === 1))
        ) {
            $hit = true;
        }
    }

    return $hit;
}

/**
 * The type of one answer, without parameters, lower case.
 *
 * @param array $answer status, type, proxied
 * @return string
 */
function pg_image_vary_answer_type($answer)
{
    return strtolower(trim(explode(';', (string) (is_array($answer) && isset($answer['type']) ? $answer['type'] : ''))[0]));
}

/**
 * The verdict of the public test: four fetches of one picture at a fresh
 * address answered public, Accept naming WebP, then not, then naming it,
 * then not. Each answer is array(status, type (Content-Type), proxied (a CDN
 * header came back), hit (it came out of a cache)).
 *
 * ok when the first and third got WebP, the second and fourth the original,
 * and the third and fourth came out of the cache: the edge was seen keeping
 * both copies. not_cached when every reader got its own format but the last
 * two did not come out of a cache: nothing shows the edge keeps a copy per
 * format, since requests that skip its cache (the server asking its own
 * address) are answered right whatever it gives a visitor. unreachable when
 * one was not answered with 200 and a picture; not_proxied when no answer
 * came through a CDN or proxy; no_webp when the first got no WebP (the
 * switch is off or the server made none); shared when one reader got the
 * other's copy: the edge keeps one copy for every browser.
 *
 * @param array $answers
 * @return string
 */
function pg_image_vary_verdict($answers)
{
    if (!is_array($answers) || (count($answers) !== 4)) {
        return 'unreachable';
    }

    $answers = array_values($answers);
    $proxied = false;

    foreach ($answers as $answer) {

        // A page (a login form, an error page) answered with 200 is no picture.
        if (!is_array($answer) || (($answer['status'] ?? 0) !== 200) || (strpos(pg_image_vary_answer_type($answer), 'image/') !== 0)) {
            return 'unreachable';
        }

        if (!empty($answer['proxied'])) {
            $proxied = true;
        }
    }

    if (!$proxied) {
        return 'not_proxied';
    }

    if (pg_image_vary_answer_type($answers[0]) !== 'image/webp') {
        return 'no_webp';
    }

    if (
        (pg_image_vary_answer_type($answers[1]) === 'image/webp')
        || (pg_image_vary_answer_type($answers[2]) !== 'image/webp')
        || (pg_image_vary_answer_type($answers[3]) === 'image/webp')
    ) {
        return 'shared';
    }

    if (empty($answers[2]['hit']) || empty($answers[3]['hit'])) {
        return 'not_cached';
    }

    return 'ok';
}

/**
 * The verdict of the private test: a fresh address answered private, asked
 * with WebP, then without. kept when the second still got WebP (the edge
 * keeps an answer marked private, and gives it to everyone); honoured when
 * it got the original; no_webp when the first got no WebP; unreachable when
 * one was not answered with 200 and a picture.
 *
 * @param array $answers
 * @return string
 */
function pg_image_vary_private_verdict($answers)
{
    if (!is_array($answers) || (count($answers) !== 2)) {
        return 'unreachable';
    }

    $answers = array_values($answers);

    foreach ($answers as $answer) {
        if (!is_array($answer) || (($answer['status'] ?? 0) !== 200) || (strpos(pg_image_vary_answer_type($answer), 'image/') !== 0)) {
            return 'unreachable';
        }
    }

    if (pg_image_vary_answer_type($answers[0]) !== 'image/webp') {
        return 'no_webp';
    }

    return (pg_image_vary_answer_type($answers[1]) === 'image/webp') ? 'kept' : 'honoured';
}

/**
 * What a test changes. cdn: set (the edge was seen keeping a copy per
 * format), clear (it was confirmed, but keeps one copy, or was not seen
 * keeping either), '' (unchanged). mode: public (confirmed), private
 * (pictures negotiated, kept by no shared cache), original (the edge keeps
 * private answers too), '' (keep the last mode: the test could not tell).
 * A confirmation the test cannot see through (not_proxied: the server's
 * requests reach no CDN) stays as the operator left it.
 *
 * @param string $verdict         pg_image_vary_verdict(), or no_sample
 * @param string $private_verdict pg_image_vary_private_verdict() ('' when not run)
 * @param bool   $cdn             bit 8 is set now
 * @return array cdn, mode
 */
function pg_image_vary_outcome($verdict, $private_verdict, $cdn)
{
    if ($verdict === 'ok') {
        return array('cdn' => $cdn ? '' : 'set', 'mode' => 'public');
    }

    if ($verdict === 'not_proxied') {
        return array('cdn' => '', 'mode' => 'private');
    }

    if ($verdict === 'shared') {
        $mode = '';

        if ($private_verdict === 'honoured') {
            $mode = 'private';
        } elseif ($private_verdict === 'kept') {
            $mode = 'original';
        }

        return array('cdn' => $cdn ? 'clear' : '', 'mode' => $mode);
    }

    if ($verdict === 'not_cached') {
        return array('cdn' => $cdn ? 'clear' : '', 'mode' => '');
    }

    return array('cdn' => '', 'mode' => '');
}

/**
 * Whether the general job runs the test now: the switch is on (bits 1 and
 * 4), and the last test is older than a day (a week when nothing was in
 * between), or was made under other bits (the switch was just turned on, the
 * confirmation ticked or taken back). A confirmed site is tested too: a
 * confirmation ticked by hand, or one the CDN's setup no longer keeps, would
 * otherwise send one copy to every reader for good.
 *
 * @param array|null $state pg_image_vary_state()
 * @param int        $bits  config.image_derivative_formats
 * @param int        $now
 * @return bool
 */
function pg_image_vary_test_due($state, $bits, $now)
{
    $bits = (int) $bits;

    if (($bits & 5) !== 5) {
        return false;
    }

    if (!is_array($state) || !isset($state['time'], $state['bits']) || !is_numeric($state['time']) || ((int) $state['bits'] !== $bits)) {
        return true;
    }

    $interval = (isset($state['verdict']) && ($state['verdict'] === 'not_proxied')) ? 604800 : 86400;

    return ((int) $now - (int) $state['time']) > $interval;
}

/**
 * Where the test's state is kept: beside the derivatives, where
 * get_file.php reads it. A purge of the derivative cache takes it along, and
 * the next job tick tests again.
 *
 * @return string '' when the file directory is not known
 */
function pg_image_vary_state_path()
{
    $directory = pg_image_derivative_directory();

    return ($directory !== '') ? $directory . '/same_address.json' : '';
}

/**
 * The stored state of the last test: mode, verdict, private (its verdict),
 * bits, time; null when there is none.
 *
 * @return array|null
 */
function pg_image_vary_state()
{
    $path = pg_image_vary_state_path();

    if ($path === '') {
        return null;
    }

    $state = @json_decode((string) @file_get_contents($path), true);

    return is_array($state) ? $state : null;
}

/**
 * One fetch of the sample: status, type, proxied, hit (it came out of a
 * cache), and why it failed.
 *
 * @param string $url
 * @param string $accept
 * @return array
 */
function pg_image_vary_fetch($url, $accept)
{
    $answer = pg_curl_run('GET', $url, array(
        'headers'          => array('Accept: ' . $accept),
        'response_headers' => true,
        'timeout'          => 20,
        'max_size'         => 52428800));

    $reason = '';

    if (!$answer['ok']) {
        $reason = ($answer['status'] > 0)
            ? lang(array('string' => 'The site\'s address answered with HTTP {var:1}.', 'vars' => $answer['status']))
            : (string) $answer['error'];
    }

    $headers = isset($answer['headers']) ? $answer['headers'] : '';

    return array(
        'status'  => (int) $answer['status'],
        'type'    => (string) $answer['content_type'],
        'proxied' => pg_image_vary_answer_proxied($headers),
        'hit'     => pg_image_vary_answer_hit($headers),
        'reason'  => $reason);
}

/**
 * The test itself: the newest public JPEG or PNG with a lighter WebP copy
 * (made here first, so the test does not wait for an encode slot) fetched
 * four times at a fresh public test address (pg_image_vary_verdict()); when
 * the edge keeps one copy, twice more at a fresh private one
 * (pg_image_vary_private_verdict()). Nothing is stored.
 *
 * @return array verdict (pg_image_vary_verdict(), or no_sample when no
 *               picture fits), private ('' when not run), file (its name),
 *               reason (why a fetch failed, not escaped)
 */
function pg_image_vary_check()
{
    $result = array('verdict' => 'no_sample', 'private' => '', 'file' => '', 'reason' => '');

    $settings = pg_image_delivery();
    $directory = pg_image_derivative_directory();

    // The newest public pictures first: a folder that is not public would
    // answer a login page.
    $rows = db_items(
        "SELECT id, name, folder
        FROM files
        WHERE type IN ('jpg', 'jpeg', 'jpe', 'png') AND attachment = '0' AND folder > 0
        ORDER BY id DESC
        LIMIT 30");

    $sample = null;

    foreach ((array) $rows as $row) {

        $path = FILE_DIRECTORY_PATH . '/' . $row['name'];

        if (pg_file_withheld($row['name']) || (get_access_control_type($row['folder']) !== 'public') || !is_file($path)) {
            continue;
        }

        $derivative = pg_image_derivative((int) $row['id'], $path, array('width' => 0, 'format' => 'webp'), $directory, $settings);

        if ($derivative['status'] === 'derivative') {
            $sample = $row;
            break;
        }
    }

    if ($sample === null) {
        return $result;
    }

    $result['file'] = $sample['name'];

    // Through the edge: the site's public address, https as a CDN serves it.
    $base = 'https://' . HOSTNAME_SETTING . PATH . encode_url_path($sample['name']) . '?pg_vary_check=';
    $webp = 'image/webp,image/*,*/*;q=0.8';
    $other = 'image/*,*/*;q=0.8';

    $collect = function ($url, $accepts) use (&$result) {
        $answers = array();

        foreach ($accepts as $accept) {
            $answer = pg_image_vary_fetch($url, $accept);

            if (($answer['reason'] !== '') && ($result['reason'] === '')) {
                $result['reason'] = $answer['reason'];
            }

            $answers[] = $answer;
        }

        return $answers;
    };

    $result['verdict'] = pg_image_vary_verdict($collect($base . bin2hex(random_bytes(8)), array($webp, $other, $webp, $other)));

    if ($result['verdict'] === 'shared') {
        $result['private'] = pg_image_vary_private_verdict($collect($base . bin2hex(random_bytes(8)) . '&pg_vary_cache=private', array($webp, $other)));
    }

    if (in_array('unreachable', array($result['verdict'], $result['private']), true) && ($result['reason'] === '')) {
        $result['reason'] = lang('The address did not answer with the picture.');
    }

    return $result;
}

/**
 * Runs the test and keeps what it found: bit 8 set or taken back
 * (pg_image_vary_outcome()), the mode, verdict and time in same_address.json,
 * the System Status cache dropped and the Site Log written when anything
 * changed.
 *
 * @param string $user who asked (the Site Log's user; UNKNOWN for the job)
 * @return array pg_image_vary_check(), plus mode (the mode now) and cdn
 *               (set, clear or '')
 */
function pg_image_vary_test_run($user)
{
    $bits = (int) pg_image_settings()['derivative_formats'];
    $check = pg_image_vary_check();
    $outcome = pg_image_vary_outcome($check['verdict'], $check['private'], ($bits & 8) === 8);

    if ($outcome['cdn'] === 'set') {
        db("UPDATE config SET image_derivative_formats = image_derivative_formats | 8");
        $bits = $bits | 8;
        log_activity(lang('WebP check passed: the CDN keeps a copy of each picture per format, so pictures are cached at the edge again.'), $user);
    } elseif ($outcome['cdn'] === 'clear') {
        db("UPDATE config SET image_derivative_formats = image_derivative_formats & ~8");
        $bits = $bits & ~8;
        log_activity(lang('WebP check failed: the confirmation that the CDN keeps a copy per format was taken back.'), $user);
    }

    $before = pg_image_vary_state();
    $mode = ($outcome['mode'] !== '') ? $outcome['mode'] : pg_image_same_address_mode($before);

    if (($mode === 'original') && (pg_image_same_address_mode($before) !== 'original')) {
        log_activity(lang('WebP check: the CDN keeps pictures marked private too, so it gets the original until the next check.'), $user);
    }

    $path = pg_image_vary_state_path();

    if ($path !== '') {
        $directory = dirname($path);

        if (@is_dir($directory) || @mkdir($directory, 0755, true)) {
            pg_image_derivative_put($path, json_encode(array(
                'mode'    => $mode,
                'verdict' => $check['verdict'],
                'private' => $check['private'],
                'bits'    => $bits,
                'time'    => time())));
        }
    }

    if (($outcome['cdn'] !== '') || ($mode !== pg_image_same_address_mode($before))) {
        @unlink(PG_FUNCTIONS_DIR . '/data/temp/system_status_cache.json');
    }

    $check['mode'] = $mode;
    $check['cdn'] = $outcome['cdn'];

    return $check;
}

/**
 * The general job's call: the test when pg_image_vary_test_due() says so.
 *
 * @return void
 */
function pg_image_vary_test_daily()
{
    if (!pg_image_delivery_ready() || !function_exists('pg_curl_run') || !defined('HOSTNAME_SETTING') || (HOSTNAME_SETTING === '')) {
        return;
    }

    if (!pg_image_vary_test_due(pg_image_vary_state(), (int) pg_image_settings()['derivative_formats'], time())) {
        return;
    }

    pg_image_vary_test_run('UNKNOWN');
}
