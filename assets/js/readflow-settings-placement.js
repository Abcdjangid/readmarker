/* Native disclosure keeps the shared per-post target accessible in every placement mode. */
(function () {
 'use strict';
 const position = document.getElementById('readflow-position');
 const selector = document.getElementById('readflow-target_selector');
 const details = selector && selector.closest('details');
 if (!position || !details) return;
 const update = () => { if (position.value === 'selector') details.open = true; };
 position.addEventListener('change', update);
 update();
}());
(function () {
 'use strict';
 const enabled = document.getElementById('readflow-reader_controls_enabled');
 if (!enabled || typeof enabled.addEventListener !== 'function') return;
 const rows = ['placement', 'visibility', 'panel'].map(key => document.getElementById('readflow-reader_controls_' + key)).filter(Boolean).map(field => field.closest('tr')).filter(Boolean);
 const update = () => { rows.forEach(row => { row.hidden = !enabled.checked; }); };
 enabled.addEventListener('change', update);
 update();
}());
/* Compare only the main Settings API form; values remain private to this page. */
(function () {
 'use strict';
 const form = document.querySelector('.readflow-settings form[action="options.php"]');
 if (!form || form.dataset.readflowUnsavedInitialized || typeof FormData !== 'function' || typeof FormData.prototype.entries !== 'function') return;
 form.dataset.readflowUnsavedInitialized = 'true';
 const serialize = () => JSON.stringify(Array.from(new FormData(form).entries()));
 let baseline = serialize();
 let dirty = false;
 const warn = event => {
  event.preventDefault();
  event.returnValue = '';
 };
 const setDirty = next => {
  if (dirty === next) return;
  dirty = next;
  if (dirty) window.addEventListener('beforeunload', warn);
  else window.removeEventListener('beforeunload', warn);
 };
 const compare = () => setDirty(serialize() !== baseline);
 form.addEventListener('input', compare);
 form.addEventListener('change', compare);
 form.addEventListener('submit', () => {
  baseline = serialize();
  setDirty(false);
 });
}());
/* Native WordPress picker; notify the existing form comparison with a normal input event. */
(function () {
 'use strict';
 const input = document.getElementById('readflow-progress_color');
 const $ = typeof window !== 'undefined' ? window.jQuery : null;
 if (!input || !$ || !$.fn || typeof $.fn.wpColorPicker !== 'function' || input.dataset.readflowColorPicker) return;
 input.dataset.readflowColorPicker = 'true';
 let ready = false;
 const notify = () => input.dispatchEvent(new window.Event('input', { bubbles: true }));
 $(input).wpColorPicker({
  change: function (event, ui) {
   if (!ready) return;
   input.value = ui.color.toString();
   notify();
  },
  clear: function () { if (ready) notify(); }
 });
 ready = true;
}());
