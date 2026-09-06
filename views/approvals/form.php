<div class="flex-between">
    <h1>New Approval Request</h1>
    <a href="<?= url('approvals') ?>" class="btn">Back</a>
</div>

<div class="card" style="max-width:600px">
    <form method="post" action="<?= url('approvals', ['action' => 'store']) ?>">
        <?= Csrf::field() ?>
        
        <div class="form-group">
            <label>Title *</label>
            <input type="text" name="title" class="form-control" required placeholder="e.g. Leave Approval, Expense Request, etc.">
        </div>
        
        <div class="form-group">
            <label>Description / Details</label>
            <textarea name="description" class="form-control" rows="5" placeholder="Provide context for the approval..."></textarea>
        </div>
        
        <button type="submit" class="btn btn-primary">Submit Request</button>
    </form>
</div>
