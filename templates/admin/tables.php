<?php $title = 'Tavoli'; $active = 'tables'; require base_path('templates/admin/partials/header.php'); ?>

<div class="page-head">
    <h1>Tavoli</h1>
    <p class="muted">Conti aperti in totale: <strong><?= e(format_price((int) $grandTotal)) ?></strong></p>
    <?php if ($tables): ?>
        <a class="btn btn--primary" href="/admin/tables/new">Nuovo ordine al tavolo</a>
    <?php endif; ?>
</div>

<div class="search-bar">
    <label for="table-search">Cerca un tavolo aperto</label>
    <input id="table-search" type="search" data-search-target="tables-list" placeholder="Numero o nome del tavolo...">
</div>

<?php if (!$tables): ?>
    <form method="post" action="/admin/tables/new" class="card form table-create">
        <?= csrf_field() ?>
        <h2>Crea nuovo tavolo</h2>
        <label>Nome o numero del tavolo
            <input name="identifier" maxlength="100" required autofocus placeholder="Es. 1 o Veranda">
        </label>
        <p class="muted">Inserisci un numero o un nome per creare il primo tavolo e iniziare l'ordine.</p>
        <div class="form__actions">
            <button class="btn btn--primary">Crea tavolo</button>
        </div>
    </form>
<?php endif; ?>

<div class="table-grid" id="tables-list">
    <?php foreach ($tables as $table): ?>
        <?php $isOpen = $table['open_orders'] > 0; ?>
        <section class="table-card <?= $isOpen ? 'table-card--open' : '' ?>" data-search-item="tables-list"
                 data-search-title="<?= e('Tavolo ' . $table['number'] . ' ' . ($table['label'] ?? '')) ?>">
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
<p class="empty" data-search-empty="tables-list" hidden>Nessun tavolo corrisponde alla ricerca.</p>

<?php require base_path('templates/admin/partials/footer.php'); ?>
