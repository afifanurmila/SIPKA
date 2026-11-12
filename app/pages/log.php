<?php
declare(strict_types=1);

$u = require_role('admin');

$logs = q_all(
    'SELECT l.*, u.username, u.nama, u.role
       FROM log_aktivitas l
       LEFT JOIN users u ON u.id = l.user_id
      ORDER BY l.id DESC
      LIMIT 100'
);

render_header('Log Aktivitas Sistem', 'log');
?>
<div class="page-head">
    <h2>Log Aktivitas Pengguna</h2>
    <span class="muted small">Menampilkan 100 aktivitas terakhir</span>
</div>

<div class="card">
    <?php if (!$logs): ?>
        <p class="muted">Belum ada riwayat aktivitas yang tercatat.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table compact">
                <thead>
                    <tr>
                        <th>Waktu</th>
                        <th>Pengguna</th>
                        <th>Peran</th>
                        <th>Aksi</th>
                        <th>Detail</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($logs as $l): ?>
                    <tr>
                        <td class="small nowrap"><?= h($l['waktu']) ?></td>
                        <td>
                            <strong><?= h($l['nama'] ?? 'Sistem') ?></strong>
                            <?php if ($l['username']): ?><div class="small muted"><code><?= h($l['username']) ?></code></div><?php endif; ?>
                        </td>
                        <td class="small"><span class="badge badge-info"><?= h(ROLES[$l['role']] ?? $l['role'] ?? 'System') ?></span></td>
                        <td><code><?= h($l['aksi']) ?></code></td>
                        <td class="small"><?= nl2br(h($l['detail'] ?? '–')) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
<?php render_footer();
