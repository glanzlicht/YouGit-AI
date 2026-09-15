(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.yougitai-code-editor').forEach(function (editor) {
            var startInput = document.querySelector('input[name="line_start"]');
            var endInput = document.querySelector('input[name="line_end"]');
            if (!startInput || !endInput) return;

            var anchor = null;
            var lines = Array.prototype.slice.call(editor.querySelectorAll('.yougitai-code-line'));

            function renderSelection(start, end) {
                lines.forEach(function (line) {
                    var n = parseInt(line.getAttribute('data-line'), 10);
                    line.classList.toggle('is-selected', n >= start && n <= end);
                });
            }

            lines.forEach(function (line) {
                line.addEventListener('click', function (event) {
                    var current = parseInt(line.getAttribute('data-line'), 10);
                    if (!anchor || !event.shiftKey) {
                        anchor = current;
                        startInput.value = current;
                        endInput.value = current;
                        renderSelection(current, current);
                        return;
                    }
                    var start = Math.min(anchor, current);
                    var end = Math.max(anchor, current);
                    startInput.value = start;
                    endInput.value = end;
                    renderSelection(start, end);
                });
            });

            [startInput, endInput].forEach(function (input) {
                input.addEventListener('input', function () {
                    var start = parseInt(startInput.value || '0', 10);
                    var end = parseInt(endInput.value || '0', 10);
                    if (start > 0 && end >= start) renderSelection(start, end);
                });
            });
        });
    });
}());

(function () {
    'use strict';
    document.addEventListener('DOMContentLoaded', function () {
        var selector = document.getElementById('yougitai-ai-provider');
        if (!selector) return;
        function updateProviderPanels() {
            var provider = selector.value;
            document.querySelectorAll('[data-yougitai-provider-panel="direct"]').forEach(function (panel) {
                panel.hidden = provider !== 'direct';
            });
            document.querySelectorAll('[data-yougitai-provider-panel="api"]').forEach(function (panel) {
                panel.hidden = provider === 'direct';
            });
            document.querySelectorAll('[data-yougitai-api-card]').forEach(function (card) {
                card.hidden = provider === 'direct' || card.getAttribute('data-yougitai-api-card') !== provider;
            });
        }
        selector.addEventListener('change', updateProviderPanels);
        updateProviderPanels();
    });
}());

(function () {
    'use strict';
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-yougitai-repo-select]').forEach(function (button) {
            button.addEventListener('click', function () {
                var mode = button.getAttribute('data-yougitai-repo-select');
                document.querySelectorAll('.yougitai-repository-choice input[type="checkbox"]:not(:disabled)').forEach(function (checkbox) {
                    var row = checkbox.closest('.yougitai-repository-choice');
                    if (mode === 'all') checkbox.checked = true;
                    if (mode === 'none') checkbox.checked = false;
                    if (mode === 'private') checkbox.checked = row && row.getAttribute('data-yougitai-visibility') === 'private';
                });
            });
        });
    });
}());

(function () {
    'use strict';
    document.addEventListener('DOMContentLoaded', function () {
        var form = document.querySelector('.yougitai-display-mode-form');
        if (!form) return;
        var radios = form.querySelectorAll('input[name="display_mode"]');
        var confirm = form.querySelector('.yougitai-original-confirm');
        function updateOriginalWarning() {
            var selected = form.querySelector('input[name="display_mode"]:checked');
            if (!selected || !confirm) return;
            confirm.hidden = selected.value !== 'original';
        }
        radios.forEach(function (radio) { radio.addEventListener('change', updateOriginalWarning); });
        updateOriginalWarning();
    });
}());

(function () {
    'use strict';
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-yougitai-select-all]').forEach(function (master) {
            var group = master.getAttribute('data-yougitai-select-all');
            var items = Array.prototype.slice.call(document.querySelectorAll('[data-yougitai-select-item="' + group + '"]'));
            master.addEventListener('change', function () {
                items.forEach(function (item) { item.checked = master.checked; });
            });
            items.forEach(function (item) {
                item.addEventListener('change', function () {
                    var checked = items.filter(function (candidate) { return candidate.checked; }).length;
                    master.checked = items.length > 0 && checked === items.length;
                    master.indeterminate = checked > 0 && checked < items.length;
                });
            });
        });
    });
}());

