/*
 * Поле поиска ФИО с подсказками (без внешних библиотек).
 *
 *   FioCombobox.init({
 *     input:  <input type="text">,     // поле ввода
 *     list:   <ul>,                    // контейнер подсказок
 *     hidden: <input type="hidden">,   // сюда пишется id выбранной записи
 *     items:  [{id, fio, position}],   // весь список (передаётся со страницы)
 *     onSelect: function (item) {},    // вызывается при выборе (item = null при сбросе)
 *     limit: 50                        // сколько подсказок показывать
 *   });
 *
 * Поиск: слова запроса ищутся в любом месте ФИО в любом порядке; регистр и «ё/е» не важны.
 * Работает с мышью, пальцем и клавиатурой (стрелки, Enter, Esc).
 */
(function (global) {
    'use strict';

    function norm(s) {
        return String(s).toLowerCase().replace(/ё/g, 'е').replace(/\s+/g, ' ').trim();
    }

    function init(opts) {
        var input = opts.input, list = opts.list, hidden = opts.hidden;
        var items = (opts.items || []).map(function (it) {
            return { id: it.id, fio: it.fio, position: it.position, key: norm(it.fio) };
        });
        var limit = opts.limit || 50;
        var shown = [];      // что сейчас показано в списке
        var active = -1;     // индекс подсвеченной подсказки
        var selected = null; // выбранная запись

        list.setAttribute('role', 'listbox');
        input.setAttribute('role', 'combobox');
        input.setAttribute('aria-autocomplete', 'list');
        input.setAttribute('aria-expanded', 'false');
        input.setAttribute('autocomplete', 'off');

        function search(query) {
            var tokens = norm(query).split(' ').filter(Boolean);
            if (!tokens.length) { return items.slice(); }

            var res = [];
            items.forEach(function (it) {
                if (tokens.every(function (t) { return it.key.indexOf(t) !== -1; })) {
                    // ранг: 0 - ФИО начинается с запроса, 1 - какое-то слово начинается, 2 - просто содержит
                    var rank = 2;
                    if (it.key.indexOf(tokens[0]) === 0) { rank = 0; }
                    else if (it.key.indexOf(' ' + tokens[0]) !== -1) { rank = 1; }
                    res.push({ it: it, rank: rank });
                }
            });
            res.sort(function (a, b) { return a.rank - b.rank; }); // стабильно: порядок списка сохраняется
            return res.map(function (r) { return r.it; });
        }

        // Текст с подсветкой найденных слов (через DOM-узлы, без innerHTML)
        function highlight(container, text, tokens) {
            var k = norm(text);
            if (k.length !== text.length || !tokens.length) {
                container.appendChild(document.createTextNode(text));
                return;
            }
            var marks = new Array(text.length + 1).fill(false);
            tokens.forEach(function (t) {
                var from = 0, idx;
                while ((idx = k.indexOf(t, from)) !== -1) {
                    for (var i = idx; i < idx + t.length; i++) { marks[i] = true; }
                    from = idx + t.length;
                }
            });
            var buf = '', on = false;
            function flush() {
                if (!buf) { return; }
                if (on) { var m = document.createElement('mark'); m.textContent = buf; container.appendChild(m); }
                else { container.appendChild(document.createTextNode(buf)); }
                buf = '';
            }
            for (var i = 0; i < text.length; i++) {
                if (marks[i] !== on) { flush(); on = marks[i]; }
                buf += text.charAt(i);
            }
            flush();
        }

        function setActive(i) {
            var nodes = list.querySelectorAll('.combo-item');
            if (active >= 0 && nodes[active]) { nodes[active].classList.remove('active'); nodes[active].setAttribute('aria-selected', 'false'); }
            active = i;
            if (active >= 0 && nodes[active]) {
                nodes[active].classList.add('active');
                nodes[active].setAttribute('aria-selected', 'true');
                input.setAttribute('aria-activedescendant', nodes[active].id);
                nodes[active].scrollIntoView({ block: 'nearest' });
            } else {
                input.removeAttribute('aria-activedescendant');
            }
        }

        function close() {
            list.hidden = true;
            input.setAttribute('aria-expanded', 'false');
            active = -1;
        }

        function render() {
            var query = input.value;
            var tokens = norm(query).split(' ').filter(Boolean);
            var found = search(query);
            shown = found.slice(0, limit);
            list.textContent = '';

            if (!shown.length) {
                var empty = document.createElement('li');
                empty.className = 'combo-empty';
                empty.textContent = 'Никого не найдено. Проверьте написание.';
                list.appendChild(empty);
            }
            shown.forEach(function (it, i) {
                var li = document.createElement('li');
                li.className = 'combo-item';
                li.id = list.id + '-opt-' + i;
                li.setAttribute('role', 'option');
                var name = document.createElement('span');
                name.className = 'combo-name';
                highlight(name, it.fio, tokens);
                var pos = document.createElement('span');
                pos.className = 'combo-pos';
                pos.textContent = it.position;
                li.appendChild(name);
                li.appendChild(pos);
                // pointerdown + preventDefault: выбор срабатывает до потери фокуса полем (важно для телефонов)
                li.addEventListener('pointerdown', function (e) { e.preventDefault(); choose(it); });
                list.appendChild(li);
            });
            if (found.length > shown.length) {
                var more = document.createElement('li');
                more.className = 'combo-more';
                more.textContent = 'Показаны первые ' + shown.length + ' из ' + found.length + '. Продолжайте вводить, чтобы сузить поиск.';
                list.appendChild(more);
            }
            list.hidden = false;
            input.setAttribute('aria-expanded', 'true');
            setActive(shown.length === 1 ? 0 : -1);
        }

        function choose(it) {
            selected = it;
            input.value = it.fio;
            hidden.value = it.id;
            close();
            input.classList.add('is-selected');
            if (opts.onSelect) { opts.onSelect(it); }
        }

        function reset() {
            if (selected) {
                selected = null;
                hidden.value = '';
                input.classList.remove('is-selected');
                if (opts.onSelect) { opts.onSelect(null); }
            }
        }

        input.addEventListener('input', function () { reset(); render(); });
        input.addEventListener('focus', function () { if (!selected) { render(); } });
        input.addEventListener('click', function () { if (!selected && list.hidden) { render(); } });
        input.addEventListener('blur', function () { setTimeout(close, 120); });

        input.addEventListener('keydown', function (e) {
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                if (list.hidden) { render(); }
                if (shown.length) { setActive((active + 1) % shown.length); }
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                if (shown.length) { setActive(active <= 0 ? shown.length - 1 : active - 1); }
            } else if (e.key === 'Enter') {
                if (!list.hidden && shown.length) {
                    e.preventDefault();
                    choose(shown[active >= 0 ? active : 0]);
                }
            } else if (e.key === 'Escape' || e.key === 'Tab') {
                close();
            }
        });

        // Ссылка на выбранную запись для проверки перед отправкой формы
        return { getSelected: function () { return selected; }, choose: choose };
    }

    global.FioCombobox = { init: init };
})(window);
