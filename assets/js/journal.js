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

    return {
        countWords,
        nextTemplateState,
        hasMeaningfulDraft,
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

    if (!(form instanceof HTMLFormElement) || !(editor instanceof HTMLTextAreaElement)) {
        return;
    }

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
}());
