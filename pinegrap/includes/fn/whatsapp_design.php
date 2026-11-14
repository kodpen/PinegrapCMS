<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The WhatsApp template designer (whatsapp_template.php): a template is
 * written in the panel, previewed as a phone shows it and kept as a draft in
 * whatsapp_templates (a row without meta_id, status DRAFT) until it is sent
 * to Meta for approval.
 *
 * A draft holds what Meta's template API reads: the name, the category, the
 * language and the components (BODY with its {{1}}, {{2}} … variables and
 * their samples, FOOTER). The samples column keeps the designer's own side of
 * each variable: what it stands for (a label the senders show when they
 * choose what fills it) and the sample value the preview and Meta's review
 * read. A template Meta already holds is shown read-only; an approved one can
 * be sent to the signed-in person's own WhatsApp with the sample values.
 *
 * The preview's formatting (*bold*, _italic_, ~strike~, ```code```) is
 * pg_whatsapp_design_format_html() here and pgWaFormat() in the screen's
 * script; the two are kept alike.
 *
 * Loaded where it is needed (require_once), not by functions.php.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/whatsapp.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/var_chips.php');

// Meta's limits for a template's parts, in characters.
define('PG_WHATSAPP_DESIGN_BODY_MAX', 1024);
define('PG_WHATSAPP_DESIGN_FOOTER_MAX', 60);
define('PG_WHATSAPP_DESIGN_NAME_MAX', 512);

// The longest sample value and variable label the designer keeps.
define('PG_WHATSAPP_DESIGN_SAMPLE_MAX', 200);

// Meta's limits for a header text and a button's text, in characters.
define('PG_WHATSAPP_DESIGN_HEADER_MAX', 60);
define('PG_WHATSAPP_DESIGN_BUTTON_MAX', 25);

// Buttons a template designed here holds (Meta takes ten; more than three
// show as a list behind "See all options").
define('PG_WHATSAPP_DESIGN_BUTTONS_MAX', 3);

// Variables one body may hold: more is refused by Meta as too dense long
// before this, which only bounds what is read from a form.
define('PG_WHATSAPP_DESIGN_VARIABLES_MAX', 20);


/* ---------------------------------------------------------------------------
   What a template may be (pure)
   --------------------------------------------------------------------------- */

/**
 * The languages a template can be written in: Meta's codes and each
 * language's own name, which needs no translation.
 *
 * @return array code => name
 */
function pg_whatsapp_design_languages()
{
    return array(
        'tr'    => 'Türkçe',
        'en'    => 'English',
        'en_US' => 'English (US)',
        'en_GB' => 'English (UK)',
        'de'    => 'Deutsch',
        'fr'    => 'Français',
        'es'    => 'Español',
        'it'    => 'Italiano',
        'nl'    => 'Nederlands',
        'pt_BR' => 'Português (BR)',
        'pt_PT' => 'Português (PT)',
        'ru'    => 'Русский',
        'uk'    => 'Українська',
        'pl'    => 'Polski',
        'ro'    => 'Română',
        'bg'    => 'Български',
        'el'    => 'Ελληνικά',
        'ar'    => 'العربية',
        'fa'    => 'فارسی',
        'az'    => 'Azərbaycan',
        'ka'    => 'ქართული',
        'sq'    => 'Shqip',
        'bs'    => 'Bosanski',
        'zh_CN' => '中文 (简体)',
    );
}

/**
 * The template types in the order the designer offers them.
 *
 * @return array MARKETING, UTILITY, AUTHENTICATION => label
 */
function pg_whatsapp_design_categories()
{
    return array(
        'UTILITY'        => pg_whatsapp_template_category_label('UTILITY'),
        'MARKETING'      => pg_whatsapp_template_category_label('MARKETING'),
        'AUTHENTICATION' => pg_whatsapp_template_category_label('AUTHENTICATION'),
    );
}

/**
 * What "Add variable" offers: a label for what the variable stands for. The
 * sender (an order moment, a form, a flow) chooses what fills it; the label
 * only says what was meant.
 *
 * @return array labels
 */
function pg_whatsapp_design_variable_presets()
{
    return array(
        lang('Customer\'s name'),
        lang('Order number'),
        lang('Order total'),
        lang('Date'),
        lang('Tracking number'),
        lang('Link'),
    );
}

/**
 * The variables a text holds, in the order they first appear: {{1}}, {{2}}
 * or, in a template Meta holds with named parameters, {{first_name}}.
 *
 * @param string $text
 * @return array the keys as strings ('1', '2', 'first_name')
 */
function pg_whatsapp_design_variables($text)
{
    preg_match_all('/\{\{\s*([a-z0-9_]{1,64})\s*\}\}/', (string) $text, $matches);

    return array_values(array_unique($matches[1]));
}


/**
 * Where a variable's example sits in Meta's example list: {{1}} is the
 * first of a positional list, a named variable is its own key.
 *
 * @param string $key
 * @return int|string
 */
function pg_whatsapp_design_example_index($key)
{
    return ctype_digit((string) $key) ? ((int) $key - 1) : (string) $key;
}


/* ---------------------------------------------------------------------------
   A design: read from a row or a form, checked, turned into components
   --------------------------------------------------------------------------- */

/**
 * An empty design, as "New template" starts it.
 *
 * @return array
 */
function pg_whatsapp_design_new()
{
    return array(
        'id' => 0, 'meta_id' => '', 'name' => '', 'language' => 'tr', 'category' => 'UTILITY',
        'status' => 'DRAFT', 'reason' => '', 'parameter_format' => 'POSITIONAL',
        'header' => array(), 'body' => '', 'footer' => '', 'buttons' => array(),
        'labels' => array(), 'samples' => array(), 'components' => array(),
        'header_file' => array(), 'header_link' => '',
    );
}

