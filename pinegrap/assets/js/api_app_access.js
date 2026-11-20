/**
 * Pinegrap - Enterprise Website Platform
 *
 * The permission picker of Application Access
 * (includes/api/app_permissions_view.php): the search, the counts and the
 * bulk buttons, in the bar and in each area's menu. bulkChoice() and
 * moneyCount() are the twins of api_app_bulk_choice() and
 * api_app_bulk_money_count() in includes/api/app_access.php.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

(function (root) {
	'use strict';

	// groups: key => {read: bool, write: bool}; which: all, none, read, write.
	// Returns key => '', 'read' or 'write'.
	function bulkChoice(groups, which) {
		var choices = {};
		Object.keys(groups).forEach(function (key) {
			var read = !!groups[key].read;
			var write = !!groups[key].write;
			if (which === 'all') {
				choices[key] = write ? 'write' : (read ? 'read' : '');
			} else if (which === 'read') {
				choices[key] = read ? 'read' : '';
			} else if (which === 'write') {
				choices[key] = write ? 'write' : '';
			} else {
				choices[key] = '';
			}
		});
		return choices;
	}

	// money: key => the level whose scope moves money ('read', 'write', '').
	function moneyCount(choices, money) {
		var count = 0;
		Object.keys(choices).forEach(function (key) {
			var level = money[key] || '';
			if ((choices[key] === 'write' && level !== '') || (choices[key] === 'read' && level === 'read')) {
				count++;
			}
		});
		return count;
	}

	root.pgAppAccess = { bulkChoice: bulkChoice, moneyCount: moneyCount };

	if (typeof document === 'undefined') {
		return;
	}

	function fill(template, values) {
		return template.replace(/\{var:(\d+)\}/g, function (all, n) {
			return String(values[Number(n) - 1]);
		});
	}

	function chosen(row) {
		var input = row.querySelector('input[type="radio"]:checked');
		return input ? input.value : '';
	}

	// The rows a bulk button acts on: the area's, or every row; with a search
	// typed, only the rows it shows.
	function targetRows(picker, area) {
		var scope = area ? picker.querySelector('.api-pick-area[data-area="' + area + '"]') : picker;
		return scope ? Array.prototype.filter.call(scope.querySelectorAll('.api-perm'), function (row) { return !row.hidden; }) : [];
	}

	function shape(rows) {
		var groups = {};
		var money = {};
		rows.forEach(function (row) {
			groups[row.dataset.group] = { read: row.dataset.read === '1', write: row.dataset.write === '1' };
			money[row.dataset.group] = row.dataset.money || '';
		});
		return { groups: groups, money: money };
	}

	function refresh(picker) {
		var rows = picker.querySelectorAll('.api-perm');
		var on = 0;
		rows.forEach(function (row) { if (chosen(row) !== '') { on++; } });
		picker.querySelector('[data-pick-count]').textContent = fill(picker.dataset.countText, [on, rows.length]);
		picker.querySelectorAll('.api-pick-area').forEach(function (area) {
			var own = area.querySelectorAll('.api-perm');
			var areaOn = 0;
			own.forEach(function (row) { if (chosen(row) !== '') { areaOn++; } });
			area.querySelector('[data-area-count]').textContent = fill(picker.dataset.areaText, [areaOn, own.length]);
		});
		// The count of money rows All and All write would tick, beside them.
		picker.querySelectorAll('[data-bulk="all"], [data-bulk="write"]').forEach(function (button) {
			var part = shape(targetRows(picker, button.dataset.area || ''));
			var count = moneyCount(bulkChoice(part.groups, button.dataset.bulk), part.money);
			var badge = button.querySelector('[data-money-count]');
			if (!badge) { return; }
			badge.hidden = (count === 0);
			badge.querySelector('span').textContent = count;
		});
	}

	function applyBulk(picker, which, area) {
		var rows = targetRows(picker, area);
		var part = shape(rows);
		var choices = bulkChoice(part.groups, which);
		rows.forEach(function (row) {
			var input = row.querySelector('input[type="radio"][value="' + choices[row.dataset.group] + '"]');
			if (input && !input.checked) {
				input.checked = true;
				input.dispatchEvent(new Event('change', { bubbles: true }));
			}
		});
		var money = moneyCount(choices, part.money);
		picker.querySelector('[data-pick-note]').textContent = money ? fill(picker.dataset.moneyText, [money]) : '';
		refresh(picker);
	}

	function search(picker, text) {
		var words = text.toLowerCase().split(/\s+/).filter(Boolean);
		var shown = 0;
		picker.querySelectorAll('.api-pick-area').forEach(function (area) {
			var areaShown = 0;
			var areaTitle = area.querySelector('.api-pick-head b').textContent.toLowerCase();
			area.querySelectorAll('.api-perm').forEach(function (row) {
				var hay = (row.querySelector('.api-perm-text').textContent + ' ' + areaTitle).toLowerCase();
				var match = words.every(function (word) { return hay.indexOf(word) !== -1; });
				row.hidden = !match;
				if (match) { areaShown++; }
			});
			area.hidden = (areaShown === 0);
			shown += areaShown;
		});
		picker.querySelector('[data-pick-empty]').hidden = (shown !== 0);
		refresh(picker);
	}

	function init(picker) {
		var box = picker.querySelector('[data-pick-search]');
		box.addEventListener('input', function () { search(picker, box.value); });
		// Return in the box searches; it never posts the form around it.
		box.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); } });
		picker.addEventListener('change', function () { refresh(picker); });
		picker.addEventListener('click', function (e) {
			var button = e.target.closest('[data-bulk]');
			if (!button || !picker.contains(button)) { return; }
			applyBulk(picker, button.dataset.bulk, button.dataset.area || '');
		});
		refresh(picker);
	}

	function start() {
		var pickers = document.querySelectorAll('[data-pg-app-access]');
		pickers.forEach(init);
		// The screen's own script sets the radios when it opens an
		// application or a starting point, without a change event; the counts
		// are read again after every click and key that may have done so.
		var later = function () { window.setTimeout(function () { pickers.forEach(refresh); }, 0); };
		document.addEventListener('click', later, true);
		document.addEventListener('keyup', later, true);
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', start);
	} else {
		start();
	}
})(typeof window !== 'undefined' ? window : globalThis);
