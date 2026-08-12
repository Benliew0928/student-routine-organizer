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

document.querySelectorAll('[data-pie-tooltip]').forEach((chart) => {
    if (!(chart instanceof HTMLElement)) {
        return;
    }

    let slices = [];

    try {
        slices = JSON.parse(chart.dataset.pieSlices || '[]');
    } catch (error) {
        slices = [];
    }

    const defaultTooltip = chart.dataset.pieTooltip || '';
    const setTooltip = (value) => {
        chart.dataset.pieTooltip = value;
        chart.setAttribute('title', value);
    };

    const formatCalories = (value) => Number(value || 0).toLocaleString();

    setTooltip(defaultTooltip);

    chart.addEventListener('pointermove', (event) => {
        if (!slices.length) {
            return;
        }

        const rect = chart.getBoundingClientRect();
        const centerX = rect.left + rect.width / 2;
        const centerY = rect.top + rect.height / 2;
        const angle = (Math.atan2(event.clientY - centerY, event.clientX - centerX) * 180 / Math.PI + 90 + 360) % 360;
        const percent = angle / 3.6;
        const activeSlice = slices.find((slice) => percent >= Number(slice.start) && percent < Number(slice.end));

        if (activeSlice) {
            setTooltip(`${activeSlice.activity}: ${formatCalories(activeSlice.calories)} kcal`);
        }
    });

    chart.addEventListener('pointerleave', () => setTooltip(defaultTooltip));
    chart.addEventListener('blur', () => setTooltip(defaultTooltip));
});

document.querySelectorAll('[data-exercise-routine]').forEach((card) => {
    if (!(card instanceof HTMLElement)) {
        return;
    }

    const durationDisplay = card.querySelector('[data-exercise-value="duration"]');
    const caloriesDisplay = card.querySelector('[data-exercise-value="calories"]');
    const durationInput = card.querySelector('[data-exercise-input="duration"]');
    const caloriesInput = card.querySelector('[data-exercise-input="calories"]');

    if (!(durationDisplay instanceof HTMLElement)
        || !(caloriesDisplay instanceof HTMLElement)
        || !(durationInput instanceof HTMLInputElement)
        || !(caloriesInput instanceof HTMLInputElement)) {
        return;
    }

    const formatNumber = (value) => Number(value || 0).toLocaleString();
    const clamp = (value, min, max) => Math.max(min, Math.min(max, value));

    card.querySelectorAll('[data-exercise-adjust]').forEach((button) => {
        if (!(button instanceof HTMLButtonElement)) {
            return;
        }

        button.addEventListener('click', () => {
            const target = button.dataset.exerciseAdjust;
            const direction = button.dataset.direction === 'down' ? -1 : 1;

            if (target === 'duration') {
                const nextValue = clamp(Number(durationInput.value || 0) + direction, 1, 1440);
                durationInput.value = String(nextValue);
                durationDisplay.textContent = formatNumber(nextValue);
            }

            if (target === 'calories') {
                const nextValue = clamp(Number(caloriesInput.value || 0) + direction, 0, 20000);
                caloriesInput.value = String(nextValue);
                caloriesDisplay.textContent = formatNumber(nextValue);
            }
        });
    });
});

document.querySelectorAll('#activity_type').forEach((select) => {
    if (!(select instanceof HTMLSelectElement)) {
        return;
    }

    const form = select.closest('form');
    const customWrap = form?.querySelector('[data-custom-activity-wrap]');
    const customInput = customWrap?.querySelector('input[name="custom_activity_type"]');

    if (!(customWrap instanceof HTMLElement) || !(customInput instanceof HTMLInputElement)) {
        return;
    }

    const toggleCustomActivity = () => {
        const needsCustomName = select.value === 'Other';
        customWrap.hidden = !needsCustomName;
        customInput.required = needsCustomName;

        if (!needsCustomName) {
            customInput.value = '';
        }
    };

    select.addEventListener('change', toggleCustomActivity);
    toggleCustomActivity();
});

