'use strict';
const assert=require('node:assert/strict');const fs=require('node:fs');const vm=require('node:vm');
const mount=require('../assets/js/readmarker-placement.js');let checks=0;
function check(value,message){assert.ok(value,message);checks++;}
function fixture(selector,missing=false,invalid=false){
 const original={id:'rendered-output'};const attributes={'data-readmarker-selector':selector};
 const source={content:{firstElementChild:original,contains:()=>false},hasAttribute:key=>key in attributes,getAttribute:key=>attributes[key],setAttribute:(key,value)=>{attributes[key]=value;},remove(){this.removed=true;}};
 const first={children:['existing title'],appendChild(fragment){this.children.push(fragment.firstElementChild);fragment.firstElementChild=null;}};
 const second={children:['other title']};const queries=[];
 const document={querySelector(query){queries.push(query);if(query==='template[data-readmarker-placement]')return source.removed?null:source;if(invalid)throw new SyntaxError('Invalid CSS');return missing?null:first;}};
 return {document,source,first,second,original,queries};
}
for(const selector of ['article .entry-content','#header > div:first-child','[data-title="x"]']){
 const f=fixture(selector);check(mount(f.document),'Valid selector mounts');
 check(f.first.children.length===2&&f.first.children[1]===f.original,'Moves existing output without cloning/replacing');
 check(f.second.children.length===1,'Only first matching target');
 check(f.queries[1]===selector,'Selector passed as data to querySelector');
 const queries=f.queries.length;check(mount(f.document)&&f.first.children.length===2&&queries===f.queries.length,'Mounted retry true without DOM queries');
}
for(const [selector,missing,invalid] of [['',false,false],['   ',false,false],['[invalid',false,true],['.absent',true,false]]){
 const f=fixture(selector,missing,invalid);check(!mount(f.document),'Invalid/empty/missing safe');
 const attempts=f.queries.length;check(!mount(f.document)&&f.queries.length===attempts+(selector.trim()?1:0),'Explicit failure retry only queries when selector exists');
 check(f.first.children.length===1,'No target modification on failure');
}
check(!mount({querySelector:()=>null}),'Missing output safe');
const empty=fixture('#x');empty.source.content.firstElementChild=null;check(!mount(empty.document),'Empty template safe');
const source=fs.readFileSync('assets/js/readmarker-placement.js','utf8');
check(!/setInterval|setTimeout|requestAnimationFrame|MutationObserver|eval\(|new Function/.test(source),'No timers, engines or dynamic execution');
check(!/['"]scroll['"]|['"]resize['"]/.test(source),'No scroll or resize listeners');
const row={hidden:false,open:false};let change;const position={value:'before',addEventListener(name,fn){check(name==='change','Only admin selection listener');change=fn;}};
vm.runInNewContext(fs.readFileSync('assets/js/readmarker-settings-placement.js','utf8'),{document:{querySelector:()=>null,getElementById:id=>id==='readmarker-position'?position:{closest:()=>row}}});
check(!row.hidden&&!row.open,'Shared selector available collapsed for before and per-post use');position.value='selector';change();check(!row.hidden&&row.open,'Selector expanded for global custom');
for(const value of ['after','manual']){position.value=value;change();check(!row.hidden,'Shared selector remains reachable for per-post overrides');}

function browser(f, namespace) {
 const events=new Map();f.document.readyState='loading';
 f.document.addEventListener=(name,listener)=>{if(!events.has(name))events.set(name,[]);events.get(name).push(listener);};
 f.document.dispatchEvent=event=>{for(const listener of events.get(event.type)||[]) listener(event);};
 const window={document:f.document};if(namespace!==undefined)window.ReadMarker=namespace;
 vm.runInNewContext(source,{window});return {window,events};
}
{
 const f=fixture('#late');const lookup=f.document.querySelector;let available=false;
 f.document.querySelector=query=>query==='template[data-readmarker-placement]'?lookup(query):available?lookup(query):null;
 const existing={otherFeature:{enabled:true},placement:{custom:'preserved'}};
 const {window,events}=browser(f,existing);
 const retry=window.ReadMarker.placement.retry;
 check(window.ReadFlow.placement.retry===retry&&events.get('readflow:placement-ready')[0]===retry,'Legacy API and event share the same placement callback');
 check(typeof retry==='function','Public retry exposed when adapter loaded');
 check(window.ReadMarker===existing&&existing.placement.custom==='preserved'&&existing.otherFeature.enabled,'Namespace preserved');
 check(events.get('readmarker:placement-ready')[0]===retry&&events.get('DOMContentLoaded')[0]===retry,'Initial/event/public API share exact callback');
 f.document.dispatchEvent({type:'DOMContentLoaded'});check(f.first.children.length===1,'Initial missing target safe');
 f.document.dispatchEvent({type:'readmarker:placement-ready'});check(f.first.children.length===1,'Early lifecycle event safe');
 check(retry()===false,'Missing target returns false');
 available=true;f.document.dispatchEvent({type:'readflow:placement-ready'});
 check(f.first.children[1]===f.original&&f.second.children.length===1,'Event mounts first target after it appears');
 const queries=f.queries.length;
 for(let i=0;i<5;i++){check(retry()===true,'Mounted retries return true');f.document.dispatchEvent({type:'readmarker:placement-ready'});}
 check(f.first.children.length===2&&f.queries.length===queries,'Repeated event/API no duplicates or target queries');
}
{
 const f=fixture('#api');const lookup=f.document.querySelector;let target=false;
 f.document.querySelector=query=>query==='template[data-readmarker-placement]'?lookup(query):target?lookup(query):null;
 const {window}=browser(f);check(!window.ReadMarker.placement.retry(),'API before target');target=true;
 check(window.ReadMarker.placement.retry()&&f.first.children.length===2,'API mounts late target');
}
for(const selector of ['', '[invalid']){
 const f=fixture(selector,false,true);const {window}=browser(f);
 check(window.ReadMarker.placement.retry()===false,'Public empty/invalid selector false');
 f.document.dispatchEvent({type:'readmarker:placement-ready'});check(f.first.children.length===1,'Invalid event safe');
}
{
 const f=fixture('#none');f.document.querySelector=()=>null;const {window}=browser(f);
 check(window.ReadMarker.placement.retry()===false,'Non-selector/missing transport no-op');
 f.document.dispatchEvent({type:'readmarker:placement-ready'});check(f.first.children.length===1,'No non-selector DOM modification');
}
for(const namespace of [Object.freeze({}), {placement:Object.freeze({})}, {placement:{retry:()=> 'existing'}}]){
 const f=fixture('#safe');const originalRetry=namespace.placement&&namespace.placement.retry;
 const {window}=browser(f,namespace);f.document.dispatchEvent({type:'readmarker:placement-ready'});
 check(f.first.children.length===2,'Conflicting namespace still permits event placement');
 if(originalRetry)check(window.ReadMarker.placement.retry===originalRetry,'Never overwrite existing retry');
}
check(mount(null)===false&&mount({querySelector(){throw new Error('unavailable');}})===false,'Public internal operation fails safely');
console.log(`PASS: ${checks} placement JavaScript assertions.`);

