'use strict';
const assert = require('node:assert/strict');
const { calculate, format, formatFinishTime, Layer } = require('../assets/js/readflow-remaining-time.js');
let checks = 0;
function check(value, label) { assert.ok(value, label); checks++; }
for (const ratio of [0, 0.25, 0.5, 0.75, 1]) {
	const state = calculate(600, ratio);
	check(state.total_seconds === 600 && state.progress_ratio === ratio, 'total and ratio');
	check(state.elapsed_seconds === 600 * ratio && state.remaining_seconds === 600 * (1 - ratio), 'elapsed and remaining');
	check(state.remaining_percentage === 100 * (1 - ratio) && state.completed === (ratio === 1), 'percentage and completion');
	check(Object.isFrozen(state), 'immutable result');
}
check(calculate(600, 2).completed && calculate(600, 2).remaining_seconds === 0, 'over 100% clamped');
check(calculate(600, -1).remaining_seconds === 600, 'below zero clamped');
for (const total of [undefined, null, 0, -1, NaN, Infinity, -Infinity, '600', {}, []]) {
	const state = calculate(total, 0.5);
	check(state.total_seconds === 0 && state.remaining_seconds === 0 && state.elapsed_seconds === 0 && !state.completed, 'unknown total normalized');
}
for (const ratio of [undefined, null, NaN, Infinity, -Infinity, '0.5', {}, []]) {
	const state = calculate(600, ratio);
	check(state.progress_ratio === 0 && state.remaining_seconds === 600 && !state.completed, 'invalid ratio normalized');
}
check(calculate(0, 1).completed, 'completion follows progress even with zero total');
check(Math.abs(calculate(489.8933333333333, 0.25).remaining_seconds - 367.42) < 1e-10, 'fractional precision');
check(calculate(Number.MAX_VALUE, 0.5).remaining_seconds === Number.MAX_VALUE / 2, 'large finite duration');
const formats = [
	[0, '0 sec', '00:00', '0 sec'],
	[30, '30 sec', '00:30', '30 sec'],
	[59, '59 sec', '00:59', '59 sec'],
	[60, '1 min', '01:00', '1 min'],
	[61, '1 min 1 sec', '01:01', '2 min'],
	[90, '1 min 30 sec', '01:30', '2 min'],
	[332, '5 min 32 sec', '05:32', '6 min'],
	[3599, '59 min 59 sec', '59:59', '1 hr'],
	[3600, '1 hr', '01:00:00', '1 hr'],
	[4320, '1 hr 12 min', '01:12:00', '1 hr 12 min'],
	[7200, '2 hr', '02:00:00', '2 hr'],
	[90061, '25 hr 1 min 1 sec', '25:01:01', '25 hr 2 min'],
	[0.01, '1 sec', '00:01', '1 sec'],
	[59.1, '1 min', '01:00', '1 min']
];
for (const [seconds, detailed, clock, short] of formats) {
	check(format(seconds, 'natural') === `${detailed} remaining`, `natural ${seconds}`);
	check(format(seconds, 'detailed') === detailed, `detailed ${seconds}`);
	check(format(seconds, 'clock') === clock, `clock ${seconds}`);
	check(format(seconds, 'short') === short, `short ${seconds}`);
}
for (const input of [-10, NaN, Infinity, undefined]) check(format(input) === '0 sec remaining', 'invalid formatting input');
check(format(30, 'unknown') === '30 sec remaining', 'unknown format defaults natural');
check(format(60, 'natural', { minute: 'minute', remaining: 'Remaining: %s' }) === 'Remaining: 1 minute', 'injectable presentation labels');

