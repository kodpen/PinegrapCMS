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

// Meta's limits for a template's parts, in characters.
define('PG_WHATSAPP_DESIGN_BODY_MAX', 1024);
define('PG_WHATSAPP_DESIGN_FOOTER_MAX', 60);
define('PG_WHATSAPP_DESIGN_NAME_MAX', 512);

// The longest sample value and variable label the designer keeps.
define('PG_WHATSAPP_DESIGN_SAMPLE_MAX', 200);

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

    $samples = isset($row['samples']) ? json_decode((string) $row['samples'], true) : null;
    $kept = (is_array($samples) && isset($samples['body']) && is_array($samples['body'])) ? array_values($samples['body']) : array();

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
 *                      label_<n>, sample_<n>
 * @param array $design the design the screen was opened with
 * @return array
 */
function pg_whatsapp_design_posted($post, $design)
{
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
        $components[] = array('type' => 'BUTTONS', 'buttons' => array_values($design['buttons']));
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

    return json_encode(array('body' => $body), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

/**
 * Whether a template can still be changed in the designer: a draft only.
 * Editing a template Meta rejected is the designer's second half.
 *
 * @param array $design
 * @return bool
 */
function pg_whatsapp_design_editable($design)
{
    return ($design['meta_id'] === '') && ($design['status'] === 'DRAFT');
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
 * of its header text and body with its sample; no database.
 *
 * A template whose header is a picture, video or document, or with a button
 * that takes a value, cannot be sent from the designer's first half.
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

        if ($format !== 'TEXT') {
            return array('ok' => false, 'components' => array(), 'error' => lang('A template with a picture, video or document at its top cannot be sent as a test yet.'));
        }

        $text = isset($design['header']['text']) ? (string) $design['header']['text'] : '';
        $keys = pg_whatsapp_design_variables($text);

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

    foreach ($design['buttons'] as $button) {
        $type = isset($button['type']) ? strtoupper((string) $button['type']) : '';
        $url = isset($button['url']) ? (string) $button['url'] : '';

        if (($type === 'COPY_CODE') || (($type === 'URL') && (strpos($url, '{{') !== false)) || ($type === 'OTP')) {
            return array('ok' => false, 'components' => array(), 'error' => lang('A template with a button that takes a value cannot be sent as a test yet.'));
        }
    }

    return array('ok' => true, 'components' => $components, 'error' => '');
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

    $columns = "name = '" . e($design['name']) . "',
        language = '" . e($design['language']) . "',
        category = '" . e($design['category']) . "',
        parameter_format = 'POSITIONAL',
        components = '" . e(json_encode(pg_whatsapp_design_components($design), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) . "',
        samples = '" . e(pg_whatsapp_design_samples_json($design)) . "',
        updated_user_id = '" . (int) $user_id . "',
        updated_at = UNIX_TIMESTAMP()";

    if ($id) {
        // Only while it is still a draft: a refresh or "Send for approval"
        // that gave it Meta's id since the screen read it wins.
        $saved = db_q("UPDATE whatsapp_templates SET " . $columns . " WHERE (id = '" . $id . "') AND (meta_id IS NULL) AND (status = 'DRAFT')");

        if ($saved === false) {
            return array('ok' => false, 'id' => $id, 'field' => '', 'error' => lang('The draft could not be saved.'));
        }

        if ((mysqli_affected_rows(db::$con) < 1) && !db_value("SELECT id FROM whatsapp_templates WHERE (id = '" . $id . "') AND (meta_id IS NULL) AND (status = 'DRAFT')")) {
            return array('ok' => false, 'id' => $id, 'field' => '', 'error' => lang('This template is no longer a draft, so it was not changed.'));
        }

        return array('ok' => true, 'id' => $id, 'field' => '', 'error' => '');
    }

    $saved = db_q("INSERT INTO whatsapp_templates SET " . $columns . ",
        meta_id = NULL,
        status = 'DRAFT',
        offer = '',
        created_user_id = '" . (int) $user_id . "',
        created_at = UNIX_TIMESTAMP()");

    if ($saved === false) {
        return array('ok' => false, 'id' => 0, 'field' => '', 'error' => lang('The draft could not be saved.'));
    }

    return array('ok' => true, 'id' => (int) mysqli_insert_id(db::$con), 'field' => '', 'error' => '');
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
