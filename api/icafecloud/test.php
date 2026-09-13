<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../services/IcaFeCloudSyncService.php';

require_login();
require_permission('settings.manage');
verify_csrf();

try {
    $service = new IcaFeCloudSyncService();
    $result = $service->testConnection();
    save_setting('icafecloud_status', 'CONNECTED');
    audit_log('ICAFECLOUD_TEST_CONNECTION', 'icafecloud', null, ['status' => 'CONNECTED']);
    json_response($result);
} catch (IcaFeCloudApiException $e) {
    save_setting('icafecloud_status', $e->codeName);
    audit_log('ICAFECLOUD_TEST_CONNECTION', 'icafecloud', null, ['status' => $e->codeName, 'http' => $e->httpStatus]);
    json_response(['ok' => false, 'status' => $e->codeName, 'message' => $e->getMessage()], 422);
} catch (Throwable) {
    save_setting('icafecloud_status', 'API ERROR');
    audit_log('ICAFECLOUD_TEST_CONNECTION', 'icafecloud', null, ['status' => 'API ERROR']);
    json_response(['ok' => false, 'status' => 'API ERROR', 'message' => 'iCafeCloud test failed.'], 422);
}
