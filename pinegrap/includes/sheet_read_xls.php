<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The .xls reader of the importers' sheet reader (includes/fn/sheet_read.php):
 * excel_reader2.php, the bundled php-excel-reader, with the parts it gets
 * wrong for an import taken over in subclasses, so the library file itself
 * stays as it is.
 *
 * - The compound file is read with every block chain bounded: the library
 *   follows a chain that loops, or a count the header makes up, until memory
 *   runs out.
 * - The workbook stream and the sheet are walked once before the library
 *   walks them, and refused when they do not end where they say: the
 *   library's own walk past the end of the data never stops.
 * - Only the sheet asked for is read.
 * - A number is read as the exact double it is stored as (the library's own
 *   arithmetic makes 0 a tiny non-zero number), and a date is known by its
 *   number format read from the file in full (the library cannot decode a
 *   format stored as UTF-16, as PHPExcel and Excel write them).
 * - A boolean, an error and the result of a formula are told apart, and a
 *   LABEL or a formula's text is decoded from the workbook's code page.
 *
 * Loaded by pg_sheet_xls_load() after excel_reader2.php.
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
 * The compound file (OLE2) around the workbook stream, every chain bounded.
 */
class Pg_Sheet_Xls_Ole extends OLERead
{
    // Declared here, as the library sets them on the fly (deprecated from
    // PHP 8.2) in the version this code runs with.
    public $error;
    public $numBigBlockDepotBlocks;
    public $sbdStartBlock;
    public $rootStartBlock;
    public $extensionBlock;
    public $numExtensionBlocks;
    public $bigBlockChain;
    public $smallBlockChain;
    public $entry;
    public $props;
    public $wrkbook;
    public $rootentry;

    public function __construct()
    {
    }

    /**
     * Reads the compound file's header and chains, as OLERead::read() does,
     * refusing a count or a block the file does not hold.
     *
     * @param string $sFileName
     * @return bool
     */
    public function read($sFileName)
    {
        $this->error = 1;
        $this->props = array();
        $this->wrkbook = null;
        $this->rootentry = null;
        $data = is_readable((string) $sFileName) ? @file_get_contents((string) $sFileName) : false;

        if (!is_string($data) || (strlen($data) < BIG_BLOCK_SIZE) || (substr($data, 0, 8) !== IDENTIFIER_OLE)) {
            return false;
        }

        $this->data = $data;
        $blocks = (int) floor(strlen($data) / BIG_BLOCK_SIZE);
        $this->numBigBlockDepotBlocks = GetInt4d($data, NUM_BIG_BLOCK_DEPOT_BLOCKS_POS);
        $this->sbdStartBlock = GetInt4d($data, SMALL_BLOCK_DEPOT_BLOCK_POS);
        $this->rootStartBlock = GetInt4d($data, ROOT_START_BLOCK_POS);
        $this->extensionBlock = GetInt4d($data, EXTENSION_BLOCK_POS);
        $this->numExtensionBlocks = GetInt4d($data, NUM_EXTENSION_BLOCK_POS);

        if (($this->numBigBlockDepotBlocks < 0) || ($this->numBigBlockDepotBlocks > $blocks)
            || ($this->numExtensionBlocks < 0) || ($this->numExtensionBlocks > $blocks)) {
            return false;
        }

        $depot = array();
        $in_header = (int) ((BIG_BLOCK_SIZE - BIG_BLOCK_DEPOT_BLOCKS_POS) / 4);
        $count = ($this->numExtensionBlocks !== 0) ? $in_header : min($in_header, $this->numBigBlockDepotBlocks);
        $pos = BIG_BLOCK_DEPOT_BLOCKS_POS;

        for ($i = 0; $i < $count; $i++) {
            $depot[$i] = GetInt4d($data, $pos);
            $pos += 4;
        }

        for ($j = 0; $j < $this->numExtensionBlocks; $j++) {
            $pos = ($this->extensionBlock + 1) * BIG_BLOCK_SIZE;

            if (($this->extensionBlock < 0) || ($pos + BIG_BLOCK_SIZE > strlen($data))) {
                return false;
            }

            $to_read = min($this->numBigBlockDepotBlocks - $count, (int) (BIG_BLOCK_SIZE / 4) - 1);

            for ($i = 0; $i < $to_read; $i++) {
                $depot[$count + $i] = GetInt4d($data, $pos);
                $pos += 4;
            }

            $count += max(0, $to_read);

            if ($count < $this->numBigBlockDepotBlocks) {
                $this->extensionBlock = GetInt4d($data, $pos);
            }
        }

        $this->bigBlockChain = array();

        for ($i = 0; $i < $this->numBigBlockDepotBlocks; $i++) {
            if (!isset($depot[$i]) || !$this->pg_block_ok($depot[$i])) {
                return false;
            }

            $pos = ($depot[$i] + 1) * BIG_BLOCK_SIZE;

            for ($j = 0; $j < BIG_BLOCK_SIZE / 4; $j++) {
                $this->bigBlockChain[] = GetInt4d($data, $pos);
                $pos += 4;
            }
        }

        $this->smallBlockChain = array();

        foreach ($this->pg_chain($this->sbdStartBlock) as $block) {
            $pos = ($block + 1) * BIG_BLOCK_SIZE;

            for ($j = 0; $j < BIG_BLOCK_SIZE / 4; $j++) {
                $this->smallBlockChain[] = GetInt4d($data, $pos);
                $pos += 4;
            }
        }

        $this->entry = $this->__readData($this->rootStartBlock);
        $this->__readPropertySets();

        if (($this->wrkbook === null) || ($this->rootentry === null)) {
            return false;
        }

        $this->error = 0;

        return true;
    }

