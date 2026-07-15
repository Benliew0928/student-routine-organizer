document.addEventListener('click', (event) => {
    const target = event.target;

    if (target instanceof HTMLElement && target.matches('[data-confirm]')) {
        const message = target.getAttribute('data-confirm') || 'Are you sure?';
        if (!window.confirm(message)) {
            event.preventDefault();
        }
    }
});

document.querySelectorAll('.nav-links a').forEach((link) => {
    if (!(link instanceof HTMLAnchorElement)) {
        return;
    }

    const currentPath = window.location.pathname.replace(/\/index\.php$/, '/');
    const linkPath = new URL(link.href, window.location.origin).pathname.replace(/\/index\.php$/, '/');

    if (currentPath === linkPath) {
        link.classList.add('is-active');
        link.setAttribute('aria-current', 'page');
    }
});

document.querySelectorAll('.button').forEach((button) => {
    button.addEventListener('pointerdown', () => {
        button.classList.add('is-pressing');
    });

    ['pointerup', 'pointerleave', 'blur'].forEach((eventName) => {
        button.addEventListener(eventName, () => {
            button.classList.remove('is-pressing');
        });
    });
});

const filterDrawer = document.querySelector('[data-filter-drawer]');
const filterBackdrop = document.querySelector('[data-filter-backdrop]');
const filterOpenButtons = document.querySelectorAll('[data-filter-open]');
const filterCloseButtons = document.querySelectorAll('[data-filter-close]');

function setFilterDrawer(open) {
    if (!(filterDrawer instanceof HTMLElement)) {
        return;
    }

    filterDrawer.classList.toggle('is-open', open);
    filterDrawer.setAttribute('aria-hidden', open ? 'false' : 'true');
    document.body.classList.toggle('has-open-drawer', open);

    if (filterBackdrop instanceof HTMLElement) {
        filterBackdrop.hidden = !open;
    }

    filterOpenButtons.forEach((button) => {
        if (button instanceof HTMLElement) {
            button.setAttribute('aria-expanded', open ? 'true' : 'false');
        }
    });

    if (open) {
        const firstField = filterDrawer.querySelector('input, select, button, a');
        if (firstField instanceof HTMLElement) {
            firstField.focus();
        }
    }
}

filterOpenButtons.forEach((button) => {
    button.addEventListener('click', () => setFilterDrawer(true));
});

filterCloseButtons.forEach((button) => {
    button.addEventListener('click', () => setFilterDrawer(false));
});

if (filterBackdrop instanceof HTMLElement) {
    filterBackdrop.addEventListener('click', () => setFilterDrawer(false));
}

document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
        setFilterDrawer(false);
    }
});

function journalStorageGet(key) {
    try {
        return window.localStorage.getItem(key);
    } catch (error) {
        return null;
    }
}

function journalStorageSet(key, value) {
    try {
        window.localStorage.setItem(key, value);
    } catch (error) {
        // The server-side form remains fully usable when browser storage is unavailable.
    }
}

function journalStorageRemove(key) {
    try {
        window.localStorage.removeItem(key);
    } catch (error) {
        // Nothing else is required when browser storage is unavailable.
    }
}

const journalSavedMarker = document.querySelector('[data-journal-saved]');
if (journalSavedMarker instanceof HTMLElement) {
    const savedDraftKey = journalSavedMarker.dataset.draftKey || '';
    if (savedDraftKey !== '') {
        journalStorageRemove(savedDraftKey);
    }
}

document.querySelectorAll('[data-journal-logout]').forEach((link) => {
    link.addEventListener('click', () => {
        if (!(link instanceof HTMLElement)) {
            return;
        }

        const userId = link.dataset.journalUser || '';
        if (userId !== '') {
            journalStorageRemove(`journalDraft:${userId}:create`);
        }
    });
});

const journalEditor = document.querySelector('[data-journal-editor]');
const journalWordCount = document.querySelector('[data-word-count]');
const journalCharacterCount = document.querySelector('[data-character-count]');

function updateJournalEditor() {
    if (!(journalEditor instanceof HTMLTextAreaElement)) {
        return;
    }

    const content = journalEditor.value;
    const words = content.trim() === '' ? 0 : content.trim().split(/\s+/u).length;

    if (journalWordCount instanceof HTMLElement) {
        journalWordCount.textContent = String(words);
    }

    if (journalCharacterCount instanceof HTMLElement) {
        journalCharacterCount.textContent = String(content.length);
    }

    journalEditor.style.height = 'auto';
    journalEditor.style.height = `${Math.min(Math.max(journalEditor.scrollHeight, 280), 900)}px`;
}

if (journalEditor instanceof HTMLTextAreaElement) {
    journalEditor.addEventListener('input', updateJournalEditor);
    updateJournalEditor();
}

const journalForm = document.querySelector('[data-journal-form]');
const templatePicker = document.querySelector('[data-template-picker]');
const templateInput = document.querySelector('[data-template-input]');
const journalDraftBanner = document.querySelector('[data-journal-draft-banner]');
const journalDraftRestoreButtons = document.querySelectorAll('[data-journal-draft-restore]');
const journalDraftDiscardButtons = document.querySelectorAll('[data-journal-draft-discard]');

