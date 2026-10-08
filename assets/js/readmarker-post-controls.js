/* Progressive disclosure only. Native WordPress controls supply keyboard/focus behavior. */
(function (root, factory) {
 'use strict';
 if (typeof module === 'object' && module.exports) module.exports = factory();
 else {
  const init = () => factory().init(root.document);
  if (root.document.readyState === 'loading') root.document.addEventListener('DOMContentLoaded', init, {once:true});
  else init();
 }
}(typeof window !== 'undefined' ? window : globalThis, function () {
 'use strict';
 function init(document) {
  document.querySelectorAll('.readmarker-post-controls').forEach(box => {
   if (box.dataset.readmarkerBound) return;
   const fields=box.querySelector('[data-readmarker-override-fields]');
   if (!fields) return;
   const update=()=>{
    const choice=box.querySelector('input[name="readmarker_post[behavior]"]:checked');
    const enabled=!!choice && choice.value==='override';
    fields.hidden=!enabled;fields.disabled=!enabled;
   };
   box.dataset.readmarkerBound='true';box.addEventListener('change',update);update();
  });
 }
 return {init};
}));
