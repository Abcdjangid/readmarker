'use strict';
// No packages or browser downloads. Geometry/lifecycle tests with explicit DOM doubles.
const assert = require('node:assert/strict');
const { calculate, Engine, TopBar, Circular, Percentage, DisplayManager, boot } = require('../assets/js/readflow-progress.js');
let checks = 0;
function check(value, message) { assert.ok(value, message); checks++; }
const geometry = { articleTop: 300, articleHeight: 1800, viewportHeight: 600 };
for (const [scrollPosition, ratio] of [[300, 0], [900, 0.5], [1500, 1], [-100, 0], [9999, 1]]) {
	const state = calculate({ ...geometry, scrollPosition });
	check(state.ratio === ratio && state.percentage === ratio * 100, `progress at ${scrollPosition}`);
	check(Object.isFrozen(state), 'immutable state');
}
for (const viewportHeight of [320, 768, 1080]) {
	check(calculate({ ...geometry, viewportHeight, scrollPosition: 300 + (1800 - viewportHeight) / 2 }).percentage === 50, 'responsive viewport midpoint');
}
check(calculate({ ...geometry, articleHeight: 0 }).ratio === 0, 'zero height');
check(calculate({ ...geometry, viewportHeight: 0 }).ratio === 0, 'zero viewport');
check(calculate({ ...geometry, articleHeight: 200, scrollPosition: -200 }).ratio === 0, 'short article not reached');
check(calculate({ ...geometry, articleHeight: 200, scrollPosition: 0 }).ratio === 1, 'short article fully visible');
check(calculate({ ...geometry, articleHeight: 600, scrollPosition: 300 }).ratio === 1, 'equal-height article reached');
for (const field of Object.keys(geometry).concat('scrollPosition')) {
	for (const value of [NaN, Infinity, -Infinity, 'bad']) {
		const state = calculate({ ...geometry, [field]: value });
		check(['ratio', 'percentage', 'articleTop', 'articleHeight', 'viewportHeight', 'scrollPosition'].every(key => Number.isFinite(state[key])) && state.ratio === 0, `safe invalid ${field}`);
	}
}

class Target {
	constructor() { this.events = new Map(); }
	addEventListener(name, fn) { if (!this.events.has(name)) this.events.set(name, new Set()); this.events.get(name).add(fn); }
	removeEventListener(name, fn) { this.events.get(name)?.delete(fn); }
	dispatchEvent(event) { this.events.get(event.type)?.forEach(fn => fn(event)); }
	count() { return [...this.events.values()].reduce((sum, set) => sum + set.size, 0); }
}
function environment() {
	const win = new Target();
	win.scrollY = 300;
	win.innerHeight = 600;
	win.document = new Target();
	win.document.fonts = new Target();
	win.document.body = { appendChild() {} };
	win.document.getElementById = () => null;
	win.visualViewport = new Target();
	win.visualViewport.height = 600;
	win.visualViewport.offsetTop = 0;
	let counter = 0;
	const frames = new Map();
	win.requestAnimationFrame = fn => { frames.set(++counter, fn); return counter; };
	win.cancelAnimationFrame = id => frames.delete(id);
	win.flush = () => { const work = [...frames.values()]; frames.clear(); work.forEach(fn => fn()); };
	win.pending = () => frames.size;
	win.CustomEvent = class { constructor(type, options) { this.type = type; Object.assign(this, options); } };
	win.ResizeObserver = class {
		constructor(fn) { this.fn = fn; this.targets = []; this.disconnected = false; }
		observe(target) { this.targets.push(target); }
		disconnect() { this.disconnected = true; }
	};
	const article = new Target();
	article.isConnected = true;
	article.height = 1800;
	article.top = 300;
	article.reads = 0;
	article.getBoundingClientRect = () => { article.reads++; return { top: article.top - win.scrollY, height: article.height }; };
	return { win, article };
}
const { win, article } = environment();
check(!new Engine(null, win).init(), 'missing article fails closed');
const engine = new Engine(article, win);
let notifications = 0;
let detail = null;
article.addEventListener('readflow:progress', event => { detail = event.detail; });
const unsubscribe = engine.subscribe(() => notifications++);
check(engine.init() && engine.init(), 'initialization idempotent');
check(win.count() === 2 && engine.observer.targets.length === 2, 'single scroll/resize and article/body observer');
for (let i = 0; i < 50; i++) win.dispatchEvent({ type: 'scroll' });
check(win.pending() === 1, 'scroll burst coalesced');
win.flush();
check(article.reads === 1 && notifications === 1 && detail === engine.state, 'one read and shared event snapshot');
win.scrollY = 900;
win.dispatchEvent({ type: 'scroll' }); win.flush();
check(engine.state.percentage === 50, 'scroll updates progress');
engine.refresh(); win.flush();
check(notifications === 2, 'unchanged state does not notify');
article.height = 3000;
engine.observer.fn(); win.flush();
check(engine.state.percentage === 25, 'dynamic article height recalculation');
article.top = 600;
win.document.dispatchEvent({ type: 'load' }); win.flush();
check(engine.state.articleTop === 600 && engine.state.ratio === 0.125, 'late image/upstream layout shift');
win.visualViewport.height = 400;
win.visualViewport.offsetTop = 50;
win.visualViewport.dispatchEvent({ type: 'resize' }); win.flush();
check(engine.state.viewportHeight === 400 && engine.state.scrollPosition === 950, 'zoom/visual viewport geometry');
const before = notifications;
unsubscribe();
win.scrollY = 1000; engine.refresh(); win.flush();
check(notifications === before, 'unsubscribe');
let immediate = null;
engine.subscribe(state => { immediate = state; });
check(immediate === engine.state, 'new subscriber gets current state');
engine.refresh(); engine.destroy();
check(win.pending() === 0 && win.count() === 0 && win.document.count() === 0 && win.visualViewport.count() === 0 && win.document.fonts.count() === 0 && engine.observer.disconnected, 'cleanup removes all resources');
engine.refresh(); check(win.pending() === 0, 'destroyed engine cannot schedule');

