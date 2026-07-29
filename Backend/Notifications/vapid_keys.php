<?php
/**
 * Backend/Notifications/vapid_keys.php
 * Shared VAPID loader for notification scripts.
 */
require_once __DIR__ . '/../bootstrap.php';

function notification_vapid_keys(): array
{
    return load_vapid_keys();
}
