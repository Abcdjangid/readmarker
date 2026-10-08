'use strict';
const assert=require('node:assert/strict');const fs=require('node:fs');const api=require('../assets/js/readmarker-reader-controls.js');let checks=0;
function check(v,m){assert.ok(v,m);checks++;}
for(const value of [null,{},'bad',{version:2,textSize:100,readingWidth:100},{version:1,textSize:150,readingWidth:100},{version:1,textSize:100,readingWidth:Infinity},{version:1,textSize:'100',readingWidth:100}])check(api.normalize(value).textSize===100&&api.normalize(value).readingWidth===100,'Invalid loaded preferences default');
for(const textSize of [80,90,100,110,120,130,140])for(const readingWidth of [80,90,100,110,120])check(api.normalize({version:1,textSize,readingWidth}).textSize===textSize,'Valid preference steps');
check(api.step(80,'textSize',-1)===80&&api.step(140,'textSize',1)===140,'Text clamps');check(api.step(80,'readingWidth',-1)===80&&api.step(120,'readingWidth',1)===120,'Width clamps');
check(api.step(100,'textSize',1)===110&&api.step(100,'textSize',-1)===90,'Increment and decrement');
for(const value of ['{','null','{"version":9}'])check(api.read({localStorage:{getItem:()=>value}}).textSize===100,'Corrupt storage');
const blocked={get localStorage(){throw Error('SecurityError');}};check(api.read(blocked).textSize===100,'Storage unavailable');api.save(blocked,{version:1,textSize:120,readingWidth:100});check(true,'Save failure safe');
function element(attrs={}){const values={};return {attrs,hidden:true,disabled:false,textContent:'',style:{getPropertyValue:key=>values[key]||'',getPropertyPriority:()=>'',setProperty:(key,value)=>{values[key]=value;}},classList:{add(){},remove(){},toggle(){}},closest(){return null;},getAttribute:key=>attrs[key]??null,setAttribute:(key,value)=>{attrs[key]=value;},hasAttribute:key=>key in attrs,focus(){this.focused=true;}};}
function fixture(storage){
 const article=element({'data-readmarker-article':'456'});article.parentElement=element();article.contains=()=>false;article.nextSibling={after:true};article.parentNode={insertBefore(node,next){node.mountedBefore=next;node.mounts=(node.mounts||0)+1;}};const paragraph=element();const metadata=element();metadata.closest=()=>metadata;
 article.querySelectorAll=query=>query.startsWith('p,')?[paragraph]:[metadata];
 const summary=element();const trigger=element(),panel=element(),reset=element({'data-reader-reset':''});
 const displays={textSize:element(),readingWidth:element()};const buttons=[];
 for(const key of ['textSize','readingWidth'])for(const step of [-1,1])buttons.push(element({'data-reader-key':key,'data-reader-step':String(step)}));
 const listeners={};const ui=element();ui.contains=node=>[ui,trigger,panel,reset,...buttons].includes(node);panel.contains=node=>[panel,reset,...buttons].includes(node);ui.addEventListener=(name,fn)=>{listeners[name]=fn;};ui.removeEventListener=name=>{delete listeners[name];};
 ui.querySelector=query=>query==='[data-reader-summary]'?summary:query==='[data-reader-toggle]'?trigger:query==='[data-reader-panel]'?panel:displays[query.includes('textSize')?'textSize':'readingWidth'];
 ui.querySelectorAll=query=>buttons.filter(button=>query.includes(button.attrs['data-reader-key']));
 let refresh=0,writes=0;const saved=[];
 const documentListeners=new Map();
 const signals=[]; const legacySignals=[];
 const win={CustomEvent:class{constructor(type,options){this.type=type;this.detail=options.detail;}},document:{dispatchEvent(event){(event.type==='readflow:content-presentation-changed'?legacySignals:signals).push(event);if(event.type==='readmarker:content-presentation-changed')refresh++;for(const fn of documentListeners.get(event.type)||[])fn(event);},activeElement:null,addEventListener(name,fn){if(!documentListeners.has(name))documentListeners.set(name,new Set());documentListeners.get(name).add(fn);},removeEventListener(name,fn){documentListeners.get(name)?.delete(fn);},querySelector:()=>null,querySelectorAll:query=>query.startsWith('.readmarker-article')?[article]:[ui]},getComputedStyle:node=>({fontSize:node===paragraph?'20px':'16px',width:node===article?'600px':'1000px'}),localStorage:storage||{getItem:()=>null,setItem:(key,value)=>{writes++;saved.push([key,value]);}},ReadMarkerProgress:{refresh(){refresh++;}}};
 const click=button=>listeners.click({target:{closest:()=>button}});
 return {signals,legacySignals,summary,documentListeners,win,article,paragraph,metadata,ui,trigger,panel,buttons,reset,displays,listeners,click,saved,refresh:()=>refresh,writes:()=>writes};
}
{
 const f=fixture();const control=api.boot(f.win);check(control===api.boot(f.win)&&Object.keys(f.listeners).length===2,'Idempotent two UI-only handlers');
 check(!f.ui.hidden&&f.panel.hidden&&f.displays.textSize.textContent==='100%','Initial state');
 f.click(f.trigger);check(!f.panel.hidden&&f.trigger.attrs['aria-expanded']==='true','Open aria state');
 f.listeners.keydown({key:'Escape'});check(f.panel.hidden&&f.trigger.focused&&f.trigger.attrs['aria-expanded']==='false','Escape closes and returns focus');
 const increase=f.buttons.find(b=>b.attrs['data-reader-key']==='textSize'&&b.attrs['data-reader-step']==='1');
 const before=f.refresh();f.click(increase);check(f.paragraph.style.getPropertyValue('font-size')==='22px','Article text scaled');check(f.metadata.style.getPropertyValue('font-size')==='16px','Metadata retains baseline size');check(f.refresh()===before+1,'One existing-engine refresh');
 for(let i=0;i<10;i++)f.click(increase);check(control.preferences.textSize===140&&increase.disabled,'Text bound disables increment');
 const width=f.buttons.find(b=>b.attrs['data-reader-key']==='readingWidth'&&b.attrs['data-reader-step']==='1');f.click(width);check(f.article.style.getPropertyValue('width')==='66%','Relative article width, no fixed pixel width');
 f.click(f.reset);check(control.preferences.textSize===100&&control.preferences.readingWidth===100&&f.paragraph.style.getPropertyValue('font-size')===''&&f.article.style.getPropertyValue('width')==='','Reset restores original inline styles');
 const record=JSON.parse(f.saved.at(-1)[1]);check(Object.keys(record).join(',')==='version,textSize,readingWidth'&&f.saved.at(-1)[0]==='readflow_reader_preferences','Only anonymous presentation preferences persisted');
 control.destroy();check(f.ui.hidden&&Object.keys(f.listeners).length===0,'Cleanup');
}
{
 const f=fixture({getItem:()=>JSON.stringify({version:1,textSize:120,readingWidth:80}),setItem(){throw Error('quota');}});const c=api.boot(f.win);check(c.preferences.textSize===120&&f.paragraph.style.getPropertyValue('font-size')==='24px','Loaded preferences applied');f.click(f.reset);check(c.preferences.textSize===100,'Reset works without persistence');c.destroy();
}
for(const count of [0,2]){let reads=0;const win={document:{querySelectorAll:()=>Array(count).fill({})},get localStorage(){reads++;throw Error('blocked');}};check(api.boot(win)===null&&reads===0,'Missing/ambiguous target no storage or UI');}
const source=fs.readFileSync('assets/js/readmarker-reader-controls.js','utf8');check(!/setInterval|setTimeout|requestAnimationFrame|MutationObserver|['"]scroll['"]|['"]resize['"]|fetch\(/.test(source),'No observers/timers/network/scroll system');

for(const placement of ['above','below','reading_info'])for(const expanded of [false,true]){
 const f=fixture();f.ui.attrs['data-reader-placement']=placement;f.ui.attrs['data-reader-panel-state']=expanded?'expanded':'collapsed';
 const info=element({'data-readmarker-reading-info':'456'});info.appendChild=node=>{node.metadataParent=info;node.mounts=(node.mounts||0)+1;};f.win.document.querySelector=()=>info;
 const controller=api.boot(f.win);
 check(placement==='reading_info'?f.ui.metadataParent===info:f.ui.mountedBefore===(placement==='below'?f.article.nextSibling:f.article),'Requested placement');
 check(f.panel.hidden===!expanded&&f.trigger.attrs['aria-expanded']===String(expanded),'Initial state and aria match');
 check(api.boot(f.win)===controller&&controller.init()&&f.ui.mounts===1,'Repeated init never remounts');
 if(expanded){f.listeners.keydown({key:'Escape'});check(f.panel.hidden&&f.trigger.attrs['aria-expanded']==='false','Expanded Escape');}
 controller.destroy();
}
{
 const f=fixture();f.ui.attrs['data-reader-placement']='reading_info';api.boot(f.win);check(f.ui.mountedBefore===f.article,'Missing reading info falls back above');
 check(f.writes()===0,'Panel settings are never persisted on init');
}
const css=fs.readFileSync('assets/css/readmarker-reader-controls.css','utf8');
check(css.includes('(width < 782px)')&&css.includes('(min-width: 782px)'),'Exact 782px breakpoint');
check(css.includes('[data-reader-visibility="desktop"] { display: none; }')&&css.includes('[data-reader-visibility="mobile"] { display: none; }'),'Responsive display removes UI from keyboard/accessibility tree');
check(!/innerWidth|matchMedia|ResizeObserver/.test(source),'No viewport JavaScript');

{
 const f=fixture();const c=api.boot(f.win);const count=()=>f.documentListeners.get('click')?.size||0;
 const outside=target=>{for(const fn of [...(f.documentListeners.get('click')||[])])fn({target});};
 check(count()===0&&!f.trigger.focused,'Collapsed init adds no document listener or focus');
 for(const key of ['Enter',' ']){
  // Native button activation emits click; no custom key interception is required.
  f.listeners.keydown({key});check(f.panel.hidden,'Native activation not duplicated by keydown');
  f.click(f.trigger);check(!f.panel.hidden&&count()===1,'Native activation click opens');
  outside(f.trigger);check(!f.panel.hidden,'Bubbling trigger click does not double-toggle');
  f.click(f.trigger);check(f.panel.hidden&&count()===0&&f.trigger.focused,'Native activation click closes and returns focus');
 }
 f.trigger.focused=false;f.click(f.trigger);check(!f.trigger.focused,'Opening does not steal focus');
 f.click(f.buttons[1]);outside(f.buttons[1]);check(!f.panel.hidden,'Adjustment keeps open');
 f.click(f.reset);outside(f.reset);check(!f.panel.hidden,'Reset keeps open');
 outside(f.panel);outside(f.ui);check(!f.panel.hidden,'Inside panel and wrapper stay open');
 const unrelated=element();f.win.document.activeElement=unrelated;outside(unrelated);
 check(f.panel.hidden&&!f.trigger.focused&&count()===0,'Outside focus preserved and listener removed');
 f.click(f.trigger);f.win.document.activeElement=f.buttons[0];outside(unrelated);
 check(f.panel.hidden&&f.trigger.focused,'Outside closure restores stranded panel focus');
 f.click(f.trigger);f.listeners.keydown({key:'Escape',defaultPrevented:true});check(!f.panel.hidden,'Respect consumed Escape');
 f.listeners.keydown({key:'Tab'});check(!f.panel.hidden,'Natural Tab handling');
 outside({closest:()=>({})});check(!f.panel.hidden,'Other Reader Controls isolated');
 const external=()=>{};f.win.document.addEventListener('click',external);
 for(let i=0;i<5;i++){c.init();api.boot(f.win);}check(count()===2&&f.writes()===2,'Repeated init no listeners or writes');
 c.destroy();c.destroy();check(count()===1&&f.documentListeners.get('click').has(external),'Destroy only removes owned listener');
}
{
 const f=fixture();f.ui.attrs['data-reader-panel-state']='expanded';api.boot(f.win);
 check(!f.trigger.focused&&f.documentListeners.get('click').size===1,'Expanded init no focus steal, one listener');
 const original=f.win.getComputedStyle;f.win.getComputedStyle=node=>node===f.ui?{display:'none'}:original(node);
 f.listeners.keydown({key:'Escape'});check(!f.trigger.focused,'Responsive-hidden trigger never focused');
}

for(const [textSize,readingWidth,expected] of [[100,100,''],[130,100,' \u00b7 Text 130%'],[80,100,' \u00b7 Text 80%'],[100,80,' \u00b7 Width 80%'],[100,120,' \u00b7 Width 120%'],[130,80,' \u00b7 Text 130% \u00b7 Width 80%']]){
 let reads=0;const f=fixture({getItem(){reads++;return JSON.stringify({version:1,textSize,readingWidth});},setItem(){}});
 const c=api.boot(f.win);check(f.summary.textContent===expected,'Summary from normalized initial preferences');
 let writes=0;let text=f.summary.textContent;Object.defineProperty(f.summary,'textContent',{get:()=>text,set:value=>{writes++;text=value;}});
 c.apply();api.boot(f.win);check(writes===0&&reads===1,'Unchanged summary no DOM write or storage reread');
 f.click(f.trigger);f.click(f.reset);check(f.summary.textContent===''&&!f.panel.hidden,'Reset removes summary and keeps panel open');c.destroy();
}
{
 const f=fixture();api.boot(f.win);f.click(f.trigger);
 const button=(key,step)=>f.buttons.find(b=>b.attrs['data-reader-key']===key&&b.attrs['data-reader-step']===String(step));
 for(let i=0;i<3;i++)f.click(button('textSize',1));check(f.summary.textContent===' \u00b7 Text 130%','Live text summary');
 f.click(button('readingWidth',-1));f.click(button('readingWidth',-1));check(f.summary.textContent===' \u00b7 Text 130% \u00b7 Width 80%','Live combined summary');
 for(let i=0;i<3;i++)f.click(button('textSize',-1));check(f.summary.textContent===' \u00b7 Width 80%','Default text removed from summary');
 f.click(f.reset);check(f.summary.textContent===''&&f.trigger.attrs['aria-expanded']==='true','Reset disclosure remains unchanged');
}
{
 const f=fixture({getItem:()=>'{bad',setItem(){}});api.boot(f.win);check(f.summary.textContent==='','Invalid storage never appears in summary');
}

{
 const f=fixture();api.boot(f.win);check(f.signals.length===0,'Default initialization has no change signal');
 f.click(f.reset);check(f.signals.length===0,'Default reset has no signal');
 const button=(key,direction)=>f.buttons.find(b=>b.attrs['data-reader-key']===key&&b.attrs['data-reader-step']===String(direction));
 f.win.document.addEventListener('readmarker:content-presentation-changed',event=>{
  check(f.saved.length>0&&JSON.parse(f.saved.at(-1)[1]).textSize===Number(f.displays.textSize.textContent.replace('%','')),'Signal follows persistence and UI update');
  check(Object.keys(event.detail).join(',')==='reason'&&['reset','reader-controls'].includes(event.detail.reason),'Minimal safe payload');
 });
 f.click(button('textSize',1));check(f.legacySignals.length===1&&f.legacySignals[0].detail.reason===f.signals[0].detail.reason,'Legacy presentation event retains payload');check(f.signals.length===1&&f.signals[0].detail.reason==='reader-controls','Text signals');
 f.click(button('readingWidth',-1));check(f.signals.length===2,'Width signals');
 f.click(f.reset);check(f.signals.length===3&&f.signals[2].detail.reason==='reset'&&f.summary.textContent==='','Reset signals after presentation restored');
 f.click(f.reset);check(f.signals.length===3,'Unchanged reset no signal');
 for(let i=0;i<10;i++)f.click(button('textSize',1));check(f.signals.length===7,'Clamped/disabled input adds no signals');
}
{
 let attempts=0;const f=fixture({getItem:()=>null,setItem(){attempts++;throw Error('quota');}});api.boot(f.win);f.click(f.buttons[1]);
 check(f.signals.length===1&&attempts===1&&f.paragraph.style.getPropertyValue('font-size')==='22px','Failed persistence still signals visual change');
}
for(const missing of ['textSize','readingWidth','CustomEvent','getComputedStyle']) {
 const f=fixture();const lookup=f.ui.querySelector;
 if(['textSize','readingWidth'].includes(missing))f.ui.querySelector=query=>query==='[data-reader-value="'+missing+'"]'?null:lookup(query);
 else delete f.win[missing];
 check(api.boot(f.win)===null && f.ui.hidden && !f.ui.mounts && Object.keys(f.listeners).length===0 && f.documentListeners.size===0,'Incomplete controls/API fail before mounting or adding listeners: '+missing);
}
console.log(`PASS: ${checks} reader controls JavaScript assertions.`);
