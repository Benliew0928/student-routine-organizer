'use strict';

(function exposeJournalCore(root, factory) {
    const api = factory();

    if (typeof module === 'object' && module.exports) {
        module.exports = api;
    }

    if (root) {
        root.JournalDrafts = api;
    }
}(typeof globalThis !== 'undefined' ? globalThis : this, function buildJournalCore() {
    function countWords(content) {
        const normalized = String(content || '').trim();
        return normalized === '' ? 0 : normalized.split(/\s+/u).length;
    }

    function nextTemplateState(currentContent, nextContent, confirmed) {
        const current = String(currentContent || '');
        const next = String(nextContent || '');
        const replacementRequired = current.trim() !== '' && current !== next;

        return replacementRequired && !confirmed
            ? { applied: false, content: current }
            : { applied: true, content: next };
    }

    function hasMeaningfulDraft(draft) {
        return String(draft.title || '').trim() !== ''
            || String(draft.content || '').trim() !== ''
            || String(draft.mood_status || '').trim() !== ''
            || String(draft.template_key || 'blank') !== 'blank';
    }

    function createSaveQueue(saveOperation) {
        let tail = Promise.resolve();

        return {
            run(payload) {
                const operation = tail
                    .catch(() => undefined)
                    .then(() => saveOperation(payload));
                tail = operation;
                return operation;
            },
            wait() {
                return tail;
            },
        };
    }

    return {
        countWords,
        nextTemplateState,
        hasMeaningfulDraft,
        createSaveQueue,
    };
}));

