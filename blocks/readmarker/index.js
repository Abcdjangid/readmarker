/* No build step: WordPress supplies the editor packages. No frontend runtime here. */
(function (wp) {
 'use strict';
 const el = wp.element.createElement;
 const __ = wp.i18n.__;
 const types = [ ['reading_time', 'Reading Time'], ['word_count', 'Word Count'], ['progress', 'Progress'], ['remaining', 'Remaining Time'], ['combined', 'Combined'] ];
 const settings = {
  apiVersion: 3, title: __('ReadMarker', 'readmarker'), description: __('Display reading time, progress, word count, or remaining reading time.', 'readmarker'), category: 'widgets', icon: 'clock',
  attributes: { type: { type: 'string', default: 'reading_time', enum: types.map(item => item[0]) }, format: { type: 'string', default: 'short', enum: ['short', 'long', 'natural', 'clock', 'detailed'] }, showLabel: { type: 'boolean', default: true }, showPercentage: { type: 'boolean', default: true } },
  supports: { html: false, customClassName: false },
  edit: function (props) {
   const a = props.attributes;
   const type = types.some(item => item[0] === a.type) ? a.type : 'reading_time';
   const controls = [el(wp.components.SelectControl, { key: 'type', label: __('Display Type', 'readmarker'), value: type, options: types.map(item => ({ value: item[0], label: __(item[1], 'readmarker') })), onChange: value => props.setAttributes({ type: value, format: value === 'remaining' ? 'natural' : 'short' }) })];
   if (type === 'reading_time' || type === 'remaining') {
    const formats = type === 'remaining' ? ['natural', 'short', 'clock', 'detailed'] : ['short', 'long'];
    controls.push(el(wp.components.SelectControl, { key: 'format', label: __('Format', 'readmarker'), value: formats.includes(a.format) ? a.format : formats[0], options: formats.map(value => ({ value, label: __(value.charAt(0).toUpperCase() + value.slice(1), 'readmarker') })), onChange: format => props.setAttributes({ format }) }));
   }
   if (type === 'word_count' || type === 'progress') {
    const key = type === 'word_count' ? 'showLabel' : 'showPercentage';
    controls.push(el(wp.components.ToggleControl, { key, label: type === 'word_count' ? __('Show label', 'readmarker') : __('Show percentage', 'readmarker'), checked: a[key] !== false, onChange: value => props.setAttributes({ [key]: !!value }) }));
   }
   const examples = {
    reading_time: a.format === 'long' ? __('7 min read', 'readmarker') : __('7 min', 'readmarker'),
    word_count: a.showLabel === false ? '1,240' : __('1,240 words', 'readmarker'),
    progress: a.showPercentage === false ? __('Progress bar', 'readmarker') : '65%',
    remaining: ({ short: '3 min', clock: '03:00', detailed: '3 min 0 sec' })[a.format] || __('3 min remaining', 'readmarker'),
    combined: __('7 min read � 1,240 words', 'readmarker')
   };
   return el(wp.element.Fragment, null,
    el(wp.blockEditor.InspectorControls, null, el(wp.components.PanelBody, { title: __('ReadMarker', 'readmarker') }, controls)),
    el('div', wp.blockEditor.useBlockProps({ className: 'readmarker-block' }),
     el('strong', null, __('ReadMarker', 'readmarker')),
     el('p', null, examples[type]),
     el('small', null, __('Illustrative preview. The published article supplies the actual reading data; progress updates on the frontend.', 'readmarker'))));
  },
  save: function () { return null; }
 };
 // Existing saved blocks remain editable without adding a second inserter entry.
 wp.blocks.registerBlockType('readflow/readflow', Object.assign({}, settings, { supports: Object.assign({}, settings.supports, { inserter: false }) }));
 wp.blocks.registerBlockType('readmarker/readmarker', settings);
}(window.wp));