document.querySelectorAll('[data-bmi-calculator]').forEach((form) => {
    if (!(form instanceof HTMLFormElement)) {
        return;
    }

    const heightInput = form.querySelector('input[name="height"]');
    const weightInput = form.querySelector('input[name="weight"]');
    const result = document.querySelector('[data-bmi-result]');

    if (!(heightInput instanceof HTMLInputElement)
        || !(weightInput instanceof HTMLInputElement)
        || !(result instanceof HTMLElement)) {
        return;
    }

    const value = result.querySelector('strong');
    const note = result.querySelector('p');

    const bmiCategory = (bmi) => {
        if (bmi < 18.5) {
            return 'Underweight range';
        }
        if (bmi < 25) {
            return 'Healthy range';
        }
        if (bmi < 30) {
            return 'Overweight range';
        }
        return 'Obesity range';
    };

    form.addEventListener('submit', (event) => {
        event.preventDefault();

        const heightCm = Number(heightInput.value);
        const weightKg = Number(weightInput.value);

        if (!heightCm || !weightKg || heightCm <= 0 || weightKg <= 0) {
            if (value) {
                value.textContent = '--';
            }
            if (note) {
                note.textContent = 'Please enter a valid height and weight.';
            }
            return;
        }

        const heightM = heightCm / 100;
        const bmi = weightKg / (heightM * heightM);

        if (value) {
            value.textContent = bmi.toFixed(1);
        }
        if (note) {
            note.textContent = bmiCategory(bmi);
        }
    });
});

