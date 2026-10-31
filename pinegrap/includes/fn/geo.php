<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Visitor location - the site's own IP-to-country database: when it is
 * needed, fetching it, turning it into the files includes/geo.php searches,
 * and what the settings screens say about it.
 *
 * The data is DB-IP's "IP to Country Lite" list (https://db-ip.com), free
 * under the Creative Commons Attribution 4.0 licence and published once a
 * month. It is fetched only while a feature that reads it is switched on, and
 * only by the general job or the settings screen - never on a visitor's
 * request. A fetch or a build that fails keeps the database that was there.
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
 * Whether a feature reads the database: the language redirect when it looks
 * at the visitor's location, and the firewall's country restriction while
 * the firewall is on.
 */
function pg_geo_db_needed()
{
    if (defined('LANGUAGE_REDIRECT') && LANGUAGE_REDIRECT
        && defined('LANGUAGE_REDIRECT_MODE') && (LANGUAGE_REDIRECT_MODE !== 'language')) {
        return true;
    }

    if (function_exists('waf_setting') && (waf_mode() !== 'off')
        && in_array((string) waf_setting('waf_country_mode', 'off'), array('allow', 'block'), true)) {
        return true;
    }

    return false;
}

/**
 * The month a database is named after ("2026-10"), for a timestamp.
 */
function pg_geo_db_month($timestamp)
{
    return gmdate('Y-m', (int) $timestamp);
}

/**
 * The addresses of a month's list and of the month before it: a month's list
 * appears during its first days, and until then the previous one is current.
 *
 * @param int $now
 * @return array version => url
 */
function pg_geo_db_source_urls($now)
{
    $this_month = pg_geo_db_month($now);
    $last_month = gmdate('Y-m', gmmktime(0, 0, 0, (int) gmdate('n', $now) - 1, 1, (int) gmdate('Y', $now)));

    return array(
        $this_month => 'https://download.db-ip.com/free/dbip-country-lite-' . $this_month . '.csv.gz',
        $last_month => 'https://download.db-ip.com/free/dbip-country-lite-' . $last_month . '.csv.gz',
    );
}

/**
 * Whether the general job should fetch the database now: a feature needs it,
 * and there is none or it is not this month's, and the last attempt was more
 * than twelve hours ago. Costs a stat and a small file read.
 */
function pg_geo_db_due()
{
    if (!pg_geo_db_needed()) {
        return false;
    }

    $meta = pg_geo_db_meta();

    if (is_array($meta) && (($meta['version'] ?? '') === pg_geo_db_month(time()))) {
        return false;
    }

    return !pg_geo_db_tried_recently();
}

/**
 * Whether a download was tried in the last twelve hours, successful or not.
 */
function pg_geo_db_tried_recently()
{
    $attempt = pg_geo_db_dir() . '/attempt.txt';

    return (is_file($attempt) && ((int) @file_get_contents($attempt) > time() - 43200));
}

/**
 * Fetches the newest list and builds the database from it.
 *
 * @param bool $force true from the settings screen's button: no twelve-hour
 *                    wait, and a list of the version already built is built
 *                    again
 * @return array status (updated | fresh | failed | busy), version, reason
 */
function pg_geo_db_refresh($force = false)
{
    $dir = pg_geo_db_dir();

    if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
        return array('status' => 'failed', 'version' => '', 'reason' => lang('The folder data/geo could not be created.'));
    }

    // One refresh at a time: the general job and the button may meet.
    $lock = @fopen($dir . '/refresh.lock', 'c');

    if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
        if ($lock) {
            fclose($lock);
        }

        return array('status' => 'busy', 'version' => '', 'reason' => '');
    }

    @file_put_contents($dir . '/attempt.txt', (string) time());

    if (function_exists('set_time_limit')) {
        @set_time_limit(300);
    }

    $meta = pg_geo_db_meta();
    $result = array('status' => 'failed', 'version' => '', 'reason' => '');

    foreach (pg_geo_db_source_urls(time()) as $version => $url) {

        if (!$force && is_array($meta) && (($meta['version'] ?? '') === $version)) {
            $result = array('status' => 'fresh', 'version' => $version, 'reason' => '');
            break;
        }

        $download = $dir . '/download-' . bin2hex(random_bytes(6)) . '.csv.gz';
        $fetched = pg_geo_db_download($url, $download);

        if ($fetched['status'] === 'missing') {
            // Not published yet: the month before is the current one.
            @unlink($download);
            $result['reason'] = $fetched['reason'];
            continue;
        }

        if ($fetched['status'] !== 'ok') {
            @unlink($download);
            $result = array('status' => 'failed', 'version' => $version, 'reason' => $fetched['reason']);
            break;
        }

        $built = pg_geo_db_build($download, $dir, $version);
        @unlink($download);

        $result = $built['ok']
            ? array('status' => 'updated', 'version' => $version, 'reason' => '')
            : array('status' => 'failed', 'version' => $version, 'reason' => $built['reason']);
        break;
    }

    flock($lock, LOCK_UN);
    fclose($lock);

    return $result;
}