    /**
     * Does a big block number name a block the file holds?
     *
     * @param int $block
     * @return bool
     */
    public function pg_block_ok($block)
    {
        return is_int($block) && ($block >= 0) && ((($block + 2) * BIG_BLOCK_SIZE) <= strlen($this->data));
    }

    /**
     * The big blocks of a chain in order, up to its end mark (-2). A chain
     * that loops, leaves the file or runs past the chain table ends the read
     * of it there.
     *
     * @param int $block the first block
     * @return int[]
     */
    public function pg_chain($block)
    {
        $out = array();
        $limit = count((array) $this->bigBlockChain);

        while (($block !== -2) && (count($out) < $limit) && $this->pg_block_ok($block)) {
            $out[] = $block;

            if (!isset($this->bigBlockChain[$block])) {
                break;
            }

            $block = $this->bigBlockChain[$block];
        }

        return $out;
    }

    /**
     * A stream kept in big blocks, from its first block.
     *
     * @param int $bl
     * @return string
     */
    public function __readData($bl) // phpcs:ignore PHPCompatibility.FunctionNameRestrictions.ReservedFunctionNames.MethodDoubleUnderscore -- overrides the library method OLERead::read() calls by this name
    {
        $data = '';

        foreach ($this->pg_chain($bl) as $block) {
            $data .= substr($this->data, ($block + 1) * BIG_BLOCK_SIZE, BIG_BLOCK_SIZE);
        }

        return $data;
    }

    /**
     * The workbook stream, from big blocks or from the root entry's small
     * blocks, as OLERead::getWorkBook() reads it, every chain bounded.
     *
     * @return string
     */
    public function getWorkBook()
    {
        if (!isset($this->props[$this->wrkbook], $this->props[$this->rootentry])) {
            return '';
        }

        $book = $this->props[$this->wrkbook];

        if ($book['size'] >= SMALL_BLOCK_THRESHOLD) {
            return substr($this->__readData($book['startBlock']), 0, max(0, (int) $book['size']));
        }

        $root = $this->__readData($this->props[$this->rootentry]['startBlock']);
        $stream = '';
        $block = $book['startBlock'];
        $limit = count((array) $this->smallBlockChain);
        $steps = 0;

        while (($block !== -2) && is_int($block) && ($block >= 0) && ($steps++ <= $limit) && (($block * SMALL_BLOCK_SIZE) < strlen($root))) {
            $stream .= substr($root, $block * SMALL_BLOCK_SIZE, SMALL_BLOCK_SIZE);

            if (!isset($this->smallBlockChain[$block])) {
                break;
            }

            $block = $this->smallBlockChain[$block];
        }

        return $stream;
    }
}

