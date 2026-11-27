/**
 * Pinegrap - Enterprise Website Platform
 *
 * The rows' order in the grid of one Data table
 * (workspace_wtables.php?id=N), for a person who may write the table, while
 * the rows stand in the table's own order (no filter, no sort): a row is
 * dragged by its handle (.ws-wt-handle in the "#" cell) onto another row
 * and takes that row's place, the rows between moving one place. The move
 * posts the screen's row_move action through its one action form, as the
 * row menu's Move up and Move down do; the keyboard has those.
 *
 * dropPlace() is pure (tests/workspace_wtable_order_js_test.php runs it in
 * Node). Part of the workspace_wtable_grid.min.js bundle (pg_js_bundles(),
 * includes/fn/output.php).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

(function () {
    'use strict';

    /**
     * A row at place `from` dropped on the row at place `over`: the place
     * it takes, and on which side of that row the line shows (above when it
     * moves up, under when it moves down); null when it stays.
     */
    function dropPlace(from, over) {
        from = parseInt(from, 10);
        over = parseInt(over, 10);

        if (!(from > 0) || !(over > 0) || (from === over)) {
            return null;
        }

        return { position: over, side: (over < from) ? 'before' : 'after' };
    }

    if (typeof module !== 'undefined' && module.exports) {
        module.exports = { dropPlace: dropPlace };
        return;
    }

    var grid = document.querySelector('.ws-wt-grid');
    var form = document.getElementById('ws_wt_action_form');

    if (!grid || !form || !grid.querySelector('.ws-wt-handle')) {
        return;
    }

    var dragged = null;
    var marked = null;

    function unmark() {
        if (marked) {
            marked.classList.remove('ws-wt-drop-before', 'ws-wt-drop-after');
            marked = null;
        }
    }

    grid.addEventListener('dragstart', function (e) {
        var handle = e.target.closest ? e.target.closest('.ws-wt-handle') : null;
        var row = handle ? handle.closest('tr[data-ws-wt-position]') : null;

        if (!row) {
            return;
        }

        dragged = row;
        row.classList.add('ws-wt-dragging');
        e.dataTransfer.effectAllowed = 'move';
        e.dataTransfer.setData('text/plain', row.dataset.wsWtRow);

        if (e.dataTransfer.setDragImage) {
            e.dataTransfer.setDragImage(row, 16, 16);
        }
    });

    grid.addEventListener('dragover', function (e) {
        var row = dragged ? e.target.closest('tbody tr[data-ws-wt-position]') : null;
        var place = row ? dropPlace(dragged.dataset.wsWtPosition, row.dataset.wsWtPosition) : null;

        if (!place) {
            unmark();
            return;
        }

        e.preventDefault();
        e.dataTransfer.dropEffect = 'move';

        if (marked !== row) {
            unmark();
            marked = row;
        }

        row.classList.add('ws-wt-drop-' + place.side);
    });

    grid.addEventListener('drop', function (e) {
        var row = dragged ? e.target.closest('tbody tr[data-ws-wt-position]') : null;
        var place = row ? dropPlace(dragged.dataset.wsWtPosition, row.dataset.wsWtPosition) : null;

        if (!place) {
            return;
        }

        e.preventDefault();
        form.elements.wt_action.value = 'row_move';
        form.elements.wt_row.value = dragged.dataset.wsWtRow;
        form.elements.position.value = String(place.position);
        form.submit();
    });

    grid.addEventListener('dragend', function () {
        unmark();

        if (dragged) {
            dragged.classList.remove('ws-wt-dragging');
            dragged = null;
        }
    });
}());
