<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - starters for a new workflow table: a set of columns (with a
 * status column's choices) and a few sample rows, for work people often
 * keep in a table: a contact pipeline and a checklist. They live in code,
 * like the built-in channel templates, and are made through the store's
 * own functions, so the limits, checks and rights stay the store's.
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
 * The starters by key: name, description, columns (name, type, options)
 * and sample rows, each a list of values by column position. A sample
 * value is text as a file would hold it, array('days' => N) for a date N
 * days from today, or 'me' for the person making the table.
 *
 * @return array
 */
function ws_wtable_starters()
{
    $status = function ($labels) {
        $colors = array('#6c757d', '#0d6efd', '#fd7e14', '#198754', '#dc3545', '#6f42c1');
        $choices = array();

        foreach (array_values($labels) as $i => $label) {
            $choices[] = array('key' => 's' . ($i + 1), 'label' => $label, 'color' => $colors[$i % count($colors)]);
        }

        return array('choices' => $choices);
    };

    return array(
        'contacts' => array(
            'name'        => lang('Contact pipeline'),
            'description' => lang('People and companies on their way to becoming customers, one stage at a time.'),
            'columns'     => array(
                array('name' => lang('Name'), 'type' => 'text', 'options' => array()),
                array('name' => lang('Company'), 'type' => 'text', 'options' => array()),
                array('name' => lang('E-mail'), 'type' => 'text', 'options' => array()),
                array('name' => lang('Phone'), 'type' => 'text', 'options' => array()),
                array('name' => lang('Stage'), 'type' => 'status', 'options' => $status(array(lang('Lead'), lang('Contacted'), lang('Proposal'), lang('Won'), lang('Lost')))),
                array('name' => lang('Value'), 'type' => 'money', 'options' => array('decimals' => 2, 'currency' => '')),
                array('name' => lang('Owner'), 'type' => 'person', 'options' => array()),
                array('name' => lang('Next step'), 'type' => 'date', 'options' => array('time' => false)),
            ),
            'rows'        => array(
                array('Ada Yılmaz', 'Yılmaz Tekstil', 'ada@example.com', '', lang('Lead'), '1500', 'me', array('days' => 2)),
                array('Can Demir', 'Demir Yapı', 'can@example.com', '', lang('Proposal'), '8200', 'me', array('days' => 5)),
            ),
        ),
        'checklist' => array(
            'name'        => lang('Checklist'),
            'description' => lang('Things to do, who does them and by when.'),
            'columns'     => array(
                array('name' => lang('Item'), 'type' => 'text', 'options' => array()),
                array('name' => lang('Done'), 'type' => 'checkbox', 'options' => array()),
                array('name' => lang('Who'), 'type' => 'person', 'options' => array()),
                array('name' => lang('Due'), 'type' => 'date', 'options' => array('time' => false)),
                array('name' => lang('Notes'), 'type' => 'text', 'options' => array()),
            ),
            'rows'        => array(
                array(lang('Write down what is needed'), 'yes', 'me', array('days' => 0), ''),
                array(lang('Share the list with the team'), 'no', 'me', array('days' => 1), ''),
            ),
        ),
    );
}

/**
 * One sample value as the import's cell reader takes it.
 *
 * @param mixed $value as ws_wtable_starters() writes it
 * @param int   $me    the person making the table
 * @param int   $today unix time
 * @return string
 */
function ws_wtable_starter_value($value, $me, $today)
{
    if (is_array($value)) {
        return date('Y-m-d', (int) $today + 86400 * (int) ($value['days'] ?? 0));
    }

    return ($value === 'me') ? (string) (int) $me : (string) $value;
}

/**
 * Make a table from a starter: the table, its columns, then the sample
 * rows when asked, every row through ws_wtable_row_create(). A refused
 * column deletes the table made so far.
 *
 * @param array  $viewer
 * @param string $key     a key of ws_wtable_starters()
 * @param string $name    the table's name ('' for the starter's)
 * @param string $description ('' for the starter's)
 * @param bool   $samples add the sample rows
 * @return array ok, error, table_id
 */
function ws_wtable_starter_create($viewer, $key, $name, $description, $samples)
{
    $starter = ws_wtable_starters()[(string) $key] ?? null;

    if (!$starter) {
        return array('ok' => false, 'error' => 'starter_unknown', 'table_id' => 0);
    }

    $name = (trim((string) $name) !== '') ? (string) $name : $starter['name'];
    $description = (trim((string) $description) !== '') ? (string) $description : $starter['description'];
    $made = ws_wtable_create($viewer, array('name' => $name, 'description' => $description));

    if (!$made['ok']) {
        return array('ok' => false, 'error' => $made['error'], 'table_id' => 0);
    }

    $table = ws_wtable((int) $made['table_id']);
    $map = array();

    foreach ($starter['columns'] as $index => $column) {
        $result = ws_wtable_column_create($viewer, $table, $column);

        if (!$result['ok']) {
            ws_wtable_delete($viewer, $table);

            return array('ok' => false, 'error' => $result['error'], 'table_id' => 0);
        }

        $map[$index] = (int) $result['column_id'];
    }

    if ($samples) {
        $rows = array();

        foreach ($starter['rows'] as $row) {
            $rows[] = array_map(function ($value) use ($viewer) {
                return ws_wtable_starter_value($value, (int) $viewer['id'], time());
            }, $row);
        }

        ws_wtable_import_rows($viewer, ws_wtable((int) $made['table_id']), $rows, $map);
    }

    return array('ok' => true, 'error' => '', 'table_id' => (int) $made['table_id']);
}
