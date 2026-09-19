<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * ERP - accounts imported from a CSV file.
 *
 * The screen (erp_accounts_import.php) only moves the file about and draws
 * tables; everything that reads, interprets or checks a row is here, with no
 * HTML and no request state, so the same functions can be run from the command
 * line against a sample file.
 *
 * A spreadsheet export is rarely the file the importer would like: the
 * delimiter depends on the locale the sheet was saved in, the encoding on the
 * program, and the column names on whoever typed them. So the file is sniffed
 * rather than specified: the delimiter is counted, the bytes are converted to
 * UTF-8 when they are not already, and the header is matched against Turkish
 * and English names for each field.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_ERP_ENTRY')) {
    exit;
}

/**
 * How many rows an import writes between two commits.
 */
if (!defined('ERP_IMPORT_BATCH_SIZE')) {
    define('ERP_IMPORT_BATCH_SIZE', 100);
}

/**
 * The largest file the screen accepts, in bytes.
 */
if (!defined('ERP_IMPORT_MAX_BYTES')) {
    define('ERP_IMPORT_MAX_BYTES', 5 * 1024 * 1024);
}

/**
 * The fields a row can be mapped onto.
 *
 * Each field carries the label the screen shows, the column length it is
 * trimmed to, and the header names it is recognised by. The translated label
 * is added to the synonyms at run time, so a file made from the template maps
 * itself whatever language the panel is in.
 *
 * @return array  field => ['label' => string, 'length' => int, 'synonyms' => array]
 */
function erp_import_fields()
{
    static $fields = null;

    if ($fields !== null) {
        return $fields;
    }

    $fields = array(
        'title' => array('label' => lang('Name'), 'length' => 255, 'synonyms' => array(
            'unvan', 'ünvan', 'ad', 'adı', 'adi', 'firma', 'firma adı', 'cari adı', 'cari', 'cari hesap', 'hesap adı',
            'müşteri', 'müşteri adı', 'name', 'company', 'company name', 'account', 'account name', 'title', 'customer')),
        'kind' => array('label' => lang('Type'), 'length' => 20, 'synonyms' => array(
            'tür', 'tur', 'türü', 'tip', 'tipi', 'cari türü', 'hesap türü', 'type', 'kind', 'account type')),
        'is_person' => array('label' => lang('Taxpayer'), 'length' => 10, 'synonyms' => array(
            'mükellef', 'mükellef türü', 'şahıs', 'şahıs/şirket', 'kişi', 'taxpayer', 'person', 'is person', 'entity')),
        'tax_number' => array('label' => lang('VKN / TCKN'), 'length' => 11, 'synonyms' => array(
            'vkn', 'tckn', 'vkn/tckn', 'vkn / tckn', 'vergi no', 'vergi numarası', 'vergi numarasi', 'tc', 'tc kimlik',
            'tc kimlik no', 'kimlik no', 'tax number', 'tax no', 'tax id', 'vat number', 'tin')),
        'tax_office' => array('label' => lang('Tax Office'), 'length' => 100, 'synonyms' => array(
            'vergi dairesi', 'vd', 'tax office')),
        'email' => array('label' => lang('E-mail Address'), 'length' => 255, 'synonyms' => array(
            'e-posta', 'eposta', 'e posta', 'e-posta adresi', 'mail', 'e-mail', 'email', 'email address', 'e-mail address')),
        'phone' => array('label' => lang('Phone Number'), 'length' => 50, 'synonyms' => array(
            'telefon', 'tel', 'telefon no', 'telefon numarası', 'cep', 'cep telefonu', 'gsm', 'phone', 'phone number',
            'mobile', 'mobile phone')),
        'address' => array('label' => lang('Address'), 'length' => 255, 'synonyms' => array(
            'adres', 'açık adres', 'address', 'street', 'address 1')),
        'district' => array('label' => lang('District'), 'length' => 100, 'synonyms' => array(
            'ilçe', 'ilce', 'semt', 'district', 'town')),
        'city' => array('label' => lang('City'), 'length' => 100, 'synonyms' => array(
            'il', 'şehir', 'sehir', 'city', 'province', 'state')),
        'postcode' => array('label' => lang('Postal Code'), 'length' => 20, 'synonyms' => array(
            'posta kodu', 'pk', 'postcode', 'post code', 'postal code', 'zip', 'zip code')),
        'country_code' => array('label' => lang('Country'), 'length' => 2, 'synonyms' => array(
            'ülke', 'ulke', 'ülke kodu', 'country', 'country code')),
        'currency' => array('label' => lang('Currency'), 'length' => 3, 'synonyms' => array(
            'para birimi', 'döviz', 'doviz', 'currency', 'currency code')),
        'status' => array('label' => lang('Status'), 'length' => 10, 'synonyms' => array(
            'durum', 'durumu', 'status', 'active')),
        'notes' => array('label' => lang('Notes'), 'length' => 65535, 'synonyms' => array(
            'not', 'notlar', 'açıklama', 'aciklama', 'notes', 'note', 'description', 'comments')),
        'payment_days' => array('label' => lang('Payment Term (days)'), 'length' => 4, 'synonyms' => array(
            'vade', 'vade günü', 'vade gunu', 'vade gün', 'vade gun', 'ödeme vadesi', 'odeme vadesi', 'payment days',
            'payment term', 'payment terms', 'terms', 'due days')),
        'overdue_notify_days' => array('label' => lang('Reminder threshold (days)'), 'length' => 4, 'synonyms' => array(
            'hatırlatma eşiği', 'hatirlatma esigi', 'hatırlatma günü', 'hatirlatma gunu', 'gecikme eşiği', 'gecikme esigi',
            'reminder days', 'reminder threshold', 'overdue days', 'overdue reminder')),
    );

    foreach ($fields as $field => $definition) {
        $fields[$field]['synonyms'][] = $definition['label'];
    }

    return $fields;
}

