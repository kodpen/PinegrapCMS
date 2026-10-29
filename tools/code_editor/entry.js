/**
 * Pinegrap - Enterprise Website Platform
 *
 * Entry point of the code editor bundle (pinegrap/assets/lib/code_editor/
 * cm6.bundle.js). Everything exported here is reachable from the page as
 * window.PgCM.<name>; the facade in pinegrap/assets/js/pg_code_editor.js is
 * the only script that reads it. The packages and their versions are pinned
 * in package.json / package-lock.json next to this file, and the bundle is
 * written by tools/build_code_editor.php.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

export * as state from '@codemirror/state';
export * as view from '@codemirror/view';
export * as language from '@codemirror/language';
export * as commands from '@codemirror/commands';
export * as search from '@codemirror/search';
export * as autocomplete from '@codemirror/autocomplete';
export * as lint from '@codemirror/lint';
export * as langHtml from '@codemirror/lang-html';
export * as langCss from '@codemirror/lang-css';
export * as langJavascript from '@codemirror/lang-javascript';
export * as langJson from '@codemirror/lang-json';
export * as highlight from '@lezer/highlight';
export * as lezer from '@lezer/common';
export { vscodeKeymap } from '@replit/codemirror-vscode-keymap';
export * as minimap from '@replit/codemirror-minimap';

// The formatter of ed.format(): js-beautify's CSS and JavaScript parts only
// (its HTML part and command line are left out). JSON goes through the
// JavaScript part.
import formatCss from 'js-beautify/js/src/css/index.js';
import formatJs from 'js-beautify/js/src/javascript/index.js';
export const format = { css: formatCss, js: formatJs };

// Emmet's core (the `emmet` package, MIT): expand() turns an abbreviation
// into markup or CSS, extract() finds the abbreviation before the cursor on
// a line. The facade's expand-abbreviation command is built on these two;
// @emmetio/codemirror6-plugin is not used (it carries no license).
import emmetExpand, { extract as emmetExtract } from 'emmet';
export const emmet = { expand: emmetExpand, extract: emmetExtract };