/**
 * Fetches the database for a settings screen and says how it went: when the
 * button asks for it, and on its own when a setting that reads the visitor's
 * location is saved and the site has none yet, at most once in twelve hours,
 * so a server that cannot download does not make every save wait. After that
 * the general job keeps it current.
 *
 * @param object $liveform the screen's form, for the notices
 * @param bool   $forced   the "download now" button was pressed
 * @param bool   $wanted   a setting saved in this request reads the location
 */
function pg_geo_db_refresh_for_screen($liveform, $forced, $wanted)
{
    if (!$forced && !($wanted && (pg_geo_db_meta() === null) && !pg_geo_db_tried_recently())) {
        return;
    }

    $refresh = pg_geo_db_refresh($forced);

    if ($refresh['status'] === 'updated') {
        $liveform->add_notice(lang(array(
            'string' => 'The location database was downloaded ({var:1}).',
            'vars'   => array($refresh['version']))));
    } elseif ($refresh['status'] === 'fresh') {
        $liveform->add_notice(lang('The location database is already the newest one.'));
    } elseif ($refresh['status'] === 'busy') {
        $liveform->add_notice(lang('The location database is being downloaded by another request. Look again in a minute.'));
    } else {
        $liveform->add_notice(lang(array(
            'string' => 'The location database could not be downloaded: {var:1}. The country Cloudflare sends is still used.',
            'vars'   => array(($refresh['reason'] !== '') ? h($refresh['reason']) : lang('unknown error')))));
    }
}

/**
 * Downloads one list to a file.
 *
 * @param string $url
 * @param string $target
 * @return array status (ok | missing | failed), reason
 */
function pg_geo_db_download($url, $target)
{
    if (!function_exists('curl_init')) {
        return array('status' => 'failed', 'reason' => lang('The PHP cURL extension is not enabled.'));
    }

    $file = @fopen($target, 'wb');

    if (!$file) {
        return array('status' => 'failed', 'reason' => lang('The folder data/geo is not writable.'));
    }

    $ch = curl_init($url);

    curl_setopt_array($ch, array(
        CURLOPT_FILE           => $file,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 3,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT        => 60,
        CURLOPT_FAILONERROR    => false,
    ));

    // A request with no user agent is turned away by firewalls, this site's
    // own included (see pinegrap_user_agent()).
    curl_setopt($ch, CURLOPT_USERAGENT, function_exists('pinegrap_user_agent') ? pinegrap_user_agent() : 'Pinegrap');

    if (function_exists('pg_curl_tls')) {
        pg_curl_tls($ch);
    }

    $ok = curl_exec($ch);
    $errno = curl_errno($ch);
    $error = curl_error($ch);
    $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    unset($ch);
    fclose($file);

    if (($ok === false) || $errno) {
        return array('status' => 'failed', 'reason' => 'cURL ' . $errno . ': ' . $error . (function_exists('pg_curl_tls_hint') ? pg_curl_tls_hint($errno) : ''));
    }

    if ($http === 404) {
        return array('status' => 'missing', 'reason' => 'HTTP 404');
    }

    if ($http !== 200) {
        return array('status' => 'failed', 'reason' => 'HTTP ' . $http);
    }

    clearstatcache(true, $target);

    if (filesize($target) < 1024) {
        return array('status' => 'failed', 'reason' => lang('The downloaded list is empty.'));
    }

    return array('status' => 'ok', 'reason' => '');
}

