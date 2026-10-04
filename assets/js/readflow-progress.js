/* ReadFlow progress: no telemetry or network activity. Optional local storage is isolated in position memory. */
(function (root, factory) {
	'use strict';
	if (typeof module === 'object' && module.exports) {
		module.exports = factory(require('./readflow-remaining-time.js')); // Same calculation/lifecycle in Node.
	} else if (!root.ReadFlowProgress && root.ReadFlowRemainingTime) {
		root.ReadFlowProgress = factory(root.ReadFlowRemainingTime);
		const boot = () => root.ReadFlowProgress.boot(root);
		if (root.document.readyState === 'loading') {
			root.document.addEventListener('DOMContentLoaded', boot, { once: true });
		} else {
			boot();
		}
	}
}(typeof window !== 'undefined' ? window : globalThis, function (remainingTime) {
	'use strict';
	const selector = '.readflow-article-content[data-readflow-article]';
	const { normalizeProgress, completionThresholds, calculateCompletionState } = remainingTime;
	const finite = value => typeof value === 'number' && Number.isFinite(value);

	/**
	 * Pure geometry policy (CSS pixels, document coordinates).
	 * Long article: 0 at viewport top == article top, 1 at viewport bottom == end.
	 * Short/equal-height article: 1 once its bottom is visible, otherwise 0.
	 * Zero/invalid geometry returns a safe zero state. No document-height fallback.
	 */
	function calculate({ articleTop = 0, articleHeight = 0, viewportHeight = 0, scrollPosition = 0 } = {}) {
		const valid = [articleTop, articleHeight, viewportHeight, scrollPosition].every(finite);
		articleTop = finite(articleTop) ? articleTop : 0;
		articleHeight = finite(articleHeight) ? Math.max(0, articleHeight) : 0;
		viewportHeight = finite(viewportHeight) ? Math.max(0, viewportHeight) : 0;
		scrollPosition = finite(scrollPosition) ? scrollPosition : 0;
		let ratio = 0;
		if (valid && articleHeight > 0 && viewportHeight > 0) {
			ratio = articleHeight <= viewportHeight
				? Number(scrollPosition + viewportHeight >= articleTop + articleHeight)
				: (scrollPosition - articleTop) / (articleHeight - viewportHeight);
		}
		ratio = normalizeProgress(ratio);
		return Object.freeze({ ratio, percentage: ratio * 100, articleTop, articleHeight, viewportHeight, scrollPosition, completion: calculateCompletionState(ratio) });
	}

	/**
	 * Engine API: init(), refresh(), subscribe(listener) -> unsubscribe(), destroy().
	 * state is an immutable geometry snapshot, never reading history or user data.
	 * Subscribers immediately receive current state when available. Changes also
	 * dispatch a bubbling `readflow:progress` CustomEvent from the article with
	 * the same state in event.detail. No UI knowledge exists in this class.
	 */
	class Engine {
		constructor(article, win) {
			this.article = article;
			this.win = win;
			this.state = null;
			this.running = false;
			this.frame = null;
			this.listeners = new Set();
			this.removers = [];
			this.refresh = this.refresh.bind(this);
		}
		init() {
			if (this.running) return true;
			if (!this.article || !this.article.isConnected || !this.win || !this.win.requestAnimationFrame) return false;
			this.running = true;
			const listen = (target, type, options) => {
				if (!target || !target.addEventListener) return;
				target.addEventListener(type, this.refresh, options);
				this.removers.push(() => target.removeEventListener(type, this.refresh, options));
			};
			listen(this.win, 'scroll', { passive: true });
			listen(this.win, 'resize', { passive: true });
			listen(this.win.visualViewport, 'resize', { passive: true });
			listen(this.win.visualViewport, 'scroll', { passive: true });
			listen(this.win.document, 'readflow:content-presentation-changed'); // Payload is informational; refresh never trusts it.
			listen(this.win.document, 'load', true); // Captures late images, including above the article.
			listen(this.win.document.fonts, 'loadingdone');
			if (this.win.ResizeObserver) {
				this.observer = new this.win.ResizeObserver(this.refresh);
				this.observer.observe(this.article);
				if (this.win.document.body) this.observer.observe(this.win.document.body);
			}
			this.refresh();
			return true;
		}
		refresh() {
			if (!this.running || this.frame !== null) return;
			this.frame = this.win.requestAnimationFrame(() => {
				this.frame = null;
				if (!this.running) return;
				if (!this.article.isConnected) {
					this.publish(calculate());
					this.destroy();
					return;
				}
				// One geometry read per scheduled frame, never per raw scroll event.
				// Reading top again also handles upstream layout shifts during scrolling.
				const rect = this.article.getBoundingClientRect();
				const scroll = this.win.scrollY || 0;
				const viewport = this.win.visualViewport;
				this.publish(calculate({
					articleTop: rect.top + scroll,
					articleHeight: rect.height,
					viewportHeight: viewport ? viewport.height : this.win.innerHeight,
					scrollPosition: scroll + (viewport ? viewport.offsetTop : 0)
				}));
			});
		}
		publish(state) {
			if (this.state && Object.keys(state).every(key => state[key] === this.state[key])) return;
			const current = state.completion.status;
			const previous = this.state ? this.state.completion.status : current;
			// Transition describes this publication, not a one-shot event or persisted history.
			// Initial snapshots report current/current and changed=false.
			state = Object.freeze({ ...state, completionTransition: Object.freeze({ previous, current, changed: previous !== current }) });
			this.state = state;
			this.listeners.forEach(listener => {
				try { listener(state); } catch (error) { if (this.win.reportError) this.win.reportError(error); }
			});
			this.article.dispatchEvent(new this.win.CustomEvent('readflow:progress', { detail: state, bubbles: true }));
		}
		subscribe(listener) {
			if (typeof listener !== 'function') return () => {};
			this.listeners.add(listener);
			if (this.state) {
				try { listener(this.state); } catch (error) { if (typeof this.win.reportError === 'function') this.win.reportError(error); }
			}
			return () => this.listeners.delete(listener);
		}
		destroy() {
			this.running = false;
			if (this.frame !== null) this.win.cancelAnimationFrame(this.frame);
			this.frame = null;
			this.removers.splice(0).forEach(remove => remove());
			if (this.observer) this.observer.disconnect();
			this.listeners.clear();
			this.state = null;
		}
	}

	/** First consumer only: a noninteractive visual indicator, with no live region. */
	class TopBar {
		constructor(element, win) {
			this.element = element;
			this.win = win;
			this.fill = element.querySelector('.readflow-progress-fill');
			this.adminBar = win.document.getElementById('wpadminbar');
			this.ratio = null;
			this.offset = null;
			this.update = this.update.bind(this);
		}
		mount(engine) {
			if (!this.fill) return false;
			// Avoid theme transforms/overflow changing fixed-position containment.
			this.win.document.body.appendChild(this.element);
			this.unsubscribe = engine.subscribe(this.update);
			return true;
		}
		update(state) {
			const offset = this.adminBar ? Math.max(0, this.adminBar.getBoundingClientRect().bottom) : 0;
			const hidden = state.articleHeight <= 0 || state.viewportHeight <= 0;
			if (this.element.hidden !== hidden) this.element.hidden = hidden;
			if (offset !== this.offset) {
				this.element.style.setProperty('--readflow-progress-offset', `${offset}px`);
				this.offset = offset;
			}
			if (state.ratio !== this.ratio) {
				this.fill.style.transform = `scaleX(${state.ratio})`;
				this.ratio = state.ratio;
			}
		}
		destroy() {
			if (this.unsubscribe) this.unsubscribe();
			this.element.hidden = true;
		}
	}

	/** Accessible, non-live percentage. No geometry reads, scheduling, or scroll listeners. */
	class Percentage {
		constructor(element, win) {
			this.element = element;
			this.win = win;
			this.value = element.querySelector('.readflow-progress-value');
			this.percentage = null;
			this.update = this.update.bind(this);
		}
		mount(engine) {
			if (!this.value) return false;
			this.win.document.body.appendChild(this.element);
			this.unsubscribe = engine.subscribe(this.update);
			return true;
		}
		update(state) {
			const hidden = state.articleHeight <= 0 || state.viewportHeight <= 0;
			if (this.element.hidden !== hidden) this.element.hidden = hidden;
			// Presentation rounding only: never claim completion before the engine does.
			const percentage = Math.floor(state.percentage);
			if (percentage !== this.percentage) {
				this.value.textContent = `${percentage}%`;
				this.element.setAttribute('aria-valuenow', String(percentage));
				this.percentage = percentage;
			}
		}
		destroy() {
			if (this.unsubscribe) this.unsubscribe();
			this.element.hidden = true;
		}
	}

	/** SVG pathLength=100 makes dash offset a presentation of the engine percentage. */
	class Circular extends Percentage {
		constructor(element, win) {
			super(element, win);
			this.stroke = element.querySelector('.readflow-progress-stroke');
			this.offset = null;
		}
		mount(engine) { return this.stroke ? super.mount(engine) : false; }
		update(state) {
			super.update(state);
			const offset = 100 - state.percentage;
			if (offset !== this.offset) {
				this.stroke.style.strokeDashoffset = String(offset);
				this.offset = offset;
			}
		}
	}

	/** Shared duration consumer; mode selects presentation, never calculation or timing. */
	class RemainingDisplay {
		constructor(element, win, mode) {
			this.element = element;
			this.win = win;
			this.mode = mode;
			this.value = element.querySelector('.readflow-duration-value');
			this.prefix = element.querySelector('.readflow-duration-prefix');
			this.check = element.querySelector('.readflow-duration-check');
			this.totalLabel = element.getAttribute('data-readflow-total-label') || '';
			this.labels = {};
			try {
				const labels = JSON.parse(element.getAttribute('data-readflow-labels') || '{}');
				for (const key of ['hour', 'minute', 'second', 'remaining', 'finished', 'finish']) {
					if (labels && typeof labels[key] === 'string') this.labels[key] = labels[key];
				}
			} catch (_) { /* Cached/malformed labels safely use the formatter defaults. */ }
		}
		mount(engine, manager) {
			if (!this.value || !this.prefix || !this.check || !remainingTime) return false;
			this.win.document.body.appendChild(this.element);
			this.unsubscribe = manager.subscribeRemaining(state => this.update(state, engine.state));
			// Geometry-only changes need visibility updates even if ratio has not changed.
			this.unsubscribeVisibility = engine.subscribe(state => this.setVisibility(state));
			return true;
		}
		setVisibility(progress) {
			const hidden = !progress || progress.articleHeight <= 0 || progress.viewportHeight <= 0;
			if (this.element.hidden !== hidden) this.element.hidden = hidden;
		}
		update(state, progress) {
			this.setVisibility(progress);
			let prefix = '';
			if (this.mode === 'time_remaining') prefix = this.totalLabel;
			if (this.mode === 'percentage_remaining' && progress) prefix = `${Math.floor(progress.percentage)}%`;
			const prefixText = prefix ? `${prefix} · ` : '';
			if (this.prefix.textContent !== prefixText) this.prefix.textContent = prefixText;
			if (this.prefix.hidden !== !prefix) this.prefix.hidden = !prefix;
			if (this.check.hidden !== !state.completed) this.check.hidden = !state.completed;
			const text = state.completed ? (this.labels.finished || 'Finished') : this.formatValue(state);
			if (this.value.textContent !== text) this.value.textContent = text;
		}
		formatValue(state) {
			return remainingTime.format(state.remaining_seconds, this.mode === 'countdown' ? 'clock' : 'natural', this.labels);
		}
		destroy() {
			if (this.unsubscribe) this.unsubscribe();
			if (this.unsubscribeVisibility) this.unsubscribeVisibility();
			this.element.hidden = true;
		}
	}

	/** Inline adapters keep authored placement and reuse existing presentation. */
	class InlineProgress extends Percentage {
		constructor(element, win) {
			super(element, win);
			this.inline = true;
			this.fill = element.querySelector('.readflow-progress-fill');
			this.ratio = null;
		}
		mount(engine) {
			if (this.unsubscribe) return true;
			if (this.destroyed || !this.value || !this.fill) return false;
			this.unsubscribe = engine.subscribe(this.update);
			return true;
		}
		update(state) {
			if (this.destroyed) return;
			super.update(state);
			if (this.ratio !== state.ratio) {
				this.fill.style.transform = `scaleX(${state.ratio})`;
				this.ratio = state.ratio;
			}
		}
		destroy() {
			if (this.destroyed) return;
			super.destroy();
			this.destroyed = true;
			this.unsubscribe = this.value = this.fill = this.win = null;
		}
	}
	class InlineRemaining extends RemainingDisplay {
		constructor(element, win) {
			super(element, win, 'remaining');
			this.inline = true;
			const format = element.getAttribute('data-readflow-format');
			this.format = ['natural', 'short', 'clock', 'detailed'].includes(format) ? format : 'natural';
		}
		mount(engine, manager) {
			if (this.unsubscribe) return true;
			if (this.destroyed || !this.value || !this.prefix || !this.check || !remainingTime) return false;
			this.unsubscribe = manager.subscribeRemaining(state => this.update(state, engine.state));
			this.unsubscribeVisibility = engine.subscribe(state => this.setVisibility(state));
			return true;
		}
		update(state, progress) { if (!this.destroyed) super.update(state, progress); }
		formatValue(state) { return remainingTime.format(state.remaining_seconds, this.format, this.labels); }
		destroy() {
			if (this.destroyed) return;
			super.destroy();
			this.destroyed = true;
			this.unsubscribe = this.unsubscribeVisibility = this.value = this.prefix = this.check = this.win = null;
		}
	}

	/** Reads the clock only on a new derived state; no independent scheduling. */
	class EstimatedFinishTime extends RemainingDisplay {
		constructor(element, win, mode, clock = () => Date.now()) {
			super(element, win, mode);
			this.clock = clock;
			this.mounted = false;
			this.destroyed = false;
			this.lastState = null;
		}
		mount(engine, manager) {
			if (this.mounted) return true;
			if (this.destroyed) return false;
			this.mounted = super.mount(engine, manager);
			return this.mounted;
		}
		update(state, progress) {
			if (this.destroyed) return;
			this.setVisibility(progress);
			if (this.lastState && ['progress_ratio', 'remaining_seconds', 'completed'].every(key => this.lastState[key] === state[key])) return;
			this.lastState = state;
			super.update(state, progress);
		}
		formatValue(state) {
			const time = remainingTime.formatFinishTime(state.remaining_seconds, this.clock());
			return time ? (this.labels.finish || 'Finish around %s').replace('%s', () => time) : remainingTime.format(state.remaining_seconds, 'natural', this.labels);
		}
		destroy() {
			if (this.destroyed) return;
			super.destroy();
			this.destroyed = true;
			this.mounted = false;
			this.lastState = this.clock = this.unsubscribe = this.unsubscribeVisibility = null;
			this.value = this.prefix = this.check = this.win = null;
		}
	}

	/** Presentation only: reuse duration formatting, completion and the shared layer. */
	class FloatingWidget extends RemainingDisplay {
		constructor(element, win, mode) {
			super(element, win, mode);
			this.percentageNode = element.querySelector('.readflow-floating-widget__percentage');
			this.progressNode = element.querySelector('.readflow-floating-widget__progress');
			this.fill = element.querySelector('.readflow-floating-widget__progress-fill');
			this.percentage = null;
			this.ratio = null;
			this.mounted = false;
			this.destroyed = false;
		}
		mount(engine, manager) {
			if (this.mounted) return true;
			if (this.destroyed || !this.percentageNode || !this.progressNode || !this.fill) return false;
			this.mounted = super.mount(engine, manager);
			return this.mounted;
		}
		update(state, progress) {
			if (this.destroyed) return;
			super.update(state, progress);
			if (!progress) return;
			const percentage = Math.floor(progress.percentage);
			if (percentage !== this.percentage) {
				this.percentageNode.textContent = `${percentage}%`;
				this.progressNode.setAttribute('aria-valuenow', String(percentage));
				this.percentage = percentage;
			}
			if (progress.ratio !== this.ratio) {
				this.fill.style.transform = `scaleX(${progress.ratio})`;
				this.ratio = progress.ratio;
			}
		}
		destroy() {
			if (this.destroyed) return;
			super.destroy();
			this.destroyed = true;
			this.mounted = false;
			this.unsubscribe = this.unsubscribeVisibility = null;
			this.percentageNode = this.progressNode = this.fill = null;
			this.value = this.prefix = this.check = this.win = null;
			// The manager retains element only long enough to restore it for bfcache.
		}
	}

	const milestoneThresholds = Object.freeze([0.25, 0.5, completionThresholds.almostFinished, completionThresholds.finished]);
	/** Pure state/transition API. Initial snapshots have no historical crossings.
	 * crossedMilestones are newly reached going forward; lostMilestones are those
	 * no longer reached going backward. Jumping may cross several thresholds.
	 * No lifetime flags: returning across a threshold reaches it again.
	 */
	function calculateMilestones(progress, previous = null) {
		const ratio = normalizeProgress(progress);
		const before = previous === null ? ratio : normalizeProgress(previous);
		const reached = milestoneThresholds.filter(value => ratio >= value);
		return Object.freeze({
			progress: ratio,
			currentMilestone: reached.length ? reached[reached.length - 1] : null,
			nextMilestone: milestoneThresholds.find(value => value > ratio) ?? null,
			reachedMilestones: Object.freeze(reached),
			crossedMilestones: Object.freeze(milestoneThresholds.filter(value => before < value && ratio >= value)),
			lostMilestones: Object.freeze(milestoneThresholds.filter(value => before >= value && ratio < value)),
			direction: ratio > before ? 'forward' : ratio < before ? 'backward' : 'stationary'
		});
	}

	/** One subscription to the existing engine; state and presentation remain separate. */
	class MilestoneDisplay {
		constructor(element, win) {
			this.element = element;
			this.win = win;
			this.label = element.querySelector('.readflow-milestone__label');
			this.value = element.querySelector('.readflow-milestone__progress');
			this.labels = { 25: 'Getting started', 50: 'Halfway there', 75: 'Almost there', 100: 'Finished' };
			try {
				const labels = JSON.parse(element.getAttribute('data-readflow-milestone-labels') || '{}');
				for (const key of Object.keys(this.labels)) if (labels && typeof labels[key] === 'string') this.labels[key] = labels[key];
			} catch (_) { /* Cached malformed labels retain internal defaults. */ }
			this.state = null;
			this.unsubscribe = null;
			this.destroyed = false;
		}
		mount(engine) {
			if (this.unsubscribe) return true;
			if (this.destroyed || !this.label || !this.value) return false;
			this.win.document.body.appendChild(this.element);
			this.unsubscribe = engine.subscribe(progress => this.update(progress));
			return true;
		}
		update(progress) {
			if (this.destroyed) return;
			const hidden = progress.articleHeight <= 0 || progress.viewportHeight <= 0;
			if (this.element.hidden !== hidden) this.element.hidden = hidden;
			if (this.state && this.state.progress === progress.ratio) return;
			this.state = calculateMilestones(progress.ratio, this.state ? this.state.progress : null);
			const label = this.labels[(this.state.currentMilestone || 0.25) * 100];
			// Percentage is presentation of the engine value, not a milestone threshold.
			const text = Math.floor(progress.percentage) + '%';
			if (this.label.textContent !== label) this.label.textContent = label;
			if (this.value.textContent !== text) this.value.textContent = text;
		}
		destroy() {
			if (this.destroyed) return;
			if (this.unsubscribe) this.unsubscribe();
			this.element.hidden = true;
			this.destroyed = true;
			this.unsubscribe = this.state = this.label = this.value = this.labels = this.win = null;
		}
	}

	/**
	 * One manager per configuration root, independent of Engine. Each consumer
	 * subscribes to the SAME immutable state; existing state is delivered by
	 * subscribe() immediately, otherwise the engine's initial RAF supplies it.
	 * destroy() unsubscribes and restores moved nodes for clean bfcache restart.
	 */
	class DisplayManager {
		constructor(root, win) {
			this.root = root;
			this.win = win;
			this.consumers = [];
			this.remaining = null;
			this.engine = null;
		}
		mount(engine) {
			if (this.consumers.length) return true;
			const modes = { inline_progress: ['inline_progress'], top_bar: ['top_bar'], circular: ['circular'], percentage: ['percentage'], top_bar_percentage: ['top_bar', 'percentage'], remaining: ['remaining'], time_remaining: ['time_remaining'], percentage_remaining: ['percentage_remaining'], countdown: ['countdown'], floating_widget: ['floating_widget'], estimated_finish_time: ['estimated_finish_time'], reading_milestones: ['reading_milestones'] };
			const registry = { inline_progress: InlineProgress, top_bar: TopBar, circular: Circular, percentage: Percentage, remaining: RemainingDisplay, time_remaining: RemainingDisplay, percentage_remaining: RemainingDisplay, countdown: RemainingDisplay, floating_widget: FloatingWidget, estimated_finish_time: EstimatedFinishTime, reading_milestones: MilestoneDisplay };
			const requested = this.root.getAttribute ? this.root.getAttribute('data-readflow-mode') : null;
			const mode = Object.prototype.hasOwnProperty.call(modes, requested) ? requested : 'top_bar';
			this.engine = engine;
			for (const name of modes[mode]) {
				// Legacy top-bar markup remains bootable while cached pages expire.
				const element = requested === null ? this.root : this.root.querySelector(`[data-readflow-consumer="${name}"]`);
				if (!element) continue; // Missing optional markup must not disable sibling consumers.
				const consumer = new registry[name](element, this.win, name);
				if (!consumer.mount(engine, this)) { consumer.destroy(); continue; }
				this.consumers.push(consumer);
			}
			this.engine = engine;
			return true;
		}
		mountInline(engine, elements) {
			this.engine = engine;
			for (const element of elements) {
				if (this.consumers.some(consumer => consumer.element === element)) continue;
				const kind = element.getAttribute('data-readflow-inline');
				const Consumer = kind === 'progress' ? InlineProgress : kind === 'remaining' ? InlineRemaining : null;
				if (!Consumer) continue;
				const consumer = new Consumer(element, this.win);
				if (consumer.mount(engine, this)) this.consumers.push(consumer);
			}
		}
		/** Internal API for future components; one derived subscription, no new engine. */
		connectRemaining() {
			if (!this.remaining && this.engine && remainingTime) {
				const raw = this.root.getAttribute ? this.root.getAttribute('data-readflow-total-seconds') : null;
				this.remaining = new remainingTime.Layer(raw === null ? 0 : Number(raw));
				this.remaining.connect(this.engine);
			}
			return this.remaining;
		}
		subscribeRemaining(listener) {
			const layer = this.connectRemaining();
			return layer ? layer.subscribe(listener) : () => {};
		}
		destroy() {
			if (this.remaining) this.remaining.destroy();
			this.remaining = null;
			this.engine = null;
			this.consumers.splice(0).forEach(consumer => {
				consumer.destroy();
				if (!consumer.inline && consumer.element !== this.root) this.root.appendChild(consumer.element);
			});
		}
	}

	let active = null;
	/** Idempotent default page wiring. Ambiguous/missing markers fail closed. */
	function boot(win) {
		if (active) return active;
		const articles = win.document.querySelectorAll(selector);
		const bars = win.document.querySelectorAll('[data-readflow-progress]');
		if (articles.length !== 1 || bars.length !== 1) return null;
		const engine = new Engine(articles[0], win);
		const manager = new DisplayManager(bars[0], win);
		if (!engine.init()) return null;
		if (!bars[0].getAttribute || bars[0].getAttribute('data-readflow-display-disabled') !== 'true') manager.mount(engine);
		manager.mountInline(engine, articles[0].querySelectorAll ? articles[0].querySelectorAll('[data-readflow-inline]') : []);
		// Selector transport may still be inert or may already have mounted outside the article.
		// Subscribe that same node now so explicit late placement needs no second lifecycle.
		if (bars[0].getAttribute && bars[0].getAttribute('data-readflow-mode') === 'inline_progress' && win.document.querySelector) {
			const transport = win.document.querySelector('template[data-readflow-placement]');
			const automatic = win.document.querySelector('[data-readflow-auto-inline]') || (transport && transport.content.querySelector('[data-readflow-auto-inline]'));
			if (automatic) manager.mountInline(engine, [automatic]);
		}
		manager.connectRemaining();
		const prompt = bars[0].querySelector ? bars[0].querySelector('[data-readflow-memory]') : null;
		const memory = prompt && win.ReadFlowPositionMemory ? new win.ReadFlowPositionMemory.Controller(engine, articles[0], prompt, win) : null;
		if (memory) memory.init();
		const stop = () => {
			if (memory) { memory.flush(); memory.destroy(); }
			manager.destroy();
			engine.destroy();
			win.removeEventListener('pagehide', stop);
			active = null;
		};
		win.addEventListener('pagehide', stop);
		active = { engine, manager, memory, destroy: stop };
		return active;
	}
	// bfcache restores the same DOM; restart without retaining old observers/listeners.
	if (typeof window !== 'undefined') window.addEventListener('pageshow', event => { if (event.persisted) boot(window); });
	return { calculate, Engine, TopBar, Circular, Percentage, InlineProgress, InlineRemaining, RemainingDisplay, FloatingWidget, EstimatedFinishTime, calculateMilestones, MilestoneDisplay, calculateCompletionState, DisplayManager, boot, refresh: () => { if (active) active.engine.refresh(); } };
}));
