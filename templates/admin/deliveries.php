<?php $title = 'Spedizioni'; $active = 'deliveries'; require base_path('templates/admin/partials/header.php'); ?>

<div class="page-head">
    <h1>Spedizioni</h1>
    <p class="muted">Conti aperti in totale: <strong><?= e(format_price((int) $grandTotal)) ?></strong></p>
</div>

<section class="delivery-new">
    <h2>Nuovo ordine</h2>
    <form method="post" action="/admin/deliveries" class="card form table-create">
        <?= csrf_field() ?>
        <label>Nome del cliente
            <input name="identifier" maxlength="100" required placeholder="Es. Mario, Verdi ecc...">
        </label>
        <p class="muted">Crea la spedizione e apre la schermata per inserire l'ordine.</p>
        <div class="form__actions"><button class="btn btn--primary">Inizia ordine</button></div>
    </form>
</section>

<section class="delivery-created">
    <h2>Ordini creati</h2>
    <div class="search-bar">
        <label for="delivery-search">Cerca tra gli ordini creati</label>
        <input id="delivery-search" type="search" data-search-target="deliveries-list" placeholder="Cliente, numero o piatto...">
    </div>
    <?php if (!$tables): ?>
        <p class="empty">Nessuna spedizione creata.</p>
    <?php endif; ?>

    <div class="table-grid" id="deliveries-list">
        <?php foreach ($tables as $table): ?>
            <?php $isOpen = $table['open_orders'] > 0; ?>
            <article class="table-card <?= $isOpen ? 'table-card--open' : '' ?>" data-search-item="deliveries-list">
                <h3><?= e($table['label'] ?: ('Spedizione ' . $table['number'])) ?></h3>
                <?php if ($isOpen): ?>
                    <p class="table-card__total"><?= e(format_price($table['total_cents'])) ?></p>
                    <details>
                        <summary><?= $table['open_orders'] === 1 ? '1 ordine' : $table['open_orders'] . ' ordini' ?> aperti</summary>
                        <?php foreach ($table['orders'] as $order): ?>
                            <div class="order">
                                <p class="order__head">
                                    Ordine #<?= (int) $order['id'] ?> · <?= e(format_time((string) $order['created_at'])) ?>
                                    <span class="badge"><?= e(order_status_label((string) $order['status'])) ?></span>
                                </p>
                                <ul>
                                    <?php foreach ($order['items'] as $item): ?>
                                        <li>
                                            <span><?= (int) $item['quantity'] ?>× <?= e($item['dish_name']) ?>
                                                <?php if (($item['notes'] ?? '') !== ''): ?><em class="muted">(<?= e($item['notes']) ?>)</em><?php endif; ?>
                                            </span>
                                            <span><?= e(format_price((int) $item['unit_price_cents'] * (int) $item['quantity'])) ?></span>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endforeach; ?>
                    </details>
                <?php else: ?>
                    <p class="table-card__free">Nessun ordine ancora inviato</p>
                <?php endif; ?>
                <a class="btn table-card__open" href="/admin/deliveries/<?= (int) $table['number'] ?>">
                    <?= $isOpen ? 'Apri conto' : 'Apri ordine' ?>
                </a>
            </article>
        <?php endforeach; ?>
    </div>
    <p class="empty" data-search-empty="deliveries-list" hidden>Nessun ordine corrisponde alla ricerca.</p>
</section>

<?php require base_path('templates/admin/partials/footer.php'); ?>
