// Apre e chiude la finestra di dettaglio dei piatti.
// La X di chiusura funziona senza JavaScript (form method="dialog"); Esc è gestito dal browser.
document.addEventListener('click', (event) => {
    const opener = event.target.closest('[data-dialog]');

    if (opener) {
        const dialog = document.getElementById(opener.dataset.dialog);
        if (dialog && typeof dialog.showModal === 'function') {
            dialog.showModal();
        }
        return;
    }

    // Un tocco sullo sfondo scuro (fuori dalla finestra) la chiude.
    if (event.target instanceof HTMLDialogElement) {
        event.target.close();
    }
});
