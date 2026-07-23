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

document.querySelectorAll('[data-quest-adjust]').forEach((button) => {
    if (!(button instanceof HTMLButtonElement)) {
        return;
    }

    button.addEventListener('click', () => {
        const dialogId = button.dataset.dialogId;
        const dialog = dialogId ? document.getElementById(dialogId) : null;
        if (dialog instanceof HTMLDialogElement) {
            dialog.showModal();
        }
    });
});

document.querySelectorAll('.quest-dialog').forEach((dialog) => {
    if (!(dialog instanceof HTMLDialogElement)) {
        return;
    }

    dialog.addEventListener('click', (event) => {
        if (event.target === dialog) {
            dialog.close();
        }
    });
});

document.querySelectorAll('.realm-choice input[type="radio"]').forEach((input) => {
    if (!(input instanceof HTMLInputElement)) {
        return;
    }

    input.addEventListener('change', () => {
        const group = input.closest('.realm-choice-grid');
        group?.querySelectorAll('.realm-choice').forEach((choice) => {
            choice.classList.toggle('is-checked', choice.contains(input));
        });
    });
});

document.querySelectorAll('[data-quest-form]').forEach((form) => {
    if (!(form instanceof HTMLFormElement)) {
        return;
    }

    const frequency = form.querySelector('[data-frequency-select]');
    const dayPicker = form.querySelector('[data-schedule-days]');
    const days = [...form.querySelectorAll('input[name="scheduled_days[]"]')].filter((input) => input instanceof HTMLInputElement);

    const updateScheduleDays = () => {
        if (!(frequency instanceof HTMLSelectElement) || !(dayPicker instanceof HTMLElement)) {
            return;
        }

        const needsChoice = frequency.value === 'weekly' || frequency.value === 'custom';
        dayPicker.hidden = !needsChoice;

        if (frequency.value === 'weekly') {
            const selected = days.filter((day) => day.checked);
            if (selected.length !== 1) {
                days.forEach((day, index) => {
                    day.checked = index === 0;
                });
            }
        }
    };

    if (frequency instanceof HTMLSelectElement) {
        frequency.addEventListener('change', updateScheduleDays);
        updateScheduleDays();
    }

    form.querySelectorAll('[data-quest-template]').forEach((template) => {
        if (!(template instanceof HTMLButtonElement)) {
            return;
        }

        template.addEventListener('click', () => {
            const [name, realm, targetFrequency, duration, motivation] = (template.dataset.questTemplate || '').split('|');
            const nameField = form.querySelector('#habit_name');
            const durationField = form.querySelector('#duration_minutes');
            const motivationField = form.querySelector('#motivation');
            const realmField = form.querySelector(`input[name="realm"][value="${realm}"]`);

            if (nameField instanceof HTMLInputElement) nameField.value = name || '';
            if (durationField instanceof HTMLInputElement) durationField.value = duration || '';
            if (motivationField instanceof HTMLTextAreaElement) motivationField.value = motivation || '';
            if (frequency instanceof HTMLSelectElement && targetFrequency) {
                frequency.value = targetFrequency;
                updateScheduleDays();
            }
            if (realmField instanceof HTMLInputElement) {
                realmField.checked = true;
                realmField.dispatchEvent(new Event('change', { bubbles: true }));
            }
        });
    });
});