/**
 * A header name reduced to what two people typing the same thing share.
 *
 * Lower case, no spaces, dots, dashes or underscores, and Turkish letters
 * folded onto their plain forms so "Ünvan", "unvan" and "UNVAN" are one word.
 *
 * @param string $header
 * @return string
 */
function erp_import_normalize_header($header)
{
    $header = mb_strtolower(trim((string) $header), 'UTF-8');
    $header = str_replace(
        array('ç', 'ğ', 'ı', 'i̇', 'ö', 'ş', 'ü', 'â', 'î', 'û'),
        array('c', 'g', 'i', 'i', 'o', 's', 'u', 'a', 'i', 'u'),
        $header
    );

    return preg_replace('/[\s._\-\/:()*]+/u', '', $header);
}

/**
 * The field a header most likely names, or '' when it names none.
 *
 * @param string $header
 * @return string
 */
function erp_import_guess_field($header)
{
    static $lookup = null;

    if ($lookup === null) {
        $lookup = array();
        foreach (erp_import_fields() as $field => $definition) {
            foreach ($definition['synonyms'] as $synonym) {
                $key = erp_import_normalize_header($synonym);
                // The first field to claim a name keeps it, so the order of
                // erp_import_fields() decides ties.
                if (($key !== '') && !isset($lookup[$key])) {
                    $lookup[$key] = $field;
                }
            }
        }
    }

    $key = erp_import_normalize_header($header);

    return isset($lookup[$key]) ? $lookup[$key] : '';
}

/**
 * The bytes of a file as UTF-8 with Unix line endings.
 *
 * A byte order mark is dropped. Bytes that are not valid UTF-8 are taken to be
 * the Windows Turkish code page, which is what a Turkish Excel writes and a
 * superset of ISO-8859-9. CR-only and CR-LF endings both become LF so
 * fgetcsv() sees one convention.
 *
 * @param string $bytes
 * @param string $encoding  Filled with the encoding the bytes were read as
 * @return string
 */
