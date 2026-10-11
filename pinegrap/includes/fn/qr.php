<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * QR codes, drawn on the server as inline SVG. The text never leaves the
 * server and never goes into a URL: an authenticator key in a query string
 * would end up in access logs, referrers and the browser history.
 *
 * The embedded generator (includes/qrcode/qrcode.php) is loaded on first use.
 * Its own size helpers (QRCode::getMinimumQRCode(), QRUtil::getMaxLength())
 * only know versions 1-10 and raise a fatal error above that, and its mode
 * detection can take UTF-8 byte pairs for Kanji, so the version is picked
 * here from the Reed-Solomon block table and the text is always encoded in
 * byte mode. While the generator runs, its E_USER_ERROR (overflow, bad
 * parameter) becomes an exception and an empty result instead of the end of
 * the request; any other notice takes PHP's normal path and the code is
 * still produced (pg_qr_error_handler()).
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
 * The longest text, in bytes, the pg_qr_* functions accept; longer text
 * gives an empty result. Covers otpauth:// key URIs (about 150 bytes) and
 * ordinary links while keeping the code small enough to scan off a screen.
 *
 * @return int
 */
function pg_qr_max_length()
{
    return 1000;
}

/**
 * The module matrix of the QR code for a text, in byte mode, at the smallest
 * version (1-40) that holds it.
 *
 * @param string $text  UTF-8 bytes, encoded as they are.
 * @param string $level Error correction: 'L', 'M', 'Q' or 'H'; anything
 *                      else is taken as 'M'.
 * @return int[][] Rows of 0 (light) and 1 (dark), as many rows as columns;
 *                 array() when the text is empty, longer than
 *                 pg_qr_max_length() or cannot be encoded.
 */
function pg_qr_matrix($text, $level = 'M')
{
    $text = (string) $text;
    $length = strlen($text);

    if (($length === 0) || ($length > pg_qr_max_length())) {
        return array();
    }

    require_once(PG_FUNCTIONS_DIR . '/includes/qrcode/qrcode.php');

    $levels = array(
        'L' => QR_ERROR_CORRECT_LEVEL_L,
        'M' => QR_ERROR_CORRECT_LEVEL_M,
        'Q' => QR_ERROR_CORRECT_LEVEL_Q,
        'H' => QR_ERROR_CORRECT_LEVEL_H);

    $level = strtoupper((string) $level);
    $correction = isset($levels[$level]) ? $levels[$level] : $levels['M'];

    // Byte mode: 4 bits of mode, a character count of 8 bits below version
    // 10 and 16 bits from there on, then 8 bits per byte. The capacity of a
    // version is the sum of the data codewords of its blocks.
    $version = 0;

    for ($candidate = 1; $candidate <= 40; $candidate++) {
        $bits = 4 + (($candidate < 10) ? 8 : 16) + (8 * $length);
        $capacity = 0;

        foreach (QRRSBlock::getRSBlocks($candidate, $correction) as $block) {
            $capacity += $block->getDataCount();
        }

        if ($bits <= ($capacity * 8)) {
            $version = $candidate;
            break;
        }
    }

    if ($version === 0) {
        return array();
    }

    set_error_handler('pg_qr_error_handler');

    try {
        $qr = new QRCode();
        $qr->setTypeNumber($version);
        $qr->setErrorCorrectLevel($correction);
        $qr->addData($text, QR_MODE_8BIT_BYTE);
        $qr->make();

        $count = $qr->getModuleCount();
        $matrix = array();

        for ($row = 0; $row < $count; $row++) {
            $cells = array();

            for ($column = 0; $column < $count; $column++) {
                $cells[] = $qr->isDark($row, $column) ? 1 : 0;
            }

            $matrix[] = $cells;
        }
    } catch (Throwable $exception) {
        $matrix = array();
    } finally {
        restore_error_handler();
    }

    return $matrix;
}

/**
 * Error handler in force while the generator runs. The generator reports
 * overflow and bad parameters with trigger_error(E_USER_ERROR), which would
 * end the request; that one becomes an exception, so it costs only the code.
 * Anything else (a notice or deprecation from a newer PHP) goes on to PHP's
 * own handling and the code is still produced.
 *
 * @param int    $severity
 * @param string $message
 * @param string $file
 * @param int    $line
 * @return bool false: not handled here.
 * @throws ErrorException On E_USER_ERROR.
 */
function pg_qr_error_handler($severity, $message, $file = '', $line = 0)
{
    if ($severity === E_USER_ERROR) {
        throw new ErrorException($message, 0, $severity, $file, $line);
    }

    return false;
}

/**
 * The QR code for a text as inline <svg> markup: one white square with a
 * quiet zone of four modules on every side and one black path, one
 * rectangle per run of dark modules in a row.
 *
 * @param string $text
 * @param int    $size    Width and height in pixels; 0 leaves them out so
 *                        the image takes the size of its box.
 * @param array  $options 'level' (error correction, default 'M'),
 *                        'label' (accessible name; without one the image is
 *                        hidden from assistive technology), 'id', 'class'.
 * @return string '' when pg_qr_matrix() has nothing for the text.
 */
function pg_qr_svg($text, $size = 200, $options = array())
{
    $matrix = pg_qr_matrix($text, isset($options['level']) ? (string) $options['level'] : 'M');

    if (!$matrix) {
        return '';
    }

    $quiet = 4;
    $count = count($matrix);
    $box = $count + (2 * $quiet);

    $path = '';

    foreach ($matrix as $y => $cells) {
        $x = 0;

        while ($x < $count) {
            if (!$cells[$x]) {
                $x++;
                continue;
            }

            $start = $x;

            while (($x < $count) && $cells[$x]) {
                $x++;
            }

            $run = $x - $start;
            $path .= 'M' . ($start + $quiet) . ' ' . ($y + $quiet) . 'h' . $run . 'v1h-' . $run . 'z';
        }
    }

    $attributes = ' xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $box . ' ' . $box . '"';

    $size = (int) $size;

    if ($size > 0) {
        $attributes .= ' width="' . $size . '" height="' . $size . '"';
    }

    foreach (array('id', 'class') as $name) {
        if (isset($options[$name]) && ((string) $options[$name] !== '')) {
            $attributes .= ' ' . $name . '="' . h((string) $options[$name]) . '"';
        }
    }

    $label = isset($options['label']) ? (string) $options['label'] : '';

    $attributes .= ($label !== '')
        ? ' role="img" aria-label="' . h($label) . '"'
        : ' aria-hidden="true"';

    return '<svg' . $attributes . ' shape-rendering="crispEdges">'
        . '<rect width="' . $box . '" height="' . $box . '" fill="#ffffff"/>'
        . '<path d="' . $path . '" fill="#000000"/>'
        . '</svg>';
}

/**
 * The QR code for a text as a data: URI, for an <img src> or a download
 * link.
 *
 * @param string $text
 * @param int    $size    See pg_qr_svg().
 * @param array  $options See pg_qr_svg().
 * @return string '' when pg_qr_svg() returns ''.
 */
function pg_qr_svg_data_uri($text, $size = 0, $options = array())
{
    $svg = pg_qr_svg($text, $size, $options);

    return ($svg !== '') ? 'data:image/svg+xml;base64,' . base64_encode($svg) : '';
}
