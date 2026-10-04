'use strict';
const assert=require('node:assert/strict');const {init}=require('../assets/js/readflow-shortcodes.js');let checks=0;const check=(v,m)=>{assert.ok(v,m);checks++;};
function fixture(clipboard){
 const attrs={'aria-label':'Copy [readflow_time format="long"]','data-copy-label':'Copy','data-copied-label':'Copied','data-failed-label':'Select and copy the shortcode manually.'};
 const code={textContent:'[readflow_time format="long"]'};let handler,count=0,selected=0;const timers=new Map();let sequence=0;
 const button={dataset:{},hidden:true,textContent:'Copy',parentNode:{querySelector:()=>code},getAttribute:k=>attrs[k],setAttribute:(k,v)=>attrs[k]=v,addEventListener:(event,fn)=>{check(event==='click','Native click only');handler=fn;count++;}};
 const win={navigator:{clipboard},document:{querySelectorAll:()=>[button],createRange:()=>({selectNodeContents(node){check(node===code,'Select exact visible example');}})},getSelection:()=>({removeAllRanges(){},addRange(){selected++;}}),setTimeout(fn,delay){check(delay===2000,'Short feedback timeout');timers.set(++sequence,fn);return sequence;},clearTimeout(id){timers.delete(id);}};
 return {win,button,attrs,code,timers,click:()=>handler(),count:()=>count,selected:()=>selected};
}
(async()=>{
 for(const text of ['[readflow]','[readflow_time format="long"]','[readflow_words label="false"]','[readflow_progress show_percentage="false"]','[readflow_remaining format="clock"]']){
  let copied;const f=fixture({writeText:async value=>{copied=value;}});f.code.textContent=text;init(f.win);init(f.win);check(f.count()===1&&!f.button.hidden,'One handler and enabled button');await f.click();check(copied===text&&f.button.textContent==='Copied','Exact clipboard text and feedback');await f.click();check(f.timers.size===1,'Repeated clicks keep single feedback timeout');for(const fn of f.timers.values())fn();check(f.button.textContent==='Copy'&&f.attrs['aria-label']==='Copy [readflow_time format="long"]','Label and accessible name restored');
 }
 for(const clipboard of [undefined,{writeText:async()=>{throw Error('denied');}}]){const f=fixture(clipboard);init(f.win);await f.click();check(f.selected()===1&&f.button.textContent.includes('manually'),'Unavailable/rejected clipboard safely selects for manual copying');}
 const missing=fixture();missing.button.parentNode.querySelector=()=>null;init(missing.win);check(missing.count()===0&&missing.button.hidden,'Missing code safe');
 init(null);init({document:{querySelectorAll:()=>[]}});check(true,'Missing page safe');
 const source=require('node:fs').readFileSync('assets/js/readflow-shortcodes.js','utf8');check(!/fetch\(|XMLHttpRequest|localStorage|sessionStorage|document\.cookie|setInterval/.test(source),'No network storage or polling');
 console.log(`PASS: ${checks} shortcode copy assertions.`);
})().catch(error=>{console.error(error);process.exitCode=1;});
