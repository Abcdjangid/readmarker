'use strict';
const assert=require('node:assert/strict');
const api=require('../assets/js/readflow-position-memory.js');
const {calculateCompletionState}=require('../assets/js/readflow-progress.js');
let checks=0;const check=(ok,label)=>{assert.ok(ok,label);checks++;};
let now=1800000000000;
function storage(){return {map:new Map(),writes:0,removes:0,getItem(k){return this.map.get(k)??null;},setItem(k,v){this.writes++;this.map.set(k,v);},removeItem(k){this.removes++;this.map.delete(k);}};}
const db=storage(),store=new api.Store(db,1,()=>now);
for(const ratio of [0.1,0.25,0.5,0.75,0.99]){check(store.save(101,ratio),'save ratio');check(store.read(101).progress===ratio,'read precise normalized ratio');}
for(const [id,ratio] of [[101,0.3],[102,0.6],[103,0.8]])store.save(id,ratio);
for(const [id,ratio] of [[101,0.3],[102,0.6],[103,0.8]])check(store.read(id).progress===ratio,'independent article keys');
check(new api.Store(db,2,()=>now).read(101)===null,'site IDs isolated');
store.remove(101);check(store.read(101)===null && store.read(102).progress===0.6,'remove independent record');
store.save(101,0.5);now+=api.EXPIRATION_MS-1;check(store.read(101)!==null,'just before expiry valid');now++;check(store.read(101)===null && !db.map.has(store.key(101)),'exact expiry removed');
store.save(101,0.5);now+=api.EXPIRATION_MS+1;check(store.read(101)===null,'expired removed');
const good={version:1,articleId:101,progress:0.5,updatedAt:now};
for(const raw of ['bad','null','[]',...[
 {...good,version:2},{...good,articleId:102},{...good,progress:'hello'},{...good,progress:null},{...good,progress:-1},{...good,progress:4},{...good,progress:1},{...good,progress:undefined},{...good,updatedAt:undefined},{...good,updatedAt:'today'},{...good,updatedAt:now+1},{...good,updatedAt:-1}
].map(JSON.stringify)]){db.setItem(store.key(101),raw);check(store.read(101)===null && !db.map.has(store.key(101)),'corrupt record safely removed');}
for(const invalid of [NaN,Infinity,-Infinity,-1,4,'0.5',null,undefined])check(!store.save(101,invalid),'invalid save rejected');
for(const id of [null,undefined,'url?private=1','',0,-1,1.5,{},'__proto__'])check(!store.save(id,0.5) && store.read(id)===null,'invalid article ID disabled');
for(const method of ['getItem','setItem','removeItem']){const broken=storage();broken[method]=()=>{throw Error('blocked');};const safe=new api.Store(broken,1,()=>now);if(method==='getItem')safe.read(101);else if(method==='setItem')safe.save(101,0.5);else safe.remove(101);check(!safe.available,'storage failure disables safely');}
check(new api.Store(null,1).read(101)===null,'missing storage safe');
for(const [ratio,geometry,max,offset,expected] of [[0.5,{articleTop:300,articleHeight:1800,viewportHeight:600},3000,0,900],[0.5,{articleTop:300,articleHeight:1800,viewportHeight:600},3000,20,880],[0.99,{articleTop:300,articleHeight:1800,viewportHeight:600},700,0,700],[0.5,{articleTop:-500,articleHeight:100,viewportHeight:600},700,0,0],[0.5,{articleTop:300,articleHeight:100,viewportHeight:600},700,0,300]])check(api.restorationTarget(ratio,geometry,max,offset)===expected,'inverse and clamps');
check(api.restorationTarget(0.5,{articleTop:0,articleHeight:0,viewportHeight:600},1000)===null,'invalid restoration geometry');
class Node {
 constructor(){this.attrs={};this.events=new Map();this.hidden=true;this.textContent='';this.parentNode=null;}
 getAttribute(k){return this.attrs[k]??null;}setAttribute(k,v){this.attrs[k]=v;}removeAttribute(k){delete this.attrs[k];}
 addEventListener(k,fn){if(!this.events.has(k))this.events.set(k,new Set());this.events.get(k).add(fn);}
 removeEventListener(k,fn){this.events.get(k)?.delete(fn);}
 emit(k,event={}){this.events.get(k)?.forEach(fn=>fn(event));}
 appendChild(node){node.parentNode=this;}contains(node){return node===this || Object.values(this.nodes||{}).includes(node);}
 focus(options){this.focusOptions=options;}querySelector(k){return this.nodes[k];}
}
function fixture(saved=null,reduced=false){
 const db=storage();const local=new api.Store(db,1,()=>now);if(saved!==null)local.save(101,saved);
 const article=new Node();article.attrs['data-readflow-article']='101';article.isConnected=true;article.reads=0;article.getBoundingClientRect=()=>{article.reads++;return {top:300,height:1800};};
 const prompt=new Node(),text=new Node(),proceed=new Node(),dismiss=new Node(),root=new Node();root.appendChild(prompt);prompt.attrs['data-readflow-site']='1';prompt.nodes={'[data-readflow-position-text]':text,'[data-readflow-continue]':proceed,'[data-readflow-dismiss]':dismiss};
 const body=new Node();body.scrollHeight=3000;
 const win={localStorage:db,scrollY:0,innerHeight:600,document:{body,documentElement:{scrollHeight:3000},activeElement:null},matchMedia:()=>({matches:reduced}),scrolls:[],scrollTo(o){this.scrolls.push(o);}};
 const engine={listeners:new Set(),state:null,refreshes:0,refresh(){this.refreshes++;},subscribe(fn){this.listeners.add(fn);if(this.state)fn(this.state);return ()=>this.listeners.delete(fn);},publish(ratio){this.state=Object.freeze({ratio,completion:calculateCompletionState(ratio)});this.listeners.forEach(fn=>fn(this.state));}};
 engine.publish(0);
 const controller=new api.Controller(engine,article,prompt,win,()=>now);
 return {db,local,article,prompt,text,proceed,dismiss,root,win,engine,controller};
}
for(const saved of [null,0,0.05,0.09,0.10,0.11,0.47,0.99,1]){
 const f=fixture(saved);check(f.controller.init(),'memory init');check(f.prompt.hidden===(saved===null||saved<0.1||saved===1),'prompt minimum threshold');check(f.win.scrolls.length===0 && f.article.reads===0,'no automatic scrolling or measurement');
 check(f.controller.init() && f.engine.listeners.size===1,'idempotent subscription');f.controller.destroy();f.controller.destroy();check(f.engine.listeners.size===0 && f.prompt.parentNode===f.root,'cleanup and bfcache DOM restoration');
}
for(const reduced of [false,true]){
 const f=fixture(0.47,reduced);f.controller.init();check(f.text.textContent==='You were at 47%','prompt text');
 f.win.document.activeElement=f.proceed;f.proceed.emit('click');check(f.prompt.hidden && f.article.reads===1,'continue hides and measures once');
 check(f.win.scrolls[0].top===864 && f.win.scrolls[0].behavior===(reduced?'instant':'smooth'),'native restoration and reduced motion');check(f.engine.state.ratio===0 && f.engine.refreshes===1,'engine remains authoritative');
 check(f.article.focusOptions.preventScroll && f.article.getAttribute('tabindex')===null,'focus moves from hidden control without scrolling');f.controller.destroy();
}
{
 const f=fixture(0.47);f.controller.init();f.dismiss.emit('click');f.engine.publish(0.6);f.controller.flush();check(f.prompt.hidden && f.local.read(101).progress===0.47 && f.win.scrolls.length===0,'dismiss preserves storage and does not scroll');
 f.controller.destroy();const second=new api.Controller(f.engine,f.article,f.prompt,f.win,()=>now);second.init();check(f.prompt.hidden,'dismissal survives bfcache in same page session');second.destroy();
}
{
 const f=fixture(0.47);f.controller.init();let prevented=false;f.prompt.emit('keydown',{key:'Escape',preventDefault(){prevented=true;}});check(prevented && f.prompt.hidden,'Escape dismisses locally');f.controller.destroy();
}
{
 const f=fixture();f.controller.init();f.engine.publish(0.4);const writes=f.db.writes;for(const r of [0.401,0.402,0.403,0.404,0.45])f.engine.publish(r);check(f.db.writes===writes,'continuous changes throttled without timer');now+=5000;f.engine.publish(0.46);check(f.db.writes===writes+1,'meaningful timed progress saved');
 now+=5000;f.engine.publish(0.461);check(f.db.writes===writes+1,'tiny changes not saved');f.controller.flush();check(f.local.read(101).progress===0.461,'page exit flush exact final position');
 f.engine.publish(0.05);f.controller.flush();check(f.local.read(101).progress===0.05,'backward below threshold replaces stale position');f.engine.publish(1);check(f.local.read(101)===null,'completion removes record');const removes=f.db.removes;f.engine.publish(1);check(f.db.removes===removes,'completion deletion deduplicated');f.controller.destroy();
}
for(const type of ['expired','malformed','unavailable','missingId']){
 const f=fixture(0.4);if(type==='expired')now+=api.EXPIRATION_MS;if(type==='malformed')f.db.setItem(f.local.key(101),'bad');if(type==='unavailable')Object.defineProperty(f.win,'localStorage',{get(){throw Error('restricted');}});if(type==='missingId')f.article.attrs={};
 f.controller.init();check(f.prompt.hidden,'invalid/unavailable data no prompt');check(f.win.scrolls.length===0,'failure no scrolling');f.controller.destroy();
}
{
 const f=fixture();f.controller.init();f.engine.publish(0.4);now+=5000;f.engine.publish(0.41);
 check(f.local.read(101).progress===0.41,'exact one-point change survives floating point subtraction');f.controller.destroy();
}
const source=require('node:fs').readFileSync(require.resolve('../assets/js/readflow-position-memory.js'),'utf8');check(!/setInterval|setTimeout|requestAnimationFrame|MutationObserver|fetch\(|XMLHttpRequest/.test(source),'no timers polling or remote requests');
check(!/addEventListener\(['"]scroll/.test(source),'no scroll listeners');

// Real progress boot owns memory and pagehide; memory-only mode creates no display.
const {boot,calculate}=require('../assets/js/readflow-progress.js');
const originalClock=Date.now;
try {
Date.now=()=>now;
for(const enabled of [false,true]){
 const f=fixture(0.47);const frames=new Map();let frameId=0;const events=new Node();
 f.win.addEventListener=events.addEventListener.bind(events);f.win.removeEventListener=events.removeEventListener.bind(events);
 f.win.requestAnimationFrame=fn=>{frames.set(++frameId,fn);return frameId;};f.win.cancelAnimationFrame=id=>frames.delete(id);
 f.win.CustomEvent=class{constructor(type,options){this.type=type;Object.assign(this,options);}};
 f.article.dispatchEvent=()=>{};f.win.document.getElementById=()=>null;
 f.root.getAttribute=name=>name==='data-readflow-display-disabled'?'true':null;
 f.root.querySelector=()=>enabled?f.prompt:null;
 f.win.document.querySelectorAll=selector=>selector.includes('article')?[f.article]:[f.root];f.win.ReadFlowPositionMemory=api;
 let storageReads=0;Object.defineProperty(f.win,'localStorage',{get(){storageReads++;return f.db;}});
 const active=boot(f.win);check(!!active && boot(f.win)===active,'boot remains idempotent');
 for(const [id,fn] of [...frames]){frames.delete(id);fn();}
 check(active.manager.consumers.length===0 && !!active.memory===enabled,'memory-only boot does not instantiate progress display');
 check(storageReads===(enabled?1:0),'disabled memory makes zero storage access');
 check(events.events.get('pagehide').size===1 && events.events.get('scroll').size===1,'one shared pagehide and scroll listener');
 if(enabled){f.dismiss.emit('click');active.memory.hold=false;active.engine.publish(calculate({articleTop:300,articleHeight:1800,viewportHeight:600,scrollPosition:840}));active.engine.publish(calculate({articleTop:300,articleHeight:1800,viewportHeight:600,scrollPosition:846}));}
 events.emit('pagehide');if(enabled)check(f.local.read(101).progress===0.455,'shared pagehide flushes final sub-threshold change');check(active.engine.listeners.size===0 && events.events.get('pagehide').size===0 && frames.size===0,'pagehide cleanup');
}
} finally { Date.now=originalClock; }
console.log(`PASS: ${checks} position-memory assertions including shared boot lifecycle.`);