/**
 * A whatsapp_templates row as the designer reads it; no database.
 *
 * The samples column ({"body": [{"label": …, "value": …}, …]}) gives the
 * labels and samples; a template read from Meta has no such column, so its
 * samples are the examples Meta keeps in the components.
 *
 * @param array $row id, meta_id, name, language, category, status, reason,
 *                   parameter_format, components (JSON), samples (JSON)
 * @return array pg_whatsapp_design_new() shape
 */
function pg_whatsapp_design_from_row($row)
{
    $design = pg_whatsapp_design_new();

    foreach (array('meta_id', 'name', 'language', 'category', 'status', 'reason', 'parameter_format') as $key) {
        $design[$key] = (isset($row[$key]) && is_scalar($row[$key])) ? (string) $row[$key] : '';
    }

    $design['id'] = isset($row['id']) ? (int) $row['id'] : 0;

    $components = isset($row['components']) ? (is_array($row['components']) ? $row['components'] : json_decode((string) $row['components'], true)) : array();
    $design['components'] = is_array($components) ? array_values(array_filter($components, 'is_array')) : array();

    $examples = array();

    foreach ($design['components'] as $component) {
        $type = isset($component['type']) ? strtoupper((string) $component['type']) : '';

        if ($type === 'HEADER') {
            $design['header'] = $component;
        } elseif ($type === 'BODY') {
            $design['body'] = isset($component['text']) && is_scalar($component['text']) ? (string) $component['text'] : '';

            if (isset($component['example']['body_text'][0]) && is_array($component['example']['body_text'][0])) {
                $examples = array_values($component['example']['body_text'][0]);
            } elseif (isset($component['example']['body_text_named_params']) && is_array($component['example']['body_text_named_params'])) {
                foreach ($component['example']['body_text_named_params'] as $param) {
                    if (isset($param['param_name'], $param['example'])) {
                        $examples[(string) $param['param_name']] = $param['example'];
                    }
                }
            }
        } elseif ($type === 'FOOTER') {
            $design['footer'] = isset($component['text']) && is_scalar($component['text']) ? (string) $component['text'] : '';
        } elseif ($type === 'BUTTONS') {
            $design['buttons'] = (isset($component['buttons']) && is_array($component['buttons'])) ? array_values($component['buttons']) : array();
        }
    }

    foreach ($design['buttons'] as $index => $button) {
        if (is_array($button) && isset($button['type'], $button['url']) && (strtoupper((string) $button['type']) === 'URL') && (strpos((string) $button['url'], '{{') !== false)) {
            $design['buttons'][$index]['sample'] = pg_whatsapp_design_url_sample($button);
        }
    }

    $samples = isset($row['samples']) ? json_decode((string) $row['samples'], true) : null;
    $kept = (is_array($samples) && isset($samples['body']) && is_array($samples['body'])) ? array_values($samples['body']) : array();

    if (is_array($samples) && isset($samples['header_file']['id'], $samples['header_file']['name'])) {
        $design['header_file'] = array('id' => (int) $samples['header_file']['id'], 'name' => (string) $samples['header_file']['name'], 'size' => 0);
    }

    foreach (pg_whatsapp_design_variables($design['body']) as $index => $key) {
        $item = isset($kept[$index]) && is_array($kept[$index]) ? $kept[$index] : array();
        $design['labels'][$key] = (isset($item['label']) && is_scalar($item['label'])) ? (string) $item['label'] : '';

        if (isset($item['value']) && is_scalar($item['value'])) {
            $design['samples'][$key] = (string) $item['value'];
        } elseif (isset($examples[pg_whatsapp_design_example_index($key)]) && is_scalar($examples[pg_whatsapp_design_example_index($key)])) {
            $design['samples'][$key] = (string) $examples[pg_whatsapp_design_example_index($key)];
        } else {
            $design['samples'][$key] = '';
        }
    }

    return $design;
}

/**
 * What the designer's form posted, over the design it was opened with; no
 * database. A field posted as a list counts as not given.
 *
 * @param array $post   $_POST: name, language, category, body, footer,
 *                      label_<n>, sample_<n>, header_format (NONE, TEXT,
 *                      IMAGE, VIDEO, DOCUMENT), header_text, header_file
 *                      (a file's id), and per button row button_type[],
 *                      button_text[], button_value[], button_sample[]
 * @param array $design the design the screen was opened with
 * @return array
 */
function pg_whatsapp_design_posted($post, $design)
{
    $stored = array('name' => $design['name'], 'language' => $design['language']);

    $text = function ($name) use ($post) {
        return (isset($post[$name]) && is_scalar($post[$name])) ? str_replace(array("\r\n", "\r"), "\n", (string) $post[$name]) : '';
    };

    $design['name'] = strtolower(trim($text('name')));
    $design['language'] = trim($text('language'));
    $design['category'] = strtoupper(trim($text('category')));
    $design['body'] = trim($text('body'));
    $design['footer'] = trim($text('footer'));
    $design['labels'] = array();
    $design['samples'] = array();

    foreach (array_slice(pg_whatsapp_design_variables($design['body']), 0, PG_WHATSAPP_DESIGN_VARIABLES_MAX) as $key) {
        $design['labels'][$key] = trim($text('label_' . $key));
        $design['samples'][$key] = trim($text('sample_' . $key));
    }

    // A template Meta already knows keeps its name and language: an edit
    // can change only its category and components.
    if (($design['meta_id'] !== '') && isset($stored)) {
        $design['name'] = $stored['name'];
        $design['language'] = $stored['language'];
    }

    $format = strtoupper(trim($text('header_format')));
    $design['header'] = array();
    $design['header_file'] = array();

    if ($format === 'TEXT') {
        $design['header'] = array('type' => 'HEADER', 'format' => 'TEXT', 'text' => trim($text('header_text')));
    } elseif (in_array($format, array('IMAGE', 'VIDEO', 'DOCUMENT'), true)) {
        $design['header'] = array('type' => 'HEADER', 'format' => $format);
        $design['header_file'] = array('id' => (int) trim($text('header_file')), 'name' => '', 'size' => 0);
    }

    // The buttons come as lists, one entry per row; a row left wholly empty
    // is no button.
    $list = function ($name) use ($post) {
        return (isset($post[$name]) && is_array($post[$name])) ? array_values($post[$name]) : array();
    };
    $types = $list('button_type');
    $texts = $list('button_text');
    $values = $list('button_value');
    $samples = $list('button_sample');
    $design['buttons'] = array();

    foreach (array_slice($types, 0, 10) as $index => $type) {
        $cell = function ($from) use ($index) {
            return (isset($from[$index]) && is_scalar($from[$index])) ? trim(str_replace(array("\r", "\n"), ' ', (string) $from[$index])) : '';
        };
        $type = strtoupper(is_scalar($type) ? trim((string) $type) : '');
        $label = $cell($texts);
        $value = $cell($values);

        if (($label === '') && ($value === '')) {
            continue;
        }

        $button = array('type' => $type, 'text' => $label);

        if ($type === 'URL') {
            $button['url'] = $value;
            $sample = $cell($samples);

            if (preg_match('/\{\{\s*1\s*\}\}$/', $value)) {
                $button['example'] = array(preg_replace('/\{\{\s*1\s*\}\}$/', '', $value) . $sample);
                $button['sample'] = $sample;
            }
        } elseif ($type === 'PHONE_NUMBER') {
            $button['phone_number'] = preg_replace('/[\s().-]+/', '', $value);
        }

        $design['buttons'][] = $button;
    }

    return $design;
}

