<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Long settings texts kept outside the config row. The config table's row has
 * almost no room left under InnoDB's 8126-byte limit on MySQL 8.0 (every TEXT
 * column costs 41 bytes there; tests/innodb_test.php keeps room for ten), so
 * a free-text setting is a row of config_text, one per name, instead of a new
 * config column. Reads go through pg_config_text(); a missing table or name
 * reads as ''.
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
 * Whether a settings text name is one config_text takes: lower case letters,
 * digits and underscores, at most 64 characters (the key column's width).
 *
 * @param string $name
 * @return bool
 */
function pg_config_text_name_valid($name)
{
    return is_string($name) && (preg_match('/^[a-z0-9_]{1,64}$/', $name) === 1);
}

/**
 * A settings text, read once per request.
 *
 * @param string $name
 * @return string  '' when it is not set or the table is not there yet
 */
function pg_config_text($name)
{
    static $cache = array();

    if (!pg_config_text_name_valid($name)) {
        return '';
    }

    if (!array_key_exists($name, $cache)) {
        $cache[$name] = pg_schema_has('config_text')
            ? (string) db_value("SELECT value FROM config_text WHERE name = '" . e($name) . "'")
            : '';
    }

    return $cache[$name];
}

/**
 * Write a settings text; '' removes it.
 *
 * @param string $name
 * @param string $value  up to 65535 bytes
 * @return bool  false for a bad name, a value too long, a missing table or a failed write
 */
function pg_config_text_save($name, $value)
{
    $value = (string) $value;

    if (!pg_config_text_name_valid($name) || (strlen($value) > 65535) || !pg_schema_has('config_text')) {
        return false;
    }

    $result = ($value === '')
        ? db_q("DELETE FROM config_text WHERE name = ?", array($name))
        : db_q("INSERT INTO config_text (name, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value = VALUES(value)", array($name, $value));

    return $result !== false;
}