function erp_import_to_utf8($bytes, &$encoding = '')
{
    $bytes = (string) $bytes;
    $encoding = 'UTF-8';

    if (substr($bytes, 0, 3) === "\xEF\xBB\xBF") {
        $bytes = substr($bytes, 3);
    }

    if (!mb_check_encoding($bytes, 'UTF-8')) {
        $encoding = erp_import_fallback_encoding();
        $bytes = mb_convert_encoding($bytes, 'UTF-8', $encoding);
    }

    return str_replace(array("\r\n", "\r"), "\n", $bytes);
}

/**
 * The single-byte encoding a file that is not UTF-8 is read as.
 *
 * Windows-1254 where mbstring knows it, ISO-8859-9 otherwise; the two differ
 * only in the 0x80-0x9F range.
 *
 * @return string
 */
function erp_import_fallback_encoding()
{
    $known = array_map('strtolower', mb_list_encodings());

    return in_array('windows-1254', $known, true) ? 'Windows-1254' : 'ISO-8859-9';
}

/**
 * The delimiter a line was written with.
 *
 * Counts the candidates outside quoted text and takes the one that occurs
 * most; a tie goes to the semicolon, which is what a Turkish locale writes.
 *
 * @param string $line
 * @return string  ';', ',' or "\t"
 */
function erp_import_detect_delimiter($line)
{
    $counts = array(';' => 0, ',' => 0, "\t" => 0);
    $quoted = false;
    $length = strlen($line);

    for ($i = 0; $i < $length; $i++) {
        $char = $line[$i];
        if ($char === '"') {
            $quoted = !$quoted;
        } elseif (!$quoted && isset($counts[$char])) {
            $counts[$char]++;
        }
    }

    $best = ';';
    foreach ($counts as $candidate => $count) {
        if ($count > $counts[$best]) {
            $best = $candidate;
        }
    }

    return $best;
}

/**
 * Read a UTF-8 CSV file: the header and the rows under it.
 *
 * Blank lines are skipped and do not count, but the row number reported for a
 * line is its line in the file, header included, so the operator can find it
 * in the spreadsheet.
 *
 * @param string $path
 * @param string $delimiter
 * @param int    $limit  Rows to read after the header; 0 for all
 * @return array|null  ['header' => array, 'rows' => [['line' => int, 'cells' => array], ...]], null when unreadable
 */
function erp_import_read($path, $delimiter, $limit = 0)
{
    $handle = @fopen($path, 'r');

    if ($handle === false) {
        return null;
    }

    $header = null;
    $rows = array();
    $line = 0;

    while (($cells = fgetcsv($handle, 0, $delimiter, '"', '\\')) !== false) {
        $line++;

        if (!is_array($cells) || ((count($cells) === 1) && (trim((string) $cells[0]) === ''))) {
            continue;
        }

        $cells = array_map('trim', array_map('strval', $cells));

        if ($header === null) {
            $header = $cells;
            continue;
        }

        $rows[] = array('line' => $line, 'cells' => $cells);

        if (($limit > 0) && (count($rows) >= $limit)) {
            break;
        }
    }

    fclose($handle);

    if ($header === null) {
        return null;
    }

    return array('header' => $header, 'rows' => $rows);
}

/**
 * Map header columns onto fields by name.
 *
 * @param array $header
 * @return array  column index => field ('' where none matched)
 */
function erp_import_guess_mapping($header)
{
    $mapping = array();
    $taken = array();

    foreach ($header as $index => $name) {
        $field = erp_import_guess_field($name);
        // Two columns cannot feed one field; the leftmost wins.
        if (($field !== '') && isset($taken[$field])) {
            $field = '';
        }
        if ($field !== '') {
            $taken[$field] = true;
        }
        $mapping[$index] = $field;
    }

    return $mapping;
}

/**
 * Kind words, Turkish and English, to the column's values.
 *
 * @param string $value
 * @return string  'customer', 'supplier', 'both' or '' when not recognised
 */
