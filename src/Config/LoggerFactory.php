<?php

namespace App\Config;

use Monolog\Formatter\JsonFormatter;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;

final class LoggerFactory
{
    public static function create(AppConfig $config): Logger
    {
        $env = $config->get('app.env', 'dev');
        $level = self::resolveLevel($config->get('logging.level', 'info'));
        $path = $config->get('logging.path', __DIR__ . '/../../var/log/app.log');

        $logDir = dirname($path);

        if (!is_dir($logDir)) {
            mkdir($logDir, 0777, true);
        }

        $logger = new Logger('file-server');

        $handler = new StreamHandler($path, $level);

        if ($env === 'prod') {
            $handler->setFormatter(new JsonFormatter());
        } else {
            $handler->setFormatter(new LineFormatter(
                "[%datetime%] %level_name%: %message% %context%\n",
                "Y-m-d H:i:s",
                true,
                true
            ));
        }

        $logger->pushHandler($handler);

        return $logger;
    }

    private static function resolveLevel(string $level): Level
    {
        return match (strtolower($level)) {
            'debug' => Level::Debug,
            'info' => Level::Info,
            'notice' => Level::Notice,
            'warning' => Level::Warning,
            'error' => Level::Error,
            'critical' => Level::Critical,
            'alert' => Level::Alert,
            'emergency' => Level::Emergency,
            default => Level::Info,
        };
    }
}