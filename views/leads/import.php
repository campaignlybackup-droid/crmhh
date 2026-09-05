<h1>Import Leads from CSV</h1>
<div class="card" style="max-width:600px">
    <p class="text-muted small">Upload a CSV export from any source. Columns don't need to match exactly — we'll automatically detect Name, Phone, Email, Company, Source and Status columns, and you'll get a chance to review and correct the mapping before anything is imported.</p>
    <form method="post" action="<?= url('leads', ['action' => 'import_preview']) ?>" enctype="multipart/form-data">
        <?= Csrf::field() ?>
        <div class="form-group">
            <label>CSV File</label>
            <input type="file" name="csv_file" accept=".csv,text/csv" required>
            <div class="help-text">Maximum 5 MB. First row must contain column headers.</div>
        </div>
        <button class="btn btn-primary">Upload &amp; Preview</button>
        <a href="<?= url('leads') ?>" class="btn">Cancel</a>
    </form>
</div>