/**
 * One line of the list: "start,end,country", the addresses written out, IPv4
 * and IPv6 in the same file.
 *
 * @param string $line
 * @return array|null family (4 or 6), start and end as binary keys (4 bytes,
 *                    or the 8 bytes of the /64 for IPv6), country ('--' for
 *                    none); null for a line that is not a range
 */
function pg_geo_db_parse_line($line)
{
    $parts = explode(',', trim((string) $line));

    if (count($parts) < 3) {
        return null;
    }

    $start = @inet_pton(trim($parts[0], " \t\""));
    $end = @inet_pton(trim($parts[1], " \t\""));

    if (($start === false) || ($end === false) || (strlen($start) !== strlen($end))) {
        return null;
    }

    $code = pg_geo_normalize_code(trim($parts[2], " \t\"\r\n"));

    if (strlen($start) === 16) {
        return array('family' => 6, 'start' => substr($start, 0, 8), 'end' => substr($end, 0, 8), 'code' => ($code !== '') ? $code : '--');
    }

    return array('family' => 4, 'start' => $start, 'end' => $end, 'code' => ($code !== '') ? $code : '--');
}

/**
 * The key after a key: the binary string read as one big-endian number, plus
 * one. Null when it is already the last one (all bits set).
 *
 * @param string $binary
 * @return string|null
 */
function pg_geo_bin_increment($binary)
{
    for ($i = strlen($binary) - 1; $i >= 0; $i--) {
        $byte = ord($binary[$i]);

        if ($byte < 255) {
            $binary[$i] = chr($byte + 1);
            return $binary;
        }

        $binary[$i] = "\0";
    }

    return null;
}

/**
 * The records of one family, from ranges in address order: neighbouring
 * ranges of one country become one record, a hole between two ranges becomes
 * a record of no country, and so does everything after the last range, so
 * the last record not above an address always describes it. A range that
 * overlaps the one before it starts where that one ends (an IPv6 range
 * smaller than a /64 lands on the key of the range before it).
 *
 * Called once per range, then once with null to finish. Returns the records
 * that are complete, each as key . country.
 *
 * @param array      $state by reference; start with array()
 * @param array|null $range from pg_geo_db_parse_line(), or null at the end
 * @return array|false the finished records, false when the ranges are out of order
 */
function pg_geo_db_records(&$state, $range)
{
    $out = array();

    if ($range === null) {
        if (!empty($state['open'])) {
            $out[] = $state['start'] . $state['code'];

            $next = pg_geo_bin_increment($state['end']);

            if ($next !== null) {
                $out[] = $next . '--';
            }
        }

        $state = array();

        return $out;
    }

    $start = $range['start'];
    $end = $range['end'];

    if (strcmp($end, $start) < 0) {
        return $out;
    }

    if (empty($state['open'])) {
        // Addresses before the first range belong to no country.
        if (trim($start, "\0") !== '') {
            $out[] = str_repeat("\0", strlen($start)) . '--';
        }

        $state = array('open' => true, 'start' => $start, 'end' => $end, 'code' => $range['code']);

        return $out;
    }

    if (strcmp($start, $state['start']) < 0) {
        return false;
    }

    if (strcmp($end, $state['end']) <= 0) {
        // Inside the open range.
        return $out;
    }

    $next = pg_geo_bin_increment($state['end']);

    if (strcmp($start, $next) < 0) {
        $start = $next;
    }

    if (($start === $next) && ($range['code'] === $state['code'])) {
        $state['end'] = $end;
        return $out;
    }

    $out[] = $state['start'] . $state['code'];

    if (strcmp($start, $next) > 0) {
        $out[] = $next . '--';
    }

    $state = array('open' => true, 'start' => $start, 'end' => $end, 'code' => $range['code']);

    return $out;
}

/**
 * Builds the two files from a downloaded list (gzip or plain) and makes them
 * current. The files are named after the build, and meta.json, written last,
 * says which pair is current: a request that is reading the old pair goes on
 * reading it, and nothing is replaced while it is open (which Windows
 * refuses).
 *
 * @param string $source
 * @param string $dir
 * @param string $version the month of the list
 * @return array ok, reason, rows4, rows6
 */