function erp_import_parse_kind($value)
{
    $key = erp_import_normalize_header($value);

    $map = array(
        'musteri' => 'customer', 'alici' => 'customer', 'customer' => 'customer', 'client' => 'customer', 'c' => 'customer',
        'tedarikci' => 'supplier', 'satici' => 'supplier', 'supplier' => 'supplier', 'vendor' => 'supplier', 's' => 'supplier',
        'herikisi' => 'both', 'ikisi' => 'both', 'musteritedarikci' => 'both', 'musterivetedarikci' => 'both', 'both' => 'both', 'b' => 'both',
    );

    return isset($map[$key]) ? $map[$key] : '';
}

/**
 * @param string $value
 * @return string  'active', 'passive' or ''
 */
function erp_import_parse_status($value)
{
    $key = erp_import_normalize_header($value);

    $map = array(
        'aktif' => 'active', 'active' => 'active', 'acik' => 'active', 'evet' => 'active', 'yes' => 'active', '1' => 'active',
        'pasif' => 'passive', 'passive' => 'passive', 'kapali' => 'passive', 'inactive' => 'passive', 'hayir' => 'passive', 'no' => 'passive', '0' => 'passive',
    );

    return isset($map[$key]) ? $map[$key] : '';
}

/**
 * @param string $value
 * @return int|null  1 person, 0 company, null when not recognised
 */
function erp_import_parse_is_person($value)
{
    $key = erp_import_normalize_header($value);

    $person = array('sahis', 'kisi', 'gercek', 'gercekkisi', 'bireysel', 'person', 'individual', 'evet', 'yes', '1', 'tckn');
    $company = array('sirket', 'firma', 'tuzel', 'tuzelkisi', 'kurumsal', 'company', 'corporate', 'legal', 'hayir', 'no', '0', 'vkn');

    if (in_array($key, $person, true)) {
        return 1;
    }

    if (in_array($key, $company, true)) {
        return 0;
    }

    return null;
}

/**
 * A country cell as an ISO code.
 *
 * Two letters are taken as the code. Anything longer is looked up by name in
 * the countries table; a name the table does not know comes back as it was
 * typed, so validation can say so.
 *
 * @param string $value
 * @return string
 */
function erp_import_parse_country($value)
{
    $value = trim((string) $value);

    if ($value === '') {
        return '';
    }

    if (preg_match('/^[A-Za-z]{2}$/', $value) === 1) {
        return strtoupper($value);
    }

    $code = (string) db_value("SELECT code FROM countries WHERE LOWER(name) = LOWER('" . escape($value) . "') LIMIT 1");

    return ($code !== '') ? strtoupper($code) : $value;
}

/**
 * Whether a code names a country the store knows.
 *
 * @param string $code
 * @return bool
 */
function erp_import_country_exists($code)
{
    static $cache = array();

    $code = strtoupper(trim((string) $code));

    if (!isset($cache[$code])) {
        $cache[$code] = ((int) db_value("SELECT COUNT(*) FROM countries WHERE code = '" . escape($code) . "'") > 0);
    }

    return $cache[$code];
}

/**
 * The defaults a row falls back on where the file says nothing.
 *
 * @param string $kind  'customer', 'supplier' or 'both'
 * @return array
 */
function erp_import_defaults($kind = 'customer')
{
    return array(
        'kind' => in_array($kind, array('customer', 'supplier', 'both'), true) ? $kind : 'customer',
        'country_code' => erp_default_country_code(),
        'currency' => erp_base_currency(),
    );
}

/**
 * Turn the cells of one row into an account array.
 *
 * Only mapped columns are read. is_person, when the file has no column for
 * it, follows the tax number: eleven digits is a person's TCKN, ten a
 * company's VKN. The 'mapped' entry lists the fields the file actually
 * supplied, so an update can leave the others alone.
 *
 * @param array $cells
 * @param array $mapping   column index => field
 * @param array $defaults  From erp_import_defaults()
 * @return array
 */