/**
 * Whether a design may be kept as a draft; no database.
 *
 * @param array $design pg_whatsapp_design_posted()
 * @return array field => message; empty when it may
 */
function pg_whatsapp_design_check($design)
{
    $errors = array();
    $length = function ($text) {
        return function_exists('mb_strlen') ? mb_strlen($text, 'UTF-8') : strlen($text);
    };

    if ($design['name'] === '') {
        $errors['name'] = lang('Give the template a name.');
    } elseif (!preg_match('/^[a-z0-9_]{1,' . PG_WHATSAPP_DESIGN_NAME_MAX . '}$/D', $design['name'])) {
        $errors['name'] = lang('A template name holds only lower-case letters (a-z), digits and _.');
    }

    if (!isset(pg_whatsapp_design_categories()[$design['category']])) {
        $errors['category'] = lang('Choose the template type.');
    }

    if (!isset(pg_whatsapp_design_languages()[$design['language']])) {
        $errors['language'] = lang('Choose the template language.');
    }

    $body = $design['body'];

    if ($body === '') {
        $errors['body'] = lang('Write the message.');
    } elseif ($length($body) > PG_WHATSAPP_DESIGN_BODY_MAX) {
        $errors['body'] = lang(array('string' => 'The message may hold at most {var:1} characters.', 'vars' => array(PG_WHATSAPP_DESIGN_BODY_MAX)));
    } else {
        $keys = pg_whatsapp_design_variables($body);
        $numbers = array_map('intval', array_filter($keys, 'ctype_digit'));
        sort($numbers);

        // Braces left over once every {{n}} is taken out: a variable
        // written by hand that WhatsApp would not read as one.
        $rest = preg_replace('/\{\{\s*[0-9]{1,2}\s*\}\}/', '', $body);

        if ((count($numbers) !== count($keys)) || (strpos($rest, '{{') !== false) || (strpos($rest, '}}') !== false)) {
            $errors['body'] = lang('Write a variable as {{1}}, {{2}} and so on, or add it with Add variable.');
        } elseif (count($keys) > PG_WHATSAPP_DESIGN_VARIABLES_MAX) {
            $errors['body'] = lang(array('string' => 'A message may hold at most {var:1} variables.', 'vars' => array(PG_WHATSAPP_DESIGN_VARIABLES_MAX)));
        } elseif ($numbers && ($numbers !== range(1, count($numbers)))) {
            $errors['body'] = lang('Number the variables in turn from {{1}}, with none left out.');
        } elseif (preg_match('/^\{\{\s*[0-9]+\s*\}\}/', $body) || preg_match('/\{\{\s*[0-9]+\s*\}\}$/', $body)) {
            $errors['body'] = lang('WhatsApp does not accept a message that starts or ends with a variable; add some text before or after it.');
        } else {
            foreach ($keys as $key) {
                $sample = isset($design['samples'][$key]) ? $design['samples'][$key] : '';

                if ($sample === '') {
                    $errors['sample_' . $key] = lang('Give each variable a sample value; WhatsApp\'s review reads it.');
                } elseif ($length($sample) > PG_WHATSAPP_DESIGN_SAMPLE_MAX) {
                    $errors['sample_' . $key] = lang(array('string' => 'A sample value may hold at most {var:1} characters.', 'vars' => array(PG_WHATSAPP_DESIGN_SAMPLE_MAX)));
                }

                if (isset($design['labels'][$key]) && ($length($design['labels'][$key]) > PG_WHATSAPP_DESIGN_SAMPLE_MAX)) {
                    $errors['label_' . $key] = lang(array('string' => 'A variable\'s name may hold at most {var:1} characters.', 'vars' => array(PG_WHATSAPP_DESIGN_SAMPLE_MAX)));
                }
            }
        }
    }

    $errors += pg_whatsapp_design_check_header($design);
    $errors += pg_whatsapp_design_check_buttons($design['buttons']);

    if ($length($design['footer']) > PG_WHATSAPP_DESIGN_FOOTER_MAX) {
        $errors['footer'] = lang(array('string' => 'The footer may hold at most {var:1} characters.', 'vars' => array(PG_WHATSAPP_DESIGN_FOOTER_MAX)));
    } elseif ((strpos($design['footer'], '{{') !== false) || (strpos($design['footer'], '}}') !== false)) {
        $errors['footer'] = lang('The footer cannot hold a variable.');
    }

    return $errors;
}

/**
 * A design's components as Meta's template API reads them; no database.
 * The header and buttons the row already holds are kept: the designer's
 * second half writes them.
 *
 * @param array $design
 * @return array
 */