function pg_geo_db_build($source, $dir, $version)
{
    $fail = function ($reason) {
        return array('ok' => false, 'reason' => $reason, 'rows4' => 0, 'rows6' => 0);
    };

    if (!function_exists('gzopen')) {
        return $fail(lang('The PHP zlib extension is not enabled.'));
    }

    // gzopen() reads a plain file as it is, so a list that arrived unpacked
    // is read the same way.
    $in = @gzopen($source, 'rb');

    if (!$in) {
        return $fail(lang('The downloaded list could not be opened.'));
    }

    $stamp = gmdate('YmdHis') . '-' . bin2hex(random_bytes(3));
    $names = array(4 => 'country-' . $stamp . '-4.dat', 6 => 'country-' . $stamp . '-6.dat');
    $files = array();
    $states = array(4 => array(), 6 => array());
    $counts = array(4 => 0, 6 => 0);
    $buffers = array(4 => '', 6 => '');

    foreach ($names as $family => $name) {
        $files[$family] = @fopen($dir . '/' . $name, 'wb');

        if (!$files[$family]) {
            return $fail(lang('The folder data/geo is not writable.'));
        }

        // The header is written again with the count once the records are in.
        fwrite($files[$family], str_repeat("\0", PG_GEO_HEADER_BYTES));
    }

    $close = function ($remove) use (&$files, $names, $dir, $in) {
        foreach ($files as $family => $handle) {
            if (is_resource($handle)) {
                fclose($handle);
            }

            if ($remove) {
                @unlink($dir . '/' . $names[$family]);
            }
        }

        if (is_resource($in)) {
            gzclose($in);
        }
    };

    while (true) {
        $line = gzgets($in, 512);

        $range = ($line === false) ? null : pg_geo_db_parse_line($line);

        if (($line !== false) && ($range === null)) {
            continue;
        }

        foreach (($line === false) ? array(4, 6) : array($range['family']) as $family) {
            $records = pg_geo_db_records($states[$family], ($line === false) ? null : $range);

            if ($records === false) {
                $close(true);
                return $fail(lang('The downloaded list is not in address order.'));
            }

            foreach ($records as $record) {
                $buffers[$family] .= $record;
                $counts[$family]++;
            }

            if (strlen($buffers[$family]) > 65536) {
                fwrite($files[$family], $buffers[$family]);
                $buffers[$family] = '';
            }
        }

        if ($line === false) {
            break;
        }
    }

    // A list cut short or of another format would give a database that
    // places nobody; the one there is kept instead.
    if (($counts[4] < 1000) || ($counts[6] < 1000)) {
        $close(true);
        return $fail(lang('The downloaded list is incomplete.'));
    }

    foreach (array(4 => 4, 6 => 8) as $family => $key_bytes) {
        fwrite($files[$family], $buffers[$family]);
        fseek($files[$family], 0);
        fwrite($files[$family], PG_GEO_MAGIC . (string) $family . chr($key_bytes) . pack('N', $counts[$family]) . "\0\0\0\0");
    }

    $close(false);

    $meta = array(
        'source'   => 'db-ip',
        'version'  => (string) $version,
        'built_at' => time(),
        'file4'    => $names[4],
        'file6'    => $names[6],
        'rows4'    => $counts[4],
        'rows6'    => $counts[6],
    );

    $temp = $dir . '/meta-' . $stamp . '.json';

    if (@file_put_contents($temp, json_encode($meta)) === false) {
        $close(true);
        return $fail(lang('The folder data/geo is not writable.'));
    }

    // A request reading meta.json at this moment holds it open, which on
    // Windows makes the rename fail; it is over within milliseconds.
    $renamed = false;

    for ($attempt = 0; $attempt < 20; $attempt++) {
        if (@rename($temp, $dir . '/meta.json')) {
            $renamed = true;
            break;
        }

        usleep(100000);
    }

    if (!$renamed) {
        @unlink($temp);

        foreach ($names as $name) {
            @unlink($dir . '/' . $name);
        }

        return $fail(lang('The folder data/geo is not writable.'));
    }

    // The previous pairs. One still open is left for the next build.
    $old_files = glob($dir . '/country-*.dat');

    foreach (is_array($old_files) ? $old_files : array() as $old) {
        if (!in_array(basename($old), $names, true)) {
            @unlink($old);
        }
    }

    return array('ok' => true, 'reason' => '', 'rows4' => $counts[4], 'rows6' => $counts[6]);
}

