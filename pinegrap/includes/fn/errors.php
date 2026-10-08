<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Central record of PHP errors and uncaught exceptions.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// An uncaught exception or a fatal error reaches the operator only through
// the web server's error log, if the host keeps one and lets the site read it;
// on most shared hosts that is nowhere. The handlers below write every one of
// them, one JSON line each, to data/temp/php_errors.log, which the site log
// screen (view_log.php) reads next to the error_log files.
//
// The handlers only record. Every error handler call returns false, so PHP's
// own handling (display_errors, error_log) carries on exactly as before, and
// the exception handler ends the request the way PHP would have. Nothing here
// touches the database: the shutdown handler runs after a fatal error, when
// the connection may be gone, and a failed query must not become a second
// error inside the first one.

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/**
 * Install the exception, error and shutdown handlers. Safe to call twice.
 *
 * Installed for CLI runs too: the scheduled jobs are where an error is least
 * likely to be seen by anyone.
 *
 * @return void
 */
function pg_error_install()
{
    static $installed = false;

    if ($installed) {
        return;
    }

    $installed = true;

    pg_error_previous_handler('exception', set_exception_handler('pg_error_uncaught'));
    pg_error_previous_handler('error', set_error_handler('pg_error_record'));
    register_shutdown_function('pg_error_shutdown');
}

/**
 * The handler that was in place before pg_error_install(), so it still runs.
 *
 * @param string $kind 'exception' or 'error'
 * @param callable|null|false $handler false reads, anything else stores
 * @return callable|null
 */
function pg_error_previous_handler($kind, $handler = false)
{
    static $previous = array('exception' => null, 'error' => null);

    if ($handler !== false) {
        $previous[$kind] = $handler;
    }

    return isset($previous[$kind]) ? $previous[$kind] : null;
}

/**
 * Error handler: records the error, then lets PHP handle it as usual.
 *
 * @param int $errno
 * @param string $errstr
 * @param string $errfile
 * @param int $errline
 * @return bool
 */
function pg_error_record($errno, $errstr, $errfile = '', $errline = 0)
{
    $errno = (int) $errno;
    $reporting = error_reporting();

    // The @ operator lowers error_reporting() for the length of the call: to 0
    // up to PHP 7, to the fatal mask (4437) from PHP 8. The software has
    // thousands of @mysqli_query() and similar calls whose failure is handled
    // where they are written; none of those is recorded.
    $silenced = ($reporting === 0)
        || ($reporting === (E_ERROR | E_CORE_ERROR | E_COMPILE_ERROR | E_USER_ERROR | E_RECOVERABLE_ERROR | E_PARSE));

    $reported = (bool) ($reporting & $errno);

    // init.php leaves deprecations out of error_reporting. With debugging on
    // they are recorded anyway: that list is what the next PHP version breaks.
    if (!$reported && !$silenced && ($errno & (E_DEPRECATED | E_USER_DEPRECATED)) && defined('DEBUG') && DEBUG) {
        $reported = true;
    }

    if ($reported) {
        $level = pg_error_level_name($errno);

        if ($level === 'fatal') {
            $record = pg_error_once('fatal|' . $errfile . ':' . $errline . '|' . $errstr);
        } else {
            $record = pg_error_flood_allows($errfile . ':' . $errline);
        }

        if ($record) {
            // The first frame is this handler.
            $frames = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 11);
            array_shift($frames);

            pg_error_write(pg_error_entry(
                $level,
                pg_error_type_name($errno),
                $errstr,
                $errfile,
                $errline,
                pg_error_trace_short($frames)));
        }
    }

    $previous = pg_error_previous_handler('error');

    if (is_callable($previous)) {
        return call_user_func($previous, $errno, $errstr, $errfile, $errline);
    }

    return false;
}

