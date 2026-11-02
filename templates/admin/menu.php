<?php $title = 'Menù'; $active = 'menu'; require base_path('templates/admin/partials/header.php'); ?>

<?php
$availableMenu = [];
$unavailableDishes = [];
foreach ($menu as $category) {
    $availableDishes = array_filter($category['dishes'], static fn (array $dish): bool => $dish['is_available']);
    if ($availableDishes) {
        $availableMenu[] = array_merge($category, ['dishes' => $availableDishes]);
    }

    foreach ($category['dishes'] as $dish) {
        if (!$dish['is_available']) {
            $unavailableDishes[] = ['category' => $category['name'], 'dish' => $dish];
        }
    }
}
?>

<div class="page-head">
    <h1>Menù</h1>
    <div class="form__actions">
        <a class="btn" href="/admin/categories">Categorie</a>
        <a class="btn btn--primary" href="/admin/menu/new">+ Aggiungi piatto</a>
    </div>
</div>

<div class="search-bar">
    <label for="menu-search">Cerca un piatto in tutto il menù</label>
    <input id="menu-search" type="search" data-search-target="menu-list" placeholder="Nome del piatto o categoria...">
</div>

<?php if (!$availableMenu && !$unavailableDishes): ?>
    <p class="empty">Il menù è vuoto: aggiungi il primo piatto.</p>
<?php endif; ?>

<div id="menu-list">
<?php foreach ($availableMenu as $category): ?>
    <section class="admin-category <?= $category['parent_id'] !== null ? 'admin-category--subcategory' : '' ?>" data-search-group="menu-list">
        <h2><?= e($category['name']) ?></h2>

        <ul class="admin-dishes">
            <?php foreach ($category['dishes'] as $dish): ?>
                <?php $image = dish_image_url($dish['image_path']); ?>
                <li class="admin-dish" data-search-item="menu-list" data-search-keywords="<?= e($category['name']) ?>">
                    <?php if ($image): ?>
                        <img class="dish__photo" src="<?= e($image) ?>" alt="" width="72" height="72" loading="lazy">
                    <?php else: ?>
                        <span class="dish__photo dish__photo--empty" aria-hidden="true"></span>
                    <?php endif; ?>

                    <div class="admin-dish__info">
                        <strong><?= e($dish['name']) ?></strong>
                        <span><?= e(format_price((int) $dish['price_cents'])) ?></span>
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

<?php if ($unavailableDishes): ?>
    <section class="admin-category admin-category--unavailable" data-search-group="menu-list">
        <h2>Piatti non disponibili</h2>
        <ul class="admin-dishes">
            <?php foreach ($unavailableDishes as $entry): ?>
                <?php $dish = $entry['dish']; $image = dish_image_url($dish['image_path']); ?>
                <li class="admin-dish admin-dish--hidden" data-search-item="menu-list" data-search-keywords="<?= e($entry['category']) ?>">
                    <?php if ($image): ?>
                        <img class="dish__photo" src="<?= e($image) ?>" alt="" width="72" height="72" loading="lazy">
                    <?php else: ?>
                        <span class="dish__photo dish__photo--empty" aria-hidden="true"></span>
                    <?php endif; ?>

                    <div class="admin-dish__info">
                        <strong><?= e($dish['name']) ?></strong>
                        <span><?= e(format_price((int) $dish['price_cents'])) ?></span>
                        <span class="badge badge--warn">Non disponibile</span>
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
<?php endif; ?>
</div>
<p class="empty" data-search-empty="menu-list" hidden>Nessun piatto corrisponde alla ricerca.</p>

<?php require base_path('templates/admin/partials/footer.php'); ?>
