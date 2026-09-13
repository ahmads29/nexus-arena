<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../services/IcaFeCloudSyncService.php';

require_login();
require_permission('settings.manage');
verify_csrf();

try {
    $service = new IcaFeCloudSyncService();
    json_response($service->sync(true));
} catch (Throwable) {
    save_setting('icafecloud_status', 'API ERROR');
    audit_log('ICAFECLOUD_SYNC_ERROR', 'icafecloud', null, ['status' => 'API ERROR']);
    json_response(['ok' => false, 'status' => 'API ERROR', 'message' => 'iCafeCloud sync failed.'], 422);
}