/**
 * Whether one more warning/notice/deprecation may be recorded this request.
 *
 * A loop that warns on every pass would otherwise write the same line ten
 * thousand times in one request: each file:line is recorded once, and no more
 * than 50 lines in all.
 *
 * @param string $key file:line
 * @return bool
 */
function pg_error_flood_allows($key)
{
    static $count = 0;

    if ($count >= 50) {
        return false;
    }

    if (!pg_error_once('flood|' . $key)) {
        return false;
    }

    $count++;

    return true;
}

/**
 * True the first time a key is seen in this request.
 *
 * Also keeps a fatal error from being written twice: an E_USER_ERROR goes
 * through the error handler and is then reported again by error_get_last().
 *
 * @param string $key
 * @return bool
 */
function pg_error_once($key)
{
    static $seen = array();

    if (isset($seen[$key])) {
        return false;
    }

    $seen[$key] = true;

    return true;
}

/**
 * Exception handler: records the uncaught exception and ends the request.
 *
 * output_error() throws a RuntimeException while the SEO pass or a JSON
 * endpoint has asked it to; those callers catch it, so it only lands here when
 * one of them did not.
 *
 * @param Throwable $throwable
 * @return void
 */
function pg_error_uncaught($throwable)
{
    pg_error_once('fatal|' . $throwable->getFile() . ':' . $throwable->getLine() . '|' . $throwable->getMessage());

    pg_error_write(pg_error_entry(
        'fatal',
        get_class($throwable),
        $throwable->getMessage(),
        $throwable->getFile(),
        $throwable->getLine(),
        pg_error_trace_short($throwable->getTrace())));

    $previous = pg_error_previous_handler('exception');

    if (is_callable($previous)) {
        call_user_func($previous, $throwable);
        return;
    }

    // What PHP does without a handler: a 500, the message where display is
    // wanted, exit status 255.
    $detail = 'Uncaught ' . get_class($throwable) . ': ' . $throwable->getMessage()
        . ' in ' . $throwable->getFile() . ':' . $throwable->getLine();

    if (PHP_SAPI === 'cli') {
        $stream = defined('STDERR') ? STDERR : @fopen('php://stderr', 'w');

        if ($stream) {
            @fwrite($stream, 'PHP Fatal error:  ' . $detail . PHP_EOL);
        }
    } else {
        if (!headers_sent()) {
            http_response_code(500);
        }

        if (defined('DEBUG') && DEBUG) {
            $message = $detail;
        } else {
            // lang() may itself be what failed; the request is ending either way.
            try {
                $message = lang('An unexpected error occurred.');
            } catch (Throwable $error) {
                $message = 'An unexpected error occurred.';
            }
        }

        echo htmlspecialchars((string) $message, ENT_QUOTES, 'UTF-8');
    }

    exit(255);
}

/**
 * Shutdown handler: records a fatal error that ended the request.
 *
 * Fatal errors never reach the error handler, error_get_last() is the only
 * place they show. There is no stack left to read.
 *
 * @return void
 */
function pg_error_shutdown()
{
    $error = error_get_last();

    if (!is_array($error)) {
        return;
    }

    $fatal = E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR | E_USER_ERROR | E_RECOVERABLE_ERROR;

    if (!((int) $error['type'] & $fatal)) {
        return;
    }

    if (!pg_error_once('fatal|' . $error['file'] . ':' . $error['line'] . '|' . $error['message'])) {
        return;
    }

    // An exception that got past the exception handler (thrown inside it, or
    // code run with php -r, which never calls it) arrives here as PHP's own
    // "Uncaught ..." text, whose stack trace can carry call arguments.
    $message = preg_replace('/\s*Stack trace:.*$/s', '', (string) $error['message']);

    pg_error_write(pg_error_entry(
        'fatal',
        pg_error_type_name((int) $error['type']),
        $message,
        $error['file'],
        $error['line'],
        array()));
}

/**
 * The record of one error, with what is known about the request.
 *
 * @param string $level
 * @param string $type
 * @param string $message
 * @param string $file
 * @param int $line
 * @param string[] $trace
 * @return array
 */
