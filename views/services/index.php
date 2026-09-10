<h1>Services Catalog</h1>
<div class="grid grid-2">
    <div class="card">
        <div class="card-title">Existing Services</div>
        <div class="table-wrap responsive-table"><table>
            <thead><tr><th>Name</th><th>Unit</th><th>Status</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($services as $s): ?>
                <tr>
                    <td data-label="Name">
                        <strong><?= e($s['name']) ?></strong>
                        <?php if (!empty($s['subcategories'])): ?>
                            <div class="mt-2" style="display:flex; flex-wrap:wrap; gap:4px">
                            <?php foreach ($s['subcategories'] as $sub): ?>
                                <span class="tag">
                                    <?= e($sub['name']) ?> 
                                    <form method="post" action="<?= url('services', ['action' => 'remove_subcategory']) ?>" style="display:inline" data-confirm="Remove subcategory?">
                                        <?= Csrf::field() ?><input type="hidden" name="id" value="<?= $sub['id'] ?>">
                                        <button type="submit" style="background:none;border:none;color:inherit;cursor:pointer;margin-left:4px">&times;</button>
                                    </form>
                                </span>
                            <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        
                        <form method="post" action="<?= url('services', ['action' => 'add_subcategory']) ?>" class="form-row mt-2" style="align-items:center">
                            <?= Csrf::field() ?><input type="hidden" name="service_id" value="<?= $s['id'] ?>">
                            <div class="form-group mb-0" style="flex:1"><input type="text" name="name" placeholder="New subcategory (e.g. Posts, Reels)" required style="padding:4px 8px;font-size:12px;width:100%"></div>
                            <button class="btn btn-sm btn-primary">Add</button>
                        </form>
                    </td>
                    <td data-label="Unit"><?= e($s['unit_label']) ?></td>
                    <td data-label="Status"><span class="badge badge-<?= $s['is_active']?'success':'secondary' ?>"><?= $s['is_active']?'Active':'Inactive' ?></span></td>
                    <td data-label="Actions">
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
