// Chiede conferma prima di inviare i form con data-confirm (es. "Elimina").
document.addEventListener('submit', (event) => {
    const message = event.target.dataset ? event.target.dataset.confirm : null;
    if (message && !window.confirm(message)) {
        event.preventDefault();
    }
});