// Deterministic upstream subscription double: deliberately has no scroll/timer/DOM API.
const upstream = {
	state: { ratio: 0.4 }, listeners: new Set(),
	subscribe(fn) { this.listeners.add(fn); if (this.state) fn(this.state); return () => this.listeners.delete(fn); },
	publish(ratio) { this.state = { ratio }; this.listeners.forEach(fn => fn(this.state)); }
};
const layer = new Layer(600);
layer.connect(upstream); layer.connect(upstream);
check(upstream.listeners.size === 1, 'one upstream subscription');
check(layer.state.remaining_seconds === 360 && layer.state.elapsed_seconds === 240, 'initial restored 40% consumed immediately');
const snapshots = [];
const unsubscribe = layer.subscribe(state => snapshots.push(state));
check(snapshots.length === 1 && snapshots[0] === layer.state, 'initial subscriber snapshot immediate');
upstream.publish(0.4);
check(snapshots.length === 1, 'unchanged ratio suppressed');
upstream.publish(0.5);
check(snapshots.length === 2 && layer.state.remaining_seconds === 300, 'live progress delivery');
upstream.publish(1);
check(layer.state.completed && layer.state.remaining_seconds === 0, 'completion');
upstream.publish(0.25);
check(!layer.state.completed && layer.state.remaining_seconds === 450, 'scrolling backwards updates estimate');
unsubscribe(); const count = snapshots.length; upstream.publish(0.6);
check(snapshots.length === count, 'unsubscribe');
layer.subscribe(() => { throw new Error('Consumer test failure'); });
let healthy = null;
layer.subscribe(state => { healthy = state; }); upstream.publish(0.75);
check(healthy === layer.state && healthy.remaining_seconds === 150, 'consumer exception isolated');
layer.destroy(); layer.destroy();
check(upstream.listeners.size === 0 && layer.listeners.size === 0 && layer.state === null, 'idempotent cleanup');
const pending = { ...upstream, state: null, listeners: new Set() };
const pendingLayer = new Layer(480); pendingLayer.connect(pending);
check(pendingLayer.state === null, 'unknown initial progress not invented');
pending.publish(0.25);
check(pendingLayer.state.remaining_seconds === 360, 'first measurement initializes layer without scroll requirement');
pendingLayer.destroy();
for (const [seconds, expected] of [[0,'00:00'],[1,'00:01'],[9,'00:09'],[30,'00:30'],[59,'00:59'],[60,'01:00'],[61,'01:01'],[90,'01:30'],[599,'09:59'],[600,'10:00'],[3599,'59:59'],[3600,'01:00:00'],[3661,'01:01:01']]) {
	check(format(seconds, 'clock') === expected, `countdown boundary ${seconds}`);
}
// Local date constructors pin the clock without hardcoding the reader timezone.
const local = (hour, minute, second = 0) => new Date(2026, 0, 15, hour, minute, second).getTime();
for (const [now, seconds, expected] of [
 [local(16,15),480,'4:23 PM'], [local(23,58),300,'12:03 AM'],
 [local(23,0),5400,'12:30 AM'], [local(0,0),1800,'12:30 AM'], [local(12,0),1800,'12:30 PM'],
 [local(16,15),0,'4:15 PM'], [local(16,15),1,'4:15 PM'], [local(16,15),59,'4:15 PM'],
 [local(16,15),60,'4:16 PM'], [local(16,15),3540,'5:14 PM'], [local(16,15),3600,'5:15 PM'],
 [local(16,15),59.49,'4:15 PM'], [local(16,15),59.5,'4:16 PM'], [local(16,15),245.7,'4:19 PM'],
 [local(16,15),172800+480,'4:23 PM'], [local(16,15,59),1,'4:16 PM']
]) check(formatFinishTime(seconds, now) === expected, 'Deterministic local finish timestamp: ' + expected);
for (const value of [NaN, Infinity, null, '600', -1]) check(formatFinishTime(value,local(16,15)) === '4:15 PM', 'Invalid remaining duration normalizes safely');
for (const value of [NaN, Infinity, null, 'now']) check(formatFinishTime(600,value) === '', 'Invalid clock fails safely');
check(formatFinishTime(Number.MAX_VALUE,local(16,15)) === '', 'Native Date overflow fails safely');
console.log(`PASS: ${checks} remaining-time assertions.`);