function pg_error_entry($level, $type, $message, $file, $line, array $trace)
{
    if (isset($_SERVER['REQUEST_URI'])) {
        $url = (string) $_SERVER['REQUEST_URI'];
    } else {
        $url = isset($_SERVER['SCRIPT_NAME']) ? (string) $_SERVER['SCRIPT_NAME'] : '';
    }

    return array(
        'level'      => (string) $level,
        'type'       => (string) $type,
        'message'    => (string) $message,
        'file'       => (string) $file,
        'line'       => (int) $line,
        'request_id' => pg_error_request_id(),
        'method'     => isset($_SERVER['REQUEST_METHOD']) ? (string) $_SERVER['REQUEST_METHOD'] : 'CLI',
        'url'        => pg_error_mask_url($url),
        'user_id'    => defined('USER_ID') ? (int) USER_ID : 0,
        'ip'         => isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : '',
        'trace'      => $trace,
    );
}

/**
 * A short id shared by every line one request writes.
 *
 * @return string
 */
function pg_error_request_id()
{
    static $id = null;

    if ($id === null) {
        $id = '';

        if (function_exists('random_bytes')) {
            try {
                $id = substr(bin2hex(random_bytes(6)), 0, 12);
            } catch (Throwable $error) {
                $id = '';
            }
        }

        if ($id === '') {
            $id = substr(uniqid(), -12);
        }
    }

    return $id;
}

/**
 * Append one entry to the log, rotating it first when it has grown too big.
 *
 * Silent when the file cannot be written: a full disk or a read-only data
 * folder must not turn one error into another.
 *
 * @param array $entry
 * @return void
 */
function pg_error_write(array $entry)
{
    static $busy = false;

    // A warning raised while writing would come straight back here.
    if ($busy) {
        return;
    }

    $busy = true;

    $path = pg_error_log_path();
    $directory = dirname($path);

    if (!is_dir($directory)) {
        @mkdir($directory, 0755, true);
    }

    // 5 MB per file, the current one and two older ones.
    $size = @filesize($path);
    $plan = pg_error_rotate_plan(($size === false) ? 0 : $size, 5 * 1024 * 1024, 3);

    foreach ($plan as $step) {
        // rename() does not replace an existing file on Windows.
        if (file_exists($path . $step[1])) {
            @unlink($path . $step[1]);
        }

        if (file_exists($path . $step[0])) {
            @rename($path . $step[0], $path . $step[1]);
        }
    }

    if (count($plan) > 0) {
        clearstatcache();
    }

    @file_put_contents($path, pg_error_format_line($entry, time()) . "\n", FILE_APPEND | LOCK_EX);

    $busy = false;
}

/**
 * Where the log is written.
 *
 * @return string
 */
function pg_error_log_path()
{
    return PG_FUNCTIONS_DIR . '/data/temp/php_errors.log';
}

/**
 * The log and its rotated copies that exist, newest first.
 *
 * @return string[]
 */
function pg_error_log_files()
{
    $path = pg_error_log_path();
    $files = array();

    foreach (array('', '.1', '.2') as $suffix) {
        if (is_file($path . $suffix)) {
            $files[] = $path . $suffix;
        }
    }

    return $files;
}

/**
 * The renames that rotate a log of the given size.
 *
 * Each step is array(from suffix, to suffix) relative to the log's own path
 * ('' is the log itself) and is carried out in order; the oldest copy is
 * dropped by being renamed over.
 *
 * @param int $size current size in bytes
 * @param int $limit size above which the log is rotated
 * @param int $keep files kept, the log included (at least 2)
 * @return array
 */
function pg_error_rotate_plan($size, $limit, $keep)
{
    if ((int) $size <= (int) $limit) {
        return array();
    }

    $keep = max(2, (int) $keep);
    $plan = array();

    for ($i = $keep - 2; $i >= 1; $i--) {
        $plan[] = array('.' . $i, '.' . ($i + 1));
    }

    $plan[] = array('', '.1');

    return $plan;
}

