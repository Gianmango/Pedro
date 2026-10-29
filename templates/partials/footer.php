</main>
<?php foreach (($scripts ?? []) as $script): ?>
<script src="<?= e($script) ?>" defer></script>
<?php endforeach; ?>
</body>
</html>