/**
 * One sheet of an .xls file, read by excel_reader2's Spreadsheet_Excel_Reader
 * with the checks and types the importers need.
 */
class Pg_Sheet_Xls_Reader extends Spreadsheet_Excel_Reader
{
    // Declared here, as the library sets them on the fly (deprecated from
    // PHP 8.2) in the version this code runs with.
    public $_encoderFunction;
    public $store_extended_info;
    public $version;
    public $nineteenFour;
    public $sn;

    /** @var int which worksheet is read, 0 for the first */
    public $pg_wanted = 0;

    /** @var int the boundsheet index of that worksheet, -1 until found */
    public $pg_found = -1;

    /** @var int worksheets met so far */
    public $pg_worksheets = 0;

    /** @var bool the stream was refused */
    public $pg_broken = false;

    /** @var array number format index => format code, decoded */
    public $pg_formats = array();

    /** @var string the code page of a BIFF5/7 workbook's text, as iconv names it */
    public $pg_codepage = 'CP1252';

    /** @var array row => column => cell, the cells the library gives no type for (0-based) */
    public $pg_cells = array();

    /**
     * @param int $wanted which worksheet to read, 0 for the first
     */
    public function __construct($wanted = 0)
    {
        // The library's own constructor is not run: in the version written
        // for PHP 4 it is a method named like the class, which PHP 8 never
        // calls. Only what the read needs is set up.
        $this->_ole = new Pg_Sheet_Xls_Ole();
        $this->setUTFEncoder(function_exists('iconv') ? 'iconv' : 'mb');
        $this->store_extended_info = true;
        $this->nineteenFour = false;
        $this->pg_wanted = max(0, (int) $wanted);
    }

    /**
     * The workbook globals: walked once first, the formats and the code page
     * taken in full, and handed to the library only when every record lies
     * inside the stream and an EOF ends it.
     *
     * @return bool|int
     */
    public function _parse()
    {
        $data = (string) $this->data;
        $length = strlen($data);
        $pos = 0;
        $ended = false;
        $version = ($length >= 6) ? (ord($data[4]) | (ord($data[5]) << 8)) : 0;

        while ($pos + 4 <= $length) {
            $code = ord($data[$pos]) | (ord($data[$pos + 1]) << 8);
            $size = ord($data[$pos + 2]) | (ord($data[$pos + 3]) << 8);

            if ($pos + 4 + $size > $length) {
                break;
            }

            $record = substr($data, $pos + 4, $size);

            if ($code === SPREADSHEET_EXCEL_READER_TYPE_EOF) {
                $ended = true;
                break;
            }

            if (($code === 0x42) && ($size >= 2)) {
                $this->pg_codepage = $this->pg_codepage_name(ord($record[0]) | (ord($record[1]) << 8));
            } elseif (($code === SPREADSHEET_EXCEL_READER_TYPE_FORMAT) && ($size >= 3)) {
                $index = ord($record[0]) | (ord($record[1]) << 8);
                $this->pg_formats[$index] = ($version === SPREADSHEET_EXCEL_READER_BIFF8)
                    ? $this->pg_unicode($record, 2, true)
                    : $this->pg_bytes(substr($record, 3, ord($record[2])));
            } elseif (($code === SPREADSHEET_EXCEL_READER_TYPE_SST) && ($size >= 8)) {
                // The library loops once for every string the table says it
                // holds: a string takes at least three bytes.
                if ((ord($record[4]) | (ord($record[5]) << 8) | (ord($record[6]) << 16) | (ord($record[7]) << 24)) * 3 > $length) {
                    break;
                }
            }

            $pos += 4 + $size;
        }

        if (!$ended || ($length === 0)) {
            $this->pg_broken = true;

            return false;
        }

        return parent::_parse();
    }