/**
 * One log line: "[Y-m-d H:i:s] {json}".
 *
 * The date prefix is the form view_log.php already recognises as the start
 * of an entry. json_encode() escapes line breaks, so an entry is always one
 * line.
 *
 * @param array $entry
 * @param int $timestamp
 * @return string
 */
function pg_error_format_line(array $entry, $timestamp)
{
    $flags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;

    // PHP 7.2+. Without it a message with broken UTF-8 would fail to encode
    // altogether; on 7.1 only that value is lost.
    if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
        $flags |= JSON_INVALID_UTF8_SUBSTITUTE;
    } else {
        $flags |= JSON_PARTIAL_OUTPUT_ON_ERROR;
    }

    $json = json_encode($entry, $flags);

    if (!is_string($json)) {
        $json = '{}';
    }

    return '[' . date('Y-m-d H:i:s', (int) $timestamp) . '] ' . $json;
}

/**
 * A stack trace as "file:line class->function()" lines, without arguments.
 *
 * Arguments are left out on purpose: they carry passwords, card numbers and
 * whole request bodies, which is also why getTraceAsString() is not used.
 *
 * @param array $frames frames as debug_backtrace() or getTrace() return them
 * @param int $max
 * @return string[]
 */
function pg_error_trace_short(array $frames, $max = 10)
{
    $lines = array();

    foreach (array_slice($frames, 0, (int) $max) as $frame) {
        if (!is_array($frame)) {
            continue;
        }

        $where = isset($frame['file'])
            ? $frame['file'] . ':' . (isset($frame['line']) ? (int) $frame['line'] : 0)
            : '[internal]';

        $call = isset($frame['function']) ? (string) $frame['function'] : '';

        if (isset($frame['class'])) {
            $call = $frame['class'] . (isset($frame['type']) ? $frame['type'] : '::') . $call;
        }

        $lines[] = $where . ($call !== '' ? ' ' . $call . '()' : '');
    }

    return $lines;
}

/**
 * The level an error number is recorded under.
 *
 * @param int $errno
 * @return string fatal|error|warning|notice|deprecated
 */
function pg_error_level_name($errno)
{
    switch ((int) $errno) {
        case E_ERROR:
        case E_PARSE:
        case E_CORE_ERROR:
        case E_COMPILE_ERROR:
        case E_USER_ERROR:
            return 'fatal';
        case E_WARNING:
        case E_CORE_WARNING:
        case E_COMPILE_WARNING:
        case E_USER_WARNING:
            return 'warning';
        case E_NOTICE:
        case E_USER_NOTICE:
        case 2048: // E_STRICT, whose constant is itself deprecated from PHP 8.4
            return 'notice';
        case E_DEPRECATED:
        case E_USER_DEPRECATED:
            return 'deprecated';
    }

    return 'error';
}

/**
 * The constant name of an error number, e.g. E_WARNING.
 *
 * @param int $errno
 * @return string
 */
function pg_error_type_name($errno)
{
    $names = array(
        E_ERROR             => 'E_ERROR',
        E_WARNING           => 'E_WARNING',
        E_PARSE             => 'E_PARSE',
        E_NOTICE            => 'E_NOTICE',
        E_CORE_ERROR        => 'E_CORE_ERROR',
        E_CORE_WARNING      => 'E_CORE_WARNING',
        E_COMPILE_ERROR     => 'E_COMPILE_ERROR',
        E_COMPILE_WARNING   => 'E_COMPILE_WARNING',
        E_USER_ERROR        => 'E_USER_ERROR',
        E_USER_WARNING      => 'E_USER_WARNING',
        E_USER_NOTICE       => 'E_USER_NOTICE',
        2048                => 'E_STRICT',
        E_RECOVERABLE_ERROR => 'E_RECOVERABLE_ERROR',
        E_DEPRECATED        => 'E_DEPRECATED',
        E_USER_DEPRECATED   => 'E_USER_DEPRECATED',
    );

    return isset($names[(int) $errno]) ? $names[(int) $errno] : 'E_' . (int) $errno;
}

