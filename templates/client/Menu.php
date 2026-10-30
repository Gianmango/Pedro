<?php require base_path('templates/partials/header.php'); ?>

<?php if (empty($menu)): ?>
    <p class="empty">Il menù non è ancora disponibile.</p>
<?php else: ?>

    <!-- Scorciatoie alle sezioni -->
    <nav class="category-nav" aria-label="Sezioni del menù">
        <?php foreach ($menu as $categoryId => $category): ?>
            <a href="#cat-<?= (int) $categoryId ?>"><?= e($category['name']) ?></a>
        <?php endforeach; ?>
    </nav>

    <?php foreach ($menu as $categoryId => $category): ?>
        <section class="category" id="cat-<?= (int) $categoryId ?>">
            <h2><?= e($category['name']) ?></h2>

            <ul class="dish-list">
                <?php foreach ($category['dishes'] as $dish): ?>
                    <?php
                    $dishId   = (int) $dish['id'];
                    $dialogId = 'dish-' . $dishId;
                    $image    = dish_image_url($dish['image_path']);
                    ?>
                    <li class="dish">

                        <!-- Toccando la riga si apre il dettaglio del piatto. -->
                        <button type="button" class="dish__open" data-dialog="<?= e($dialogId) ?>" aria-haspopup="dialog">
                            <?php if ($image): ?>
                                <img class="dish__photo" src="<?= e($image) ?>" alt="" width="72" height="72" loading="lazy">
                            <?php else: ?>
                                <span class="dish__photo dish__photo--empty" aria-hidden="true"></span>
                            <?php endif; ?>

                            <span class="dish__text">
                                <span class="dish__name"><?= e($dish['name']) ?></span>
                                <span class="dish__price"><?= e(format_price((int) $dish['price_cents'])) ?></span>
                                <?php if ($dish['allergens']): ?>
                                    <span class="allergens">
                                        <?php foreach ($dish['allergens'] as $allergen): ?>
                                            <?php view('partials/allergen_badge', ['allergen' => $allergen]); ?>
                                        <?php endforeach; ?>
                                    </span>
                                <?php endif; ?>
                            </span>
                        </button>

                        <!-- Dettaglio del piatto -->
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

                                <p class="dish-dialog__price"><?= e(format_price((int) $dish['price_cents'])) ?></p>
                            </div>
                        </dialog>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>
    <?php endforeach; ?>

    <p class="menu-note">Hai allergie o intolleranze? Chiedi al personale prima di ordinare.</p>

<?php endif; ?>

<?php require base_path('templates/partials/footer.php'); ?>
