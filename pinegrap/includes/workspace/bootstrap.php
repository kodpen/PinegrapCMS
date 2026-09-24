<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - the module's single entry point.
 *
 * Channels where the team talks, tasks with owners and dates, and a planning
 * board that knows how much of each person's day is already spoken for. The
 * screens (workspace*.php), the panel's AJAX door (api.php, ws_* actions) and
 * the external API (includes/workspace/api.php) all load this file and nothing
 * else; it pulls in the rest of the module.
 *
 * Every file here holds functions only, so loading the module costs a parse
 * and nothing more. The panel menu loads it on every page while the module is
 * switched on, to draw the unread badge.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/access.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/people.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/channels.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/refs.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/render.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/calc.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/messages.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/interact.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/tasks.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/recurrence.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/task_work.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/schedule.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/workdays.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/commands.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/notify.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/files.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/calendar.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/briefing.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/claude.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/changes.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/timeline.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/home.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/notes.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/file_edits.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/nav.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/screen.php');
