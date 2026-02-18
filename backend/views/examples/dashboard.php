<?php $this->layout('layouts.app'); ?>

<?php $this->beginSection('title'); ?>Dashboard<?php $this->endSection(); ?>

<div class="card">
    <h2>Vítejte, <?= $this->e($user['display_name'] ?? $user['username']) ?></h2>
    <p>Přihlášen jako <strong><?= $this->e($user['username']) ?></strong> (<?= $this->e($user['email']) ?>)</p>
</div>

<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 24px;">
    <div class="card">
        <h2>Uživatelé</h2>
        <p style="font-size: 32px; font-weight: 700; margin: 8px 0;"><?= (int) $stats['users'] ?></p>
        <p style="color: #6b7280; font-size: 14px;">Aktivních účtů</p>
    </div>
    <div class="card">
        <h2>Aktivity dnes</h2>
        <p style="font-size: 32px; font-weight: 700; margin: 8px 0;"><?= (int) $stats['activities_today'] ?></p>
        <p style="color: #6b7280; font-size: 14px;">Zaznamenaných akcí</p>
    </div>
    <div class="card">
        <h2>Moduly</h2>
        <p style="font-size: 32px; font-weight: 700; margin: 8px 0;"><?= (int) $stats['modules'] ?></p>
        <p style="color: #6b7280; font-size: 14px;">Aktivních modulů</p>
    </div>
</div>

<?php if (!empty($recentActivity)): ?>
<div class="card">
    <h2>Poslední aktivita</h2>
    <table>
        <thead>
            <tr>
                <th>Čas</th>
                <th>Uživatel</th>
                <th>Akce</th>
                <th>Zdroj</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($recentActivity as $activity): ?>
            <tr>
                <td><?= $this->e($activity['created_at']) ?></td>
                <td><?= $this->e($activity['username'] ?? '—') ?></td>
                <td><span class="badge badge-blue"><?= $this->e($activity['action_type']) ?></span></td>
                <td><?= $this->e($activity['resource_type'] ?? '') ?> #<?= (int) ($activity['resource_id'] ?? 0) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>
