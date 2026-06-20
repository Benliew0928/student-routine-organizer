document.addEventListener('click', (event) => {
    const target = event.target;

    if (target instanceof HTMLElement && target.matches('[data-confirm]')) {
        const message = target.getAttribute('data-confirm') || 'Are you sure?';
        if (!window.confirm(message)) {
            event.preventDefault();
        }
    }
});

