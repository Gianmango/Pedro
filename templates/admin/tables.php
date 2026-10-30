<?php $title = 'Tavoli'; $active = 'tables'; require base_path('templates/admin/partials/header.php'); ?>

<div class="page-head">
    <h1>Tavoli</h1>
    <p class="muted">Conti aperti in totale: <strong><?= e(format_price((int) $grandTotal)) ?></strong></p>
</div>

<form method="post" action="/admin/tables" class="card form table-create">
    <?= csrf_field() ?>
    <label for="table-count">Aggiungi tavoli
        <input id="table-count" type="number" name="count" min="1" max="50" step="1" value="1" required>
    </label>
    <p class="muted">Verranno assegnati i numeri successivi all'ultimo tavolo esistente (massimo 50 per volta).</p>
    <div class="form__actions">
        <button class="btn btn--primary">Aggiungi</button>
    </div>
</form>

<?php if (empty($tables)): ?>
    <p class="empty">Nessun tavolo configurato.</p>
<?php endif; ?>

<div class="table-grid">
    <?php foreach ($tables as $table): ?>
        <?php $isOpen = $table['open_orders'] > 0; ?>
        <section class="table-card <?= $isOpen ? 'table-card--open' : '' ?>">
            <h2>
                <a class="table-card__link" href="/admin/tables/<?= (int) $table['number'] ?>">Tavolo <?= e((string) $table['number']) ?></a>
                <?php if (!empty($table['label'])): ?><small><?= e($table['label']) ?></small><?php endif; ?>
            </h2>

            <?php if ($isOpen): ?>
                <p class="table-card__total"><?= e(format_price($table['total_cents'])) ?></p>

                <details>
                    <summary><?= $table['open_orders'] === 1 ? '1 ordine' : $table['open_orders'] . ' ordini' ?> da pagare</summary>

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
                                            <?php if (($item['notes'] ?? '') !== ''): ?>
                                                <em class="muted">(<?= e($item['notes']) ?>)</em>
                                            <?php endif; ?>
                                        </span>
                                        <span><?= e(format_price((int) $item['unit_price_cents'] * (int) $item['quantity'])) ?></span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endforeach; ?>
                </details>
            <?php else: ?>
                <p class="table-card__free">Nessun conto aperto</p>
            <?php endif; ?>
            <a class="btn table-card__open" href="/admin/tables/<?= (int) $table['number'] ?>">
                <?= $isOpen ? 'Apri carrello' : 'Prendi ordine' ?>
            </a>
        </section>
    <?php endforeach; ?>
</div>

<?php require base_path('templates/admin/partials/footer.php'); ?>
