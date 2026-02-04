<?php
namespace NGN\Lib\Logging;

use Monolog\Logger;
use NGN\Lib\Config;

class AuditLogger
{
    public static function create(Config $config): Logger
    {
        // Use a dedicated channel for admin audits
        return LoggerFactory::create($config, 'admin_audit');
    }
}