function pg_whatsapp_design_components($design)
{
    $body = array('type' => 'BODY', 'text' => $design['body']);
    $keys = pg_whatsapp_design_variables($design['body']);

    if ($keys) {
        $samples = array();

        foreach ($keys as $key) {
            $samples[] = isset($design['samples'][$key]) ? (string) $design['samples'][$key] : '';
        }

        $body['example'] = array('body_text' => array($samples));
    }

    $components = array();

    if ($design['header']) {
        $components[] = $design['header'];
    }

    $components[] = $body;

    if ($design['footer'] !== '') {
        $components[] = array('type' => 'FOOTER', 'text' => $design['footer']);
    }

    if ($design['buttons']) {
        // Meta wants quick replies next to each other: the others first, in
        // their order, then the quick replies in theirs.
        $others = array();
        $replies = array();

        foreach ($design['buttons'] as $button) {
            unset($button['sample']);

            if (isset($button['type']) && ($button['type'] === 'QUICK_REPLY')) {
                $replies[] = $button;
            } else {
                $others[] = $button;
            }
        }

        $components[] = array('type' => 'BUTTONS', 'buttons' => array_merge($others, $replies));
    }

    return $components;
}

/**
 * The samples column of a design: each variable's label and sample, in the
 * order the body holds them.
 *
 * @param array $design
 * @return string JSON
 */