function erp_import_normalize_row($cells, $mapping, $defaults)
{
    $fields = erp_import_fields();

    $account = array(
        'title' => '', 'kind' => $defaults['kind'], 'is_person' => null, 'tax_number' => '', 'tax_office' => '',
        'email' => '', 'phone' => '', 'address' => '', 'district' => '', 'city' => '', 'postcode' => '',
        'country_code' => '', 'currency' => '', 'status' => 'active', 'notes' => '', 'payment_days' => '',
        'overdue_notify_days' => '',
    );
    $mapped = array();
    $unparsed = array();

    foreach ($mapping as $index => $field) {
        if (($field === '') || !isset($fields[$field]) || !isset($cells[$index])) {
            continue;
        }

        $value = trim((string) $cells[$index]);
        $mapped[$field] = true;

        if ($value === '') {
            continue;
        }

        switch ($field) {
            case 'kind':
                $parsed = erp_import_parse_kind($value);
                if ($parsed === '') {
                    $unparsed['kind'] = $value;
                } else {
                    $account['kind'] = $parsed;
                }
                break;

            case 'status':
                $parsed = erp_import_parse_status($value);
                if ($parsed === '') {
                    $unparsed['status'] = $value;
                } else {
                    $account['status'] = $parsed;
                }
                break;

            case 'is_person':
                $parsed = erp_import_parse_is_person($value);
                if ($parsed === null) {
                    $unparsed['is_person'] = $value;
                } else {
                    $account['is_person'] = $parsed;
                }
                break;

            case 'tax_number':
                $account['tax_number'] = preg_replace('/\s+/', '', $value);
                break;

            case 'country_code':
                $account['country_code'] = erp_import_parse_country($value);
                break;

            case 'currency':
                $account['currency'] = strtoupper($value);
                break;

            case 'email':
                $account['email'] = mb_strtolower($value, 'UTF-8');
                break;

            default:
                $account[$field] = mb_substr($value, 0, $fields[$field]['length'], 'UTF-8');
                break;
        }
    }

    if ($account['country_code'] === '') {
        $account['country_code'] = $defaults['country_code'];
    }

    if ($account['currency'] === '') {
        $account['currency'] = $defaults['currency'];
    }

    if ($account['is_person'] === null) {
        $length = strlen($account['tax_number']);
        $account['is_person'] = ($length === 10) ? 0 : 1;
    }

    $account['mapped'] = $mapped;
    $account['unparsed'] = $unparsed;

    return $account;
}

/**
 * What is wrong with a row, as messages for the operator.
 *
 * @param array $account  From erp_import_normalize_row()
 * @return array  Empty when the row can be written
 */
function erp_import_validate_row($account)
{
    $errors = array();

    if (trim((string) $account['title']) === '') {
        $errors[] = lang('Name is missing.');
    }

    if (($account['tax_number'] !== '') && (preg_match('/^[0-9]{10,11}$/', $account['tax_number']) !== 1)) {
        $errors[] = lang('Tax number must be 10 or 11 digits.');
    }

    if (($account['email'] !== '') && !validate_email_address($account['email'])) {
        $errors[] = lang('Invalid e-mail address.');
    }

    if (($account['country_code'] !== '') && ((preg_match('/^[A-Z]{2}$/', $account['country_code']) !== 1) || !erp_import_country_exists($account['country_code']))) {
        $errors[] = lang('Unknown country code.');
    }

    if ($account['currency'] !== erp_base_currency()) {
        if (!erp_fx_enabled() || !erp_fx_currency_allowed($account['currency'])) {
            $errors[] = lang('Currency not enabled.');
        }
    }

    // A term is whole days; ten years is more than any trade allows.
    if (((string) ($account['payment_days'] ?? '') !== '')
        && ((preg_match('/^[0-9]{1,4}$/', (string) $account['payment_days']) !== 1) || ((int) $account['payment_days'] > 3650))) {
        $errors[] = lang('Payment term must be a whole number of days, 0 to 3650.');
    }

    // A threshold is whole days; ten years is more than any reminder needs.
    if (((string) ($account['overdue_notify_days'] ?? '') !== '')
        && ((preg_match('/^[0-9]{1,4}$/', (string) $account['overdue_notify_days']) !== 1) || ((int) $account['overdue_notify_days'] > 3650))) {
        $errors[] = lang('Reminder threshold must be a whole number of days, 0 to 3650.');
    }

    foreach ($account['unparsed'] as $field => $value) {
        $errors[] = lang(array('string' => 'Unrecognised value for {var:1}: {var:2}', 'vars' => array(erp_import_fields()[$field]['label'], $value)));
    }

    return $errors;
}

