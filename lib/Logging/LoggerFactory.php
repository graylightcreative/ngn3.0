<?php
namespace NGN\Lib\Logging;

use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;
use NGN\Lib\Config;

class LoggerFactory
{
    public static function create(Config $config, string $channel = 'api'): Logger
    {
        $logger = new Logger($channel);
        $level = Level::fromName(strtoupper($config->logLevel()));
        $path = rtrim($config->logPath(), '/');
        if (!is_dir($path)) {
            @mkdir($path, 0775, true);
        }
        error_log('Log Path: ' . $path); error_log('Log Level: ' . $level->getName()); error_log('Logging to: ' . $file = $path.'/'.date('Y-m-d').'.log');
        $logger->pushHandler(new StreamHandler($file, $level));
        return $logger;
    }
}
