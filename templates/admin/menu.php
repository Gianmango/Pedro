<?php $title = 'Menù'; $active = 'menu'; require base_path('templates/admin/partials/header.php'); ?>

<div class="page-head">
    <h1>Menù</h1>
    <a class="btn btn--primary" href="/admin/menu/new">+ Aggiungi piatto</a>
</div>

<?php if (empty($menu)): ?>
    <p class="empty">Il menù è vuoto: aggiungi il primo piatto.</p>
<?php endif; ?>

<?php foreach ($menu as $category): ?>
    <section class="admin-category">
        <h2><?= e($category['name']) ?></h2>

        <ul class="admin-dishes">
            <?php foreach ($category['dishes'] as $dish): ?>
                <?php $image = dish_image_url($dish['image_path']); ?>
                <li class="admin-dish <?= $dish['is_available'] ? '' : 'admin-dish--hidden' ?>">
                    <?php if ($image): ?>
                        <img class="dish__photo" src="<?= e($image) ?>" alt="" width="72" height="72" loading="lazy">
                    <?php else: ?>
                        <span class="dish__photo dish__photo--empty" aria-hidden="true"></span>
                    <?php endif; ?>

                    <div class="admin-dish__info">
                        <strong><?= e($dish['name']) ?></strong>
                        <span><?= e(format_price((int) $dish['price_cents'])) ?></span>
                        <?php if (!$dish['is_available']): ?>
                            <span class="badge badge--warn">Nascosto ai clienti</span>
                        <?php endif; ?>
                        <?php if ($dish['allergens']): ?>
                            <span class="allergens">
                                <?php foreach ($dish['allergens'] as $allergen): ?>
                                    <?php view('partials/allergen_badge', ['allergen' => $allergen]); ?>
                                <?php endforeach; ?>
                            </span>
                        <?php endif; ?>
                    </div>

                    <div class="admin-dish__actions">
                        <a class="btn" href="/admin/menu/<?= (int) $dish['id'] ?>/edit">Modifica</a>
                        <form method="post" action="/admin/menu/<?= (int) $dish['id'] ?>/delete"
                              data-confirm="<?= e('Eliminare «' . $dish['name'] . '»? L\'operazione non si può annullare.') ?>">
                            <?= csrf_field() ?>
                            <button class="btn btn--danger">Elimina</button>
                        </form>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>
<?php endforeach; ?>

<?php require base_path('templates/admin/partials/footer.php'); ?>
