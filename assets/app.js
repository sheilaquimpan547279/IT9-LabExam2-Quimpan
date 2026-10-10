const drawer = document.querySelector('.student-drawer');
const backdrop = document.querySelector('.drawer-backdrop');

function openDrawer() {
    drawer.classList.add('is-open');
    backdrop.classList.add('is-open');
    drawer.setAttribute('aria-hidden', 'false');
    document.body.classList.add('drawer-visible');
    window.setTimeout(() => drawer.querySelector('input:not([type="hidden"])')?.focus(), 80);
}

function closeDrawer() {
    drawer.classList.remove('is-open');
    backdrop.classList.remove('is-open');
    drawer.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('drawer-visible');
}

document.querySelectorAll('[data-open-form]').forEach((button) => {
    button.addEventListener('click', openDrawer);
});

document.querySelectorAll('[data-close-form]').forEach((button) => {
    button.addEventListener('click', closeDrawer);
});

document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && drawer.classList.contains('is-open')) {
        closeDrawer();
    }
});

document.querySelectorAll('.delete-form').forEach((form) => {
    form.addEventListener('submit', (event) => {
        const studentName = form.dataset.studentName;
        if (!window.confirm(`Delete ${studentName}'s student record? This action cannot be undone.`)) {
            event.preventDefault();
        }
    });
});

document.querySelectorAll('.alert-close').forEach((button) => {
    button.addEventListener('click', () => button.closest('.alert').remove());
});

if (drawer.classList.contains('is-open')) {
    document.body.classList.add('drawer-visible');
}