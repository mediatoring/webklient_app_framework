<?php $this->layout('layouts.app'); ?>

<?php $this->beginSection('title'); ?>Uživatelé<?php $this->endSection(); ?>

<div class="card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
        <h2 style="margin: 0;">Správa uživatelů</h2>
        <a href="/users/create" class="btn">+ Nový uživatel</a>
    </div>

    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Uživatel</th>
                <th>Email</th>
                <th>Role</th>
                <th>Stav</th>
                <th>Vytvořen</th>
                <th>Akce</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($users as $u): ?>
            <tr>
                <td><?= (int) $u['id'] ?></td>
                <td>
                    <strong><?= $this->e($u['display_name'] ?? $u['username']) ?></strong><br>
                    <small style="color: #6b7280;">@<?= $this->e($u['username']) ?></small>
                </td>
                <td><?= $this->e($u['email']) ?></td>
                <td>
                    <?php foreach ($u['roles'] ?? [] as $role): ?>
                        <span class="badge badge-blue"><?= $this->e($role['name']) ?></span>
                    <?php endforeach; ?>
                </td>
                <td>
                    <?php if ($u['is_active']): ?>
                        <span class="badge badge-green">Aktivní</span>
                    <?php else: ?>
                        <span class="badge badge-red">Neaktivní</span>
                    <?php endif; ?>
                </td>
                <td><?= $this->e($u['created_at'] ?? '') ?></td>
                <td>
                    <a href="/users/<?= (int) $u['id'] ?>/edit" class="btn btn-sm">Upravit</a>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <?php if (!empty($pagination)): ?>
    <div style="margin-top: 16px; display: flex; justify-content: center; gap: 8px;">
        <?php for ($i = 1; $i <= $pagination['last_page']; $i++): ?>
            <a href="?page=<?= $i ?>"
               class="btn btn-sm"
               style="<?= $i === $pagination['current_page'] ? '' : 'background: #e5e7eb; color: #374151;' ?>">
                <?= $i ?>
            </a>
        <?php endfor; ?>
    </div>
    <?php endif; ?>
</div>
