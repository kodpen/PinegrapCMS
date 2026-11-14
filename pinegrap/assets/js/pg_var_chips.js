/**
 * Pinegrap - Enterprise Website Platform
 *
 * Variable chips: a message's variables drawn as chips that name what they
 * stand for, instead of the {{1}} a message service reads. Shared by the
 * screens that write such messages (the WhatsApp template designer, the
 * rows that say what fills a template's variables).
 *
 *   pgVarChips.chip(label, options)    one chip element; options.removable
 *                                      adds its remove button, options.drag
 *                                      makes it draggable.
 *   pgVarChips.mount(textarea, options) turns a textarea holding {{n}} into
 *                                      an editor of text and chips. The
 *                                      textarea stays the form's field
 *                                      (hidden) and always holds the text
 *                                      with {{1}}, {{2}} … numbered by first
 *                                      appearance.
 *
 * mount() options:
 *   labels    {n: label} of the variables the text holds
 *   palette   an element whose [data-pg-var-label] chips are dragged or
 *             clicked into the editor
 *   max       the most different variables (default 20)
 *   readonly  no editing
 *   texts     {remove, fallback} with {var:1}: the remove button's name,
 *             the label of a variable without one ("Value {var:1}")
 *   onChange  function (text, labels) after every change; labels is the
 *             list of labels for {{1}}, {{2}} …
 *
 * A chip's identity is its label: the same label twice is one variable.
 * Loaded with a plain <script src>; no jQuery.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

(function () {
    'use strict';

    var DRAG_TYPE = 'application/x-pg-var';

    function fill(template, value) {
        return String(template || '').replace('{var:1}', value);
    }

    function chip(label, options) {
        options = options || {};

        var element = document.createElement('span');
        var text = document.createElement('span');

        element.className = 'pg-var-chip';
        element.setAttribute('data-pg-var-label', label);
        element.setAttribute('contenteditable', 'false');
        text.className = 'pg-var-chip-text';
        text.textContent = label;
        element.appendChild(text);

        if (options.drag) {
            element.setAttribute('draggable', 'true');
        }

        if (options.removable) {
            var remove = document.createElement('button');

            remove.type = 'button';
            remove.className = 'pg-var-chip-remove';
            remove.setAttribute('aria-label', fill(options.removeText, label));
            remove.setAttribute('tabindex', '-1');
            remove.innerHTML = '<i class="bi bi-x" aria-hidden="true"></i>';
            element.appendChild(remove);
        }

        return element;
    }

    // Where a drop at a point lands, as a collapsed range; null outside.
    function rangeAt(x, y) {
        if (document.caretRangeFromPoint) {
            return document.caretRangeFromPoint(x, y);
        }

        if (document.caretPositionFromPoint) {
            var position = document.caretPositionFromPoint(x, y);

            if (position) {
                var range = document.createRange();
                range.setStart(position.offsetNode, position.offset);
                range.collapse(true);

                return range;
            }
        }

        return null;
    }

    function mount(textarea, options) {
        options = options || {};

        var texts = options.texts || {};
        var max = options.max || 20;
        var editor = document.createElement('div');
        var saved = null;
        var dragged = null;

        editor.className = 'pg-var-editor';
        editor.setAttribute('role', 'textbox');
        editor.setAttribute('aria-multiline', 'true');
        editor.setAttribute('contenteditable', options.readonly ? 'false' : 'true');

        ['aria-label', 'aria-labelledby', 'aria-describedby', 'aria-invalid'].forEach(function (name) {
            if (textarea.hasAttribute(name)) {
                editor.setAttribute(name, textarea.getAttribute(name));
            }
        });

        if (textarea.id) {
            editor.id = textarea.id + '_editor';
            // The field's <label for> leads to the editor.
            var label = document.querySelector('label[for="' + textarea.id + '"]');

            if (label) {
                label.setAttribute('for', editor.id);
            }
        }

        function makeChip(name) {
            return chip(name, {removable: !options.readonly, drag: !options.readonly, removeText: texts.remove});
        }

        // The text with {{n}} drawn as nodes.
        function load(value, labels) {
            editor.textContent = '';

            String(value).split(/(\{\{\s*[a-z0-9_]{1,64}\s*\}\})/).forEach(function (part) {
                var match = /^\{\{\s*([a-z0-9_]{1,64})\s*\}\}$/.exec(part);

                if (match) {
                    var name = (labels && labels[match[1]]) ? labels[match[1]] : fill(texts.fallback, match[1]);
                    editor.appendChild(makeChip(name));
                    return;
                }

                part.split('\n').forEach(function (line, index) {
                    if (index > 0) {
                        editor.appendChild(document.createElement('br'));
                    }

                    if (line !== '') {
                        editor.appendChild(document.createTextNode(line));
                    }
                });
            });
        }

        // The nodes as text with {{n}}, numbered by first appearance.
        function serialize() {
            var order = [];
            var out = '';

            function walk(node) {
                Array.prototype.forEach.call(node.childNodes, function (child, index) {
                    if (child.nodeType === 3) {
                        out += child.nodeValue.replace(/ /g, ' ');
                    } else if (child.nodeType === 1 && child.hasAttribute('data-pg-var-label')) {
                        var name = child.getAttribute('data-pg-var-label');
                        var at = order.indexOf(name);

                        if (at === -1) {
                            order.push(name);
                            at = order.length - 1;
                        }

                        out += '{{' + (at + 1) + '}}';
                    } else if (child.nodeName === 'BR') {
                        out += '\n';
                    } else if (child.nodeType === 1) {
                        // A block the browser made for a new line.
                        if ((child.nodeName === 'DIV' || child.nodeName === 'P') && (index > 0 || out !== '')) {
                            out += '\n';
                        }

                        walk(child);
                    }
                });
            }

            walk(editor);

            return {text: out.replace(/\n$/, ''), labels: order};
        }

        function changed() {
            var result = serialize();

            textarea.value = result.text;

            if (options.onChange) {
                options.onChange(result.text, result.labels);
            }
        }

        function distinct() {
            return serialize().labels;
        }

        function remember() {
            var selection = window.getSelection();

            if (selection.rangeCount && editor.contains(selection.getRangeAt(0).commonAncestorContainer)) {
                saved = selection.getRangeAt(0).cloneRange();
            }
        }

        function place(range) {
            var selection = window.getSelection();

            selection.removeAllRanges();
            selection.addRange(range);
            saved = range.cloneRange();
        }

        // Puts a node where the range is (the end of the text without one)
        // and the caret after it.
        function insertNode(node, range) {
            if (!range || !editor.contains(range.commonAncestorContainer)) {
                range = document.createRange();
                range.selectNodeContents(editor);
                range.collapse(false);
            }

            range.deleteContents();
            range.insertNode(node);

            var after = document.createRange();
            after.setStartAfter(node);
            after.collapse(true);
            place(after);
        }

        // Adds a variable; false when the message holds the most already.
        function insert(name, range) {
            if (options.readonly || !name) {
                return false;
            }

            if (distinct().indexOf(name) === -1 && distinct().length >= max) {
                return false;
            }

            // A space on each side keeps the chip off the words around it.
            var made = makeChip(name);
            var before;

            insertNode(made, range || saved);
            before = made.previousSibling;

            if (before && before.nodeType === 3 && /\S$/.test(before.nodeValue)) {
                made.parentNode.insertBefore(document.createTextNode(' '), made);
            }

            insertNode(document.createTextNode(' '), saved);
            editor.focus();
            changed();

            return true;
        }

        // Wraps the selection in a marker, or puts a pair at the caret.
        function wrap(marker) {
            if (options.readonly) {
                return;
            }

            var range = saved;

            if (!range || !editor.contains(range.commonAncestorContainer)) {
                range = document.createRange();
                range.selectNodeContents(editor);
                range.collapse(false);
            }

            var end = range.cloneRange();
            end.collapse(false);
            end.insertNode(document.createTextNode(marker));

            var start = range.cloneRange();
            start.collapse(true);
            var opening = document.createTextNode(marker);
            start.insertNode(opening);

            var caret = document.createRange();
            caret.setStartAfter(opening);
            caret.collapse(true);
            place(caret);
            editor.focus();
            changed();
        }

        load(textarea.value, options.labels);
        textarea.hidden = true;
        textarea.parentNode.insertBefore(editor, textarea);

        if (!options.readonly) {
            editor.addEventListener('input', changed);
            editor.addEventListener('keyup', remember);
            editor.addEventListener('mouseup', remember);
            editor.addEventListener('blur', remember);

            editor.addEventListener('keydown', function (event) {
                if (event.key === 'Enter') {
                    event.preventDefault();
                    document.execCommand('insertLineBreak');
                    changed();
                }
            });

            // Plain text only: a pasted page brings no markup in.
            editor.addEventListener('paste', function (event) {
                event.preventDefault();
                document.execCommand('insertText', false, (event.clipboardData || window.clipboardData).getData('text/plain'));
                changed();
            });

            editor.addEventListener('click', function (event) {
                var button = event.target.closest('.pg-var-chip-remove');

                if (button && editor.contains(button)) {
                    button.closest('.pg-var-chip').remove();
                    changed();
                }
            });

            editor.addEventListener('dragstart', function (event) {
                var target = event.target.closest ? event.target.closest('.pg-var-chip') : null;

                if (target && editor.contains(target)) {
                    dragged = target;
                    event.dataTransfer.effectAllowed = 'move';
                    event.dataTransfer.setData(DRAG_TYPE, target.getAttribute('data-pg-var-label'));
                    event.dataTransfer.setData('text/plain', '');
                }
            });

            editor.addEventListener('dragend', function () {
                dragged = null;
            });

            editor.addEventListener('dragover', function (event) {
                if (Array.prototype.indexOf.call(event.dataTransfer.types, DRAG_TYPE) !== -1) {
                    event.preventDefault();
                    event.dataTransfer.dropEffect = dragged ? 'move' : 'copy';
                }
            });

            editor.addEventListener('drop', function (event) {
                var name = event.dataTransfer.getData(DRAG_TYPE);

                if (!name) {
                    return;
                }

                event.preventDefault();

                var range = rangeAt(event.clientX, event.clientY);

                if (dragged) {
                    // Moved within the message: the same chip, taken from
                    // where it was.
                    if (range && dragged.contains(range.startContainer)) {
                        return;
                    }

                    var moving = dragged;
                    dragged = null;
                    moving.remove();
                    insertNode(moving, range);
                    editor.focus();
                    changed();
                    return;
                }

                insert(name, range);
            });
        }

        if (options.palette && !options.readonly) {
            options.palette.addEventListener('click', function (event) {
                var source = event.target.closest('[data-pg-var-label]');

                if (source && options.palette.contains(source)) {
                    insert(source.getAttribute('data-pg-var-label'));
                }
            });

            options.palette.addEventListener('dragstart', function (event) {
                var source = event.target.closest ? event.target.closest('[data-pg-var-label]') : null;

                if (source) {
                    dragged = null;
                    event.dataTransfer.effectAllowed = 'copy';
                    event.dataTransfer.setData(DRAG_TYPE, source.getAttribute('data-pg-var-label'));
                    event.dataTransfer.setData('text/plain', '');
                }
            });
        }

        return {
            element: editor,
            insert: insert,
            wrap: wrap,
            serialize: serialize,
            focus: function () {
                editor.focus();
            },
        };
    }

    window.pgVarChips = {chip: chip, mount: mount};
})();