/**
 * The account already on file that this row describes, if any.
 *
 * Tax number first, then e-mail, then - only when the row carries neither -
 * the exact name. A name alone is a weak match, two people can share one, so
 * it is not tried while a stronger key is there to say the row is somebody
 * else.
 *
 * @param array $account
 * @return array  ['id' => int, 'by' => 'tax_number'|'email'|'title'|'']
 */
function erp_import_find_existing($account)
{
    if ($account['tax_number'] !== '') {
        $id = (int) db_value("SELECT id FROM erp_accounts WHERE tax_number = '" . escape($account['tax_number']) . "' ORDER BY id ASC LIMIT 1");
        if ($id > 0) {
            return array('id' => $id, 'by' => 'tax_number');
        }
    }

    if ($account['email'] !== '') {
        $id = (int) db_value("SELECT id FROM erp_accounts WHERE LOWER(email) = '" . escape($account['email']) . "' ORDER BY id ASC LIMIT 1");
        if ($id > 0) {
            return array('id' => $id, 'by' => 'email');
        }
    }

    if (($account['tax_number'] === '') && ($account['email'] === '') && ($account['title'] !== '')) {
        $id = (int) db_value("SELECT id FROM erp_accounts WHERE LOWER(title) = LOWER('" . escape($account['title']) . "') ORDER BY id ASC LIMIT 1");
        if ($id > 0) {
            return array('id' => $id, 'by' => 'title');
        }
    }

    return array('id' => 0, 'by' => '');
}

/**
 * The key two rows of one file are the same account by.
 *
 * Same order as erp_import_find_existing(): a row with a tax number is that
 * number, a row with only an e-mail is that address, and a row with neither
 * is its name.
 *
 * @param array $account
 * @return string
 */
function erp_import_file_key($account)
{
    if ($account['tax_number'] !== '') {
        return 't:' . $account['tax_number'];
    }

    if ($account['email'] !== '') {
        return 'e:' . $account['email'];
    }

    return 'n:' . mb_strtolower(trim((string) $account['title']), 'UTF-8');
}

/**
 * Work out what the import would do with each row, without writing anything.
 *
 * The preview draws this and the import executes it, so the two cannot
 * disagree about a row.
 *
 * @param array $rows      From erp_import_read()
 * @param array $mapping
 * @param array $defaults
 * @return array  One entry per row: line, account, errors, existing_id, match_by, duplicate_of (line or 0)
 */
function erp_import_plan($rows, $mapping, $defaults)
{
    $plan = array();
    $seen = array();

    foreach ($rows as $row) {
        $account = erp_import_normalize_row($row['cells'], $mapping, $defaults);
        $errors = erp_import_validate_row($account);

        $entry = array(
            'line' => (int) $row['line'],
            'account' => $account,
            'errors' => $errors,
            'existing_id' => 0,
            'match_by' => '',
            'duplicate_of' => 0,
        );

        if (empty($errors)) {
            $key = erp_import_file_key($account);

            if (isset($seen[$key])) {
                $entry['duplicate_of'] = $seen[$key];
            } else {
                $seen[$key] = $entry['line'];
                $existing = erp_import_find_existing($account);
                $entry['existing_id'] = $existing['id'];
                $entry['match_by'] = $existing['by'];
            }
        }

        $plan[] = $entry;
    }

    return $plan;
}

/**
 * The columns an update writes: what the file supplied, over what is on file.
 *
 * An unmapped field, or an empty cell, leaves the stored value alone. A file
 * that lists names and phone numbers must not blank every address it does not
 * mention.
 *
 * @param array $existing  The row from erp_accounts
 * @param array $account   From erp_import_normalize_row()
 * @return array  Data for erp_account_save()
 */
