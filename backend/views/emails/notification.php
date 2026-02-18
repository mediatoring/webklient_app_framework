<?php $this->layout('layouts.email'); ?>

<?php $this->beginSection('title'); ?>Oznámení<?php $this->endSection(); ?>

<p>Dobrý den<?= !empty($displayName) ? ', ' . $this->e($displayName) : '' ?>.</p>

<?= $body ?>

<?php if (!empty($actionUrl)): ?>
<p style="text-align: center; margin: 32px 0;">
    <a href="<?= $this->e($actionUrl) ?>" class="btn"><?= $this->e($actionLabel ?? 'Zobrazit') ?></a>
</p>
<?php endif; ?>
