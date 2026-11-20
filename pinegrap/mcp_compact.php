<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The MCP server with its compact tool list. tools/list names guide_read,
 * tools_find, tools_describe, tools_read and tools_write, and a tool that
 * moves money under its own name; every other tool is found with tools_find
 * and run through tools_read or tools_write, so an AI assistant's context is
 * not filled with hundreds of descriptions before the first question. A tool
 * called by its own name runs here as on mcp.php, with the same token
 * (includes/mcp/tools.php).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

define('PG_MCP_COMPACT', true);

require(dirname(__FILE__) . '/mcp.php');
