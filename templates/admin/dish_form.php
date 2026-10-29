<?php
$title  = $heading;
$active = 'menu';
require base_path('templates/admin/partials/header.php');

$currentPhoto = dish_image_url($dish['image_path'] ?? null);
?>

<h1><?= e($heading) ?></h1>

<?php if ($errors): ?>
    <p class="flash flash--error" role="alert">Controlla i campi evidenziati.</p>
<?php endif; ?>

<form method="post" action="<?= e($action) ?>" enctype="multipart/form-data" class="card form">
    <?= csrf_field() ?>

    <label>Nome
        <input name="name" maxlength="150" required value="<?= e($dish['name']) ?>">
        <?php if (isset($errors['name'])): ?><span class="field-error"><?= e($errors['name']) ?></span><?php endif; ?>
    </label>

    <div class="form__row">
        <label>Categoria
            <select name="category_id" required>
                <option value="" disabled <?= (int) $dish['category_id'] === 0 ? 'selected' : '' ?>>Scegli…</option>
                <?php foreach ($categories as $category): ?>
                    <option value="<?= (int) $category['id'] ?>" <?= (int) $dish['category_id'] === (int) $category['id'] ? 'selected' : '' ?>>
                        <?= e($category['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?php if (isset($errors['category_id'])): ?><span class="field-error"><?= e($errors['category_id']) ?></span><?php endif; ?>
        </label>

        <label>Prezzo (€)
            <input name="price" inputmode="decimal" placeholder="12,50" required value="<?= e((string) $dish['price']) ?>">
            <?php if (isset($errors['price'])): ?><span class="field-error"><?= e($errors['price']) ?></span><?php endif; ?>
        </label>
    </div>

    <label>Descrizione
        <textarea name="description" rows="4" maxlength="1000"><?= e($dish['description']) ?></textarea>
        <?php if (isset($errors['description'])): ?><span class="field-error"><?= e($errors['description']) ?></span><?php endif; ?>
    </label>

    <fieldset>
        <legend>Allergeni contenuti nel piatto</legend>
        <div class="checks">
            <?php foreach ($allergens as $allergen): ?>
                <label class="check">
                    <input type="checkbox" name="allergens[]" value="<?= (int) $allergen['id'] ?>"
                        <?= in_array((int) $allergen['id'], $selected, true) ? 'checked' : '' ?>>
                    <?php view('partials/allergen_badge', ['allergen' => $allergen]); ?>
                    <span><?= e($allergen['name']) ?></span>
                </label>
            <?php endforeach; ?>
        </div>
    </fieldset>

    <fieldset>
        <legend>Foto</legend>

        <?php if ($currentPhoto): ?>
            <div class="photo-current">
                <img src="<?= e($currentPhoto) ?>" alt="Foto attuale" width="120" height="120">
                <label class="check">
                    <input type="checkbox" name="remove_photo" value="1"> Rimuovi la foto attuale
                </label>
            </div>
        <?php endif; ?>

        <label><?= $currentPhoto ? 'Sostituisci con' : 'Carica una foto' ?> (JPG, PNG o WebP, max 8 MB)
            <input type="file" name="photo" accept="image/jpeg,image/png,image/webp">
            <?php if (isset($errors['photo'])): ?><span class="field-error"><?= e($errors['photo']) ?></span><?php endif; ?>
        </label>
    </fieldset>

    <label class="check">
        <input type="checkbox" name="is_available" value="1" <?= $dish['is_available'] ? 'checked' : '' ?>>
        Disponibile (visibile ai clienti)
    </label>

    <div class="form__actions">
        <button class="btn btn--primary">Salva</button>
        <a class="btn" href="/admin/menu">Annulla</a>
    </div>
</form>

<?php require base_path('templates/admin/partials/footer.php'); ?>
