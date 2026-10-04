'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const source = fs.readFileSync(require.resolve('../assets/js/readflow-settings-placement.js'), 'utf8');
let checks = 0;
const check = (value, label) => { assert.ok(value, label); checks++; };
function target() {
 const events = new Map();
 return { events, addEventListener(type, fn) { if (!events.has(type)) events.set(type, new Set()); events.get(type).add(fn); },
  removeEventListener(type, fn) { events.get(type)?.delete(fn); },
  fire(type, event = {}) { for (const fn of events.get(type) || []) fn(event); },
  count(type) { return events.get(type)?.size || 0; } };
}
const form = Object.assign(target(), {dataset:{}, fields:[
 {name:'text',value:'original'}, {name:'number',value:'200'}, {name:'color',value:'#2563eb'},
 {name:'checkbox',value:'1',type:'checkbox',checked:false},
 {name:'select',value:'before'}, {name:'radio',value:'a',type:'radio',checked:true},
 {name:'radio',value:'b',type:'radio',checked:false}, {name:'multi',values:['a']},
 {name:'textarea',value:'notes'}, {name:'nonce',value:'private',type:'hidden'}
]});
const reset = Object.assign(target(), {dataset:{},fields:[]});
const window = target();
let reads = 0;
class FormDataDouble {
 constructor(selected) { check(selected === form, 'Only main form serialized'); reads++; this.data=[];
  for(const field of selected.fields) {
   if(field.disabled || (['checkbox','radio'].includes(field.type) && !field.checked)) continue;
   for(const value of field.values || [field.value]) this.data.push([field.name,value]);
  }
 }
 entries() { return this.data.values(); }
}
const document = {getElementById(){return null;},querySelector(selector){check(selector === '.readflow-settings form[action="options.php"]','Scoped main form lookup');return form;}};
const context = {document,window,FormData:FormDataDouble};
vm.runInNewContext(source,context);
check(window.count('beforeunload')===0,'Initially clean');
const initialReads=reads;
vm.runInNewContext(source,context);
check(reads===initialReads && form.count('change')===1 && form.count('input')===1 && form.count('submit')===1,'Reinitialization preserves baseline and handlers');
for(const index of [0,1,2,4,8]) {
 const original=form.fields[index].value;
 form.fields[index].value='changed';form.fire('input');check(window.count('beforeunload')===1,'Text/number/color/select/textarea dirty');
 form.fire('change');check(window.count('beforeunload')===1,'One warning handler');
 form.fields[index].value=original;form.fire('change');check(window.count('beforeunload')===0,'Reverted value clean');
}
form.fields[3].checked=true;form.fire('change');check(window.count('beforeunload')===1,'Checkbox dirty');
form.fields[3].checked=false;form.fire('change');check(window.count('beforeunload')===0,'Checkbox reverted');
form.fields[5].checked=false;form.fields[6].checked=true;form.fire('change');check(window.count('beforeunload')===1,'Radio group dirty');
form.fields[5].checked=true;form.fields[6].checked=false;form.fire('change');check(window.count('beforeunload')===0,'Radio group reverted');
form.fields[7].values=['a','b'];form.fire('change');check(window.count('beforeunload')===1,'Multiselect dirty');
form.fields[7].values=['a'];form.fire('change');check(window.count('beforeunload')===0,'Multiselect reverted');
reset.fire('change');reset.fire('submit');check(window.count('beforeunload')===0 && reset.events.size===0,'Reset form has no listeners or serialization');
form.fields[0].value='new';form.fields[1].value='250';form.fire('input');form.fields[0].value='original';form.fire('change');check(window.count('beforeunload')===1,'Another outstanding change remains dirty');
let prevented=false;const event={preventDefault(){prevented=true;}};window.fire('beforeunload',event);
check(prevented && event.returnValue==='','Native beforeunload request without custom wording');
vm.runInNewContext(source,context);check(window.count('beforeunload')===1,'Dirty reinitialization does not duplicate warning');
form.fire('submit',{preventDefault(){throw Error('Must not intercept submission');}});check(window.count('beforeunload')===0,'Normal submission clears warning');
form.fire('change');check(window.count('beforeunload')===0,'Submitted baseline is clean');
vm.runInNewContext(source,{document:{getElementById(){return null;},querySelector(){return null;}}});check(true,'Missing form safe');
check(!/localStorage|sessionStorage|document\.cookie|fetch\(|XMLHttpRequest|setTimeout|setInterval|MutationObserver|ResizeObserver/.test(source),'No persistence requests timers or observers');
for(const unavailable of [undefined, class {}]) {
 const absentForm=Object.assign(target(),{dataset:{}});
 vm.runInNewContext(source,{document:{getElementById(){return null;},querySelector(){return absentForm;}},FormData:unavailable});
 check(absentForm.events.size===0 && !absentForm.dataset.readflowUnsavedInitialized,'Missing FormData/entries leaves native settings submission usable');
}

{
 const color=form.fields[2];color.dataset={};color.dispatchEvent=event=>form.fire(event.type,event);
 let options,initializations=0;
 function jquery(node){check(node===color,'Native picker targets existing Progress Color field');return {wpColorPicker(config){options=config;initializations++;config.change(null,{color:{toString:()=> '#000000'}});}};}
 jquery.fn={wpColorPicker(){}};window.jQuery=jquery;window.Event=class{constructor(type,options){this.type=type;this.bubbles=options.bubbles;}};
 document.getElementById=id=>id==='readflow-progress_color'?color:null;
 vm.runInNewContext(source,context);
 check(color.value==='#2563eb'&&window.count('beforeunload')===0,'Saved color retained and initialization stays clean');
 options.change(null,{color:{toString:()=> '#ff0000'}});
 check(color.value==='#ff0000'&&window.count('beforeunload')===1,'Picker updates native input and existing dirty detection');
 options.change(null,{color:{toString:()=> '#2563eb'}});
 check(window.count('beforeunload')===0,'Picker returning to saved color clears warning');
 color.value='#123456';form.fire('input');check(window.count('beforeunload')===1,'Manual HEX input remains supported');
 color.value='';options.clear();check(window.count('beforeunload')===1,'Native Clear reaches existing comparison');
 color.value='#2563eb';form.fire('change');check(window.count('beforeunload')===0,'Manual revert stays clean');
 vm.runInNewContext(source,context);check(initializations===1,'Picker initialized only once');
 document.getElementById=()=>null;vm.runInNewContext(source,context);check(initializations===1,'Missing color field safely skipped');
}
console.log(`PASS: ${checks} settings protection JavaScript assertions.`);