    /**
     * One sheet: only the worksheet asked for is read, after a walk that
     * finds its EOF inside the stream and takes the cells the library reads
     * without their type.
     *
     * @param int $spos where the sheet's BOF record starts
     * @return int
     */
    public function _parsesheet($spos)
    {
        $data = (string) $this->data;
        $length = strlen($data);

        if (($spos < 0) || ($spos + 8 > $length)) {
            return -1;
        }

        $substream = ord($data[$spos + 6]) | (ord($data[$spos + 7]) << 8);

        // A chart, macro or dialog sheet holds no cells and is not counted,
        // as PHPExcel did not count it.
        if ($substream !== SPREADSHEET_EXCEL_READER_WORKSHEET) {
            return -2;
        }

        if ($this->pg_worksheets++ !== $this->pg_wanted) {
            return 0;
        }

        $version = ord($data[$spos + 4]) | (ord($data[$spos + 5]) << 8);
        $biff8 = ($version === SPREADSHEET_EXCEL_READER_BIFF8);
        $pos = $spos + 4 + (ord($data[$spos + 2]) | (ord($data[$spos + 3]) << 8));
        $ended = false;
        $formula_at = null;

        while ($pos + 4 <= $length) {
            $code = ord($data[$pos]) | (ord($data[$pos + 1]) << 8);
            $size = ord($data[$pos + 2]) | (ord($data[$pos + 3]) << 8);

            if ($pos + 4 + $size > $length) {
                break;
            }

            // The library reads only the low byte of the code for an EOF.
            if (($code & 0xFF) === SPREADSHEET_EXCEL_READER_TYPE_EOF) {
                $ended = true;
                break;
            }

            $record = substr($data, $pos + 4, $size);
            $pos += 4 + $size;

            if ($size < 6) {
                continue;
            }

            $row = ord($record[0]) | (ord($record[1]) << 8);
            $column = ord($record[2]) | (ord($record[3]) << 8);

            switch ($code) {
                case SPREADSHEET_EXCEL_READER_TYPE_BOOLERR:
                    if ($size >= 8) {
                        $this->pg_cells[$row][$column] = (ord($record[7]) === 0)
                            ? array('t' => 'b', 'v' => (ord($record[6]) !== 0) ? '1' : '0')
                            : array('t' => 'e', 'v' => $this->pg_error_text(ord($record[6])));
                    }
                    break;

                case SPREADSHEET_EXCEL_READER_TYPE_FORMULA:
                case SPREADSHEET_EXCEL_READER_TYPE_FORMULA2:
                    $formula_at = null;

                    // A result that is not a number has 0xFFFF in its last
                    // two bytes and its kind in the first.
                    if (($size >= 14) && (ord($record[12]) === 0xFF) && (ord($record[13]) === 0xFF)) {
                        $kind = ord($record[6]);

                        if ($kind === 0) {
                            // Text, in the STRING record that follows.
                            $formula_at = array($row, $column);
                        } elseif ($kind === 1) {
                            $this->pg_cells[$row][$column] = array('t' => 'b', 'v' => (ord($record[8]) !== 0) ? '1' : '0');
                        } elseif ($kind === 2) {
                            $this->pg_cells[$row][$column] = array('t' => 'e', 'v' => $this->pg_error_text(ord($record[8])));
                        } elseif ($kind === 3) {
                            $this->pg_cells[$row][$column] = null;
                        }
                    }
                    break;

                case SPREADSHEET_EXCEL_READER_TYPE_STRING:
                    if ($formula_at !== null) {
                        $this->pg_cells[$formula_at[0]][$formula_at[1]] = array('t' => 's', 'v' => $biff8 ? $this->pg_unicode($record, 0, false) : $this->pg_bytes(substr($record, 2, ord($record[0]) | (ord($record[1]) << 8))));
                        $formula_at = null;
                    }
                    break;

                case SPREADSHEET_EXCEL_READER_TYPE_LABEL:
                    $this->pg_cells[$row][$column] = array('t' => 's', 'v' => $biff8 ? $this->pg_unicode($record, 6, false) : $this->pg_bytes(substr($record, 8, ord($record[6]) | (ord($record[7]) << 8))));
                    break;
            }

            if (($code !== SPREADSHEET_EXCEL_READER_TYPE_STRING) && ($code !== SPREADSHEET_EXCEL_READER_TYPE_FORMULA) && ($code !== SPREADSHEET_EXCEL_READER_TYPE_FORMULA2)) {
                $formula_at = null;
            }
        }

        if (!$ended) {
            $this->pg_broken = true;
            $this->pg_cells = array();

            return -1;
        }

        $this->pg_found = $this->sn;

        return parent::_parsesheet($spos);
    }

