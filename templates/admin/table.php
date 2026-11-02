<?php
$entity = $table['is_delivery'] ? 'Spedizione' : 'Tavolo';
$entityName = $table['label'] ?: (string) $table['number'];
$title = $entity . ' ' . $entityName;
$active = $table['is_delivery'] ? 'deliveries' : 'tables';
require base_path('templates/admin/partials/header.php');

$number = (int) $table['number'];
$cart   = $state['cart'];
$total  = (int) $state['total_cents'];
$sendUrl = $orderOnly ? $baseUrl . '/order/send' : $baseUrl . '/send';

// Quante porzioni di ogni piatto ci sono già nel carrello (somma di tutte le righe, note comprese)
$qtyByDish = [];
$cartCount = 0;
foreach ($cart as $line) {
    $cartCount += $line['quantity'];
    if ($line['dish_id'] !== null) {
        $qtyByDish[$line['dish_id']] = ($qtyByDish[$line['dish_id']] ?? 0) + $line['quantity'];
    }
}
?>

<div data-order-root
     data-items-url="<?= e($baseUrl) ?>/items"
     data-note-url="<?= e($baseUrl) ?>/note"
     data-csrf="<?= e(csrf_token()) ?>">

    <div class="ord-head">
        <a class="ord-back" href="<?= e($backUrl) ?>">← <?= $table['is_delivery'] ? 'Tutte le spedizioni' : 'Tutti i tavoli' ?></a>
        <h1><?= e($entity) ?> <?= e($entityName) ?></h1>
    </div>

    <p class="flash flash--error" data-error role="alert" hidden></p>

    <!-- Stato iniziale del carrello: order.js lo disegna e poi lo aggiorna dopo ogni tocco -->
    <script type="application/json" data-initial-state><?= json_encode($state, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?></script>

    <div class="ord-layout">

        <!-- ================= Menù con + / − ================= -->
        <section class="ord-menu" aria-label="Menù">
            <?php if (empty($menu)): ?>
                <p class="empty">Il menù è vuoto: aggiungi i piatti da «Menù».</p>
            <?php else: ?>

                <nav class="category-nav" aria-label="Sezioni del menù">
                    <?php foreach ($menu as $categoryId => $category): ?>
                        <a href="#cat-<?= (int) $categoryId ?>"><?= e($category['name']) ?></a>
                    <?php endforeach; ?>
                </nav>

                <?php foreach ($menu as $categoryId => $category): ?>
                    <section class="category <?= $category['parent_id'] !== null ? 'category--subcategory' : '' ?>"
                             id="cat-<?= (int) $categoryId ?>">
                        <h2><?= e($category['name']) ?></h2>

                        <ul class="dish-list">
                            <?php foreach ($category['dishes'] as $dish): ?>
                                <?php
                                $dishId   = (int) $dish['id'];
                                $dialogId = 'ord-dish-' . $dishId;
                                $image    = dish_image_url($dish['image_path']);
                                $qty      = $qtyByDish[$dishId] ?? 0;
                                ?>
                                <li class="dish ord-dish <?= $qty > 0 ? 'ord-dish--active' : '' ?>">

                                    <!-- Riga: toccandola si apre il dettaglio (foto grande e allergeni) -->
                                    <button type="button" class="dish__open" data-dialog="<?= e($dialogId) ?>" aria-haspopup="dialog">
                                        <?php if ($image): ?>
                                            <img class="dish__photo" src="<?= e($image) ?>" alt="" width="72" height="72" loading="lazy">
                                        <?php else: ?>
                                            <span class="dish__photo dish__photo--empty" aria-hidden="true"></span>
                                        <?php endif; ?>

                                        <span class="dish__text">
                                            <span class="dish__name"><?= e($dish['name']) ?></span>
                                            <span class="ord-price"><?= e(format_price((int) $dish['price_cents'])) ?></span>
                                            <?php if ($dish['allergens']): ?>
                                                <span class="allergens">
                                                    <?php foreach ($dish['allergens'] as $allergen): ?>
                                                        <?php view('partials/allergen_badge', ['allergen' => $allergen]); ?>
                                                    <?php endforeach; ?>
                                                </span>
                                            <?php endif; ?>
                                        </span>
                                    </button>

                                    <div class="ord-stepper" data-dish="<?= $dishId ?>">
                                        <button type="button" class="ord-btn" data-delta="-1" aria-label="Togli una porzione di <?= e($dish['name']) ?>">−</button>
                                        <span class="ord-qty" data-qty="<?= $dishId ?>"><?= $qty ?></span>
                                        <button type="button" class="ord-btn ord-btn--plus" data-delta="1" aria-label="Aggiungi una porzione di <?= e($dish['name']) ?>">+</button>
                                    </div>

                                    <!-- Dettaglio del piatto (con gli stessi + / −) -->
                                    <dialog id="<?= e($dialogId) ?>" class="dish-dialog" aria-labelledby="<?= e($dialogId) ?>-title">
                                        <form method="dialog" class="dish-dialog__close">
                                            <button aria-label="Chiudi">&times;</button>
                                        </form>

                                        <?php if ($image): ?>
                                            <img class="dish-dialog__photo" src="<?= e($image) ?>" alt="<?= e($dish['name']) ?>" loading="lazy">
                                        <?php else: ?>
                                            <div class="dish-dialog__photo dish-dialog__photo--empty" aria-hidden="true"></div>
                                        <?php endif; ?>

                                        <div class="dish-dialog__body">
                                            <h3 id="<?= e($dialogId) ?>-title"><?= e($dish['name']) ?></h3>
                                            <p class="ord-price ord-price--big"><?= e(format_price((int) $dish['price_cents'])) ?></p>

                                            <?php if ($dish['description'] !== ''): ?>
                                                <p class="dish-dialog__desc"><?= e($dish['description']) ?></p>
                                            <?php endif; ?>

                                            <?php if ($dish['allergens']): ?>
                                                <ul class="allergen-list">
                                                    <?php foreach ($dish['allergens'] as $allergen): ?>
                                                        <li>
                                                            <?php view('partials/allergen_badge', ['allergen' => $allergen]); ?>
                                                            <span>Il piatto contiene <?= e(mb_strtolower($allergen['name'])) ?></span>
                                                        </li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            <?php endif; ?>

                                            <div class="ord-stepper ord-stepper--dialog" data-dish="<?= $dishId ?>">
                                                <button type="button" class="ord-btn" data-delta="-1" aria-label="Togli una porzione">−</button>
                                                <span class="ord-qty" data-qty="<?= $dishId ?>"><?= $qty ?></span>
                                                <button type="button" class="ord-btn ord-btn--plus" data-delta="1" aria-label="Aggiungi una porzione">+</button>
                                            </div>
                                        </div>
                                    </dialog>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </section>
                <?php endforeach; ?>
            <?php endif; ?>
        </section>

        <!-- ================= Carrello e conto ================= -->
        <aside class="ord-cart" id="ord-cart" aria-label="Carrello">
            <h2>Carrello</h2>

            <ul class="ord-lines" data-cart-list></ul>
            <p class="ord-empty" data-cart-empty <?= $cart ? 'hidden' : '' ?>>Il carrello è vuoto: tocca <strong>+</strong> sui piatti del menù.</p>

            <?php if ($sent): ?>
                <div class="ord-sent">
                    <h3>Già in corso</h3>
                    <?php foreach ($sent as $order): ?>
                        <p class="ord-sent__head">
                            Ordine #<?= (int) $order['id'] ?> · <?= e(format_time($order['created_at'])) ?>
                            <span class="badge"><?= e(order_status_label($order['status'])) ?></span>
                        </p>
                        <ul class="ord-sent__items">
                            <?php foreach ($order['items'] as $item): ?>
                                <li>
                                    <span>
                                        <?= (int) $item['quantity'] ?>× <?= e($item['dish_name']) ?>
                                        <?php if ($item['notes'] !== ''): ?><em class="ord-note-text">(<?= e($item['notes']) ?>)</em><?php endif; ?>
                                    </span>
                                    <span><?= e(format_price((int) $item['unit_price_cents'] * (int) $item['quantity'])) ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                        <form method="post" action="<?= e($baseUrl) ?>/orders/<?= (int) $order['id'] ?>/reprint">
                            <?= csrf_field() ?>
                            <button class="btn ord-reprint">Ristampa biglietto</button>
                        </form>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <form method="post" action="<?= e($sendUrl) ?>" class="ord-send"
                  data-confirm="<?= e('Inviare in cucina l\'ordine ' . $entityName . '? Verrà stampato il biglietto.') ?>">
                <?= csrf_field() ?>
                <button class="btn btn--primary ord-block" data-send <?= $cart ? '' : 'disabled' ?>>🖨 Invia in cucina</button>
            </form>

            <div class="ord-total">
                <span>Totale <?= strtolower($entity) ?></span>
                <strong data-total><?= e(format_price($total)) ?></strong>
            </div>

            <?php if (!$orderOnly): ?>
                <form method="post" action="<?= e($baseUrl) ?>/pay"
                      data-confirm="<?= e('Segnare il conto ' . $entityName . ' come pagato? Il tavolo/spedizione verrà rimosso.') ?>">
                    <?= csrf_field() ?>
                    <button class="btn ord-block ord-pay" data-pay <?= $total > 0 ? '' : 'disabled' ?>><?= $table['is_delivery'] ? "Chiudi l'ordine" : 'Chiudi il tavolo' ?></button>
                </form>
                <p class="muted ord-hint">Alla chiusura del conto <?= strtolower($entity) ?> viene rimosso dall'elenco.</p>
            <?php else: ?>
                <p class="muted ord-hint">Questa schermata serve solo a inserire il nuovo ordine: il pagamento è disponibile nella schermata degli ordini creati.</p>
            <?php endif; ?>
        </aside>
    </div>

    <!-- Barra fissa in basso, solo su schermi stretti -->
    <a class="ord-bar" href="#ord-cart">
        <span><strong data-count><?= $cartCount ?></strong> porzioni</span>
        <strong data-total><?= e(format_price($total)) ?></strong>
        <span>Vedi carrello ↓</span>
    </a>
</div>

<script src="<?= e(asset('js/menu.js')) ?>" defer></script>
<script src="<?= e(asset('js/order.js')) ?>" defer></script>

<?php require base_path('templates/admin/partials/footer.php'); ?>
