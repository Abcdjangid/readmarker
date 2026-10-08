(function(root,factory){
 'use strict';
 if(typeof module==='object'&&module.exports)module.exports=factory();
 else if(!root.ReadMarkerReaderControls){root.ReadFlowReaderControls=root.ReadMarkerReaderControls=factory();const init=()=>root.ReadMarkerReaderControls.boot(root);if(root.document.readyState==='loading')root.document.addEventListener('DOMContentLoaded',init,{once:true});else init();}
}(typeof window!=='undefined'?window:null,function(){
 'use strict';
 const KEY='readflow_reader_preferences';
 const defaults=()=>({version:1,textSize:100,readingWidth:100});
 const limits={textSize:140,readingWidth:120};
 function normalize(value){
  if(!value||value.version!==1||!['textSize','readingWidth'].every(key=>typeof value[key]==='number'&&Number.isFinite(value[key])&&value[key]>=80&&value[key]<=limits[key]&&value[key]%10===0))return defaults();
  return {version:1,textSize:value.textSize,readingWidth:value.readingWidth};
 }
 function step(value,key,direction){if(!Object.prototype.hasOwnProperty.call(limits,key))return 100;const current=typeof value==='number'&&Number.isFinite(value)?Math.round(value/10)*10:100;return Math.max(80,Math.min(limits[key],current+(direction<0?-10:10)));}
 function read(win){try{return normalize(JSON.parse(win.localStorage.getItem(KEY)));}catch(_){return defaults();}}
 function save(win,value){try{win.localStorage.setItem(KEY,JSON.stringify(normalize(value)));}catch(_){/* Page controls remain functional without persistence. */}}
 const instances=new WeakMap();
 class Controls{
  constructor(win,article,ui){
   this.win=win;this.article=article;this.ui=ui;this.removers=[];this.outsideListening=false;
   this.outsideClick=event=>{
    const target=event.target;
    if(this.panel.hidden||!target||this.ui.contains(target))return;
    // Defensive isolation: interaction with another controls component is not ours.
    if(typeof target.closest==='function'&&target.closest('[data-readmarker-reader-controls]'))return;
    const restore=this.panel.contains(this.win.document.activeElement);
    this.open(false);
    // A pointer click that focused a link/form elsewhere must keep that focus.
    if(restore)this.focusTrigger();
   };
  }
  init(){
   if(this.started)return true;
   this.trigger=this.ui.querySelector('[data-reader-toggle]');this.panel=this.ui.querySelector('[data-reader-panel]');
   if(!this.trigger||!this.panel||typeof this.win.getComputedStyle!=='function'||typeof this.win.CustomEvent!=='function'||!['textSize','readingWidth'].every(key=>this.ui.querySelector('[data-reader-value="'+key+'"]')))return false;
   this.summary=this.ui.querySelector('[data-reader-summary]');
   const parent=this.article.parentNode;
   if(!parent)return false;
   const placement=this.ui.getAttribute('data-reader-placement');
   const info=placement==='reading_info'?this.win.document.querySelector('[data-readmarker-reading-info]'):null;
   if(info&&info.getAttribute('data-readmarker-reading-info')===this.article.getAttribute('data-readmarker-article')&&!this.article.contains(info))info.appendChild(this.ui);
   else parent.insertBefore(this.ui,placement==='below'?this.article.nextSibling:this.article);
   this.open(this.ui.getAttribute('data-reader-panel-state')==='expanded');
   this.started=true;this.preferences=read(this.win);
   // Capture article text styles once. Exclude inline ReadMarker blocks/shortcodes and their descendants.
   const nodes=[this.article,...this.article.querySelectorAll('p,li,h1,h2,h3,h4,h5,h6,blockquote,td,th,pre,figcaption')];
   this.fonts=nodes.filter(node=>!node.closest('.readmarker-display,.readmarker-block,[data-readmarker-inline],[data-readmarker-shortcode]')).map(node=>({node,size:parseFloat(this.win.getComputedStyle(node).fontSize),value:node.style.getPropertyValue('font-size'),priority:node.style.getPropertyPriority('font-size')}));
   for(const node of this.article.querySelectorAll('.readmarker-display,.readmarker-block,[data-readmarker-inline],[data-readmarker-shortcode]'))this.fonts.push({node,size:parseFloat(this.win.getComputedStyle(node).fontSize),value:node.style.getPropertyValue('font-size'),priority:node.style.getPropertyPriority('font-size'),fixed:true});
   const articleWidth=parseFloat(this.win.getComputedStyle(this.article).width);
   const parentWidth=this.article.parentElement?parseFloat(this.win.getComputedStyle(this.article.parentElement).width):0;
   this.baseWidth=parentWidth>0&&articleWidth>0?Math.min(100,articleWidth/parentWidth*100):100;
   this.width=this.article.style.getPropertyValue('width');this.widthPriority=this.article.style.getPropertyPriority('width');
   this.article.classList.add('readmarker-reader-article');
   const click=event=>{
    const target=event.target;const button=target&&typeof target.closest==='function'?target.closest('button'):null;if(!button||button.disabled||!this.ui.contains(button))return;
    if(button===this.trigger){const opening=this.panel.hidden;this.open(opening);if(!opening)this.focusTrigger();return;}
    const beforeText=this.preferences.textSize,beforeWidth=this.preferences.readingWidth;
    const reset=button.hasAttribute('data-reader-reset');
    if(reset){this.preferences=defaults();}
    else{const key=button.getAttribute('data-reader-key');if(!Object.prototype.hasOwnProperty.call(limits,key))return;this.preferences[key]=step(this.preferences[key],key,Number(button.getAttribute('data-reader-step')));}
    if(beforeText===this.preferences.textSize&&beforeWidth===this.preferences.readingWidth)return;
    this.apply();save(this.win,this.preferences);
    this.win.document.dispatchEvent(new this.win.CustomEvent('readmarker:content-presentation-changed',{detail:{reason:reset?'reset':'reader-controls'}}));
    this.win.document.dispatchEvent(new this.win.CustomEvent('readflow:content-presentation-changed',{detail:{reason:reset?'reset':'reader-controls'}}));
   };
   const keydown=event=>{if(event.key==='Escape'&&!event.defaultPrevented&&!this.panel.hidden){this.open(false);this.focusTrigger();}};
   this.ui.addEventListener('click',click);this.ui.addEventListener('keydown',keydown);
   this.removers.push(()=>this.ui.removeEventListener('click',click),()=>this.ui.removeEventListener('keydown',keydown));
   this.apply();this.refresh();this.ui.hidden=false;return true;
  }
  focusTrigger(){
   // CSS owns responsive visibility; never focus a control hidden by it.
   if(!this.ui.hidden&&this.win.getComputedStyle(this.ui).display!=='none')this.trigger.focus({preventScroll:true});
  }
  open(value){
   this.panel.hidden=!value;this.trigger.setAttribute('aria-expanded',String(value));
   if(value&&!this.outsideListening){this.win.document.addEventListener('click',this.outsideClick);this.outsideListening=true;}
   else if(!value&&this.outsideListening){this.win.document.removeEventListener('click',this.outsideClick);this.outsideListening=false;}
  }
  apply(){
   if(this.summary){
    const parts=[];
    if(this.preferences.textSize!==100)parts.push((this.summary.getAttribute('data-reader-text-label')||'Text')+' '+this.preferences.textSize+'%');
    if(this.preferences.readingWidth!==100)parts.push((this.summary.getAttribute('data-reader-width-label')||'Width')+' '+this.preferences.readingWidth+'%');
    const text=parts.length?' \u00b7 '+parts.join(' \u00b7 '):'';
    if(this.summary.textContent!==text)this.summary.textContent=text;
   }
   for(const font of this.fonts){if(this.preferences.textSize===100)font.node.style.setProperty('font-size',font.value,font.priority);else if(Number.isFinite(font.size))font.node.style.setProperty('font-size',(font.size*(font.fixed?1:this.preferences.textSize/100))+'px');}
   this.article.style.setProperty('width',this.preferences.readingWidth===100?this.width:Math.min(100,this.baseWidth*this.preferences.readingWidth/100)+'%',this.preferences.readingWidth===100?this.widthPriority:'');
   this.article.classList.toggle('readmarker-reader-width-adjusted',this.preferences.readingWidth!==100);
   for(const key of Object.keys(limits)){
    this.ui.querySelector('[data-reader-value="'+key+'"]').textContent=this.preferences[key]+'%';
    for(const button of this.ui.querySelectorAll('[data-reader-key="'+key+'"]'))button.disabled=Number(button.getAttribute('data-reader-step'))<0?this.preferences[key]===80:this.preferences[key]===limits[key];
   }
  }
  // Initial application/cleanup retain their existing refresh path; user changes signal it.
  refresh(){if(this.win.ReadMarkerProgress&&typeof this.win.ReadMarkerProgress.refresh==='function')this.win.ReadMarkerProgress.refresh();}
  destroy(){
   if(!this.started)return;
   this.removers.splice(0).forEach(remove=>remove());this.preferences=defaults();this.apply();this.refresh();this.open(false);this.ui.hidden=true;this.article.classList.remove('readmarker-reader-article');this.started=false;this.fonts=[];instances.delete(this.win.document);
  }
 }
 function boot(win){
  if(instances.has(win.document))return instances.get(win.document);
  const articles=win.document.querySelectorAll('.readmarker-article-content[data-readmarker-article]');const controls=win.document.querySelectorAll('[data-readmarker-reader-controls]');
  if(articles.length!==1||controls.length!==1)return null;
  const controller=new Controls(win,articles[0],controls[0]);if(!controller.init())return null;instances.set(win.document,controller);return controller;
 }
 return {normalize,step,read,save,boot,Controls};
}));


