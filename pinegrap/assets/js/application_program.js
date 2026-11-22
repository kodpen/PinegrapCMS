/**
 * Pinegrap - Enterprise Website Platform
 *
 * The Set-up tab of an application programme (edit_application_program.php):
 * adds and removes eligibility rules, criteria and rounds from their
 * <template>s, keeps each round's weight sum and its pill current (amber
 * until 100), copies a kept template's criteria into a round and switches
 * the decision e-mails' tabs. Nothing is saved here: the form posts it all.
 * On the Reviewers tab it opens the Add reviewer drawer and switches it
 * between a panel user and an outside juror. On the Entries tab a row's
 * Override or Settle opens its note drawer for that entry.
 *
 * Loaded with a plain <script src> by the screen; no .min twin.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

(function () {
    'use strict';

    var form = document.getElementById('aprog_form');
    if (!form) {
        return;
    }

    var rounds = form.querySelector('[data-pg-ap-list="round"]');
    var templates = [];

    try {
        templates = JSON.parse(rounds.getAttribute('data-pg-ap-templates') || '[]');
    } catch (e) {
        templates = [];
    }

    // A round added later gets an index no posted round uses.
    var nextRound = rounds.querySelectorAll('[data-pg-ap-round]').length;

    function fromTemplate(template) {
        return template.content.firstElementChild.cloneNode(true);
    }

    // The list and template of a kind nearest to the button: a round's own
    // for criteria, the card's for rules and rounds.
    function scopeOf(button, kind) {
        var scope = button.parentElement;
        while (scope && scope !== form && !scope.querySelector('[data-pg-ap-template="' + kind + '"]')) {
            scope = scope.parentElement;
        }
        return scope;
    }

    function addCriterion(round, name, weight) {
        var list = round.querySelector('[data-pg-ap-list="criterion"]');
        var row = fromTemplate(round.querySelector('[data-pg-ap-template="criterion"]'));
        row.querySelector('input[type="text"]').value = name || '';
        row.querySelector('[data-pg-ap-weight]').value = (weight === undefined) ? '' : String(weight);
        list.appendChild(row);
        return row;
    }

    function updateSum(round) {
        var sum = 0;
        round.querySelectorAll('[data-pg-ap-list="criterion"] [data-pg-ap-weight]').forEach(function (input) {
            var value = parseInt(input.value, 10);
            sum += isNaN(value) ? 0 : value;
        });
        var pill = round.querySelector('[data-pg-ap-sum]');
        pill.querySelector('.pg-tabular').textContent = String(sum);
        pill.classList.toggle('pg-tone-teal', sum === 100);
        pill.classList.toggle('pg-tone-amber', sum !== 100);
    }

    function numberRounds() {
        rounds.querySelectorAll('[data-pg-ap-round]').forEach(function (round, i) {
            round.querySelector('[data-pg-ap-round-number]').textContent = String(i + 1);
        });
    }

    function fillTemplateChoice(round) {
        var select = round.querySelector('[data-pg-ap-template-choice]');
        var copy = round.querySelector('[data-pg-ap-copy]');
        templates.forEach(function (template, i) {
            var option = document.createElement('option');
            option.value = String(i);
            option.textContent = template.name;
            select.appendChild(option);
        });
        select.hidden = !templates.length;
        copy.hidden = !templates.length;
    }

    rounds.querySelectorAll('[data-pg-ap-round]').forEach(fillTemplateChoice);

    form.addEventListener('click', function (event) {
        var button = event.target.closest('button');
        if (!button || !form.contains(button)) {
            return;
        }

        var kind = button.getAttribute('data-pg-ap-add');
        if (kind === 'round') {
            var markup = form.querySelector('[data-pg-ap-template="round"]').innerHTML.replace(/__R__/g, String(nextRound++));
            var holder = document.createElement('div');
            holder.innerHTML = markup;
            var round = holder.firstElementChild;
            rounds.appendChild(round);
            if (window.pgFields) {
                window.pgFields.init(round);
            }
            fillTemplateChoice(round);
            addCriterion(round).querySelector('input[type="text"]').focus();
            numberRounds();
            return;
        }
        if (kind === 'criterion') {
            addCriterion(button.closest('[data-pg-ap-round]')).querySelector('input[type="text"]').focus();
            return;
        }
        if (kind) {
            var scope = scopeOf(button, kind);
            var row = fromTemplate(scope.querySelector('[data-pg-ap-template="' + kind + '"]'));
            scope.querySelector('[data-pg-ap-list="' + kind + '"]').appendChild(row);
            row.querySelector('select, input').focus();
            return;
        }

        if (button.hasAttribute('data-pg-ap-remove')) {
            var owner = button.closest('[data-pg-ap-round]');
            button.closest('[data-pg-ap-rule], [data-pg-ap-criterion]').remove();
            if (owner) {
                updateSum(owner);
            }
            return;
        }
        if (button.hasAttribute('data-pg-ap-remove-round')) {
            button.closest('[data-pg-ap-round]').remove();
            numberRounds();
            return;
        }

        if (button.hasAttribute('data-pg-ap-copy')) {
            var target = button.closest('[data-pg-ap-round]');
            var chosen = templates[parseInt(target.querySelector('[data-pg-ap-template-choice]').value, 10)];
            if (!chosen) {
                return;
            }
            target.querySelector('[data-pg-ap-list="criterion"]').innerHTML = '';
            chosen.criteria.forEach(function (criterion) {
                addCriterion(target, criterion.name, criterion.weight);
            });
            updateSum(target);
            return;
        }

        var tab = button.getAttribute('data-pg-ap-tab');
        if (tab) {
            form.querySelectorAll('[data-pg-ap-tab]').forEach(function (item) {
                item.setAttribute('aria-pressed', item === button ? 'true' : 'false');
            });
            form.querySelectorAll('[data-pg-ap-pane]').forEach(function (pane) {
                pane.hidden = pane.getAttribute('data-pg-ap-pane') !== tab;
            });
        }
    });

    form.addEventListener('input', function (event) {
        if (event.target.hasAttribute('data-pg-ap-weight')) {
            updateSum(event.target.closest('[data-pg-ap-round]'));
        }
    });
}());

(function () {
    'use strict';

    var drawer = document.getElementById('aprog_reviewer_add');
    if (!drawer) {
        return;
    }

    var phone = window.matchMedia('(max-width: 575.98px)');

    // From the right, or on a phone as a sheet from the bottom.
    function open() {
        if (!window.pgUI) {
            return;
        }
        drawer.classList.toggle('drawer-end', !phone.matches);
        drawer.classList.toggle('drawer-bottom', phone.matches);
        pgUI.drawer.show(drawer);
    }

    document.addEventListener('click', function (event) {
        if (event.target.closest('[data-pg-ap-drawer]')) {
            open();
        }
    });

    // A panel user or an outside juror: the hidden pane's fields are
    // disabled, so they are neither required nor posted.
    drawer.addEventListener('click', function (event) {
        var choice = event.target.closest('[data-pg-ap-kind-choice]');
        if (!choice) {
            return;
        }
        var kind = choice.getAttribute('data-pg-ap-kind-choice');
        drawer.querySelector('[data-pg-ap-kind]').value = kind;
        drawer.querySelectorAll('[data-pg-ap-kind-choice]').forEach(function (item) {
            item.setAttribute('aria-pressed', item === choice ? 'true' : 'false');
        });
        drawer.querySelectorAll('[data-pg-ap-kind-pane]').forEach(function (pane) {
            var shown = pane.getAttribute('data-pg-ap-kind-pane') === kind;
            pane.hidden = !shown;
            pane.querySelectorAll('input, select').forEach(function (field) {
                field.disabled = !shown;
            });
        });
    });

    // pgUI comes with the footer, after this script; a refused add opens the
    // drawer again with the screen.
    window.addEventListener('load', function () {
        if (drawer.hasAttribute('data-open')) {
            open();
        }
    });
}());

(function () {
    'use strict';

    var phone = window.matchMedia('(max-width: 575.98px)');

    // A row's Override or Settle: the drawer named by the button, filled
    // with the row's entry, from the right or on a phone from the bottom.
    document.addEventListener('click', function (event) {
        var button = event.target.closest('[data-pg-ap-note]');
        var drawer = button ? document.getElementById(button.getAttribute('data-pg-ap-note')) : null;
        if (!drawer || !window.pgUI) {
            return;
        }
        drawer.querySelector('[data-pg-ap-note-entry]').value = button.getAttribute('data-pg-ap-entry');
        drawer.querySelector('[data-pg-ap-note-name]').textContent = button.getAttribute('data-pg-ap-entry-name');
        drawer.querySelector('textarea').value = '';
        drawer.classList.toggle('drawer-end', !phone.matches);
        drawer.classList.toggle('drawer-bottom', phone.matches);
        pgUI.drawer.show(drawer);
    });
}());
