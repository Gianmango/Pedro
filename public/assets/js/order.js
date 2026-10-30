// Pagina del tavolo: i bottoni + e − salvano subito sul server e aggiornano
// carrello, quantità e totale senza ricaricare la pagina.
// (L'apertura del dettaglio piatto la gestisce menu.js.)
(() => {
    'use strict';

    const root = document.querySelector('[data-order-root]');
    if (!root) return;

    const url = root.dataset.itemsUrl;
    const csrf = root.dataset.csrf;
    const cartList = root.querySelector('[data-cart-list]');
    const cartEmpty = root.querySelector('[data-cart-empty]');
    const payButton = root.querySelector('[data-pay]');
    const errorBox = root.querySelector('[data-error]');

    // Le richieste partono una alla volta, nell'ordine dei tocchi:
    // così toccando + in fretta il risultato finale è sempre quello giusto.
    let queue = Promise.resolve();

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
        cartList.replaceChildren();
        let count = 0;
        const qtyByDish = new Map();

        for (const line of state.cart) {
            count += line.quantity;
            if (line.dish_id !== null) qtyByDish.set(String(line.dish_id), line.quantity);

            const li = document.createElement('li');
            li.className = 'ord-line';

            const name = document.createElement('span');
            name.className = 'ord-line__name';
            name.textContent = line.name;

            const price = document.createElement('span');
            price.className = 'ord-line__price';
            price.textContent = line.line_label;

            li.append(name, stepper('item', line.item_id, line.quantity, line.name), price);
            cartList.append(li);
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
        payButton.disabled = state.total_cents <= 0;
    }

    async function send(params) {
        try {
            const response = await fetch(url, {
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

    root.addEventListener('click', (event) => {
        const button = event.target.closest('[data-delta]');
        if (!button) return;

        const holder = button.closest('[data-dish], [data-item]');
        if (!holder) return;

        const params = { delta: button.dataset.delta };
        if (holder.dataset.dish) params.dish_id = holder.dataset.dish;
        else params.item_id = holder.dataset.item;

        queue = queue.then(() => send(params));
    });
})();
