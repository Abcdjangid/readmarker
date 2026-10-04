/* Optional local-only position memory. No timers, scroll listeners or networking. */
(function (root, factory) {
	'use strict';
	if (typeof module === 'object' && module.exports) module.exports = factory();
	else root.ReadFlowPositionMemory = factory();
}(typeof window !== 'undefined' ? window : globalThis, function () {
	'use strict';
	const VERSION = 1, MIN_RESTORE_PROGRESS = 0.10, EXPIRATION_MS = 30 * 86400000;
	const dismissedVisits = new WeakSet();
	const SAVE_DELTA = 0.01, SAVE_INTERVAL_MS = 5000;
	const validId = value => /^[1-9]\d*$/.test(String(value)) && Number.isSafeInteger(Number(value));
	const validRatio = value => typeof value === 'number' && Number.isFinite(value) && value >= 0 && value <= 1;
	/** Per-site/article keys preserve independent records and avoid map read/write races.
		* Expiration is inclusive: exactly 30 days is expired. Pruned on article revisit.
		*/
	class Store {
		constructor(storage, site, clock = () => Date.now()) { this.storage=storage; this.site=site; this.clock=clock; this.available=!!storage && validId(site); }
		key(id) { return `readflow_position_v1_${this.site}_${id}`; }
		remove(id) {
			if (!this.available || !validId(id)) return;
			try { this.storage.removeItem(this.key(id)); } catch (_) { this.available=false; }
		}
		read(id) {
			if (!this.available || !validId(id)) return null;
			try {
				const raw=this.storage.getItem(this.key(id)); if(raw===null) return null;
				let record; try { record=JSON.parse(raw); } catch (_) { this.remove(id); return null; }
				const now=this.clock();
				if (!record || record.version!==VERSION || record.articleId!==Number(id) || !validRatio(record.progress) || record.progress===1 || typeof record.updatedAt!=='number' || !Number.isFinite(record.updatedAt) || record.updatedAt<0 || record.updatedAt>now || now-record.updatedAt>=EXPIRATION_MS) { this.remove(id); return null; }
				return Object.freeze({version:VERSION,articleId:Number(id),progress:record.progress,updatedAt:record.updatedAt});
			} catch (_) { this.available=false; return null; }
		}
		save(id, progress) {
			if (!this.available || !validId(id) || !validRatio(progress)) return false;
			if(progress===1) { this.remove(id); return this.available; }
			try { this.storage.setItem(this.key(id),JSON.stringify({version:VERSION,articleId:Number(id),progress,updatedAt:this.clock()})); return true; }
			catch (_) { this.available=false; return false; }
		}
	}
	/** Inverse of existing geometry; no geometry reads here. Short articles target their start. */
	function restorationTarget(ratio, geometry, maxScroll, offsetTop=0) {
		if(!validRatio(ratio) || ![geometry.articleTop,geometry.articleHeight,geometry.viewportHeight,maxScroll,offsetTop].every(Number.isFinite)) return null;
		if(geometry.articleHeight<=0 || geometry.viewportHeight<=0) return null;
		return Math.max(0,Math.min(Math.max(0,maxScroll),geometry.articleTop+ratio*Math.max(0,geometry.articleHeight-geometry.viewportHeight)-offsetTop));
	}
	class Prompt {
		constructor(element,article,win) { this.element=element;this.parent=element.parentNode;this.article=article;this.win=win;this.removers=[]; }
		show(progress, onContinue, onDismiss) {
			const text=this.element.querySelector('[data-readflow-position-text]');
			const proceed=this.element.querySelector('[data-readflow-continue]'),dismiss=this.element.querySelector('[data-readflow-dismiss]');
			if(!text || !proceed || !dismiss) return false;
			text.textContent=(text.getAttribute('data-readflow-template')||'You were at %s%').replace('%s',String(Math.floor(progress*100)));
			const listen=(node,type,fn)=>{node.addEventListener(type,fn);this.removers.push(()=>node.removeEventListener(type,fn));};
			listen(proceed,'click',onContinue);listen(dismiss,'click',onDismiss);
			listen(this.element,'keydown',event=>{if(event.key==='Escape'){event.preventDefault();onDismiss();}});
			this.win.document.body.appendChild(this.element);this.element.hidden=false;
			return true;
		}
		hide() {
			if(this.element.contains(this.win.document.activeElement)) {
				const old=this.article.getAttribute('tabindex');this.article.setAttribute('tabindex','-1');this.article.focus({preventScroll:true});
				if(old===null)this.article.removeAttribute('tabindex');else this.article.setAttribute('tabindex',old);
			}
			this.element.hidden=true;this.removers.splice(0).forEach(remove=>remove());
		}
		destroy(){this.hide();if(this.parent)this.parent.appendChild(this.element);this.article=this.win=this.parent=null;}
	}
	/** One existing progress subscription; pagehide flushing is owned by progress boot.
		* Pending/dismissed prompts preserve the saved position for this visit. Completion
		* always clears it. Writes need >=1 percentage point AND >=5 seconds; no timer.
		*/
	class Controller {
		constructor(engine,article,element,win,clock=()=>Date.now()) {
			this.engine=engine;this.article=article;this.element=element;this.win=win;this.clock=clock;
			this.unsubscribe=null;this.destroyed=false;this.latest=null;this.lastProgress=null;this.lastWrite=0;this.hold=false;
		}
		init() {
			if(this.unsubscribe)return true;if(this.destroyed)return false;
			this.id=this.article.getAttribute('data-readflow-article');
			if(!validId(this.id))return false;
			let storage;try{storage=this.win.localStorage;}catch(_){return false;}
			this.store=new Store(storage,this.element.getAttribute('data-readflow-site'),this.clock);
			const saved=this.store.read(this.id);if(!this.store.available)return false;
			this.prompt=new Prompt(this.element,this.article,this.win);
			if(saved && saved.progress>=MIN_RESTORE_PROGRESS) {
				this.saved=saved.progress;this.hold=true;
				if(!dismissedVisits.has(this.element))this.prompt.show(this.saved,()=>this.resume(),()=>{this.prompt.hide();this.hold=true;dismissedVisits.add(this.element);});
			}
			this.unsubscribe=this.engine.subscribe(state=>this.update(state));return true;
		}
		update(state) {
			if(this.destroyed || !this.store.available)return;
			this.latest=state;
			if(state.completion.isFinished){if(!this.cleared)this.store.remove(this.id);this.cleared=true;this.prompt.hide();this.hold=false;this.lastProgress=null;return;}
			this.cleared=false;
			if(!this.hold && (state.ratio>=MIN_RESTORE_PROGRESS || this.lastProgress!==null) && (this.lastProgress===null || Math.abs(state.ratio-this.lastProgress)+Number.EPSILON>=SAVE_DELTA) && this.clock()-this.lastWrite>=SAVE_INTERVAL_MS)this.persist();
		}
		persist() {
			if(this.store.save(this.id,this.latest.ratio)){this.lastProgress=this.latest.ratio;this.lastWrite=this.clock();}
			else this.prompt.hide();
		}
		flush(){if(!this.destroyed && this.store && this.store.available && this.latest && !this.hold && !this.latest.completion.isFinished && (this.latest.ratio>=MIN_RESTORE_PROGRESS || this.lastProgress!==null) && this.latest.ratio!==this.lastProgress)this.persist();}
		resume() {
			if(this.destroyed || !this.article.isConnected)return;
			this.prompt.hide();this.hold=false;this.lastWrite=this.clock();
			const rect=this.article.getBoundingClientRect(),viewport=this.win.visualViewport;
			const geometry={articleTop:rect.top+this.win.scrollY,articleHeight:rect.height,viewportHeight:viewport?viewport.height:this.win.innerHeight};
			const maxScroll=Math.max(this.win.document.documentElement.scrollHeight,this.win.document.body.scrollHeight)-this.win.innerHeight;
			const target=restorationTarget(this.saved,geometry,maxScroll,viewport?viewport.offsetTop:0);
			if(target!==null){this.win.scrollTo({top:target,behavior:this.win.matchMedia('(prefers-reduced-motion: reduce)').matches?'instant':'smooth'});this.engine.refresh();}
		}
		destroy(){if(this.destroyed)return;if(this.unsubscribe)this.unsubscribe();if(this.prompt)this.prompt.destroy();this.destroyed=true;this.unsubscribe=this.engine=this.article=this.win=this.element=this.prompt=this.store=this.latest=this.clock=null;}
	}
	return {Store,Prompt,Controller,restorationTarget,VERSION,MIN_RESTORE_PROGRESS,EXPIRATION_MS,SAVE_DELTA,SAVE_INTERVAL_MS};
}));
