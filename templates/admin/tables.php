<?php $title = 'Tavoli'; $active = 'tables'; require base_path('templates/admin/partials/header.php'); ?>

<div class="page-head">
    <h1>Tavoli</h1>
    <p class="muted">Conti aperti in totale: <strong><?= e(format_price((int) $grandTotal)) ?></strong></p>
</div>

<?php if (empty($tables)): ?>
    <p class="empty">Nessun tavolo configurato.</p>
<?php endif; ?>

<div class="table-grid">
    <?php foreach ($tables as $table): ?>
        <?php $isOpen = $table['open_orders'] > 0; ?>
        <section class="table-card <?= $isOpen ? 'table-card--open' : '' ?>">
            <h2>
                Tavolo <?= e((string) $table['number']) ?>
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
        </section>
    <?php endforeach; ?>
</div>

<?php require base_path('templates/admin/partials/footer.php'); ?>