const detached = environment();
const detachedEngine = new Engine(detached.article, detached.win);
detachedEngine.init(); detached.win.flush();
let hiddenState;
detachedEngine.subscribe(state => { hiddenState = state; });
detached.article.isConnected = false;
detachedEngine.refresh(); detached.win.flush();
check(!detachedEngine.running && hiddenState.articleHeight === 0, 'removed article stops engine and hides consumers');
const fallback = environment();
delete fallback.win.ResizeObserver;
delete fallback.win.visualViewport;
const fallbackEngine = new Engine(fallback.article, fallback.win);
check(fallbackEngine.init(), 'no ResizeObserver fallback');
fallback.win.flush();
fallback.article.height = 0;
fallback.win.dispatchEvent({ type: 'resize' }); fallback.win.flush();
check(fallbackEngine.state.ratio === 0, 'zero-height resize safe');
fallbackEngine.destroy();

const page = environment();
let writes = 0;
const fill = { style: { set transform(value) { this.value = value; writes++; }, get transform() { return this.value; } } };
const barElement = { hidden: true, style: { setProperty(name, value) { this[name] = value; } }, querySelector: () => fill };
page.win.document.getElementById = () => ({ getBoundingClientRect: () => ({ bottom: 32 }) });
page.win.document.querySelectorAll = query => query.includes('article-content') ? [page.article] : [barElement];
const session = boot(page.win);
check(session === boot(page.win), 'page boot idempotent');
page.win.flush();
check(!barElement.hidden && barElement.style['--readflow-progress-offset'] === '32px', 'consumer offsets below admin bar');
check(fill.style.transform === 'scaleX(0)', 'consumer initial scale');
page.win.scrollY = 900; session.engine.refresh(); page.win.flush();
check(fill.style.transform === 'scaleX(0.5)', 'bar subscribes to engine');
const previousWrites = writes;
session.engine.refresh(); page.win.flush();
check(writes === previousWrites, 'no unnecessary DOM writes');
page.win.dispatchEvent({ type: 'pagehide' });
check(barElement.hidden && page.win.count() === 0, 'pagehide cleanup');
const restored = boot(page.win); page.win.flush();
check(restored && restored !== session && !barElement.hidden, 'page can restart after bfcache teardown');
restored.destroy();
page.win.document.querySelectorAll = () => [];
check(boot(page.win) === null, 'no marker means no initialization');
page.win.document.querySelectorAll = () => [page.article, page.article];
check(boot(page.win) === null, 'ambiguous markers fail closed');
function displayRoot(mode) {
	const elements = {};
	for (const name of ['top_bar', 'circular', 'percentage']) {
		const value = { textContent: '' };
		const stroke = { style: {} };
		const fill = { style: {} };
		elements[name] = {
			hidden: true, style: { setProperty(key, value) { this[key] = value; } }, attributes: {}, value, stroke, fill,
			setAttribute(key, value) { this.attributes[key] = value; },
			querySelector(query) { return { '.readflow-progress-value': value, '.readflow-progress-stroke': stroke, '.readflow-progress-fill': fill }[query]; }
		};
	}
	const root = { elements, restored: [], getAttribute: () => mode, appendChild(element) { this.restored.push(element); }, querySelector(query) { return elements[query.match(/="([^"]+)"/)[1]] || null; } };
	return root;
}
for (const [mode, types] of Object.entries({ top_bar: [TopBar], circular: [Circular], percentage: [Percentage], top_bar_percentage: [TopBar, Percentage] })) {
	const context = environment();
	context.win.scrollY = 720; // 35% restored scroll position before any user scroll.
	const source = new Engine(context.article, context.win);
	source.init(); context.win.flush();
	const root = displayRoot(mode);
	const manager = new DisplayManager(root, context.win);
	check(manager.mount(source), `${mode} mounts`);
	check(manager.consumers.length === types.length && manager.consumers.every((consumer, i) => consumer.constructor === types[i]), `${mode} consumer selection`);
	check(manager.mount(source) && source.listeners.size === types.length, `${mode} no duplicate subscriptions`);
	check(context.win.events.get('scroll').size === 1 && context.win.pending() === 0, `${mode} no extra listeners/scheduler`);
	for (const consumer of manager.consumers) {
		check(!consumer.element.hidden, `${mode} initial state visible immediately`);
		check(consumer instanceof TopBar ? consumer.fill.style.transform === 'scaleX(0.35)' : consumer.value.textContent === '35%', `${mode} initial 35% without scroll`);
	}
	for (const percentage of [0, 25, 50, 75, 100]) {
		context.win.scrollY = 300 + 1200 * percentage / 100;
		const reads = context.article.reads;
		const delivered = [];
		const unsubscribers = manager.consumers.map(() => source.subscribe(state => delivered.push(state)));
		delivered.length = 0;
		for (let i = 0; i < 10; i++) context.win.dispatchEvent({ type: 'scroll' });
		check(context.win.pending() === 1, `${mode} one RAF at ${percentage}`);
		context.win.flush();
		check(context.article.reads === reads + 1, `${mode} one geometry calculation at ${percentage}`);
		check(delivered.length === types.length && delivered.every(state => state === source.state), `${mode} identical immutable snapshots`);
		unsubscribers.forEach(unsubscribe => unsubscribe());
		for (const consumer of manager.consumers) {
			if (consumer instanceof TopBar) {
				check(consumer.fill.style.transform === `scaleX(${percentage / 100})`, `bar at ${percentage}`);
			} else {
				check(consumer.value.textContent === `${percentage}%` && consumer.element.attributes['aria-valuenow'] === String(percentage), `visible and accessible value at ${percentage}`);
				if (consumer instanceof Circular) check(consumer.stroke.style.strokeDashoffset === String(100 - percentage), `circle stroke at ${percentage}`);
			}
		}
	}
	const nodes = manager.consumers.map(consumer => consumer.element);
	manager.destroy(); manager.destroy();
	check(source.listeners.size === 0 && manager.consumers.length === 0 && nodes.every(node => node.hidden), `${mode} manager cleanup`);
	check(root.restored.length === types.length, `${mode} nodes restored for bfcache`);
	check(source.running, 'manager does not own or duplicate engine');
	source.destroy();
	check(context.win.count() === 0 && context.win.pending() === 0, `${mode} full cleanup`);
}
const invalidMode = environment();
const invalidEngine = new Engine(invalidMode.article, invalidMode.win);
invalidEngine.init(); invalidMode.win.flush();
for (const mode of ['bad', 'constructor', '__proto__']) {
	const manager = new DisplayManager(displayRoot(mode), invalidMode.win);
	check(manager.mount(invalidEngine) && manager.consumers[0] instanceof TopBar, 'JS unknown mode falls back safely');
	manager.destroy();
}
const incomplete = displayRoot('top_bar_percentage');
delete incomplete.elements.percentage;
const incompleteManager = new DisplayManager(incomplete, invalidMode.win);
check(incompleteManager.mount(invalidEngine) && invalidEngine.listeners.size === 1 && !incomplete.elements.top_bar.hidden, 'Missing percentage keeps healthy top bar subscribed');
incompleteManager.destroy();check(invalidEngine.listeners.size===0,'Partial consumer cleanup removes owned subscription');
invalidEngine.destroy();
const remainingContext = environment();
remainingContext.win.scrollY = 780; // 40%.
const remainingEngine = new Engine(remainingContext.article, remainingContext.win);
remainingEngine.init(); remainingContext.win.flush();
const remainingRoot = displayRoot('top_bar_percentage');
remainingRoot.getAttribute = name => name === 'data-readflow-total-seconds' ? '600.5' : 'top_bar_percentage';
const remainingManager = new DisplayManager(remainingRoot, remainingContext.win);
remainingManager.mount(remainingEngine);
let remainingSnapshot;
remainingManager.subscribeRemaining(state => { remainingSnapshot = state; });
check(Math.abs(remainingSnapshot.remaining_seconds - 360.3) < 1e-10, 'manager immediately derives remaining duration from current progress');
check(remainingManager.connectRemaining() === remainingManager.remaining && remainingEngine.listeners.size === 3, 'manager shares one remaining layer plus two displays');
const remainingReads = remainingContext.article.reads;
remainingContext.win.scrollY = 900;
remainingEngine.refresh(); remainingContext.win.flush();
check(remainingSnapshot.remaining_seconds === 300.25 && remainingContext.article.reads === remainingReads + 1, 'remaining layer adds no geometry calculation');
check(remainingContext.win.events.get('scroll').size === 1, 'remaining layer adds no scroll listener');
remainingManager.destroy();
check(remainingEngine.listeners.size === 0 && remainingManager.remaining === null, 'manager destroys remaining subscription');
remainingEngine.destroy();
for (const mode of ['remaining', 'time_remaining', 'percentage_remaining', 'countdown']) {
	for (const initialRatio of [0, 0.5, 1]) {
		const context = environment();
		context.win.scrollY = 300 + 1200 * initialRatio;
		const source = new Engine(context.article, context.win);
		source.init(); context.win.flush();
		let textWrites = 0;
		const textNode = () => ({ hidden: true, text: '', get textContent() { return this.text; }, set textContent(text) { this.text = text; textWrites++; } });
		const value = textNode(), prefix = textNode(), checkmark = textNode();
		const element = {
			hidden: true,
			getAttribute(name) { return name === 'data-readflow-total-label' ? '10 min read' : '{}'; },
			querySelector(name) { return { '.readflow-duration-value': value, '.readflow-duration-prefix': prefix, '.readflow-duration-check': checkmark }[name]; }
		};
		const root = { getAttribute(name) { return name === 'data-readflow-mode' ? mode : '600'; }, querySelector() { return element; }, appendChild() {} };
		const manager = new DisplayManager(root, context.win);
		check(manager.mount(source), `${mode} mount at ${initialRatio}`);
		const verify = ratio => {
			const expected = ratio === 1 ? 'Finished' : mode === 'countdown' ? (ratio === 0 ? '10:00' : '05:00') : (ratio === 0 ? '10 min remaining' : '5 min remaining');
			check(value.textContent === expected && !element.hidden, `${mode} correct visible state ${ratio}`);
			check(checkmark.hidden === (ratio !== 1), `${mode} completion checkmark ${ratio}`);
			const expectedPrefix = mode === 'time_remaining' ? '10 min read · ' : mode === 'percentage_remaining' ? `${ratio * 100}% · ` : '';
			check(prefix.textContent === expectedPrefix, `${mode} composed data ${ratio}`);
		};
		verify(initialRatio);
		check(manager.remaining.listeners.size === 1 && source.listeners.size === 2 && context.win.events.get('scroll').size === 1, 'one derived subscription and no added scroll listener');
		for (const ratio of [0, 0.5, 1, 0.5]) {
			context.win.scrollY = 300 + 1200 * ratio;
			source.refresh(); context.win.flush(); verify(ratio);
			const beforeWrites = textWrites;
			source.refresh(); context.win.flush();
			check(textWrites === beforeWrites && context.win.pending() === 0, 'no duplicate writes or countdown animation loop');
		}
		context.article.height = 0;
		source.refresh(); context.win.flush();
		check(element.hidden, 'duration display hides for invalid geometry');
		manager.destroy(); manager.destroy();
		check(element.hidden && source.listeners.size === 0 && manager.remaining === null, 'duration consumer cleanup');
		source.destroy();
		check(context.win.count() === 0 && context.win.pending() === 0, 'no lifecycle resources left');
	}
}

// Floating widget uses the same source and derived layer as the duration modes.
for (const initialRatio of [0, 0.5, 1]) {
	const context = environment();
	context.win.scrollY = 300 + 1200 * initialRatio;
	const source = new Engine(context.article, context.win);
	source.init(); context.win.flush();
	const writes = { percentage: 0, remaining: 0, fill: 0, aria: 0 };
	const textNode = key => ({ hidden: true, text: '', get textContent() { return this.text; }, set textContent(value) { this.text = value; if (key) writes[key]++; } });
	const value = textNode('remaining'), prefix = textNode(), checkmark = textNode(), percentage = textNode('percentage');
	const progress = { attributes: {}, setAttribute(name, value) { this.attributes[name] = value; writes.aria++; } };
	const fill = { style: { value: '', get transform() { return this.value; }, set transform(value) { this.value = value; writes.fill++; } } };
	const nodes = { '.readflow-duration-value': value, '.readflow-duration-prefix': prefix, '.readflow-duration-check': checkmark, '.readflow-floating-widget__percentage': percentage, '.readflow-floating-widget__progress': progress, '.readflow-floating-widget__progress-fill': fill };
	const element = { hidden: true, getAttribute() { return '{}'; }, querySelector(name) { return nodes[name]; } };
	let restored = 0;
	const root = { getAttribute(name) { return name === 'data-readflow-mode' ? 'floating_widget' : '600'; }, querySelector() { return element; }, appendChild() { restored++; } };
	const manager = new DisplayManager(root, context.win);
	check(manager.mount(source), 'widget mounts');
	const consumer = manager.consumers[0];
	check(consumer.constructor.name === 'FloatingWidget', 'manager chooses widget consumer');
	const verify = ratio => {
		check(!element.hidden && percentage.textContent === `${ratio * 100}%`, 'widget percentage immediately correct');
		check(progress.attributes['aria-valuenow'] === String(ratio * 100), 'widget accessible value');
		check(fill.style.transform === `scaleX(${ratio})`, 'widget transform from shared ratio');
		const expected = { 0: '10 min remaining', 0.5: '5 min remaining', 0.75: '2 min 30 sec remaining', 1: 'Finished', 0.8: '2 min remaining' }[ratio];
		check(value.textContent === expected, 'widget shared remaining formatter/completion');
		check(checkmark.hidden === (ratio !== 1), 'widget decorative completion checkmark');
	};
	verify(initialRatio);
	check(manager.mount(source) && consumer.mount(source, manager) && manager.consumers.length === 1, 'widget repeated initialization is idempotent');
	check(manager.remaining.listeners.size === 1 && source.listeners.size === 2, 'one remaining upstream plus existing visibility subscriber');
	check(context.win.events.get('scroll').size === 1 && context.win.pending() === 0, 'widget has no extra scroll listener or scheduler');
	for (const ratio of [0, 0.5, 0.75, 1, 0.8]) {
		context.win.scrollY = 300 + 1200 * ratio;
		const reads = context.article.reads;
		source.refresh(); context.win.flush(); verify(ratio);
		check(context.article.reads === reads + 1, 'widget does not measure geometry');
		const previous = JSON.stringify(writes);
		consumer.update(manager.remaining.state, source.state);
		source.refresh(); context.win.flush();
		check(JSON.stringify(writes) === previous, 'unchanged values cause no text, fill or ARIA writes');
	}
	// A sub-second change leaves both visible strings unchanged, but updates the fill.
	context.win.scrollY = 300 + 1200 * 0.8001;
	const previousText = [writes.percentage, writes.remaining];
	source.refresh(); context.win.flush();
	check(writes.percentage === previousText[0] && writes.remaining === previousText[1], 'same formatted values avoid writes even when ratio changes');
	const lastState = manager.remaining.state, lastProgress = source.state;
	manager.destroy(); manager.destroy();
	check(source.listeners.size === 0 && manager.remaining === null && restored === 1 && element.hidden, 'widget clean destruction and one restored node');
	const previous = JSON.stringify(writes);
	consumer.update(lastState, lastProgress);
	context.win.scrollY = 300; source.refresh(); context.win.flush();
	check(JSON.stringify(writes) === previous && !consumer.mount(source, manager), 'destroyed consumer never updates or resubscribes');
	check(consumer.fill === null && consumer.value === null && consumer.unsubscribe === null, 'widget releases subscription and child references');
	check(manager.mount(source), 'manager can recreate widget after cleanup');
	verify(0);
	manager.destroy(); source.destroy();
	check(context.win.count() === 0 && context.win.pending() === 0, 'widget leaves no listeners or animation frames');
}
const widgetSource = require('node:fs').readFileSync(require.resolve('../assets/js/readflow-progress.js'), 'utf8').split('class FloatingWidget')[1].split('class DisplayManager')[0];
check(!/setInterval|setTimeout|requestAnimationFrame|addEventListener|getBoundingClientRect/.test(widgetSource), 'widget contains no timer, listener, scheduler or geometry reads');

const { EstimatedFinishTime } = require('../assets/js/readflow-progress.js');
const originalNow = Date.now;
try {
 for (const initial of [0, 0.5, 1]) {
  let now = new Date(2026,0,15,16,15).getTime(), sampledNow = now, clockReads = 0, writes = 0;
  Date.now = () => { clockReads++; sampledNow=now; return now; };
  const context = environment(); context.win.scrollY = 300 + 1200 * initial;
  const source = new Engine(context.article, context.win); source.init(); context.win.flush();
  const value = { text: '', get textContent() { return this.text; }, set textContent(text) { this.text=text; writes++; } };
  const prefix = { hidden:true, textContent:'' }, mark = { hidden:true };
  const element = { hidden:true, getAttribute() { return '{}'; }, querySelector(name) { return { '.readflow-duration-value':value, '.readflow-duration-prefix':prefix, '.readflow-duration-check':mark }[name]; } };
  const root = { getAttribute(name) { return name === 'data-readflow-mode' ? 'estimated_finish_time' : '600'; }, querySelector() { return element; }, appendChild() {} };
  const manager = new DisplayManager(root,context.win); manager.mount(source);
  const consumer = manager.consumers[0];
  check(consumer instanceof EstimatedFinishTime, 'manager selects finish consumer');
  const verify = ratio => {
   const expected = ratio === 1 ? 'Finished' : 'Finish around ' + require('../assets/js/readflow-remaining-time.js').formatFinishTime(600*(1-ratio),sampledNow);
   check(value.textContent === expected && !element.hidden, 'finish correct immediate/current display');
   check(mark.hidden === (ratio !== 1), 'finish completion checkmark and backwards restoration');
  };
  verify(initial);
  check(manager.mount(source) && consumer.mount(source,manager) && source.listeners.size === 2 && manager.remaining.listeners.size === 1, 'finish idempotent shared subscriptions');
  for (const ratio of [0,0.5,0.8,1,0.8]) {
   now += 60000;
   const beforeClock = clockReads;
   context.win.scrollY = 300 + 1200*ratio; source.refresh(); context.win.flush(); verify(ratio);
   if(ratio===1) check(clockReads===beforeClock, 'completion does not read clock or show timestamp');
   const beforeWrites=writes, reads=clockReads;
   now += 60000;
   source.refresh(); context.win.flush(); consumer.update(manager.remaining.state,source.state);
   check(writes===beforeWrites && clockReads===reads, 'unchanged progress does not poll clock or rewrite DOM');
  }
  const beforeWrites=writes;
  context.win.scrollY += 0.01; source.refresh(); context.win.flush();
  const formattedWrites=writes;
  context.win.scrollY += 0.01; source.refresh(); context.win.flush();
  check(writes===formattedWrites && writes>=beforeWrites, 'same formatted time avoids DOM write');
  check(context.win.events.get('scroll').size===1 && context.win.pending()===0, 'finish no new scroll or scheduler');
  const state=manager.remaining.state, progress=source.state;
  manager.destroy(); manager.destroy(); const finalWrites=writes;
  consumer.update(state,progress);
  check(source.listeners.size===0 && writes===finalWrites && consumer.clock===null && !consumer.mount(source,manager), 'finish cleanup releases references and prevents updates');
  source.destroy(); check(context.win.count()===0, 'finish lifecycle fully cleaned up');
 }
} finally { Date.now=originalNow; }
const finishSource=require('node:fs').readFileSync(require.resolve('../assets/js/readflow-progress.js'),'utf8').split('class EstimatedFinishTime')[1].split('class FloatingWidget')[0];
check(!/setInterval|setTimeout|requestAnimationFrame|addEventListener|getBoundingClientRect|MutationObserver/.test(finishSource),'finish has no timers, polling, listeners or geometry');

const { calculateMilestones, MilestoneDisplay } = require('../assets/js/readflow-progress.js');
for (const ratio of [0,0.1,0.249,0.25,0.251,0.49,0.499,0.5,0.501,0.51,0.6,0.749,0.75,0.751,0.9,0.99,0.999,1]) {
 const state=calculateMilestones(ratio);
 const expected=[0.25,0.5,0.75,1].filter(value=>value<=ratio);
 check(state.progress===ratio && state.currentMilestone===(expected.length?expected[expected.length-1]:null), 'milestone current boundary '+ratio);
 check(state.nextMilestone===([0.25,0.5,0.75,1].find(value=>value>ratio)??null), 'milestone next boundary');
 check(JSON.stringify(state.reachedMilestones)===JSON.stringify(expected), 'milestone reached snapshot');
 check(state.direction==='stationary' && state.crossedMilestones.length===0, 'initial snapshot does not invent crossing history');
 check(Object.isFrozen(state) && Object.isFrozen(state.reachedMilestones) && Object.isFrozen(state.crossedMilestones) && Object.isFrozen(state.lostMilestones), 'milestone deeply immutable arrays');
}
for (const [before,after,current,crossed,lost] of [[0.1,0.3,0.25,[0.25],[]],[0.3,0.6,0.5,[0.5],[]],[0.6,0.8,0.75,[0.75],[]],[0.8,1,1,[1],[]],[1,0.8,0.75,[],[1]],[0.8,0.4,0.25,[],[0.5,0.75]],[0.4,0.1,null,[],[0.25]],[0.1,0.3,0.25,[0.25],[]],[0,1,1,[0.25,0.5,0.75,1],[]],[1,0,null,[],[0.25,0.5,0.75,1]]]) {
 const state=calculateMilestones(after,before);
 check(state.currentMilestone===current, 'forward/backward current milestone');
 check(JSON.stringify(state.crossedMilestones)===JSON.stringify(crossed) && JSON.stringify(state.lostMilestones)===JSON.stringify(lost), 'all crossed/lost thresholds including jumps and rereaching');
 check(state.direction===(after>before?'forward':'backward'), 'direction');
}
for (const invalid of [NaN,Infinity,-Infinity,undefined,null,{},'0.5',-1]) check(calculateMilestones(invalid).progress===0, 'invalid milestone input safe');
check(calculateMilestones(2).currentMilestone===1, 'milestone clamps over-completion');
check(calculateMilestones(0.5,0.5).crossedMilestones.length===0,'same boundary not recrossed');
for (const initial of [0,0.1,0.25,0.5,0.63,0.75,1]) {
 const context=environment(); context.win.scrollY=300+1200*initial;
 const source=new Engine(context.article,context.win); source.init(); context.win.flush();
 const writes={label:0,value:0};
 const node=key=>({text:'',get textContent(){return this.text;},set textContent(value){this.text=value;writes[key]++;}});
 const label=node('label'),value=node('value');
 const element={hidden:true,getAttribute(){return '{}';},querySelector(name){return name==='.readflow-milestone__label'?label:value;}};
 let restored=0;
 const root={getAttribute(){return 'reading_milestones';},querySelector(){return element;},appendChild(){restored++;}};
 const manager=new DisplayManager(root,context.win); check(manager.mount(source),'milestone manager mount');
 const consumer=manager.consumers[0]; check(consumer instanceof MilestoneDisplay,'milestone consumer selected');
 const verify=ratio=>{
  check(label.textContent===(ratio===1?'Finished':ratio>=0.75?'Almost there':ratio>=0.5?'Halfway there':'Getting started'),'milestone label current and reversible');
  check(value.textContent===`${Math.floor(ratio*100)}%` && !element.hidden,'actual percentage immediately displayed');
 };
 verify(initial);
 check(manager.mount(source) && consumer.mount(source) && source.listeners.size===1,'milestone idempotent single subscription');
 for(const ratio of [0.1,0.3,0.6,0.8,1,0.8,0.4,0.1,0.3]) {
  context.win.scrollY=300+1200*ratio; const reads=context.article.reads; source.refresh();context.win.flush();verify(ratio);
  check(context.article.reads===reads+1,'milestone adds no geometry reads');
  const before=JSON.stringify(writes); consumer.update(source.state); source.refresh();context.win.flush();
  check(JSON.stringify(writes)===before,'unchanged milestone display has no DOM writes');
 }
 context.win.scrollY=300+1200*0.501;source.refresh();context.win.flush(); const before=JSON.stringify(writes);
 for(const ratio of [0.502,0.503]) {context.win.scrollY=300+1200*ratio;source.refresh();context.win.flush();}
 check(JSON.stringify(writes)===before,'unchanged label and rounded percentage are not rewritten');
 check(context.win.events.get('scroll').size===1 && context.win.pending()===0,'milestone no additional listener or scheduler');
 const finalState=source.state; manager.destroy();manager.destroy();const lastWrites=JSON.stringify(writes);consumer.update(finalState);
 check(source.listeners.size===0 && restored===1 && element.hidden && JSON.stringify(writes)===lastWrites,'milestone cleanup prevents updates');
 check(consumer.label===null && consumer.state===null && !consumer.mount(source),'milestone references cleared and destroyed mount rejected');
 check(manager.mount(source),'milestone clean manager restart');manager.destroy();source.destroy();
 check(context.win.count()===0,'milestone full cleanup');
}
const milestoneSource=require('node:fs').readFileSync(require.resolve('../assets/js/readflow-progress.js'),'utf8').split('const milestoneThresholds')[1].split('class DisplayManager')[0];
check(!/setInterval|setTimeout|requestAnimationFrame|addEventListener|getBoundingClientRect|MutationObserver/.test(milestoneSource),'milestones have no timers, listeners, polling or geometry');

const { calculateCompletionState } = require('../assets/js/readflow-progress.js');
const completionCases=[[-1,'not_started'],[0,'not_started'],[0.0001,'reading'],[0.5,'reading'],[0.749,'reading'],[0.75,'almost_finished'],[0.7501,'almost_finished'],[0.751,'almost_finished'],[0.99,'almost_finished'],[1,'finished'],[1.0001,'finished'],[NaN,'not_started'],[Infinity,'not_started'],[-Infinity,'not_started'],[undefined,'not_started'],[null,'not_started'],['0.75','not_started']];
for(const [ratio,status] of completionCases) {
 const state=calculateCompletionState(ratio);
 check(state.status===status,'completion normalized boundary '+String(ratio));
 check(state.isStarted===(status!=='not_started') && state.isAlmostFinished===(status==='almost_finished'||status==='finished') && state.isFinished===(status==='finished'),'completion flags');
 check(Object.isFrozen(state),'immutable completion');
 check(require('../assets/js/readflow-remaining-time.js').calculate(600,ratio).completed===state.isFinished,'remaining completion uses shared policy');
}
for(const initial of [0,0.4,0.8,1]) {
 const context=environment();context.win.scrollY=300+1200*initial;
 const source=new Engine(context.article,context.win);source.init();context.win.flush();
 check(source.state.completion===calculateCompletionState(initial),'engine publishes shared completion object');
 check(!source.state.completionTransition.changed && source.state.completionTransition.previous===source.state.completion.status,'initial no invented transition');
 check(Object.isFrozen(source.state.completionTransition),'immutable transition');
 let previous=source.state.completion.status;
 for(const ratio of [0,0,0.1,0.2,0.75,0.8,1,0.9,0.5,0.5,0,1,1]) {
  // Alter scroll within clamped zero/end ranges for a real same-status publication.
  context.win.scrollY=300+1200*ratio;
  const oldSnapshot=source.state;
  source.refresh();context.win.flush();
  const current=calculateCompletionState(ratio).status;
  check(source.state.completion.status===current,'completion forward/backward state');
  if(source.state!==oldSnapshot) check(source.state.completionTransition.previous===previous && source.state.completionTransition.current===current && source.state.completionTransition.changed===(previous!==current),'transition describes publication');
  else check(previous===current,'identical geometry suppressed without transition event');
  previous=current;
 }
 const snapshot=source.state;source.refresh();context.win.flush();
 check(source.state===snapshot,'nested completion preserves duplicate publication suppression');
 check(context.win.events.get('scroll').size===1 && context.win.pending()===0,'completion adds no scroll or scheduler');
 source.destroy();check(source.state===null && context.win.count()===0,'completion history discarded on destroy');
}
const completionSource=require('node:fs').readFileSync(require.resolve('../assets/js/readflow-remaining-time.js'),'utf8').split('const normalizeProgress')[1].split('Strict numeric inputs')[0];
check(!/Date\.|setInterval|setTimeout|requestAnimationFrame|addEventListener|getBoundingClientRect|MutationObserver/.test(completionSource),'completion pure comparisons without clocks or side effects');

// Manual shortcode consumers share the real engine and one derived layer.
{
 const context = environment(); context.win.scrollY = 1020;
 const source = new Engine(context.article, context.win); source.init(); context.win.flush();
 const root = { getAttribute: () => '600', appendChild() { throw new Error('Inline nodes must not move'); } };
 const manager = new DisplayManager(root, context.win);
 const elements = ['progress', 'progress', 'progress', 'remaining', 'remaining'].map(kind => {
  const nodes = {};
  for (const name of ['readflow-progress-value', 'readflow-progress-fill', 'readflow-duration-value', 'readflow-duration-prefix', 'readflow-duration-check']) nodes['.' + name] = { textContent: '', hidden: true, style: {} };
  return { nodes, hidden: true, attributes: {}, getAttribute(name) { return name === 'data-readflow-inline' ? kind : name === 'data-readflow-format' ? 'natural' : null; }, querySelector(name) { return nodes[name]; }, setAttribute(name, value) { this.attributes[name] = value; } };
 });
 manager.mountInline(source, elements); const layer = manager.remaining;
 manager.mountInline(source, elements);
 check(manager.consumers.length === 5 && manager.remaining === layer, 'Inline initialization idempotent, one derived layer');
 check(source.listeners.size === 6, 'Three progress consumers, two visibility consumers and one remaining upstream');
 check(context.win.events.get('scroll').size === 1, 'Inline consumers add no scroll listeners');
 check(elements[0].nodes['.readflow-progress-value'].textContent === '60%', 'Immediate restored 60%');
 check(elements[3].nodes['.readflow-duration-value'].textContent === '4 min remaining', 'Immediate derived duration');
 for (const percentage of [0, 50, 75, 100, 80]) {
  context.win.scrollY = 300 + 1200 * percentage / 100;
  const reads = context.article.reads;
  context.win.dispatchEvent({ type: 'scroll' }); context.win.flush();
  check(context.article.reads === reads + 1, 'One geometry read with five consumers');
  for (const element of elements.slice(0, 3)) {
   check(element.nodes['.readflow-progress-value'].textContent === percentage + '%', 'Inline percentage update');
   check(element.attributes['aria-valuenow'] === String(percentage), 'Inline accessible value');
   check(element.nodes['.readflow-progress-fill'].style.transform === 'scaleX(' + percentage / 100 + ')', 'Inline transform');
  }
  for (const element of elements.slice(3)) {
   check(element.nodes['.readflow-duration-check'].hidden === (percentage !== 100), 'Inline completion check');
   check(percentage === 100 ? element.nodes['.readflow-duration-value'].textContent === 'Finished' : element.nodes['.readflow-duration-value'].textContent.includes('remaining'), 'Completion reverses on backward scroll');
  }
 }
 const consumers = manager.consumers.slice(); manager.destroy(); manager.destroy();
 check(source.listeners.size === 0, 'All inline subscriptions cleaned');
 for (const consumer of consumers) check(consumer.element.hidden && consumer.destroyed, 'Inline consumer destroyed');
 source.destroy(); check(context.win.count() === 0, 'No timers or lingering listeners');
}

{
 const c=environment();const event={type:'readflow:content-presentation-changed',detail:{reason:'unknown'}};
 c.win.document.dispatchEvent(event);check(c.win.pending()===0,'Signal before initialization safe');
 const source=new Engine(c.article,c.win);source.init();source.init();c.win.flush();
 check(c.win.document.events.get(event.type).size===1,'One presentation listener despite repeated init');
 const reads=c.article.reads;c.article.height=2400;
 for(const detail of [undefined,null,{reason:'reader-controls'},{reason:'reset'},{reason:'unknown'}])c.win.document.dispatchEvent({type:event.type,detail});
 check(c.win.pending()===1&&c.article.reads===reads,'Signals use existing coalesced scheduler without immediate geometry');
 c.win.flush();check(c.article.reads===reads+1&&source.state.articleHeight===2400,'Existing refresh measures updated article');
 check(c.win.events.get('scroll').size===1,'No additional scroll listener');
 source.destroy();check(c.win.document.events.get(event.type).size===0,'Presentation listener removed');
 c.win.document.dispatchEvent(event);check(c.win.pending()===0,'No refresh after destruction');
}

{
 const c=environment();c.win.scrollY=900;
 const automatic=displayRoot('percentage').elements.percentage;
 automatic.getAttribute=name=>name==='data-readflow-inline'?'progress':null;
 const root={getAttribute:name=>({'data-readflow-mode':'inline_progress','data-readflow-display-disabled':'true','data-readflow-total-seconds':'600'})[name]||null,querySelector:()=>null,appendChild(){throw Error('Inline consumer must stay in placement target');}};
 const transport={content:{querySelector:()=>automatic}};
 c.article.querySelectorAll=()=>[];
 c.win.document.querySelectorAll=query=>query==='[data-readflow-progress]'?[root]:[c.article];
 c.win.document.querySelector=query=>query==='template[data-readflow-placement]'?transport:null;
 const session=boot(c.win);c.win.flush();
 check(session&&session.manager.consumers.length===1,'Automatic selector transport uses existing inline consumer');
 check(automatic.value.textContent==='50%','Initial midpoint delivered while transport is inert');
 check(boot(c.win)===session&&c.win.events.get('scroll').size===1,'One engine/listener after repeated boot');
 for(const ratio of [0,.25,.5,.75,1,.8]){
  c.win.scrollY=300+1200*ratio;c.win.dispatchEvent({type:'scroll'});c.win.flush();
  check(automatic.value.textContent===Math.floor(ratio*100)+'%','Automatic inline state');
  check(automatic.attributes['aria-valuenow']===String(Math.floor(ratio*100)),'Automatic inline accessible value');
  check(automatic.fill.style.transform==='scaleX('+ratio+')','Automatic inline fill');
 }
 const reads=c.article.reads;c.article.height=2400;c.win.document.dispatchEvent({type:'readflow:content-presentation-changed'});
 check(c.win.pending()===1,'Presentation changes share scheduler');c.win.flush();check(c.article.reads===reads+1&&automatic.value.textContent===Math.floor(session.engine.state.percentage)+'%','Refreshed geometry reaches inline consumer');
 const count=session.manager.consumers.length;session.manager.mountInline(session.engine,[automatic]);check(session.manager.consumers.length===count,'Mounted selector node never double subscribes');
 session.destroy();check(c.win.count()===0&&automatic.hidden,'Inline lifecycle cleanup');
}
{
 const f=environment();const e=new Engine(f.article,f.win);e.init();f.win.flush();
 let errors=0, delivered=0;f.win.reportError=()=>{errors++;};
 const off=e.subscribe(()=>{throw Error('broken optional consumer');});
 e.subscribe(()=>{delivered++;});
 check(errors===1 && delivered===1 && typeof off==='function','Immediate subscriber failure isolated, unsubscribe still returned');
 off();f.win.scrollY=900;e.refresh();f.win.flush();
 check(errors===1 && delivered===2,'Other consumers continue after failed initial subscriber');e.destroy();
 const isolated={document:{readyState:'complete'}};
 require('node:vm').runInNewContext(require('node:fs').readFileSync('assets/js/readflow-progress.js','utf8'),{window:isolated});
 check(!isolated.ReadFlowProgress,'Missing duration dependency gracefully skips progress bootstrap');
}
console.log(`PASS: ${checks} JavaScript assertions including shared completion state.`);
