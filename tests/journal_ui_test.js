'use strict';

const test = require('node:test');
const assert = require('node:assert/strict');
const journal = require('../assets/js/journal.js');

test('countWords ignores surrounding whitespace and counts separated words', () => {
    assert.equal(journal.countWords('  one\n two   three  '), 3);
    assert.equal(journal.countWords('   '), 0);
});

test('nextTemplateState applies a template to an empty editor', () => {
    assert.deepEqual(
        journal.nextTemplateState('', 'Daily prompt', false),
        { applied: true, content: 'Daily prompt' }
    );
});

test('nextTemplateState preserves writing when replacement is cancelled', () => {
    assert.deepEqual(
        journal.nextTemplateState('My own writing', 'Gratitude prompt', false),
        { applied: false, content: 'My own writing' }
    );
});

test('nextTemplateState replaces writing after confirmation', () => {
    assert.deepEqual(
        journal.nextTemplateState('My own writing', 'Gratitude prompt', true),
        { applied: true, content: 'Gratitude prompt' }
    );
});

test('hasMeaningfulDraft ignores the default date alone', () => {
    assert.equal(journal.hasMeaningfulDraft({
        title: '',
        content: '',
        mood_status: '',
        entry_date: '2026-07-23',
        template_key: 'blank',
    }), false);
    assert.equal(journal.hasMeaningfulDraft({
        title: '',
        content: 'A sentence',
        mood_status: '',
        entry_date: '2026-07-23',
        template_key: 'blank',
    }), true);
});
