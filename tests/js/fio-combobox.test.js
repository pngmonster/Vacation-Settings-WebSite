const { JSDOM } = require('jsdom');
const fs = require('fs');
const js = fs.readFileSync(require('path').join(__dirname, '..', '..', 'assets', 'fio-combobox.js'), 'utf8');
const dom = new JSDOM(`<input id="i"><ul id="l" hidden></ul><input type="hidden" id="h">`, { runScripts: 'outside-only' });
const w = dom.window; w.eval(js);
w.Element.prototype.scrollIntoView = function () {};
const items = [
  {id: 1, fio: 'Иванов Иван Иванович', position: 'Врач СМП'},
  {id: 2, fio: 'Фёдоров Пётр Семёнович', position: 'Санитар'},
  {id: 3, fio: 'Сидоров Алексей Петрович', position: 'Фельдшер СМП'},
  {id: 4, fio: 'Сидоров Алексей Петрович', position: 'Санитар'},
  {id: 5, fio: 'Гаджиев Магомед Али оглы', position: 'Дезинфектор'},
  {id: 6, fio: 'Петров Сидор Иванович', position: 'Уборщик'},
  {id: 7, fio: 'Ким Ён', position: 'Санитар'},
];
for (let i = 0; i < 80; i++) items.push({id: 100 + i, fio: 'Тестов' + i + ' Тест Тестович', position: 'Уборщик'});
let sel = 'none';
const c = w.FioCombobox.init({ input: w.document.getElementById('i'), list: w.document.getElementById('l'), hidden: w.document.getElementById('h'), items, onSelect: it => sel = it ? it.id : null });
const inp = w.document.getElementById('i'), list = w.document.getElementById('l'), hid = w.document.getElementById('h');
let ok = 0, fail = 0;
function t(name, cond, extra) { if (cond) { ok++; console.log('  ok   ', name); } else { fail++; console.log('  FAIL ', name, extra || ''); } }
const names = () => [...list.querySelectorAll('.combo-item .combo-name')].map(n => n.textContent);
const type = v => { inp.value = v; inp.dispatchEvent(new w.Event('input')); };

type('иван');           t('поиск по части слова, регистр не важен', names().includes('Иванов Иван Иванович'), names());
t('ранжирование: «Иванов…» (начинается) выше «Петров Сидор Иванович»', names().indexOf('Иванов Иван Иванович') < names().indexOf('Петров Сидор Иванович'), names());
type('федоров');        t('ё/е не важны: «федоров» находит «Фёдоров»', names().length === 1 && names()[0].startsWith('Фёдоров'), names());
type('пётр фёдоров');   t('порядок слов не важен, ё в запросе', names().length === 1, names());
type('али оглы');       t('ФИО из 4 слов находится', names().length === 1 && names()[0].includes('Гаджиев'), names());
type('сидоров алекс');  t('тёзки: оба варианта, у каждого своя должность', names().length === 2 && [...list.querySelectorAll('.combo-pos')].map(n=>n.textContent).sort().join() === 'Санитар,Фельдшер СМП');
type('ким');            t('короткое ФИО из 2 слов', names().length === 1);
type('яяяя');           t('ничего не найдено -> сообщение', list.querySelector('.combo-empty') !== null && names().length === 0);
type('тест');           t('ограничение: показано 50', names().length === 50, names().length); t('есть подсказка «показаны первые 50 из 80»', list.querySelector('.combo-more') && list.querySelector('.combo-more').textContent.includes('из 80'), list.querySelector('.combo-more') && list.querySelector('.combo-more').textContent);
type('   иванов    иван  '); t('лишние пробелы в запросе не мешают, точное совпадение первым', names()[0] === 'Иванов Иван Иванович', names());
type('иванов');         t('подсветка: <mark> вокруг найденного', list.querySelector('mark') && list.querySelector('mark').textContent.toLowerCase() === 'иванов');

// XSS: HTML в ФИО не превращается в разметку
const dom2 = new JSDOM(`<input id="i"><ul id="l" hidden></ul><input type="hidden" id="h">`, { runScripts: 'outside-only' }); dom2.window.eval(js); dom2.window.Element.prototype.scrollIntoView = function(){};
dom2.window.FioCombobox.init({ input: dom2.window.document.getElementById('i'), list: dom2.window.document.getElementById('l'), hidden: dom2.window.document.getElementById('h'), items: [{id:1, fio:'<img src=x onerror=alert(1)> Тест', position:'<b>x</b>'}] });
const i2 = dom2.window.document.getElementById('i'); i2.value = 'тест'; i2.dispatchEvent(new dom2.window.Event('input'));
t('XSS: <img> из ФИО не создаёт элементов', dom2.window.document.querySelectorAll('#l img, #l b').length === 0);

// Выбор и сброс
type('ким'); list.querySelector('.combo-item').dispatchEvent(new w.Event('pointerdown', {cancelable: true, bubbles: true}));
t('выбор тапом/кликом: hidden = id, поле = ФИО, onSelect вызван', hid.value === '7' && inp.value === 'Ким Ён' && sel === 7, [hid.value, inp.value, sel]);
t('после выбора список закрыт', list.hidden === true);
inp.value = 'Ким Ё'; inp.dispatchEvent(new w.Event('input'));
t('правка текста после выбора СБРАСЫВАЕТ выбор (hidden пуст, onSelect(null))', hid.value === '' && sel === null, [hid.value, sel]);

// Клавиатура
type('сидоров'); const key = k => inp.dispatchEvent(new w.KeyboardEvent('keydown', {key: k, cancelable: true, bubbles: true}));
key('ArrowDown'); key('ArrowDown'); key('Enter');
t('стрелки + Enter выбирают 2-й вариант (Санитар)', hid.value === '4', hid.value);
type('петрович'); key('Escape'); t('Esc закрывает список', list.hidden === true);
type('ким'); key('Enter'); t('Enter при единственном совпадении выбирает его', hid.value === '7', hid.value);
console.log(`\n${fail ? 'ПРОВАЛЕНО ' + fail : 'ВСЕ ПРОЙДЕНЫ'}: ${ok} ok, ${fail} fail`); process.exit(fail ? 1 : 0);
