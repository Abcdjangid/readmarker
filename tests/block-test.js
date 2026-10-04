'use strict';
const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
let checks = 0;
function check(value, message) { assert.ok(value, message); checks++; }
let registered;
const wp = { blocks: { registerBlockType(name, settings) { registered = { name, settings }; } }, element: { Fragment: 'Fragment', createElement(type, props, ...children) { return { type, props: props || {}, children: children.flat() }; } }, i18n: { __(text) { return text; } }, blockEditor: { InspectorControls: 'InspectorControls', useBlockProps(props) { return props; } }, components: { SelectControl: 'SelectControl', ToggleControl: 'ToggleControl', PanelBody: 'PanelBody' } };
vm.runInNewContext(fs.readFileSync('blocks/readflow/index.js', 'utf8'), { window: { wp } });
const metadata = JSON.parse(fs.readFileSync('blocks/readflow/block.json'));
check(registered.name === metadata.name, 'Native block registration');
const settings = registered.settings;
check(settings.apiVersion === 3 && metadata.apiVersion === 3, 'Editor and server both declare iframe-compatible API v3');
check(JSON.stringify(settings.attributes) === JSON.stringify(metadata.attributes), 'Editor and server schemas identical');
check(settings.save() === null, 'Dynamic block saves no calculated HTML');
function walk(node) { return node && typeof node === 'object' ? [node, ...node.children.flatMap(walk)] : []; }
for (const type of metadata.attributes.type.enum) {
 let changes;
 const attributes = { type, format: type === 'remaining' ? 'natural' : 'short', showLabel: true, showPercentage: true };
 const tree = settings.edit({ attributes, setAttributes(value) { changes = value; } });
 const nodes = walk(tree);
 const selector = nodes.find(node => node.props.label === 'Display Type');
 check(selector.props.value === type && selector.props.options.length === 5, 'Accessible five-type selector');
 selector.props.onChange('remaining');
 check(changes.type === 'remaining' && changes.format === 'natural', 'Type change resets relevant format');
 const format = nodes.find(node => node.props.label === 'Format');
 check(!!format === ['reading_time', 'remaining'].includes(type), 'Only relevant format controls');
 if (format) { format.props.onChange('short'); check(changes.format === 'short', 'Format change saved'); }
 const toggle = nodes.find(node => node.type === 'ToggleControl');
 check(!!toggle === ['word_count', 'progress'].includes(type), 'Only relevant boolean controls');
 if (toggle) { toggle.props.onChange(false); check(changes[type === 'word_count' ? 'showLabel' : 'showPercentage'] === false, 'Boolean configuration'); }
 check(JSON.stringify(tree).includes('Illustrative preview'), 'Preview clearly distinguished from actual data');
 check(JSON.stringify(settings.edit({ attributes, setAttributes() {} })) === JSON.stringify(tree), 'Multiple instances have no shared mutable editor state');
}
check(walk(settings.edit({ attributes: { type: '<script>' }, setAttributes() {} })).find(node => node.props.label === 'Display Type').props.value === 'reading_time', 'Invalid editor type safe');
check(!/setInterval|setTimeout|requestAnimationFrame|addEventListener|ReadFlowProgress/.test(fs.readFileSync('blocks/readflow/index.js', 'utf8')), 'Editor adds no frontend runtime or timers');
console.log(`PASS: ${checks} block editor assertions.`);