function erp_import_merge($existing, $account)
{
    $data = array(
        'id' => (int) $existing['id'],
        'kind' => $existing['kind'],
        'title' => $existing['title'],
        'is_person' => ((int) $existing['is_person'] === 1),
        'tax_number' => $existing['tax_number'],
        'tax_office' => $existing['tax_office'],
        'email' => $existing['email'],
        'phone' => $existing['phone'],
        'address' => $existing['address'],
        'district' => $existing['district'],
        'city' => $existing['city'],
        'country_code' => $existing['country_code'],
        'postcode' => $existing['postcode'],
        'currency' => $existing['currency'],
        'contact_id' => (int) $existing['contact_id'],
        'status' => $existing['status'],
        'notes' => (string) $existing['notes'],
        'payment_days' => (int) ($existing['payment_days'] ?? 0),
        'overdue_notify_days' => (int) ($existing['overdue_notify_days'] ?? 0),
    );

    foreach ($account['mapped'] as $field => $unused) {
        if ($field === 'is_person') {
            $data['is_person'] = ((int) $account['is_person'] === 1);
            continue;
        }

        if (!isset($account[$field]) || (trim((string) $account[$field]) === '')) {
            continue;
        }

        // An account with movements keeps its currency: the own-currency
        // balance is summed over movements in that currency.
        if (($field === 'currency') && ((int) $existing['balance_fc'] !== 0)) {
            continue;
        }

        $data[$field] = $account[$field];
    }

    return $data;
}

/**
 * Write the plan.
 *
 * Rows are committed in batches so a failure late in a long file does not
 * throw away everything before it, and a failed batch is rolled back whole so
 * the file can be fixed and run again without half of it already on file.
 *
 * @param array  $plan     From erp_import_plan()
 * @param string $mode     'update' or 'skip' for rows that match an account
 * @param int    $user_id
 * @return array  counts (created, updated, skipped, failed) and results (line => ['status' => string, 'reason' => string, 'id' => int])
 */
function erp_import_run($plan, $mode, $user_id)
{
    $counts = array('created' => 0, 'updated' => 0, 'skipped' => 0, 'failed' => 0);
    $results = array();
    $update = ($mode === 'update');

    $batches = array_chunk($plan, ERP_IMPORT_BATCH_SIZE);

    foreach ($batches as $batch) {
        $batch_results = array();
        $failed_message = '';

        if (!erp_tx_begin()) {
            $failed_message = lang('Could not start a database transaction.');
        }

        if ($failed_message === '') {
            foreach ($batch as $entry) {
                $line = $entry['line'];

                if (!empty($entry['errors'])) {
                    $batch_results[$line] = array('status' => 'failed', 'reason' => implode(' ', $entry['errors']), 'id' => 0);
                    continue;
                }

                if ($entry['duplicate_of'] > 0) {
                    $batch_results[$line] = array('status' => 'skipped', 'reason' => lang(array('string' => 'Duplicate of row {var:1} in this file.', 'vars' => $entry['duplicate_of'])), 'id' => 0);
                    continue;
                }

                if ($entry['existing_id'] > 0) {
                    if (!$update) {
                        $batch_results[$line] = array('status' => 'skipped', 'reason' => lang(array('string' => 'Already exists (#{var:1}).', 'vars' => $entry['existing_id'])), 'id' => $entry['existing_id']);
                        continue;
                    }

                    $existing = erp_account($entry['existing_id']);

                    if (!is_array($existing)) {
                        $batch_results[$line] = array('status' => 'failed', 'reason' => lang('The account could not be found.'), 'id' => $entry['existing_id']);
                        continue;
                    }

                    $saved = erp_account_save(erp_import_merge($existing, $entry['account']));

                    if (!$saved['success']) {
                        $failed_message = $saved['error'];
                        break;
                    }

                    $batch_results[$line] = array('status' => 'updated', 'reason' => '', 'id' => (int) $saved['id']);
                    continue;
                }

                $account = $entry['account'];
                $account['id'] = 0;
                $account['is_person'] = ((int) $account['is_person'] === 1);
                $account['created_by'] = (int) $user_id;
                unset($account['mapped'], $account['unparsed']);

                $saved = erp_account_save($account);

                if (!$saved['success']) {
                    $failed_message = $saved['error'];
                    break;
                }

                $batch_results[$line] = array('status' => 'created', 'reason' => '', 'id' => (int) $saved['id']);
            }
        }

        if (($failed_message === '') && !erp_tx_commit()) {
            $failed_message = erp_db_error();
        }

        if ($failed_message !== '') {
            erp_tx_rollback();

            if ($failed_message === '') {
                $failed_message = lang('The row could not be saved.');
            }

            // Nothing in this batch is on file, whatever the loop above
            // recorded for the rows before the failure.
            $batch_results = array();
            foreach ($batch as $entry) {
                $batch_results[$entry['line']] = array('status' => 'failed', 'reason' => $failed_message, 'id' => 0);
            }
        }

        foreach ($batch_results as $line => $result) {
            $counts[$result['status']]++;
            $results[$line] = $result;
        }
    }

    return array('counts' => $counts, 'results' => $results);
}