document.querySelectorAll('[data-exercise-goals]').forEach((form) => {
    if (!(form instanceof HTMLFormElement)) {
        return;
    }

    const card = form.closest('.exercise-goal-card');
    const calorieInput = form.querySelector('[data-goal-input="calories"]');
    const minuteInput = form.querySelector('[data-goal-input="minutes"]');

    if (!(card instanceof HTMLElement)
        || !(calorieInput instanceof HTMLInputElement)
        || !(minuteInput instanceof HTMLInputElement)) {
        return;
    }

    const storageKey = 'exerciseProgressGoals';
    const formatNumber = (value) => Number(value || 0).toLocaleString();

    const setMeter = (type, goal) => {
        const meter = card.querySelector(`[data-goal-meter="${type}"]`);
        const target = card.querySelector(`[data-goal-target="${type}"]`);

        if (!(meter instanceof HTMLElement) || !(target instanceof HTMLElement)) {
            return;
        }

        const current = Number(meter.dataset.current || 0);
        const safeGoal = Math.max(1, Number(goal || 1));
        const progress = Math.min(100, Math.round((current / safeGoal) * 100));
        meter.style.setProperty('--goal-progress', `${progress}%`);
        target.textContent = type === 'calories'
            ? `of ${formatNumber(safeGoal)} kcal`
            : `of ${formatNumber(safeGoal)} active minutes`;
    };

    const applyGoals = () => {
        const goals = {
            calories: Math.max(1, Number(calorieInput.value || 1)),
            minutes: Math.max(1, Number(minuteInput.value || 1)),
        };

        calorieInput.value = String(goals.calories);
        minuteInput.value = String(goals.minutes);
        setMeter('calories', goals.calories);
        setMeter('minutes', goals.minutes);
        window.localStorage.setItem(storageKey, JSON.stringify(goals));
    };

    try {
        const savedGoals = JSON.parse(window.localStorage.getItem(storageKey) || '{}');
        if (savedGoals.calories) {
            calorieInput.value = String(savedGoals.calories);
        }
        if (savedGoals.minutes) {
            minuteInput.value = String(savedGoals.minutes);
        }
    } catch (error) {
        window.localStorage.removeItem(storageKey);
    }

    applyGoals();
    form.addEventListener('input', applyGoals);
    form.addEventListener('submit', (event) => {
        event.preventDefault();
        applyGoals();
    });
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

document.querySelectorAll('[data-time-picker]').forEach((picker) => {
    if (!(picker instanceof HTMLElement)) {
        return;
    }

    const valueField = picker.querySelector('[data-time-value]');
    const hourField = picker.querySelector('[data-time-hour]');
    const minuteField = picker.querySelector('[data-time-minute]');
    const periodButtons = [...picker.querySelectorAll('[data-time-period]')].filter((button) => button instanceof HTMLButtonElement);
    const clearButton = picker.querySelector('[data-time-clear]');
    const form = picker.closest('form');
    let period = 'AM';

    if (!(valueField instanceof HTMLInputElement) || !(hourField instanceof HTMLInputElement) || !(minuteField instanceof HTMLInputElement)) {
        return;
    }

    const updatePeriodButtons = () => {
        periodButtons.forEach((button) => {
            const isActive = button.dataset.timePeriod === period;
            button.setAttribute('aria-pressed', String(isActive));
        });
    };

    const clearInvalidState = () => {
        picker.classList.remove('has-invalid');
        hourField.removeAttribute('aria-invalid');
        minuteField.removeAttribute('aria-invalid');
    };

    const showInvalidState = () => {
        picker.classList.add('has-invalid');
        hourField.setAttribute('aria-invalid', 'true');
        minuteField.setAttribute('aria-invalid', 'true');
    };

    const syncValue = () => {
        const hourText = hourField.value.trim();
        const minuteText = minuteField.value.trim();

        if (!hourText && !minuteText) {
            valueField.value = '';
            clearInvalidState();
            return true;
        }

        const hour = Number(hourText);
        const minute = Number(minuteText);
        const isValid = Number.isInteger(hour) && Number.isInteger(minute) && hour >= 1 && hour <= 12 && minute >= 0 && minute <= 59;

        if (!isValid) {
            valueField.value = '';
            showInvalidState();
            return false;
        }

        const hour24 = period === 'PM' ? (hour % 12) + 12 : hour === 12 ? 0 : hour;
        valueField.value = `${String(hour24).padStart(2, '0')}:${String(minute).padStart(2, '0')}`;
        clearInvalidState();
        return true;
    };

    const normaliseField = (field, maximum) => {
        const value = Number(field.value);
        if (field.value.trim() && Number.isInteger(value) && value >= 0 && value <= maximum) {
            field.value = String(value).padStart(2, '0');
        }
    };

    const hydrateFromValue = () => {
        const match = /^(\d{2}):(\d{2})$/.exec(valueField.value);
        if (!match) {
            updatePeriodButtons();
            return;
        }

        const hour24 = Number(match[1]);
        const minute = Number(match[2]);
        period = hour24 >= 12 ? 'PM' : 'AM';
        const hour12 = hour24 % 12 || 12;
        hourField.value = String(hour12).padStart(2, '0');
        minuteField.value = String(minute).padStart(2, '0');
        updatePeriodButtons();
        syncValue();
    };

    [hourField, minuteField].forEach((field) => {
        field.addEventListener('input', () => {
            field.value = field.value.replace(/\D/g, '').slice(0, 2);
            syncValue();
        });

        field.addEventListener('blur', () => {
            normaliseField(hourField, 12);
            normaliseField(minuteField, 59);
            syncValue();
        });
    });

    periodButtons.forEach((button) => {
        button.addEventListener('click', () => {
            period = button.dataset.timePeriod === 'PM' ? 'PM' : 'AM';
            updatePeriodButtons();
            syncValue();
        });
    });

    if (clearButton instanceof HTMLButtonElement) {
        clearButton.addEventListener('click', () => {
            hourField.value = '';
            minuteField.value = '';
            valueField.value = '';
            period = 'AM';
            updatePeriodButtons();
            clearInvalidState();
            hourField.focus();
        });
    }

    if (form instanceof HTMLFormElement) {
        form.addEventListener('submit', (event) => {
            if (!syncValue()) {
                event.preventDefault();
                (hourField.value.trim() ? minuteField : hourField).focus();
            }
        });
    }

    hydrateFromValue();
});

document.querySelectorAll('[data-password-toggle]').forEach((toggle) => {
    if (!(toggle instanceof HTMLButtonElement)) {
        return;
    }

    const inputId = toggle.getAttribute('aria-controls');
    const passwordInput = inputId ? document.getElementById(inputId) : null;

    if (!(passwordInput instanceof HTMLInputElement)) {
        return;
    }

    toggle.addEventListener('click', () => {
        const showing = passwordInput.type === 'text';
        passwordInput.type = showing ? 'password' : 'text';
        toggle.setAttribute('aria-pressed', showing ? 'false' : 'true');
        toggle.setAttribute('aria-label', showing ? 'Show password' : 'Hide password');

        const icon = toggle.querySelector('i');
        if (icon instanceof HTMLElement) {
            icon.classList.toggle('bi-eye', showing);
            icon.classList.toggle('bi-eye-slash', !showing);
        }

        passwordInput.focus();
    });
});

document.querySelectorAll('[data-password-assistance]').forEach((assistance) => {
    if (!(assistance instanceof HTMLElement)) {
        return;
    }

    const form = assistance.closest('form');
    const passwordInput = form?.querySelector('[data-password-primary]');
    const confirmationInput = form?.querySelector('[data-password-confirmation]');
    const confirmationStatus = form?.querySelector('[data-password-confirmation-status]');
    const summary = assistance.querySelector('[data-password-summary]');
    const bar = assistance.querySelector('.password-requirement-bar');

    if (!(passwordInput instanceof HTMLInputElement)) {
        return;
    }

    const updatePasswordAssistance = () => {
        const password = passwordInput.value;
        const rules = {
            length: password.length >= 12 && password.length <= 128,
            uppercase: /[A-Z]/.test(password),
            lowercase: /[a-z]/.test(password),
            number: /[0-9]/.test(password),
            symbol: /[^A-Za-z0-9\s]/.test(password) && !/\s/.test(password),
        };
        const completed = Object.values(rules).filter(Boolean).length;

        assistance.querySelectorAll('.password-requirement-segment').forEach((segment, index) => {
            segment.classList.toggle('is-met', index < completed);
        });

        assistance.querySelectorAll('.password-requirement-list [data-password-rule]').forEach((element) => {
            const rule = element.getAttribute('data-password-rule');
            const met = rule !== null && rules[rule] === true;
            element.classList.toggle('is-met', met);

            const icon = element.querySelector('i');
            if (icon instanceof HTMLElement) {
                icon.className = met ? 'bi bi-check-circle-fill' : 'bi bi-circle';
            }
        });

        if (summary instanceof HTMLElement) {
            summary.textContent = `${completed} of 5 password requirements met`;
        }
        if (bar instanceof HTMLElement) {
            bar.setAttribute('aria-valuenow', String(completed));
        }

        if (confirmationInput instanceof HTMLInputElement && confirmationStatus instanceof HTMLElement) {
            if (confirmationInput.value === '') {
                confirmationStatus.classList.remove('is-match', 'is-mismatch');
                confirmationStatus.textContent = 'Enter the password again to confirm it.';
            } else if (confirmationInput.value === password) {
                confirmationStatus.classList.remove('is-mismatch');
                confirmationStatus.classList.add('is-match');
                confirmationStatus.textContent = 'Passwords match.';
            } else {
                confirmationStatus.classList.remove('is-match');
                confirmationStatus.classList.add('is-mismatch');
                confirmationStatus.textContent = 'Passwords do not match yet.';
            }
        }
    };

    passwordInput.addEventListener('input', updatePasswordAssistance);
    confirmationInput?.addEventListener('input', updatePasswordAssistance);
    updatePasswordAssistance();
});