/**
 * The URL with the values of secret-bearing query parameters replaced by ***.
 *
 * Sign-in links, password resets, API keys and signed file links all travel
 * in the query string, and the log is read by more people than the visitor.
 * The rest of the URL is left byte for byte as it came.
 *
 * @param string $url
 * @return string
 */
function pg_error_mask_url($url)
{
    $url = (string) $url;
    $question = strpos($url, '?');

    if ($question === false) {
        return $url;
    }

    $sensitive = array('password', 'token', 'k', 'sig', 'key');
    $pairs = explode('&', substr($url, $question + 1));

    foreach ($pairs as $index => $pair) {
        $equals = strpos($pair, '=');

        if ($equals === false) {
            continue;
        }

        $name = strtolower(urldecode(substr($pair, 0, $equals)));

        // password[] and token[x] are the same parameter to PHP.
        $bracket = strpos($name, '[');

        if ($bracket !== false) {
            $name = substr($name, 0, $bracket);
        }

        if (in_array($name, $sensitive, true)) {
            $pairs[$index] = substr($pair, 0, $equals + 1) . '***';
        }
    }

    return substr($url, 0, $question + 1) . implode('&', $pairs);
}

/**
 * A log line of this module as readable text, or null for any other line.
 *
 * view_log.php shows it in place of the raw JSON: the level and message, then
 * where, then the request, then the trace.
 *
 * @param string $line
 * @return string|null
 */
function pg_error_describe_line($line)
{
    if (!preg_match('/^\[[0-9]{4}-[0-9]{2}-[0-9]{2} [0-9:]+\] (\{.*\})\s*$/s', (string) $line, $match)) {
        return null;
    }

    $data = json_decode($match[1], true);

    if (!is_array($data) || !isset($data['message']) || !is_string($data['message'])) {
        return null;
    }

    $level = (isset($data['level']) && is_string($data['level']) && ($data['level'] !== '')) ? $data['level'] : 'error';
    $type = (isset($data['type']) && is_string($data['type'])) ? $data['type'] : '';

    // An exception class says more than the level; an E_* name says nothing
    // the level does not.
    $first = $level . ': ';

    if (($type !== '') && (strpos($type, 'E_') !== 0)) {
        $first .= $type . ': ';
    }

    $lines = array($first . $data['message']);

    if (!empty($data['file']) && is_string($data['file'])) {
        $lines[] = $data['file'] . ':' . (isset($data['line']) ? (int) $data['line'] : 0);
    }

    $request = trim(
        ((isset($data['method']) && is_string($data['method'])) ? $data['method'] : '') . ' '
        . ((isset($data['url']) && is_string($data['url'])) ? $data['url'] : ''));

    $about = array();

    if (!empty($data['request_id']) && is_string($data['request_id'])) {
        $about[] = 'request ' . $data['request_id'];
    }

    if (!empty($data['user_id'])) {
        $about[] = 'user ' . (int) $data['user_id'];
    }

    if (!empty($data['ip']) && is_string($data['ip'])) {
        $about[] = $data['ip'];
    }

    if (count($about) > 0) {
        $request .= (($request !== '') ? ' ' : '') . '(' . implode(', ', $about) . ')';
    }

    if ($request !== '') {
        $lines[] = $request;
    }

    if (isset($data['trace']) && is_array($data['trace'])) {
        foreach (array_values($data['trace']) as $index => $frame) {
            if (is_string($frame)) {
                $lines[] = '#' . $index . ' ' . $frame;
            }
        }
    }

    return implode("\n", $lines);
}
