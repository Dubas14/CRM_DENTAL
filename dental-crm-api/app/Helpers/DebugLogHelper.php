<?php

namespace App\Helpers;

class DebugLogHelper
{
    public static function write(string $location, string $message, array $data, string $runId = 'default', string $hypothesisId = ''): void
    {
        $logPath = base_path('.cursor/debug.log');
        $logDir = dirname($logPath);
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }
        @file_put_contents($logPath, json_encode(['sessionId' => 'debug-session', 'runId' => $runId, 'hypothesisId' => $hypothesisId, 'location' => $location, 'message' => $message, 'data' => $data, 'timestamp' => time() * 1000])."\n", FILE_APPEND);
    }
}
