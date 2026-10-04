/* Explicit placement lifecycle only. Selector data never becomes executable source. */
(function (root, factory) {
 'use strict';
 const mount = factory();
 if (typeof module === 'object' && module.exports) { module.exports = mount; return; }
 if (!root || !root.document) return;
 const document = root.document;
 const retry = () => mount(document);
 // Preserve other feature namespaces and existing integration-owned properties.
 try {
  if (root.ReadFlow === undefined) root.ReadFlow = {};
  const namespace = root.ReadFlow;
  if (namespace && (typeof namespace === 'object' || typeof namespace === 'function')) {
   if (namespace.placement === undefined) namespace.placement = {};
   const placement = namespace.placement;
   if (placement && (typeof placement === 'object' || typeof placement === 'function') && placement.retry === undefined) placement.retry = retry;
  }
 } catch (_) { /* A frozen/conflicting namespace does not disable the DOM event. */ }
 document.addEventListener('readflow:placement-ready', retry);
 if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', retry, { once: true });
 else retry();
}(typeof window !== 'undefined' ? window : null, function () {
 'use strict';
 // Document-scoped state retains only the transport until successful placement.
 const states = new WeakMap();
 return function mount(document) {
  try {
   if (!document || typeof document.querySelector !== 'function') return false;
   let state = states.get(document);
   if (!state) { state = { source: null, mounted: false }; states.set(document, state); }
   if (state.mounted) return true;
   if (!state.source) state.source = document.querySelector('template[data-readflow-placement]');
   const source = state.source;
   if (!source) return false;
   const raw = source.getAttribute('data-readflow-selector');
   const selector = typeof raw === 'string' ? raw.trim() : '';
   if (!selector || !source.content || !source.content.firstElementChild) return false;
   const target = document.querySelector(selector);
   if (!target || target === source || source.content.contains(target)) return false;
   target.appendChild(source.content);
   // Mark success before cleanup: even a failed transport removal cannot remount.
   state.mounted = true;
   state.source = null;
   source.remove();
   return true;
  } catch (_) { return false; } // Invalid selectors or unavailable DOM fail silently.
 };
}));