/**
 * What the settings screens say about the location of the request that
 * opened them and about the database.
 *
 * @return array code, source, source_label, name, database (null or meta)
 */
function pg_geo_status()
{
    $country = pg_geo_country();

    return array(
        'code'         => $country['code'],
        'source'       => $country['source'],
        'source_label' => pg_geo_source_label($country['source']),
        'name'         => ($country['code'] !== '') ? pg_geo_country_name($country['code']) : '',
        'database'     => pg_geo_db_meta(),
    );
}

/**
 * The lines the settings screens show under a setting that reads the
 * visitor's location: where this request comes from, the database and its
 * age, the attribution DB-IP's licence asks for, and the button that fetches
 * the database now (the screen's save handler answers it with
 * pg_geo_db_refresh_for_screen()).
 *
 * @return string HTML
 */
function pg_geo_settings_note()
{
    $status = pg_geo_status();

    $location = ($status['code'] !== '')
        ? lang(array(
            'string' => 'Location of this request: {var:1} ({var:2}), from {var:3}.',
            'vars'   => array($status['name'], $status['code'], $status['source_label'])))
        : lang('The location of this request is not known.');

    $database = is_array($status['database'])
        ? lang(array(
            'string' => 'Location database: {var:1}, {var:2} address ranges, built on {var:3}.',
            'vars'   => array((string) $status['database']['version'], pg_format_number((int) $status['database']['rows4'] + (int) $status['database']['rows6'], 0), get_absolute_time(array('timestamp' => (int) $status['database']['built_at'], 'type' => 'date', 'format' => 'plain_text')))))
        : lang('There is no location database yet. It is downloaded when a setting needs the visitor\'s location: on saving, and then once a month by the general job.');

    return '
                        <div class="form-text">
                            <i class="bi bi-geo-alt me-1" aria-hidden="true"></i>' . h($location) . '<br/>
                            <i class="bi bi-database me-1" aria-hidden="true"></i>' . h($database) . '<br/>
                            ' . lang('Behind Cloudflare the country comes with every request; the database answers when it does not, for example for a request that reaches the server directly.') . ' ' . lang(array(
                                'string' => 'IP geolocation by {var:1}.',
                                'vars'   => array('<a href="https://db-ip.com" target="_blank" rel="noopener">DB-IP</a>'))) . '
                        </div>
                        <button type="submit" name="geo_db_refresh" value="1" class="btn btn-sm btn-outline theme-secondary mt-3"><i class="bi bi-cloud-arrow-down me-1" aria-hidden="true"></i>' . lang('Download the location database now') . '</button>';
}

/**
 * The name of a location source, for the settings screens.
 */
function pg_geo_source_label($source)
{
    $labels = array(
        'cloudflare' => 'Cloudflare',
        'cloudfront' => 'AWS CloudFront',
        'server'     => lang('the web server'),
        'database'   => lang('the location database'),
    );

    return isset($labels[$source]) ? $labels[$source] : '';
}

/**
 * A country's name in the panel language: from the intl extension when the
 * server has it, otherwise the English name the sales map keeps.
 *
 * @param string $code two letters, or T1
 * @return string
 */
function pg_geo_country_name($code)
{
    $code = strtoupper((string) $code);

    if ($code === 'T1') {
        return lang('Tor network');
    }

    if (class_exists('Locale')) {
        $locale = function_exists('lang') ? (string) lang(array('info' => '')) : 'en';
        $name = Locale::getDisplayRegion('und_' . $code, ($locale !== '') ? $locale : 'en');

        if (is_string($name) && ($name !== '') && ($name !== $code)) {
            return $name;
        }
    }

    require_once(PG_FUNCTIONS_DIR . '/includes/sales_map.php');

    $countries = pg_sales_map_iso_countries();

    return isset($countries[$code]) ? $countries[$code] : $code;
}

/**
 * A stored country as the panel and the site name it: the stored (English)
 * name through lang() when the shards know it ("Turkey" becomes "Türkiye"),
 * else the name intl gives for its ISO code in the current language
 * (pg_geo_country_name(): "Aland Islands" becomes "Åland Adaları"), else the
 * stored name. The stored name and code stay what they are: zones, orders
 * and the accounting accounts match on the stored name.
 *
 * @param string $name countries.name
 * @param string $code countries.code
 * @return string
 */