function setSelectedJournalTemplate(templateKey) {
    if (templateInput instanceof HTMLInputElement) {
        templateInput.value = templateKey;
    }

    if (!(templatePicker instanceof HTMLElement)) {
        return;
    }

    templatePicker.querySelectorAll('[data-template-key]').forEach((button) => {
        if (!(button instanceof HTMLButtonElement)) {
            return;
        }

        const selected = button.dataset.templateKey === templateKey;
        button.classList.toggle('is-selected', selected);
        button.setAttribute('aria-pressed', selected ? 'true' : 'false');
    });
}

function journalDraftData() {
    if (!(journalForm instanceof HTMLFormElement)) {
        return null;
    }

    const title = journalForm.querySelector('[data-journal-title]');
    const mood = journalForm.querySelector('[data-journal-mood]');
    const entryDate = journalForm.querySelector('[data-journal-date]');

    return {
        title: title instanceof HTMLInputElement ? title.value : '',
        mood_status: mood instanceof HTMLInputElement ? mood.value : '',
        entry_date: entryDate instanceof HTMLInputElement ? entryDate.value : '',
        content: journalEditor instanceof HTMLTextAreaElement ? journalEditor.value : '',
        template_key: templateInput instanceof HTMLInputElement ? templateInput.value : 'blank',
    };
}

function saveJournalDraft() {
    if (!(journalForm instanceof HTMLFormElement)) {
        return;
    }

    const draftKey = journalForm.dataset.draftKey || '';
    const draft = journalDraftData();
    if (draftKey === '' || draft === null) {
        return;
    }

    const hasContent = Object.entries(draft).some(([key, value]) => key !== 'template_key' && String(value).trim() !== '');
    if (hasContent) {
        journalStorageSet(draftKey, JSON.stringify(draft));
    } else {
        journalStorageRemove(draftKey);
    }
}

function readJournalDraft() {
    if (!(journalForm instanceof HTMLFormElement)) {
        return null;
    }

    const draftKey = journalForm.dataset.draftKey || '';
    const saved = draftKey === '' ? null : journalStorageGet(draftKey);
    if (saved === null) {
        return null;
    }

    try {
        const parsed = JSON.parse(saved);
        return parsed && typeof parsed === 'object' ? parsed : null;
    } catch (error) {
        journalStorageRemove(draftKey);
        return null;
    }
}

function restoreJournalDraft(draft) {
    if (!(journalForm instanceof HTMLFormElement) || draft === null) {
        return;
    }

    const title = journalForm.querySelector('[data-journal-title]');
    const mood = journalForm.querySelector('[data-journal-mood]');
    const entryDate = journalForm.querySelector('[data-journal-date]');

    if (title instanceof HTMLInputElement) {
        title.value = typeof draft.title === 'string' ? draft.title : '';
    }
    if (mood instanceof HTMLInputElement) {
        mood.value = typeof draft.mood_status === 'string' ? draft.mood_status : '';
    }
    if (entryDate instanceof HTMLInputElement && typeof draft.entry_date === 'string' && draft.entry_date !== '') {
        entryDate.value = draft.entry_date;
    }
    if (journalEditor instanceof HTMLTextAreaElement) {
        journalEditor.value = typeof draft.content === 'string' ? draft.content : '';
    }

    setSelectedJournalTemplate(typeof draft.template_key === 'string' ? draft.template_key : 'blank');
    updateJournalEditor();

    if (journalDraftBanner instanceof HTMLElement) {
        journalDraftBanner.hidden = true;
    }
}

if (journalForm instanceof HTMLFormElement) {
    const savedDraft = readJournalDraft();
    if (savedDraft !== null && journalDraftBanner instanceof HTMLElement) {
        journalDraftBanner.hidden = false;
    }

    journalForm.addEventListener('input', saveJournalDraft);
    journalForm.addEventListener('change', saveJournalDraft);

    journalDraftRestoreButtons.forEach((button) => {
        button.addEventListener('click', () => restoreJournalDraft(readJournalDraft()));
    });

    journalDraftDiscardButtons.forEach((button) => {
        button.addEventListener('click', () => {
            const draftKey = journalForm.dataset.draftKey || '';
            if (draftKey !== '') {
                journalStorageRemove(draftKey);
            }
            if (journalDraftBanner instanceof HTMLElement) {
                journalDraftBanner.hidden = true;
            }
        });
    });
}

if (templatePicker instanceof HTMLElement) {
    templatePicker.querySelectorAll('[data-template-key]').forEach((button) => {
        button.addEventListener('click', () => {
            if (!(button instanceof HTMLButtonElement) || !(journalEditor instanceof HTMLTextAreaElement)) {
                return;
            }

            const templateKey = button.dataset.templateKey || 'blank';
            const templateContent = button.dataset.templateContent || '';
            const currentTemplate = templateInput instanceof HTMLInputElement ? templateInput.value : 'blank';
            const hasWriting = journalEditor.value.trim() !== '' && journalEditor.value !== templateContent;

            if (templateKey !== currentTemplate && hasWriting) {
                const confirmed = window.confirm('Replace your current journal content with this template?');
                if (!confirmed) {
                    return;
                }
            }

            journalEditor.value = templateContent;
            setSelectedJournalTemplate(templateKey);
            updateJournalEditor();
            saveJournalDraft();
            journalEditor.focus();
        });
    });
}