    /**
     * A NUMBER record's value, the double stored at its seventh byte, read
     * as it is (little-endian in the file).
     *
     * @param int $spos
     * @return float
     */
    public function createNumber($spos)
    {
        return $this->pg_double(substr((string) $this->data, $spos + 6, 8));
    }

    /**
     * Four bytes as a signed 32-bit number. The library's own reading keeps
     * the sign bit as a value on 64-bit PHP (a negative whole number in an
     * RK cell read as 1,073,741,817) and folds the two largest into -2, its
     * chain end mark, which made an RK -0.01 read as -1. Inside the
     * workbook stream nothing is a chain mark.
     *
     * @param string $data
     * @param int    $pos
     * @return int
     */
    public function _GetInt4d($data, $pos)
    {
        $bytes = substr((string) $data, (int) $pos, 4);

        if (strlen($bytes) !== 4) {
            return 0;
        }

        $value = unpack('V', $bytes);
        $value = (int) $value[1];

        return ($value >= 0x80000000) ? ($value - 0x100000000) : $value;
    }

    /**
     * An RK value: a whole number in the upper 30 bits, or the upper 30 bits
     * of a double; either divided by 100 when the lowest bit is set.
     *
     * @param int $rknum _GetInt4d()
     * @return float|int
     */
    public function _GetIEEE754($rknum)
    {
        $rknum = (int) $rknum;

        if (($rknum & 0x02) !== 0) {
            // An arithmetic shift keeps the sign of a signed number.
            $value = $rknum >> 2;
        } else {
            $value = $this->pg_double("\0\0\0\0" . pack('V', $rknum & 0xFFFFFFFC));
        }

        if (($rknum & 0x01) !== 0) {
            $value /= 100;
        }

        return $value;
    }

    /**
     * Eight bytes of a little-endian double as a float.
     *
     * @param string $bytes
     * @return float
     */
    public function pg_double($bytes)
    {
        if (strlen($bytes) !== 8) {
            return 0.0;
        }

        if (pack('d', 1.0) !== "\0\0\0\0\0\0\xf0\x3f") {
            $bytes = strrev($bytes);
        }

        $value = unpack('d', $bytes);

        return (float) $value[1];
    }

    /**
     * The cells read, row number => row (pg_sheet_rows()), or 'read' when
     * the file could not be read, 'sheet' when it has no such worksheet.
     *
     * @return array|string
     */
    public function pg_rows()
    {
        if ($this->pg_broken || ($this->_ole->error !== 0)) {
            return 'read';
        }

        if ($this->pg_found < 0) {
            // A workbook with no worksheet at all is not one.
            return ($this->pg_worksheets > 0) ? 'sheet' : 'read';
        }

        $sheet = isset($this->sheets[$this->pg_found]) ? $this->sheets[$this->pg_found] : array();
        $cells = isset($sheet['cells']) ? (array) $sheet['cells'] : array();
        $info = isset($sheet['cellsInfo']) ? (array) $sheet['cellsInfo'] : array();

        // The cells the library read and those it could not type, together.
        foreach ($this->pg_cells as $row => $columns) {
            foreach ($columns as $column => $cell) {
                $cells[$row + 1][$column + 1] = $cell;
                $info[$row + 1][$column + 1]['pg'] = true;
            }
        }

        ksort($cells);
        $out = array();

        foreach ($cells as $number => $columns) {
            $row = array();
            ksort($columns);

            foreach ($columns as $column => $value) {
                $details = isset($info[$number][$column]) ? $info[$number][$column] : array();
                $cell = isset($details['pg']) ? $value : $this->pg_cell($value, $details);

                if (($cell === null) || ($column < 1)) {
                    continue;
                }

                while (count($row) < $column - 1) {
                    $row[] = null;
                }

                $row[$column - 1] = $cell;
            }

            if (!empty($row)) {
                ksort($row);
                $out[(int) $number] = $row;
            }
        }

        return $out;
    }