/**
 * Where uploaded files wait between the steps of the screen.
 *
 * data/temp is denied to the web server and is the folder the rest of the
 * panel stages files in.
 *
 * @return string  Directory path without trailing slash, '' when it cannot be made
 */
function erp_import_temp_dir()
{
    $directory = PG_FUNCTIONS_DIR . '/data/temp';

    if (!is_dir($directory)) {
        @mkdir($directory, 0755, true);
    }

    return (is_dir($directory) && is_writable($directory)) ? $directory : '';
}

/**
 * A fresh token for a staged file.
 *
 * @return string  32 hex characters
 */
function erp_import_new_token()
{
    return bin2hex(random_bytes(16));
}

/**
 * The staged file a token names, or '' for a token that is not one of ours.
 *
 * The token is checked against its shape before it goes anywhere near a path.
 *
 * @param string $token
 * @return string
 */
function erp_import_temp_path($token)
{
    if (preg_match('/^[a-f0-9]{32}$/', (string) $token) !== 1) {
        return '';
    }

    $directory = erp_import_temp_dir();

    return ($directory !== '') ? ($directory . '/erp_import_' . $token . '.csv') : '';
}

/**
 * Remove staged files nobody came back for.
 *
 * @param int $max_age  Seconds
 * @return int  Files removed
 */
function erp_import_sweep($max_age = 86400)
{
    $directory = erp_import_temp_dir();

    if ($directory === '') {
        return 0;
    }

    $removed = 0;
    $files = glob($directory . '/erp_import_*.csv');

    foreach ((array) $files as $file) {
        if (is_file($file) && ((time() - (int) @filemtime($file)) > $max_age)) {
            if (@unlink($file)) {
                $removed++;
            }
        }
    }

    return $removed;
}

/**
 * The template file: the header row the importer maps by itself, and one
 * example line.
 *
 * Semicolon-separated with a byte order mark, which is what a Turkish Excel
 * opens correctly without being asked.
 *
 * @return string
 */
function erp_import_template_csv()
{
    $fields = erp_import_fields();

    $header = array();
    foreach ($fields as $field => $definition) {
        $header[] = $definition['label'];
    }

    $example = array(
        lang('Example Ltd.'), lang('Customer'), lang('Company'), '1234567890', lang('Central'), 'info@example.com',
        '+90 212 000 00 00', lang('Example Street 1'), '', '', '', erp_default_country_code(), erp_base_currency(),
        lang('Active'), '', '30', '',
    );

    $handle = fopen('php://temp', 'r+');
    fwrite($handle, "\xEF\xBB\xBF");
    fputcsv($handle, $header, ';', '"', '\\');
    fputcsv($handle, $example, ';', '"', '\\');
    rewind($handle);
    $csv = stream_get_contents($handle);
    fclose($handle);

    return $csv;
}
