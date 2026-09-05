<?php

Permission::require('roles.manage'); // service catalog is an admin-level configuration

$action = $_GET['action'] ?? 'index';

if ($action === 'store') {
    csrf_check_or_die();
    $v = Validator::make($_POST)->required('name', 'Service name');
    if ($v->fails()) { Flash::error($v->firstError()); redirect(url('services')); }
    ServiceModel::create(trim($_POST['name']), trim($_POST['unit_label'] ?? 'units'));
    Flash::success('Service added.');
    redirect(url('services'));
}

if ($action === 'toggle') {
    csrf_check_or_die();
    $id = (int)($_POST['id'] ?? 0);
    $svc = ServiceModel::find($id);
    if ($svc) ServiceModel::setActive($id, !$svc['is_active']);
    redirect(url('services'));
}

$services = ServiceModel::all(false);
render_page('services/index', compact('services'), 'Services');
