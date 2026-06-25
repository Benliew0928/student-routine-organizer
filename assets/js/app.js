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
