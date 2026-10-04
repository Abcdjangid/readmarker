/* Internal duration/state API. No DOM reads, timers, scrolling, storage or networking. */
(function (root, factory) {
	'use strict';
	if (typeof module === 'object' && module.exports) module.exports = factory();
	else root.ReadFlowRemainingTime = factory();
}(typeof window !== 'undefined' ? window : globalThis, function () {
	'use strict';
	const duration = value => typeof value === 'number' && Number.isFinite(value) && value >= 0 ? value : 0;

	// Shared ratio policy: strict finite numbers; all nonnumeric/nonfinite inputs become zero.
	const normalizeProgress = value => typeof value === 'number' && Number.isFinite(value) ? Math.max(0, Math.min(1, value)) : 0;
	const completionThresholds = Object.freeze({ started: 0, almostFinished: 0.75, finished: 1 });
	const completionStates = Object.freeze({
		not_started: Object.freeze({ status: 'not_started', isStarted: false, isAlmostFinished: false, isFinished: false }),
		reading: Object.freeze({ status: 'reading', isStarted: true, isAlmostFinished: false, isFinished: false }),
		almost_finished: Object.freeze({ status: 'almost_finished', isStarted: true, isAlmostFinished: true, isFinished: false }),
		finished: Object.freeze({ status: 'finished', isStarted: true, isAlmostFinished: true, isFinished: true })
	});
	/** Shared pure policy, hosted here to avoid a circular dependency with progress.
	 * Immutable singleton results also preserve progress snapshot equality checks.
	 */
	function calculateCompletionState(progress) {
		const ratio = normalizeProgress(progress);
		const status = ratio >= completionThresholds.finished ? 'finished' : ratio >= completionThresholds.almostFinished ? 'almost_finished' : ratio > completionThresholds.started ? 'reading' : 'not_started';
		return completionStates[status];
	}

	/**
	 * Strict numeric inputs. Missing/negative/non-finite durations become zero;
	 * non-finite progress becomes zero, finite progress is clamped to [0,1].
	 * Completion represents progress == 1, including when duration is unknown/zero.
	 * Elapsed is a scroll-derived estimate, NOT measured time spent reading.
	 * Going backwards increases remaining time. No intermediate rounding.
	 */
	function calculate(totalSeconds, progressRatio) {
		const total = duration(totalSeconds);
		const ratio = normalizeProgress(progressRatio);
		const elapsed = total * ratio;
		return Object.freeze({
			total_seconds: total,
			progress_ratio: ratio,
			remaining_seconds: Math.max(0, Math.min(total, total - elapsed)),
			elapsed_seconds: elapsed,
			remaining_percentage: (1 - ratio) * 100,
			completed: calculateCompletionState(ratio).isFinished
		});
	}

	/**
	 * Presentation only: ceil to whole seconds, never mutate calculation data.
	 * Natural = detailed + remaining suffix. Short = ceil minutes above 59 sec.
	 * Clock = MM:SS or HH:MM:SS (hours may exceed two digits). Detailed includes nonzero hours/minutes/seconds.
	 * English defaults; future integrations may supply translated labels/templates.
	 * Returns plain text: future consumers should use textContent, not innerHTML.
	 */
	function format(seconds, style = 'natural', labels = {}) {
		const words = { hour: 'hr', minute: 'min', second: 'sec', remaining: '%s remaining', ...labels };
		const whole = Math.ceil(duration(seconds));
		const hours = Math.floor(whole / 3600);
		const minutes = Math.floor((whole % 3600) / 60);
		const secs = whole % 60;
		const pad = value => String(value).padStart(2, '0');
		if (style === 'clock') return hours ? `${pad(hours)}:${pad(minutes)}:${pad(secs)}` : `${pad(minutes)}:${pad(secs)}`;
		if (style === 'short') {
			if (whole < 60) return `${whole} ${words.second}`;
			const totalMinutes = Math.ceil(whole / 60);
			const h = Math.floor(totalMinutes / 60);
			const m = totalMinutes % 60;
			return [h ? `${h} ${words.hour}` : '', m ? `${m} ${words.minute}` : ''].filter(Boolean).join(' ');
		}
		const detailed = [hours ? `${hours} ${words.hour}` : '', minutes ? `${minutes} ${words.minute}` : '', secs || !whole ? `${secs} ${words.second}` : ''].filter(Boolean).join(' ');
		return style === 'detailed' ? detailed : words.remaining.replace('%s', () => detailed);
	}

	/**
	 * One derived layer per engine/total. connect(engine) reuses subscribe(), which
	 * delivers existing progress immediately. Until the first measurement, state
	 * remains null (do not invent a 0% reading position). subscribe(fn) returns an
	 * unsubscribe function and immediately delivers known state. destroy() removes
	 * the upstream subscription and consumers. No competing custom event system.
	 * Standalone: const layer = new ReadFlowRemainingTime.Layer(totalSeconds);
	 * layer.connect(engine); const off = layer.subscribe(state => use(state));
	 * Managed: ReadFlowProgress.boot(window).manager.subscribeRemaining(listener).
	 * Format when needed: ReadFlowRemainingTime.format(state.remaining_seconds, 'clock').
	 */
	class Layer {
		constructor(totalSeconds) {
			this.total = duration(totalSeconds);
			this.state = null;
			this.listeners = new Set();
			this.engine = null;
			this.unsubscribe = null;
		}
		connect(engine) {
			if (this.engine === engine && this.unsubscribe) return;
			if (this.unsubscribe) this.unsubscribe();
			this.state = null;
			this.engine = engine;
			this.unsubscribe = engine.subscribe(progress => {
				const next = calculate(this.total, progress && progress.ratio);
				if (this.state && next.progress_ratio === this.state.progress_ratio) return;
				this.state = next;
				this.listeners.forEach(listener => this.notify(listener));
			});
		}
		notify(listener) {
			try { listener(this.state); } catch (error) {
				if (typeof globalThis.reportError === 'function') globalThis.reportError(error);
			}
		}
		subscribe(listener) {
			if (typeof listener !== 'function') return () => {};
			this.listeners.add(listener);
			if (this.state) this.notify(listener);
			return () => this.listeners.delete(listener);
		}
		destroy() {
			if (this.unsubscribe) this.unsubscribe();
			this.unsubscribe = null;
			this.engine = null;
			this.state = null;
			this.listeners.clear();
		}
	}
	/** Timestamp presentation only. Round remaining seconds once; preserve the clock's milliseconds.
	 * Native timestamp arithmetic handles midnight/DST. No timezone option: reader-local time.
	 * Out-of-range Date values return empty text instead of throwing RangeError.
	 */
	function formatFinishTime(seconds, now) {
		if (typeof now !== 'number' || !Number.isFinite(now)) return '';
		const timestamp = now + Math.round(duration(seconds)) * 1000;
		if (!Number.isFinite(timestamp) || Math.abs(timestamp) > 8640000000000000) return '';
		return new Intl.DateTimeFormat('en-US', { hour: 'numeric', minute: '2-digit', hour12: true }).format(new Date(timestamp));
	}
	return { calculate, format, formatFinishTime, Layer, normalizeProgress, completionThresholds, calculateCompletionState };
}));
