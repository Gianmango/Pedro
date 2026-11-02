// Pagina del tavolo: i bottoni + e − salvano subito sul server e aggiornano
// carrello, quantità e totale senza ricaricare la pagina.
// (L'apertura del dettaglio piatto la gestisce menu.js.)
(() => {
    'use strict';

    const root = document.querySelector('[data-order-root]');
    if (!root) return;

    const url = root.dataset.itemsUrl;
    const noteUrl = root.dataset.noteUrl;
    const csrf = root.dataset.csrf;
    const cartList = root.querySelector('[data-cart-list]');
    const cartEmpty = root.querySelector('[data-cart-empty]');
    const payButton = root.querySelector('[data-pay]');
    const sendButton = root.querySelector('[data-send]');
    const errorBox = root.querySelector('[data-error]');

    // Le richieste partono una alla volta, nell'ordine dei tocchi:
    // così toccando + in fretta il risultato finale è sempre quello giusto.
    let queue = Promise.resolve();
    let pending = 0;
    let lastState = null;
    let editingItem = null; // riga del carrello con il campo nota aperto

    function showError(message) {
        errorBox.textContent = message;
        errorBox.hidden = false;
    }

    function stepper(attribute, id, quantity, name) {
        const wrap = document.createElement('span');
        wrap.className = 'ord-stepper';
        wrap.dataset[attribute] = String(id);

        const minus = document.createElement('button');
        minus.type = 'button';
        minus.className = 'ord-btn';
        minus.dataset.delta = '-1';
        minus.setAttribute('aria-label', 'Togli una porzione di ' + name);
        minus.textContent = '−';

        const qty = document.createElement('span');
        qty.className = 'ord-qty';
        qty.textContent = String(quantity);

        const plus = document.createElement('button');
        plus.type = 'button';
        plus.className = 'ord-btn ord-btn--plus';
        plus.dataset.delta = '1';
        plus.setAttribute('aria-label', 'Aggiungi una porzione di ' + name);
        plus.textContent = '+';

        wrap.append(minus, qty, plus);
        return wrap;
    }

    // Ridisegna tutto a partire dallo stato che arriva dal server.
    function render(state) {
        errorBox.hidden = true;

        // Carrello
        lastState = state;
        cartList.replaceChildren();
        let count = 0;
        const qtyByDish = new Map();

        for (const line of state.cart) {
            count += line.quantity;
            if (line.dish_id !== null) {
                const key = String(line.dish_id);
                qtyByDish.set(key, (qtyByDish.get(key) ?? 0) + line.quantity);
            }
            cartList.append(lineElement(line));
        }
        cartEmpty.hidden = state.cart.length > 0;

        // Contatori sui piatti del menù (sia nella riga sia nel dettaglio)
        for (const el of root.querySelectorAll('[data-qty]')) {
            el.textContent = String(qtyByDish.get(el.dataset.qty) ?? 0);
        }
        for (const row of root.querySelectorAll('.ord-dish')) {
            const holder = row.querySelector('[data-dish]');
            row.classList.toggle('ord-dish--active', qtyByDish.has(holder.dataset.dish));
        }

        // Totali e pulsante "pagato"
        for (const el of root.querySelectorAll('[data-total]')) el.textContent = state.total_label;
        for (const el of root.querySelectorAll('[data-count]')) el.textContent = String(count);
        if (payButton) payButton.disabled = state.total_cents <= 0;
        updateSendButton();

        const input = cartList.querySelector('.ord-note-input');
        if (input) input.focus();
    }

    function updateSendButton() {
        if (!sendButton) return;
        sendButton.disabled = pending > 0 || !lastState || lastState.cart.length === 0;
    }

    // Una riga del carrello: nome, + / −, prezzo e sotto la nota.
    function lineElement(line) {
        const li = document.createElement('li');
        li.className = 'ord-line';

        const name = document.createElement('span');
        name.className = 'ord-line__name';
        name.textContent = line.name;

        const price = document.createElement('span');
        price.className = 'ord-line__price';
        price.textContent = line.line_label;

        li.append(name, stepper('item', line.item_id, line.quantity, line.name), price, noteElement(line));
        return li;
    }

    function noteElement(line) {
        const wrap = document.createElement('div');
        wrap.className = 'ord-note';

        if (editingItem === line.item_id) {
            const form = document.createElement('form');
            form.className = 'ord-note-form';
            form.dataset.noteForm = String(line.item_id);

            const input = document.createElement('input');
            input.type = 'text';
            input.name = 'note';
            input.className = 'ord-note-input';
            input.maxLength = 100;
            input.value = line.notes;
            input.placeholder = 'es. senza cipolla, ben cotta';
            input.setAttribute('aria-label', 'Nota per ' + line.name);
            input.autocomplete = 'off';

            const save = document.createElement('button');
            save.type = 'submit';
            save.className = 'ord-note-save';
            save.textContent = 'Salva';

            const cancel = document.createElement('button');
            cancel.type = 'button';
            cancel.className = 'ord-note-cancel';
            cancel.dataset.noteCancel = '1';
            cancel.textContent = 'Annulla';

            form.append(input, save, cancel);
            wrap.append(form);
            return wrap;
        }

        const button = document.createElement('button');
        button.type = 'button';
        button.dataset.noteEdit = String(line.item_id);
        if (line.notes) {
            button.className = 'ord-note-btn ord-note-btn--has';
            button.textContent = '✎ ' + line.notes;
            button.setAttribute('aria-label', 'Modifica la nota: ' + line.notes);
        } else {
            button.className = 'ord-note-btn';
            button.textContent = '+ Nota';
            button.setAttribute('aria-label', 'Aggiungi una nota a ' + line.name);
        }
        wrap.append(button);
        return wrap;
    }

    async function send(params, target = url) {
        try {
            const response = await fetch(target, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'X-CSRF-Token': csrf,
                    'Accept': 'application/json',
                    'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',
                },
                body: new URLSearchParams(params),
            });

            const type = response.headers.get('Content-Type') || '';
            if (!type.includes('application/json')) {
                if (response.redirected) {
                    window.location.href = response.url; // sessione scaduta: torna al login
                } else if (response.status === 419) {
                    showError('Sessione scaduta: ricarica la pagina e riprova.');
                } else {
                    showError('Operazione non riuscita (errore ' + response.status + ').');
                }
                return;
            }

            const data = await response.json();
            if (data.cart) render(data);          // anche in caso di errore, riallinea la pagina
            if (!response.ok) showError(data.error || 'Operazione non riuscita.');
        } catch (error) {
            showError('Connessione assente: controlla il Wi-Fi e riprova.');
        }
    }

    function enqueue(params, target) {
        pending++;
        updateSendButton();
        queue = queue.then(() => send(params, target)).finally(() => {
            pending--;
            updateSendButton();
        });
    }

    root.addEventListener('click', (event) => {
        const edit = event.target.closest('[data-note-edit]');
        if (edit) {
            editingItem = Number(edit.dataset.noteEdit);
            if (lastState) render(lastState);
            return;
        }

        if (event.target.closest('[data-note-cancel]')) {
            editingItem = null;
            if (lastState) render(lastState);
            return;
        }

        const button = event.target.closest('[data-delta]');
        if (!button) return;

        const holder = button.closest('[data-dish], [data-item]');
        if (!holder) return;

        const params = { delta: button.dataset.delta };
        if (holder.dataset.dish) params.dish_id = holder.dataset.dish;
        else params.item_id = holder.dataset.item;

        enqueue(params);
    });

    root.addEventListener('submit', (event) => {
        const form = event.target.closest('[data-note-form]');
        if (!form) return;
        event.preventDefault();

        const itemId = form.dataset.noteForm;
        const note = form.elements.note.value;
        editingItem = null;
        enqueue({ item_id: itemId, note }, noteUrl);
    });

    // Stato iniziale, scritto dal server nella pagina
    const initial = root.querySelector('[data-initial-state]');
    if (initial) {
        try {
            render(JSON.parse(initial.textContent));
        } catch (error) {
            showError('Impossibile leggere il carrello: ricarica la pagina.');
        }
    }
})();
