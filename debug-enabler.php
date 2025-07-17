<?php
update_option('wzi_general_settings', array(
    'enable_sync' => 'yes',
    'sync_mode' => 'manual',
    'debug_mode' => 'yes',
    'log_retention_days' => 30,
));
echo "Debug mode enabled.";