(function () {
    'use strict';
    document.addEventListener('DOMContentLoaded', function () {
        function flash(button, input) {
            var original = button.textContent;
            input.classList.add('yougitai-copy-success');
            button.textContent = button.getAttribute('data-copied-label') || original;
            window.setTimeout(function () {
                input.classList.remove('yougitai-copy-success');
                button.textContent = original;
            }, 1500);
        }
        document.querySelectorAll('[data-yougitai-copy]').forEach(function (button) {
            button.addEventListener('click', function () {
                var input = document.querySelector(button.getAttribute('data-yougitai-copy'));
                if (!input) return;
                var value = input.value || input.textContent || '';
                if (navigator.clipboard && window.isSecureContext) {
                    navigator.clipboard.writeText(value).then(function () { flash(button, input); });
                    return;
                }
                input.focus(); input.select();
                try { document.execCommand('copy'); flash(button, input); } catch (e) {}
            });
        });
        document.querySelectorAll('[data-yougitai-reveal]').forEach(function (button) {
            button.addEventListener('click', function () {
                var input = document.querySelector(button.getAttribute('data-yougitai-reveal'));
                if (!input) return;
                var hidden = input.type === 'password';
                input.type = hidden ? 'text' : 'password';
                button.textContent = hidden ? (button.getAttribute('data-hide-label') || button.textContent) : (button.getAttribute('data-show-label') || button.textContent);
            });
        });
    });
}());


(function () {
    'use strict';
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-yougitai-bulk-rule-form]').forEach(function (form) {
            var rows = Array.prototype.slice.call(form.querySelectorAll('[data-yougitai-rule-row]'));
            var master = form.querySelector('[data-yougitai-select-all="rule-proposal"]');
            var decisionFilter = form.querySelector('[data-yougitai-rule-filter="decision"]');
            var sourceFilter = form.querySelector('[data-yougitai-rule-filter="source"]');
            var resetButton = form.querySelector('[data-yougitai-rule-filter-reset]');
            var countLabel = form.querySelector('[data-yougitai-rule-filter-count]');

            function visibleItems() {
                return rows.filter(function (row) { return !row.hidden; }).map(function (row) {
                    return row.querySelector('[data-yougitai-select-item="rule-proposal"]');
                }).filter(Boolean);
            }
            function syncMaster() {
                if (!master) return;
                var items = visibleItems();
                var checked = items.filter(function (item) { return item.checked; }).length;
                master.checked = items.length > 0 && checked === items.length;
                master.indeterminate = checked > 0 && checked < items.length;
            }
            function applyFilters() {
                var decision = decisionFilter ? decisionFilter.value : '';
                var source = sourceFilter ? sourceFilter.value : '';
                var visible = 0;
                rows.forEach(function (row) {
                    var matches = (!decision || row.getAttribute('data-rule-decision') === decision) &&
                        (!source || row.getAttribute('data-rule-source') === source);
                    row.hidden = !matches;
                    if (matches) visible++;
                });
                if (countLabel) countLabel.textContent = visible + ' / ' + rows.length;
                syncMaster();
            }
            [decisionFilter, sourceFilter].forEach(function (filter) {
                if (filter) filter.addEventListener('change', applyFilters);
            });
            if (resetButton) resetButton.addEventListener('click', function () {
                if (decisionFilter) decisionFilter.value = '';
                if (sourceFilter) sourceFilter.value = '';
                applyFilters();
            });
            if (master) master.addEventListener('change', function () {
                visibleItems().forEach(function (item) { item.checked = master.checked; });
            });
            form.querySelectorAll('[data-yougitai-select-item="rule-proposal"]').forEach(function (item) {
                item.addEventListener('change', syncMaster);
            });

            var allButton = form.querySelector('[data-yougitai-approve-all-rules]');
            if (allButton) {
                allButton.addEventListener('click', function () {
                    var message = allButton.getAttribute('data-confirm') || '';
                    if (message && !window.confirm(message)) return;
                    visibleItems().forEach(function (item) { item.checked = true; });
                    form.submit();
                });
            }
            form.addEventListener('submit', function (event) {
                var submitter = event.submitter;
                if (!submitter || !submitter.hasAttribute('data-yougitai-confirm')) return;
                if (!form.querySelector('[data-yougitai-select-item="rule-proposal"]:checked')) {
                    event.preventDefault();
                    window.alert('Select at least one protection proposal.');
                    return;
                }
                if (!window.confirm(submitter.getAttribute('data-yougitai-confirm'))) event.preventDefault();
            });
            applyFilters();
        });
    });
}());
