<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * A read filter that lets a zip entry through no further than a number of
 * bytes, for the readers that stream an entry through zip:// (XMLReader over
 * a workbook's shared strings or one of its sheets).
 *
 * The central directory says how large an entry unpacks, and the code that
 * reads archives measures that figure before it opens anything. A stream does
 * not keep to it: libzip compares the size only once the entry has been read
 * to its end, so an entry that declares a kilobyte and inflates to gigabytes
 * is read whole first. Behind this filter the read fails at the first byte
 * past the limit instead.
 *
 * The limit travels in the filter's name, "pg_zip_cap.<bytes>", so one
 * registration serves every limit and nothing is shared between two streams.
 * Used through pg_zip_entry_uri() and pg_zip_entry_overran()
 * (includes/fn/files.php); loaded by the first of them.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

class pg_zip_stream_cap extends php_user_filter
{
    /**
     * Set when a stream went past its limit; read and cleared by
     * pg_zip_entry_overran().
     *
     * @var bool
     */
    public static $overran = false;

    /**
     * Bytes this stream may still pass.
     *
     * @var int
     */
    private $left = 0;

    public function onCreate(): bool
    {
        $dot = strrpos((string) $this->filtername, '.');
        $this->left = ($dot === false) ? 0 : max(0, (int) substr((string) $this->filtername, $dot + 1));

        return true;
    }

    public function filter($in, $out, &$consumed, $closing): int
    {
        while ($bucket = stream_bucket_make_writeable($in)) {
            if ($bucket->datalen > $this->left) {
                self::$overran = true;

                return PSFS_ERR_FATAL;
            }

            $this->left -= $bucket->datalen;
            $consumed += $bucket->datalen;
            stream_bucket_append($out, $bucket);
        }

        return PSFS_PASS_ON;
    }
}
