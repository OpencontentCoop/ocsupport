<?php

use Opencontent\Installer\InMemoryLogger;

class InstallerToolsLogger extends InMemoryLogger
{
    protected function append($level, $message)
    {
        $this->logs[] = $this->formatHtml($level, $message);
        InstallerTools::registerInstallingLogs(implode(PHP_EOL, $this->getLogs()));

        $message = $this->format($level, $message);
        eZCLI::instance()->output($message);
        return $message;
    }

    private function formatHtml($level, $message): string
    {
        $class = '';
        switch ($level) {
            case'emergency':
            case'alert':
            case'critical':
            case'error':
                $class = 'bg-danger text-white';
                break;
            case'warning':
            case'notice':
                $class = 'text-warning';
                break;
            case'info':
                $class = 'text-success';
                break;
            default:
                $class = 'text-info';
                break;
        }
        return sprintf('<code class="%s">%s</code>', $class, $message);
    }
}