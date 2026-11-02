// Chiede conferma prima di inviare i form con data-confirm (es. "Elimina").
document.addEventListener('submit', (event) => {
    const message = event.target.dataset ? event.target.dataset.confirm : null;
    if (message && !window.confirm(message)) {
        event.preventDefault();
    }
});

document.querySelectorAll('[data-search-target]').forEach((input) => {
    const targetId = input.dataset.searchTarget;
    const target = document.getElementById(targetId);
    if (!target) {
        return;
    }

    const items = target.querySelectorAll(`[data-search-item="${targetId}"]`);
    const groups = target.querySelectorAll(`[data-search-group="${targetId}"]`);
    const emptyMessage = document.querySelector(`[data-search-empty="${targetId}"]`);
    const normalize = (value) => value
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLocaleLowerCase();

    input.addEventListener('input', () => {
        const query = normalize(input.value.trim());
        let visibleCount = 0;

        items.forEach((item) => {
            const text = normalize(item.dataset.searchTitle ?? `${item.textContent} ${item.dataset.searchKeywords || ''}`);
            const matches = text.includes(query);
            item.hidden = !matches;
            if (matches) {
                visibleCount += 1;
            }
        });

        groups.forEach((group) => {
            group.hidden = !group.querySelector(`[data-search-item="${targetId}"]:not([hidden])`);
        });

        if (emptyMessage) {
            emptyMessage.hidden = visibleCount > 0;
        }
    });
});
