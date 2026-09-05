<h1>Services Catalog</h1>
<div class="grid grid-2">
    <div class="card">
        <div class="card-title">Existing Services</div>
        <div class="table-wrap"><table>
            <thead><tr><th>Name</th><th>Unit</th><th>Status</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($services as $s): ?>
                <tr>
                    <td><?= e($s['name']) ?></td>
                    <td><?= e($s['unit_label']) ?></td>
                    <td><span class="badge badge-<?= $s['is_active']?'success':'secondary' ?>"><?= $s['is_active']?'Active':'Inactive' ?></span></td>
                    <td>
                        <form method="post" action="<?= url('services', ['action' => 'toggle']) ?>">
                            <?= Csrf::field() ?><input type="hidden" name="id" value="<?= $s['id'] ?>">
                            <button class="btn btn-sm"><?= $s['is_active']?'Deactivate':'Activate' ?></button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
    </div>
    <div class="card">
        <div class="card-title">Add Service</div>
        <form method="post" action="<?= url('services', ['action' => 'store']) ?>">
            <?= Csrf::field() ?>
            <div class="form-group"><label>Service Name *</label><input type="text" name="name" required></div>
            <div class="form-group"><label>Unit Label</label><input type="text" name="unit_label" placeholder="e.g. posts, videos, shoots" value="units"></div>
            <button class="btn btn-primary">Add Service</button>
        </form>
    </div>
</div>