function pg_geo_country_label($name, $code = '')
{
    $name = trim((string) $name);
    $code = strtoupper(trim((string) $code));

    if ($name !== '') {
        $translated = (string) lang($name);

        if ($translated !== $name) {
            return $translated;
        }
    }

    // intl names a code it does not know "Unknown Region" in the panel's
    // language instead of refusing it: that answer, read once for a code
    // nobody has, is not a name.
    if (preg_match('/^[A-Z]{2}$/', $code)) {
        $from_code = (string) pg_geo_country_name($code);
        $unknown = (string) pg_geo_country_name('ZZ');

        if (($from_code !== '') && ($from_code !== $code) && ($from_code !== $unknown)) {
            return $from_code;
        }
    }

    return $name;
}

/**
 * A country stored on an order or an address, named in the current language.
 * Checkout stores the two-letter code; older rows and imports may hold the
 * name. A code in $names_by_code is named from its row, an unknown code from
 * intl, falling back to the code; any other text is named as a stored name,
 * falling back to itself.
 *
 * @param  mixed $stored        the stored value (code or name)
 * @param  array $names_by_code countries.code => countries.name
 * @return string Plain text; '' for an empty value.
 */
function pg_geo_country_label_stored($stored, array $names_by_code)
{
    $stored = trim((string) $stored);

    if ($stored === '') {
        return '';
    }

    if (preg_match('/^[A-Za-z]{2}$/', $stored)) {
        $code = strtoupper($stored);
        $label = isset($names_by_code[$code])
            ? pg_geo_country_label((string) $names_by_code[$code], $code)
            : pg_geo_country_label('', $code);

        return ($label !== '') ? $label : $stored;
    }

    $label = pg_geo_country_label($stored);

    return ($label !== '') ? $label : $stored;
}

/**
 * pg_geo_country_label_stored() against the site's countries table, read
 * once per request.
 *
 * @param  mixed $stored the stored value (code or name)
 * @return string Plain text.
 */
function pg_geo_country_label_site($stored)
{
    static $names_by_code = null;

    if ($names_by_code === null) {
        $names_by_code = array();
        $rows = function_exists('db_items') ? db_items("SELECT code, name FROM countries") : array();
        foreach ((is_array($rows) ? $rows : array()) as $row) {
            $code = strtoupper(trim((string) $row['code']));
            if ($code !== '' && !isset($names_by_code[$code])) {
                $names_by_code[$code] = (string) $row['name'];
            }
        }
    }

    return pg_geo_country_label_stored($stored, $names_by_code);
}

/**
 * Two labels compared the way the current language orders them: the intl
 * Collator of the panel language when PHP has it ("Çin" after "Brezilya",
 * not after "Zimbabve"), else a case-insensitive byte order. Answers below,
 * at or above 0, like strcmp().
 *
 * @param string $x
 * @param string $y
 * @return int
 */
function pg_geo_label_compare($x, $y)
{
    static $collators = array();

    $locale = function_exists('lang') ? (string) lang(array('info' => '')) : 'en';

    if (!array_key_exists($locale, $collators)) {
        $collators[$locale] = (class_exists('Collator') && ($locale !== '')) ? new Collator($locale) : null;
    }

    if ($collators[$locale] !== null) {
        $result = $collators[$locale]->compare((string) $x, (string) $y);

        return ($result === false) ? 0 : (int) $result;
    }

    return strcasecmp((string) $x, (string) $y);
}

/**
 * The rows of a country list in the order of their labels in the current
 * language (pg_geo_label_compare()). $rows is a list of arrays that carry
 * $label_key.
 *
 * @param array  $rows
 * @param string $label_key
 * @return array
 */
function pg_geo_sort_by_label(array $rows, $label_key = 'label')
{
    usort($rows, function ($a, $b) use ($label_key) {
        $x = isset($a[$label_key]) ? (string) $a[$label_key] : '';
        $y = isset($b[$label_key]) ? (string) $b[$label_key] : '';

        return pg_geo_label_compare($x, $y);
    });

    return $rows;
}