(function initializeJournalPage() {
    if (typeof document === 'undefined') {
        return;
    }

    const core = globalThis.JournalDrafts;
    const form = document.querySelector('[data-journal-form]');
    const editor = document.querySelector('[data-journal-editor]');
    const templatePicker = document.querySelector('[data-template-picker]');
    const templateInput = document.querySelector('[data-template-input]');
    const wordCount = document.querySelector('[data-word-count]');
    const characterCount = document.querySelector('[data-character-count]');
    const draftIdInput = form?.querySelector('[data-draft-id]');
    const saveStatus = form?.querySelector('[data-journal-save-status]');
    const saveText = form?.querySelector('[data-journal-save-text]');
    const retryButton = form?.querySelector('[data-journal-save-retry]');
    const titleField = form?.querySelector('[data-journal-title]');
    const moodField = form?.querySelector('[data-journal-mood]');
    const dateField = form?.querySelector('[data-journal-date]');

    if (!(form instanceof HTMLFormElement) || !(editor instanceof HTMLTextAreaElement)) {
        return;
    }

    const autosaveUrl = form.dataset.autosaveUrl || '';
    let revision = 0;
    let savedRevision = 0;
    let timerId = null;
    let activeSave = Promise.resolve();
    let submitting = false;

    function updateEditorMetrics() {
        if (wordCount instanceof HTMLElement) {
            wordCount.textContent = String(core.countWords(editor.value));
        }

        if (characterCount instanceof HTMLElement) {
            characterCount.textContent = String(editor.value.length);
        }

        editor.style.height = 'auto';
        editor.style.height = `${Math.min(Math.max(editor.scrollHeight, 280), 900)}px`;
    }

    function setSelectedTemplate(templateKey) {
        if (templateInput instanceof HTMLInputElement) {
            templateInput.value = templateKey;
        }

        templatePicker?.querySelectorAll('[data-template-key]').forEach((button) => {
            const selected = button instanceof HTMLButtonElement
                && button.dataset.templateKey === templateKey;
            button.classList.toggle('is-selected', selected);
            button.setAttribute('aria-pressed', selected ? 'true' : 'false');
        });
    }

    templatePicker?.querySelectorAll('[data-template-key]').forEach((button) => {
        button.addEventListener('click', () => {
            if (!(button instanceof HTMLButtonElement)) {
                return;
            }

            const nextContent = button.dataset.templateContent || '';
            const replacementRequired = editor.value.trim() !== ''
                && editor.value !== nextContent;
            const confirmed = !replacementRequired || window.confirm(
                'Replace your current journal content with this template?'
            );
            const next = core.nextTemplateState(editor.value, nextContent, confirmed);

            if (!next.applied) {
                return;
            }

            editor.value = next.content;
            setSelectedTemplate(button.dataset.templateKey || 'blank');
            editor.dispatchEvent(new Event('input', { bubbles: true }));
            editor.focus();
        });
    });

    editor.addEventListener('input', updateEditorMetrics);
    updateEditorMetrics();

    function collectDraft() {
        return {
            csrf_token: form.querySelector('input[name="csrf_token"]')?.value || '',
            draft_id: draftIdInput instanceof HTMLInputElement ? draftIdInput.value : '',
            title: titleField instanceof HTMLInputElement ? titleField.value : '',
            content: editor.value,
            mood_status: moodField instanceof HTMLInputElement ? moodField.value : '',
            entry_date: dateField instanceof HTMLInputElement ? dateField.value : '',
            template_key: templateInput instanceof HTMLInputElement ? templateInput.value : 'blank',
        };
    }

    function setSaveState(state, message) {
        if (saveStatus instanceof HTMLElement) {
            saveStatus.dataset.state = state;
        }

        if (saveText instanceof HTMLElement) {
            saveText.textContent = message;
        }

        if (retryButton instanceof HTMLButtonElement) {
            retryButton.hidden = state !== 'error';
        }
    }

    async function sendDraft({ draft, targetRevision }) {
        if (autosaveUrl === '') {
            throw new Error('Draft autosave is unavailable.');
        }

        setSaveState('saving', 'Saving...');
        const response = await fetch(autosaveUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',
            },
            body: new URLSearchParams(draft),
        });
        const result = await response.json();

        if (!response.ok || result.success !== true) {
            throw new Error(result.message || 'Could not save the draft.');
        }

        if (draftIdInput instanceof HTMLInputElement) {
            draftIdInput.value = String(result.draft_id);
        }

        const resumedUrl = new URL(window.location.href);
        resumedUrl.searchParams.set('draft_id', String(result.draft_id));
        window.history.replaceState({}, '', resumedUrl);
        savedRevision = Math.max(savedRevision, targetRevision);

        if (revision === targetRevision) {
            setSaveState('saved', result.saved_label);
        } else {
            queueAutosave(0);
        }
    }

    const saveQueue = core.createSaveQueue(sendDraft);

    function runSave(targetRevision) {
        const draft = collectDraft();

        if (!draft.draft_id && !core.hasMeaningfulDraft(draft)) {
            savedRevision = targetRevision;
            setSaveState('idle', 'Not saved yet');
            return Promise.resolve();
        }

        const operation = saveQueue.run({ draft, targetRevision });
        activeSave = operation.catch((error) => {
            setSaveState('error', "Couldn't save draft");
            throw error;
        });

        return activeSave;
    }

    function queueAutosave(delay = 900) {
        window.clearTimeout(timerId);
        setSaveState('idle', 'Not saved yet');
        timerId = window.setTimeout(() => {
            runSave(revision).catch(() => undefined);
        }, delay);
    }

    function markDirty() {
        revision += 1;
        queueAutosave();
    }

    async function flushAutosave() {
        window.clearTimeout(timerId);

        if (revision > savedRevision) {
            await runSave(revision);
            return;
        }

        await activeSave;
    }

    form.addEventListener('input', markDirty);
    form.addEventListener('change', markDirty);

    retryButton?.addEventListener('click', () => {
        queueAutosave(0);
    });

    form.addEventListener('submit', (event) => {
        if (submitting) {
            return;
        }

        event.preventDefault();
        const submitter = event.submitter;
        flushAutosave()
            .catch(() => undefined)
            .finally(() => {
                submitting = true;
                form.requestSubmit(submitter instanceof HTMLElement ? submitter : undefined);
            });
    });

    window.addEventListener('beforeunload', (event) => {
        if (!submitting && revision > savedRevision) {
            event.preventDefault();
            event.returnValue = '';
        }
    });
}());
