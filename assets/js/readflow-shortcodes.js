/* Local clipboard interaction only; no shortcode execution. */
(function(root,factory){
 'use strict';
 if(typeof module==='object'&&module.exports)module.exports=factory();
 else factory().init(root);
}(typeof window!=='undefined'?window:null,function(){
 'use strict';
 function init(win){
  if(!win||!win.document)return;
  win.document.querySelectorAll('.readflow-shortcodes [data-readflow-copy]').forEach(button=>{
   if(button.dataset.readflowCopyBound)return;
   const code=button.parentNode.querySelector('code');if(!code)return;
   button.dataset.readflowCopyBound='true';button.hidden=false;
   const originalName=button.getAttribute('aria-label');let timer=null,busy=false;
   button.addEventListener('click',async()=>{
    if(busy)return;busy=true;
    if(timer!==null){win.clearTimeout(timer);timer=null;}
    let copied=false;
    try{await win.navigator.clipboard.writeText(code.textContent);copied=true;}catch(_){
     // Manual selection is the fallback: no deprecated copy command or hidden input.
     try{const selection=win.getSelection();const range=win.document.createRange();range.selectNodeContents(code);selection.removeAllRanges();selection.addRange(range);}catch(_){/* Visible code remains available for manual copying. */}
    }
    const label=button.getAttribute(copied?'data-copied-label':'data-failed-label');
    button.textContent=label;button.setAttribute('aria-label',copied?label+' � '+code.textContent:label);
    busy=false;
    timer=win.setTimeout(()=>{button.textContent=button.getAttribute('data-copy-label');button.setAttribute('aria-label',originalName);timer=null;},2000);
   });
  });
 }
 return {init};
}));