function pg_whatsapp_design_samples_json($design)
{
    $body = array();

    foreach (pg_whatsapp_design_variables($design['body']) as $key) {
        $body[] = array(
            'label' => isset($design['labels'][$key]) ? (string) $design['labels'][$key] : '',
            'value' => isset($design['samples'][$key]) ? (string) $design['samples'][$key] : '',
        );
    }

    $samples = array('body' => $body);

    if (!empty($design['header_file']['id'])) {
        $samples['header_file'] = array('id' => (int) $design['header_file']['id'], 'name' => (string) $design['header_file']['name']);
    }

    return json_encode($samples, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

/**
 * Whether a template can be changed in the designer: a draft, or one Meta
 * rejected (sent again as an edit of the same template).
 *
 * @param array $design
 * @return bool
 */
function pg_whatsapp_design_editable($design)
{
    return (($design['meta_id'] === '') && ($design['status'] === 'DRAFT'))
        || (($design['meta_id'] !== '') && ($design['status'] === 'REJECTED'));
}

/**
 * The files a header of a format may take: their extensions, MIME types and
 * the largest size. Meta takes a document of up to 100 MB; a sample is read
 * into memory to be sent, so the designer stops at 16 MB.
 *
 * @return array format => extensions (extension => MIME type), max (bytes)
 */
function pg_whatsapp_design_header_formats()
{
    return array(
        'IMAGE'    => array('types' => array('jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png'), 'max' => 5242880),
        'VIDEO'    => array('types' => array('mp4' => 'video/mp4', '3gp' => 'video/3gpp'), 'max' => 16777216),
        'DOCUMENT' => array('types' => array('pdf' => 'application/pdf'), 'max' => 16777216),
    );
}

/**
 * Whether a header can be kept; no database (the chosen file's name and
 * size are in header_file).
 *
 * @param array $design
 * @return array field => message
 */
function pg_whatsapp_design_check_header($design)
{
    if (!$design['header']) {
        return array();
    }

    $format = isset($design['header']['format']) ? $design['header']['format'] : '';

    if ($format === 'TEXT') {
        $text = isset($design['header']['text']) ? (string) $design['header']['text'] : '';

        if ($text === '') {
            return array('header_text' => lang('Write the header, or choose None.'));
        }

        if ((function_exists('mb_strlen') ? mb_strlen($text, 'UTF-8') : strlen($text)) > PG_WHATSAPP_DESIGN_HEADER_MAX) {
            return array('header_text' => lang(array('string' => 'The header may hold at most {var:1} characters.', 'vars' => array(PG_WHATSAPP_DESIGN_HEADER_MAX))));
        }

        if ((strpos($text, '{{') !== false) || (strpos($text, '}}') !== false)) {
            return array('header_text' => lang('The header cannot hold a variable here; put it in the message.'));
        }

        return array();
    }

    $formats = pg_whatsapp_design_header_formats();
    $file = $design['header_file'];

    if (!isset($formats[$format])) {
        return array('header_file' => lang('Choose the header type.'));
    }

    if (empty($file['id']) || ((string) $file['name'] === '')) {
        return array('header_file' => lang('Choose the header\'s file from Files.'));
    }

    $extension = strtolower((string) pathinfo((string) $file['name'], PATHINFO_EXTENSION));

    if (!isset($formats[$format]['types'][$extension])) {
        return array('header_file' => lang(array('string' => 'This header takes a file of type {var:1}.', 'vars' => array(strtoupper(implode(', ', array_keys($formats[$format]['types'])))))));
    }

    if ((int) $file['size'] > $formats[$format]['max']) {
        return array('header_file' => lang(array('string' => 'The file may be at most {var:1} MB.', 'vars' => array((int) ($formats[$format]['max'] / 1048576)))));
    }

    return array();
}

/**
 * Whether the buttons can be kept; no database.
 *
 * @param array $buttons pg_whatsapp_design_posted() buttons
 * @return array 'button_<row>' => message
 */
function pg_whatsapp_design_check_buttons($buttons)
{
    $errors = array();
    $count = array('URL' => 0, 'PHONE_NUMBER' => 0);

    if (count($buttons) > PG_WHATSAPP_DESIGN_BUTTONS_MAX) {
        return array('button_0' => lang(array('string' => 'A template may have at most {var:1} buttons.', 'vars' => array(PG_WHATSAPP_DESIGN_BUTTONS_MAX))));
    }

    foreach (array_values($buttons) as $row => $button) {
        $type = $button['type'];
        $text = (string) $button['text'];
        $field = 'button_' . $row;

        if (!in_array($type, array('URL', 'PHONE_NUMBER', 'QUICK_REPLY'), true)) {
            $errors[$field] = lang('Choose the button\'s kind.');
        } elseif ($text === '') {
            $errors[$field] = lang('Give the button a text.');
        } elseif ((function_exists('mb_strlen') ? mb_strlen($text, 'UTF-8') : strlen($text)) > PG_WHATSAPP_DESIGN_BUTTON_MAX) {
            $errors[$field] = lang(array('string' => 'A button\'s text may hold at most {var:1} characters.', 'vars' => array(PG_WHATSAPP_DESIGN_BUTTON_MAX)));
        } elseif ($type === 'URL') {
            $url = (string) $button['url'];
            $rest = preg_replace('/\{\{\s*1\s*\}\}$/', '', $url);

            if (!preg_match('#^https?://[^\s{}]+$#i', $rest) || (strlen($url) > 2000)) {
                $errors[$field] = lang('Write the link as https://… ; a variable may stand only at its end, as {{1}}.');
            } elseif (($rest !== $url) && (!isset($button['sample']) || !preg_match('/^[^\s{}]{1,200}$/', (string) $button['sample']))) {
                $errors[$field] = lang('Give the link\'s variable a sample value without spaces.');
            } elseif (++$count['URL'] > 2) {
                $errors[$field] = lang('A template may have at most two link buttons.');
            }
        } elseif ($type === 'PHONE_NUMBER') {
            if (!preg_match('/^\+?[0-9]{7,20}$/', (string) $button['phone_number'])) {
                $errors[$field] = lang('Write the phone number with its country code, such as +90 212 555 01 02.');
            } elseif (++$count['PHONE_NUMBER'] > 1) {
                $errors[$field] = lang('A template may have only one phone button.');
            }
        }
    }

    return $errors;
}

/**
 * The template as it is sent to Meta for review; no database. A picture,
 * video or document header names the handle of its uploaded sample.
 *
 * @param array  $design checked
 * @param string $handle the header sample's handle ('' without one)
 * @return array name, language, category, components
 */
function pg_whatsapp_design_submission($design, $handle)
{
    $components = pg_whatsapp_design_components($design);

    foreach ($components as $index => $component) {
        if (($component['type'] === 'HEADER') && ($component['format'] !== 'TEXT')) {
            $components[$index]['example'] = array('header_handle' => array((string) $handle));
        }
    }

    return array(
        'name'       => $design['name'],
        'language'   => $design['language'],
        'category'   => $design['category'],
        'components' => $components,
    );
}


/* ---------------------------------------------------------------------------
   The preview (pure; pgWaFormat() in the screen's script is its twin)
   --------------------------------------------------------------------------- */

/**
 * A template text as WhatsApp shows it: escaped, its *bold*, _italic_,
 * ~strike~ and ```code``` drawn, each variable as its sample in a mark (the
 * variable itself when it has no sample), line breaks kept.
 *
 * @param string $text
 * @param array  $samples key => sample
 * @return string HTML
 */
function pg_whatsapp_design_format_html($text, $samples)
{
    $marks = array();

    // Variables first, as placeholders no formatting rule matches.
    $text = preg_replace_callback('/\{\{\s*([a-z0-9_]{1,64})\s*\}\}/', function ($match) use ($samples, &$marks) {
        $value = (isset($samples[$match[1]]) && ((string) $samples[$match[1]] !== '')) ? (string) $samples[$match[1]] : '{{' . $match[1] . '}}';
        $marks[] = '<mark>' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '</mark>';

        return "\x01" . (count($marks) - 1) . "\x02";
    }, (string) $text);

    $html = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    $html = preg_replace('/```([^`]+?)```/s', '<code>$1</code>', $html);
    $html = preg_replace('/(^|[^\w*])\*([^*\n]+?)\*(?![\w*])/u', '$1<b>$2</b>', $html);
    $html = preg_replace('/(^|[^\w_])_([^_\n]+?)_(?![\w_])/u', '$1<i>$2</i>', $html);
    $html = preg_replace('/(^|[^\w~])~([^~\n]+?)~(?![\w~])/u', '$1<s>$2</s>', $html);

    $html = preg_replace_callback("/\x01([0-9]+)\x02/", function ($match) use ($marks) {
        return $marks[(int) $match[1]];
    }, $html);

    return str_replace("\n", '<br>', $html);
}

/**
 * The parameters a test send fills an approved template with: each variable
 * of its header text and body with its sample, a picture, video or document
 * header by the public address of its file (header_link), a link button's
 * variable by its sample; no database.
 *
 * A template with a copy-code or one-time-code button, or a media header
 * whose file is not open to everyone, cannot be sent as a test.
 *
 * @param array $design pg_whatsapp_design_from_row()
 * @return array ok, components, error
 */
function pg_whatsapp_design_send_components($design)
{
    $named = (strtoupper($design['parameter_format']) === 'NAMED');
    $components = array();

    $parameters = function ($keys, $samples) use ($named) {
        $list = array();

        foreach ($keys as $key) {
            $value = (isset($samples[$key]) && ((string) $samples[$key] !== '')) ? (string) $samples[$key] : null;

            if ($value === null) {
                return null;
            }

            $parameter = array('type' => 'text', 'text' => $value);

            if ($named) {
                $parameter['parameter_name'] = (string) $key;
            }

            $list[] = $parameter;
        }

        return $list;
    };

    if ($design['header']) {
        $format = isset($design['header']['format']) ? strtoupper((string) $design['header']['format']) : '';

        if (in_array($format, array('IMAGE', 'VIDEO', 'DOCUMENT'), true)) {
            if ((string) $design['header_link'] === '') {
                return array('ok' => false, 'components' => array(), 'error' => lang('The header\'s file is not in Files or not open to everyone, so WhatsApp cannot fetch it for a test.'));
            }

            $kind = strtolower($format);
            $components[] = array('type' => 'header', 'parameters' => array(array('type' => $kind, $kind => array('link' => (string) $design['header_link']))));
            $format = 'MEDIA';
        } elseif ($format !== 'TEXT') {
            return array('ok' => false, 'components' => array(), 'error' => lang('A template with a picture, video or document at its top cannot be sent as a test yet.'));
        }

        $text = isset($design['header']['text']) ? (string) $design['header']['text'] : '';
        $keys = ($format === 'TEXT') ? pg_whatsapp_design_variables($text) : array();

        if ($keys) {
            $example = array();

            if (isset($design['header']['example']['header_text']) && is_array($design['header']['example']['header_text'])) {
                $example = array_values($design['header']['example']['header_text']);
            } elseif (isset($design['header']['example']['header_text_named_params']) && is_array($design['header']['example']['header_text_named_params'])) {
                foreach ($design['header']['example']['header_text_named_params'] as $param) {
                    if (isset($param['param_name'], $param['example'])) {
                        $example[(string) $param['param_name']] = $param['example'];
                    }
                }
            }

            $samples = array();

            foreach ($keys as $key) {
                $at = pg_whatsapp_design_example_index($key);
                $samples[$key] = (isset($example[$at]) && is_scalar($example[$at])) ? (string) $example[$at] : '';
            }

            $list = $parameters($keys, $samples);

            if ($list === null) {
                return array('ok' => false, 'components' => array(), 'error' => lang('The template\'s top line has a variable without a sample value.'));
            }

            $components[] = array('type' => 'header', 'parameters' => $list);
        }
    }

    $keys = pg_whatsapp_design_variables($design['body']);

    if ($keys) {
        $list = $parameters($keys, $design['samples']);

        if ($list === null) {
            return array('ok' => false, 'components' => array(), 'error' => lang('Give each variable a sample value; the test fills the message with them.'));
        }

        $components[] = array('type' => 'body', 'parameters' => $list);
    }

    foreach (array_values($design['buttons']) as $index => $button) {
        $type = isset($button['type']) ? strtoupper((string) $button['type']) : '';
        $url = isset($button['url']) ? (string) $button['url'] : '';

        if (($type === 'COPY_CODE') || ($type === 'OTP')) {
            return array('ok' => false, 'components' => array(), 'error' => lang('A template with a button that takes a value cannot be sent as a test yet.'));
        }

        if (($type === 'URL') && (strpos($url, '{{') !== false)) {
            $sample = pg_whatsapp_design_url_sample($button);

            if ($sample === '') {
                return array('ok' => false, 'components' => array(), 'error' => lang('Give the link\'s variable a sample value without spaces.'));
            }

            $components[] = array('type' => 'button', 'sub_type' => 'url', 'index' => (string) $index, 'parameters' => array(array('type' => 'text', 'text' => $sample)));
        }
    }

    return array('ok' => true, 'components' => $components, 'error' => '');
}


/**
 * A link button's sample: the end of its example after the fixed part of
 * its address.
 *
 * @param array $button url, example (a list of one full address), sample
 * @return string
 */
function pg_whatsapp_design_url_sample($button)
{
    if (isset($button['sample']) && is_scalar($button['sample']) && ((string) $button['sample'] !== '')) {
        return (string) $button['sample'];
    }

    $prefix = preg_replace('/\{\{\s*[a-z0-9_]+\s*\}\}$/', '', isset($button['url']) ? (string) $button['url'] : '');
    $example = (isset($button['example'][0]) && is_scalar($button['example'][0])) ? (string) $button['example'][0] : '';

    return (($prefix !== '') && (strpos($example, $prefix) === 0)) ? (string) substr($example, strlen($prefix)) : '';
}


/* ---------------------------------------------------------------------------
   Reading and keeping a draft
   --------------------------------------------------------------------------- */

/**
 * One template for the designer.
 *
 * @param int $id
 * @return array|null pg_whatsapp_design_from_row()
 */
function pg_whatsapp_design_load($id)
{
    if (!pg_whatsapp_templates_ready() || ((int) $id <= 0)) {
        return null;
    }

    $row = db_item(
        "SELECT id, meta_id, name, language, category, status, reason, parameter_format, components, samples
        FROM whatsapp_templates
        WHERE id = '" . (int) $id . "'");

    return is_array($row) ? pg_whatsapp_design_from_row($row) : null;
}

/**
 * The rows the designer may change, as pg_whatsapp_design_editable() says.
 *
 * @return string an SQL condition
 */
function pg_whatsapp_design_editable_sql()
{
    return "(((meta_id IS NULL) AND (status = 'DRAFT')) OR ((meta_id IS NOT NULL) AND (status = 'REJECTED')))";
}

/**
 * Keeps a design as a draft: a new row, or the draft it was opened from.
 *
 * @param array $design  checked (pg_whatsapp_design_check() empty)
 * @param int   $user_id
 * @return array ok, id, error (a message for the person), field
 */
function pg_whatsapp_design_save($design, $user_id)
{
    $id = (int) $design['id'];

    $taken = (int) db_value(
        "SELECT id FROM whatsapp_templates
        WHERE (name = '" . e($design['name']) . "') AND (language = '" . e($design['language']) . "') AND (id <> '" . $id . "')");

    if ($taken) {
        return array('ok' => false, 'id' => $id, 'field' => 'name', 'error' => lang('A template with this name and language exists already. Choose another name.'));
    }

    // Values bound to placeholders: db_q() reads every ? of its SQL as one,
    // and a message ("Is your order ready?") or a link holds them.
    $columns = "name = ?, language = ?, category = ?, parameter_format = 'POSITIONAL',
        components = ?, samples = ?, updated_user_id = ?, updated_at = UNIX_TIMESTAMP()";
    $values = array(
        $design['name'],
        $design['language'],
        $design['category'],
        json_encode(pg_whatsapp_design_components($design), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        pg_whatsapp_design_samples_json($design),
        (int) $user_id,
    );

    if ($id) {
        // Only while it can still be changed: a refresh or "Send for
        // approval" that moved it on since the screen read it wins.
        $saved = db_q("UPDATE whatsapp_templates SET " . $columns . " WHERE (id = '" . $id . "') AND " . pg_whatsapp_design_editable_sql(), $values);

        if ($saved === false) {
            return array('ok' => false, 'id' => $id, 'field' => '', 'error' => lang('The draft could not be saved.'));
        }

        if ((mysqli_affected_rows(db::$con) < 1) && !db_value("SELECT id FROM whatsapp_templates WHERE (id = '" . $id . "') AND " . pg_whatsapp_design_editable_sql())) {
            return array('ok' => false, 'id' => $id, 'field' => '', 'error' => lang('This template is no longer a draft, so it was not changed.'));
        }

        return array('ok' => true, 'id' => $id, 'field' => '', 'error' => '');
    }

    $saved = db_q("INSERT INTO whatsapp_templates SET " . $columns . ",
        meta_id = NULL,
        status = 'DRAFT',
        offer = '',
        created_user_id = '" . (int) $user_id . "',
        created_at = UNIX_TIMESTAMP()", $values);

    if ($saved === false) {
        return array('ok' => false, 'id' => 0, 'field' => '', 'error' => lang('The draft could not be saved.'));
    }

    return array('ok' => true, 'id' => (int) mysqli_insert_id(db::$con), 'field' => '', 'error' => '');
}

/**
 * The files of Files a header of a format can take, newest first.
 *
 * @param string $format IMAGE, VIDEO or DOCUMENT
 * @return array rows: id, name, size
 */
function pg_whatsapp_design_header_files($format)
{
    $formats = pg_whatsapp_design_header_formats();

    if (!isset($formats[$format])) {
        return array();
    }

    // files.type is cut at four characters (data/files-type-column-width.md):
    // the name's ending decides.
    $endings = array();

    foreach (array_keys($formats[$format]['types']) as $extension) {
        $endings[] = "(name LIKE '%." . e($extension) . "')";
    }

    $rows = array();

    foreach ((array) db_items(
        "SELECT id, name, size FROM files
        WHERE (design = '0') AND (" . implode(' OR ', $endings) . ") AND (size <= '" . (int) $formats[$format]['max'] . "')
        ORDER BY timestamp DESC
        LIMIT 200") as $row) {
        if (!pg_file_withheld($row['name'])) {
            $rows[] = array('id' => (int) $row['id'], 'name' => (string) $row['name'], 'size' => (int) $row['size']);
        }
    }

    return $rows;
}

/**
 * Fills a design's header file from Files: its name and size, and the
 * public address a test send gives WhatsApp when the file is open to
 * everyone. A file that is gone leaves the name empty, which the check
 * refuses.
 *
 * @param array $design
 * @return array the design
 */
function pg_whatsapp_design_resolve_file($design)
{
    $design['header_link'] = '';

    if (empty($design['header_file']['id'])) {
        return $design;
    }

    $row = db_item("SELECT id, name, size FROM files WHERE (id = '" . (int) $design['header_file']['id'] . "') AND (design = '0')");

    if (!is_array($row) || pg_file_withheld($row['name'])) {
        $design['header_file'] = array('id' => (int) $design['header_file']['id'], 'name' => '', 'size' => 0);
        return $design;
    }

    $design['header_file'] = array('id' => (int) $row['id'], 'name' => (string) $row['name'], 'size' => (int) $row['size']);

    if (function_exists('pg_file_open_to_everyone') && pg_file_open_to_everyone($row['id'])) {
        $design['header_link'] = URL_SCHEME . HOSTNAME_SETTING . PATH . rawurlencode((string) $row['name']);
    }

    return $design;
}

/**
 * Sends a saved draft or rejected template to Meta for review: a media
 * header's sample is uploaded first, then the template is created (or the
 * rejected one edited), and the row takes Meta's id and state.
 *
 * @param array $design the saved design, checked and resolved
 * @param int   $user_id
 * @return array ok, message
 */
function pg_whatsapp_design_submit($design, $user_id)
{
    $refused = function ($why) {
        return array('ok' => false, 'message' => lang(array('string' => 'The template was not sent for approval: {var:1}', 'vars' => array($why))));
    };

    if (!pg_whatsapp_design_editable($design)) {
        return $refused(lang('it is with WhatsApp already.'));
    }

    $refusal = pg_whatsapp_settings_refusal();

    if ($refusal !== '') {
        return $refused($refusal);
    }

    $app = pg_whatsapp_app();

    if (!$app['enabled'] || !pg_whatsapp_app_complete($app)) {
        return $refused(lang('WhatsApp is not set up.'));
    }

    pg_whatsapp_driver();
    require_once(PG_FUNCTIONS_DIR . '/includes/whatsapp/cloud_templates.php');

    $handle = '';

    if ($design['header'] && ($design['header']['format'] !== 'TEXT')) {
        require_once(PG_FUNCTIONS_DIR . '/includes/fn/file_storage.php');

        $formats = pg_whatsapp_design_header_formats();
        $name = (string) $design['header_file']['name'];
        $path = pg_file_reader_path($name);
        $bytes = ($path !== '') ? @file_get_contents($path) : false;

        if (($bytes === false) || (strlen($bytes) > $formats[$design['header']['format']]['max'])) {
            return $refused(lang('the header\'s file could not be read.'));
        }

        $extension = strtolower((string) pathinfo($name, PATHINFO_EXTENSION));
        $upload = pg_whatsapp_cloud_upload($app, $name, $formats[$design['header']['format']]['types'][$extension], $bytes);

        if (!$upload['ok']) {
            return $refused($upload['error']);
        }

        $handle = $upload['handle'];
    }

    $template = pg_whatsapp_design_submission($design, $handle);
    $sent = pg_whatsapp_cloud_template_submit($app, $template, $design['meta_id']);

    if (!$sent['ok']) {
        return $refused($sent['error']);
    }

    db_q("UPDATE whatsapp_templates
        SET meta_id = ?, status = ?, category = ?, reason = '', components = ?, updated_user_id = ?, updated_at = UNIX_TIMESTAMP()
        WHERE id = ?", array(
        $sent['id'],
        $sent['status'],
        ($sent['category'] !== '') ? $sent['category'] : $design['category'],
        json_encode($template['components'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        (int) $user_id,
        (int) $design['id'],
    ));

    return array('ok' => true, 'message' => ($sent['status'] === 'APPROVED')
        ? lang('WhatsApp approved the template at once. It can be used now.')
        : lang('The template was sent to WhatsApp for approval. The review takes up to 24 hours; its state is read again every six hours.'));
}

/**
 * Sends an approved template to the signed-in person's own WhatsApp, filled
 * with its sample values. At once, not through the queue, and within the
 * settings' test limit (every test is a paid message).
 *
 * @param array  $design pg_whatsapp_design_load()
 * @param string $number E.164 digits, '' when the person has no mobile number
 * @return array ok, message
 */
function pg_whatsapp_design_test($design, $number)
{
    $refused = function ($why) {
        return array('ok' => false, 'message' => lang(array('string' => 'The test message was not sent: {var:1}', 'vars' => array($why))));
    };

    if ($design['status'] !== 'APPROVED') {
        return $refused(lang('only a template WhatsApp approved can be sent.'));
    }

    $refusal = pg_whatsapp_settings_refusal();

    if ($refusal !== '') {
        return $refused($refusal);
    }

    $app = pg_whatsapp_app();

    if (!$app['enabled'] || !pg_whatsapp_app_complete($app)) {
        return $refused(lang('WhatsApp is not set up.'));
    }

    if ($number === '') {
        return $refused(lang('your user has no mobile number. Add it to your contact details in My Account.'));
    }

    $send = pg_whatsapp_design_send_components($design);

    if (!$send['ok']) {
        return $refused($send['error']);
    }

    if (function_exists('waf_rate_exceeded') && isset(db::$con) && db::$con
        && waf_rate_exceeded('site', 'whatsapp-test', PG_WHATSAPP_TESTS_PER_HOUR, 3600)) {
        return $refused(lang('the limit of ten tests an hour was reached. Try again in the next hour.'));
    }

    pg_whatsapp_driver();

    $result = pg_whatsapp_cloud_send($app, $number, $design['name'], $design['language'], $send['components']);

    if ($result['ok']) {
        return array('ok' => true, 'message' => lang(array(
            'string' => 'Meta accepted the test message to {var:1}. It should arrive in that number\'s WhatsApp within a minute.',
            'vars'   => array('+' . $number))));
    }

    if ($result['uncertain']) {
        return array('ok' => false, 'message' => lang(array(
            'string' => 'Meta may have the test message to {var:1}, but its answer did not say so: {var:2}',
            'vars'   => array('+' . $number, $result['error']))));
    }

    return $refused($result['error']);
}

/**
 * The signed-in person's mobile number, as a test goes to it.
 *
 * @return string E.164 digits, '' for none
 */
function pg_whatsapp_design_own_number()
{
    if (!defined('USER_CONTACT_ID') || !USER_CONTACT_ID) {
        return '';
    }

    return pg_sms_number((string) db_value("SELECT mobile_phone FROM contacts WHERE id = '" . (int) USER_CONTACT_ID . "'"));
}


/* ---------------------------------------------------------------------------
   The screen's parts
   --------------------------------------------------------------------------- */

/**
 * What each variable of a design is called on the screen, by number: its
 * label, "Value 2" for one without, and a label two variables share made
 * apart, since the chips' editor takes one label for one variable.
 *
 * @param array $design
 * @return array number => label
 */
function pg_whatsapp_design_chip_labels($design)
{
    $names = array();

    foreach (pg_whatsapp_design_variables($design['body']) as $key) {
        $label = (isset($design['labels'][$key]) && (trim((string) $design['labels'][$key]) !== '')) ? trim((string) $design['labels'][$key]) : lang(array('string' => 'Value {var:1}', 'vars' => array($key)));

        if (in_array($label, $names, true)) {
            $label .= ' (' . $key . ')';
        }

        $names[$key] = $label;
    }

    return $names;
}

/**
 * One row of the sample values: the variable's chip and its sample.
 * whatsapp_template.js builds the same row as chips come and go, and names
 * its fields by the number the variable has in the message then.
 *
 * @param string $key      the variable's number
 * @param string $label    pg_whatsapp_design_chip_labels()
 * @param string $sample
 * @param bool   $editable
 * @param array  $errors   pg_whatsapp_design_check()
 * @return string HTML
 */
function pg_whatsapp_design_sample_row($key, $label, $sample, $editable, $errors)
{
    $key = preg_replace('/[^a-z0-9_]/', '', (string) $key);
    $disabled = $editable ? '' : ' disabled';
    $error = '';
    $invalid = '';

    foreach (array('sample_', 'label_') as $prefix) {
        if (isset($errors[$prefix . $key])) {
            $error .= '<div class="pg-field-help pg-field-error" id="' . $prefix . h($key) . '_error">' . h($errors[$prefix . $key]) . '</div>';
        }
    }

    if (isset($errors['sample_' . $key])) {
        $invalid = ' aria-invalid="true" aria-describedby="sample_' . h($key) . '_error"';
    }

    return '<div class="pg-wa-sample" data-pg-wa-label="' . h($label) . '" data-pg-wa-number="' . h($key) . '">'
        . pg_var_chip_html($label)
        . '<input type="hidden" data-pg-wa-label-field name="label_' . h($key) . '" value="' . h($label) . '"' . $disabled . '>'
        . '<input type="text" class="pg-input pg-input-sm" data-pg-wa-sample name="sample_' . h($key) . '" value="' . h($sample) . '" maxlength="' . PG_WHATSAPP_DESIGN_SAMPLE_MAX . '" placeholder="' . h(lang('Sample value')) . '" aria-label="' . h(lang(array('string' => 'Sample value of {var:1}', 'vars' => array($label)))) . '"' . $invalid . $disabled . '>'
        . $error
        . '</div>';
}
