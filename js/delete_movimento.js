document.addEventListener('DOMContentLoaded', () => {
    const modalEl = document.getElementById('deleteModal');
    if (!modalEl) return;
    const modal = new bootstrap.Modal(modalEl);
    let target = null;

    document.body.addEventListener('click', e => {
        const icon = e.target.closest('.delete-movimento');
        if (!icon) return;
        e.stopPropagation();
        e.preventDefault();
        const movement = icon.closest('.movement');
        if (!movement) return;
        target = {
            id: movement.dataset.id,
            src: movement.dataset.src,
            element: movement
        };
        modal.show();
    }, true);

    document.getElementById('confirmDelete').addEventListener('click', () => {
        if (!target) return;
        fetch('ajax/delete_movimento.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: target.id, src: target.src })
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                target.element.remove();
            }
            modal.hide();
            target = null;
        });
    });
});