    /**
     * A cell the library read: a number (with its raw value and format) or
     * text.
     *
     * @param mixed $value   what the library holds for it
     * @param array $details its cellsInfo
     * @return array|null
     */
    public function pg_cell($value, $details)
    {
        if (isset($details['raw']) && (is_int($details['raw']) || is_float($details['raw']))) {
            $number = (float) $details['raw'];
            $xf = isset($details['xfIndex']) ? (int) $details['xfIndex'] : -1;

            if (($xf >= 0) && $this->pg_xf_is_date($xf)) {
                return array('t' => 'd', 'v' => pg_sheet_float_text($this->nineteenFour ? $number + 1462 : $number));
            }

            // As PHPExcel gave it to the importers: the float as PHP writes it.
            return array('t' => 'n', 'v' => (string) $number);
        }

        if (($value === null) || ($value === '')) {
            return null;
        }

        return array('t' => 's', 'v' => (string) $value);
    }

    /**
     * Does a cell style's number format show a date or a time? The built-in
     * date and time formats (14 to 22, 45 to 47) and any format whose code
     * names a day, month, year, hour, minute or second
     * (pg_office_xlsx_format_kind()).
     *
     * @param int $xf
     * @return bool
     */
    public function pg_xf_is_date($xf)
    {
        if (!isset($this->xfRecords[$xf]['formatIndex'])) {
            return false;
        }

        $format = (int) $this->xfRecords[$xf]['formatIndex'];

        if ((($format >= 14) && ($format <= 22)) || (($format >= 45) && ($format <= 47))) {
            return true;
        }

        return isset($this->pg_formats[$format]) && (pg_office_xlsx_format_kind($this->pg_formats[$format]) !== '');
    }

    /**
     * A BIFF8 unicode string: its character count (2 bytes), option flags,
     * the optional run and extension counts, then the characters, one byte
     * each (Latin-1) or two (UTF-16LE).
     *
     * @param string $record
     * @param int    $start    where the character count is
     * @param bool   $extended rich text and extension blocks may follow the flags
     * @return string UTF-8
     */
    public function pg_unicode($record, $start, $extended)
    {
        if (strlen($record) < $start + 3) {
            return '';
        }

        $chars = ord($record[$start]) | (ord($record[$start + 1]) << 8);
        $flags = ord($record[$start + 2]);
        $pos = $start + 3;

        if ($extended) {
            $pos += (($flags & 0x08) ? 2 : 0) + (($flags & 0x04) ? 4 : 0);
        }

        if ($flags & 0x01) {
            return $this->pg_convert(substr($record, $pos, $chars * 2), 'UTF-16LE');
        }

        return $this->pg_convert(substr($record, $pos, $chars), 'ISO-8859-1');
    }

    /**
     * Text of a BIFF5/7 workbook, in its code page, as UTF-8.
     *
     * @param string $bytes
     * @return string
     */
    public function pg_bytes($bytes)
    {
        return $this->pg_convert((string) $bytes, $this->pg_codepage);
    }

    /**
     * Bytes in an encoding as UTF-8; as they are when neither iconv nor
     * mbstring knows the encoding.
     *
     * @param string $bytes
     * @param string $encoding
     * @return string
     */
    public function pg_convert($bytes, $encoding)
    {
        if (($bytes === '') || ($encoding === 'UTF-8')) {
            return $bytes;
        }

        if (function_exists('iconv')) {
            $text = @iconv($encoding, 'UTF-8//IGNORE', $bytes);

            if (is_string($text)) {
                return $text;
            }
        }

        if (function_exists('mb_convert_encoding')) {
            $text = @mb_convert_encoding($bytes, 'UTF-8', $encoding);

            if (is_string($text)) {
                return $text;
            }
        }

        return $bytes;
    }

