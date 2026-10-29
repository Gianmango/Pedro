<?php
// Icona di un allergene. Serve la variabile $allergen (code, name, icon).
// Ordine di preferenza: file public/assets/img/allergens/<code>.svg -> emoji -> sigla di 3 lettere.
$code = preg_replace('/[^a-z0-9_-]/i', '', (string) $allergen['code']);
$iconFile = 'assets/img/allergens/' . $code . '.svg';
$hasIconFile = $code !== '' && is_file(base_path('public/' . $iconFile));
?>
<span class="allergen" title="<?= e($allergen['name']) ?>">
    <?php if ($hasIconFile): ?>
        <img src="/<?= e($iconFile) ?>" alt="" width="20" height="20">
    <?php elseif (!empty($allergen['icon'])): ?>
        <span aria-hidden="true"><?= e($allergen['icon']) ?></span>
    <?php else: ?>
        <span class="allergen__abbr" aria-hidden="true"><?= e(mb_strtoupper(mb_substr((string) $allergen['name'], 0, 3))) ?></span>
    <?php endif; ?>
    <span class="sr-only"><?= e($allergen['name']) ?></span>
</span>
