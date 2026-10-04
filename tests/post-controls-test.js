'use strict';
const assert=require('node:assert/strict');
const {init}=require('../assets/js/readflow-post-controls.js');
let checks=0;const check=(condition,label)=>{assert.ok(condition,label);checks++;};
for(const initial of ['global','disabled','override']) {
 const fields={hidden:false,disabled:false};let choice={value:initial};const listeners=[];
 const box={dataset:{},querySelector(selector){return selector==='[data-readflow-override-fields]'?fields:choice;},addEventListener(type,handler){listeners.push({type,handler});}};
 const document={querySelectorAll(){return [box];}};
 init(document);check(fields.hidden===(initial!=='override') && fields.disabled===(initial!=='override'),'initial disclosure');
 init(document);check(listeners.length===1 && listeners[0].type==='change','idempotent native change handler');
 for(const mode of ['override','disabled','global','override']){choice={value:mode};listeners[0].handler();check(fields.hidden===(mode!=='override') && fields.disabled===(mode!=='override'),'radio choice controls only override fieldset');}
 choice=null;listeners[0].handler();check(fields.hidden && fields.disabled,'missing selection hides overrides safely');
}
init({querySelectorAll(){return [];}});check(true,'other admin screens have no controls to initialize');
init({querySelectorAll(){return [{dataset:{},querySelector(){return null;}}];}});check(true,'incomplete markup safe');
const source=require('node:fs').readFileSync(require.resolve('../assets/js/readflow-post-controls.js'),'utf8');
check(!/fetch\(|XMLHttpRequest|setInterval|setTimeout|innerHTML/.test(source),'no requests timers or unsafe HTML');
console.log(`PASS: ${checks} post-controls JavaScript assertions.`);