    /**
     * A CODEPAGE record's number as the encoding name iconv knows.
     *
     * @param int $codepage
     * @return string
     */
    public function pg_codepage_name($codepage)
    {
        switch ($codepage) {
            case 367:
                return 'ASCII';

            case 1200:
                return 'UTF-16LE';

            case 10000:
            case 32768:
                return 'MACINTOSH';

            case 32769:
                return 'CP1252';

            case 65001:
                return 'UTF-8';
        }

        return in_array($codepage, array(437, 737, 775, 850, 852, 855, 857, 858, 860, 861, 862, 863, 864, 865, 866, 869, 874, 932, 936, 949, 950, 1250, 1251, 1252, 1253, 1254, 1255, 1256, 1257, 1258), true) ? 'CP' . $codepage : 'CP1252';
    }

    /**
     * A BIFF error code as the text a spreadsheet shows.
     *
     * @param int $code
     * @return string
     */
    public function pg_error_text($code)
    {
        $errors = array(0x00 => '#NULL!', 0x07 => '#DIV/0!', 0x0F => '#VALUE!', 0x17 => '#REF!', 0x1D => '#NAME?', 0x24 => '#NUM!', 0x2A => '#N/A');

        return isset($errors[$code]) ? $errors[$code] : '#N/A';
    }

    /**
     * The worksheets in their order, as the reader counts them: the
     * BOUNDSHEET records of the workbook globals, a chart, macro or dialog
     * sheet left out by its own BOF, as _parsesheet() leaves it out. Each
     * gives its name in UTF-8 (a BIFF8 short unicode string, or bytes in the
     * code page) and whether it is hidden (1 hidden, 2 very hidden); 'read'
     * when the workbook could not be read or holds no worksheet.
     *
     * @return array|string
     */
    public function pg_sheet_list()
    {
        if ($this->pg_broken || ($this->_ole->error !== 0)) {
            return 'read';
        }

        $data = (string) $this->data;
        $length = strlen($data);
        $biff8 = ($length >= 6) && ((ord($data[4]) | (ord($data[5]) << 8)) === SPREADSHEET_EXCEL_READER_BIFF8);
        $pos = 0;
        $out = array();

        // _parse() has found an EOF inside the stream before the globals'
        // end, so this walk ends there.
        while ($pos + 4 <= $length) {
            $code = ord($data[$pos]) | (ord($data[$pos + 1]) << 8);
            $size = ord($data[$pos + 2]) | (ord($data[$pos + 3]) << 8);

            if (($code === SPREADSHEET_EXCEL_READER_TYPE_EOF) || ($pos + 4 + $size > $length)) {
                break;
            }

            $record = substr($data, $pos + 4, $size);
            $pos += 4 + $size;

            if (($code !== SPREADSHEET_EXCEL_READER_TYPE_BOUNDSHEET) || ($size < 8)) {
                continue;
            }

            $at = ord($record[0]) | (ord($record[1]) << 8) | (ord($record[2]) << 16) | (ord($record[3]) << 24);

            if (($at < 0) || ($at + 8 > $length) || ((ord($data[$at + 6]) | (ord($data[$at + 7]) << 8)) !== SPREADSHEET_EXCEL_READER_WORKSHEET)) {
                continue;
            }

            if ($biff8) {
                $wide = (ord($record[7]) & 0x01) !== 0;
                $name = $this->pg_convert(substr($record, 8, ord($record[6]) * ($wide ? 2 : 1)), $wide ? 'UTF-16LE' : 'ISO-8859-1');
            } else {
                $name = $this->pg_bytes(substr($record, 7, ord($record[6])));
            }

            $out[] = array('name' => $name, 'hidden' => (ord($record[4]) & 0x03) !== 0);
        }

        return empty($out) ? 'read' : $out;
    }
}
