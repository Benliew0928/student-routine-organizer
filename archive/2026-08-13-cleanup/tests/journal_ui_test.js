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

test('createSaveQueue runs autosaves sequentially', async () => {
    const order = [];
    const queue = journal.createSaveQueue(async (value) => {
        order.push(`start:${value}`);
        await new Promise((resolve) => setImmediate(resolve));
        order.push(`end:${value}`);
        return value;
    });

    const first = queue.run('first');
    const second = queue.run('second');
    assert.deepEqual(await Promise.all([first, second]), ['first', 'second']);
    assert.deepEqual(order, [
        'start:first',
        'end:first',
        'start:second',
        'end:second',
    ]);
});

test('createSaveQueue continues after a failed autosave', async () => {
    const queue = journal.createSaveQueue(async (value) => {
        if (value === 'fail') {
            throw new Error('offline');
        }
        return value;
    });

    await assert.rejects(queue.run('fail'), /offline/);
    assert.equal(await queue.run('recover'), 'recover');
});

test('submitAfterSuccessfulSave submits only after a successful flush', async () => {
    const events = [];

    await journal.submitAfterSuccessfulSave(
        async () => events.push('saved'),
        () => events.push('submitted')
    );

    assert.deepEqual(events, ['saved', 'submitted']);
});

test('submitAfterSuccessfulSave blocks submission when flushing fails', async () => {
    let submitted = false;

    await assert.rejects(
        journal.submitAfterSuccessfulSave(
            async () => {
                throw new Error('offline');
            },
            () => {
                submitted = true;
            }
        ),
        /offline/
    );

    assert.equal(submitted, false);
});
