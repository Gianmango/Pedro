<?php $title = 'Categorie'; $active = 'menu'; require base_path('templates/admin/partials/header.php'); ?>

<div class="page-head">
    <h1>Categorie</h1>
    <a class="btn" href="/admin/menu">Torna al menù</a>
</div>

<form method="post" action="/admin/categories" class="card form table-create">
    <?= csrf_field() ?>
    <h2>Aggiungi categoria o sottocategoria</h2>
    <label>Nome
        <input name="name" maxlength="100" required>
    </label>
    <label>Categoria principale
        <select name="parent_id">
            <option value="">Nessuna (categoria principale)</option>
            <?php foreach ($parents as $parent): ?>
                <option value="<?= (int) $parent['id'] ?>"><?= e($parent['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <p class="muted">Per creare una sottocategoria, scegli la categoria principale a cui appartiene.</p>
    <div class="form__actions"><button class="btn btn--primary">Aggiungi</button></div>
</form>

<?php if (!$categories): ?>
    <p class="empty">Nessuna categoria configurata.</p>
<?php endif; ?>

<ul class="category-admin-list">
    <?php foreach ($categories as $category): ?>
        <li class="category-admin-item">
            <form method="post" action="/admin/categories/<?= (int) $category['id'] ?>">
                <?= csrf_field() ?>
                <label>
                    <span class="sr-only">Nome categoria</span>
                    <input name="name" maxlength="100" required value="<?= e($category['name']) ?>">
                </label>
                <label class="category-admin-parent">Categoria principale
                    <select name="parent_id">
                        <option value="">Nessuna (principale)</option>
                        <?php foreach ($parents as $parent): ?>
                            <?php if ((int) $parent['id'] !== (int) $category['id']): ?>
                                <option value="<?= (int) $parent['id'] ?>"
                                    <?= (int) $category['parent_id'] === (int) $parent['id'] ? 'selected' : '' ?>>
                                    <?= e($parent['name']) ?>
                                </option>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                </label>
                <button class="btn">Salva</button>
            </form>
            <form method="post" action="/admin/categories/<?= (int) $category['id'] ?>/delete"
                  data-confirm="<?= e('Eliminare la categoria «' . $category['name'] . '»?') ?>">
                <?= csrf_field() ?>
                <button class="btn btn--danger">Elimina</button>
            </form>
        </li>
    <?php endforeach; ?>
</ul>

<?php require base_path('templates/admin/partials/footer.php'); ?>
