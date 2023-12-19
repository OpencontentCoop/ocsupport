<?php

use Prometheus\CollectorRegistry;
use Prometheus\RegistryInterface;
use Prometheus\Storage\InMemory;

class MetricCollector
{
    private static $instance;

    /**
     * @var RegistryInterface
     */
    private $registry;

    private function __construct()
    {
        $this->registry = new CollectorRegistry(
            new InMemory(),
            false
        );
    }

    /**
     * @return CollectorRegistry
     */
    public function getRegistry(): RegistryInterface
    {
        return $this->registry;
    }

    public static function getWebhookLatencyAndErrors()
    {
        if (class_exists('OCWebHookJob')) {
            $currentTenant = OpenPABase::getCurrentSiteaccessIdentifier();
            $latency = 0;
            $jobs = OCWebHookJob::fetchTodoList(0, 1);
            if (count($jobs)) {
                $job = $jobs[0];
                $latency = time() - $job->attribute('created_at');
            }
            MetricCollector::instance()->getRegistry()
                ->getOrRegisterGauge(
                    'ez',
                    'webhooks_latency_seconds',
                    'Seconds from oldest pending job',
                    [
                        'tenant',
                    ]
                )->set($latency, [
                    $currentTenant,
                ]);


            $gauge = MetricCollector::instance()->getRegistry()
                ->getOrRegisterGauge(
                    'ez',
                    'webhooks_jobs_total',
                    'Number of job by status',
                    [
                        'tenant',
                        'status'
                    ]
                );
            $retryCount = OCWebHookJob::fetchCountByExecutionStatus(OCWebHookJob::STATUS_RETRYING);
            $gauge->set($retryCount, [$currentTenant, 'retry']);
            $retryCount = OCWebHookJob::fetchCountByExecutionStatus(OCWebHookJob::STATUS_PENDING);
            $gauge->set($retryCount, [$currentTenant, 'pending']);

            $errorCounter = MetricCollector::instance()->getRegistry()
                ->getOrRegisterCounter(
                    'ez',
                    'webhooks_errors_total',
                    'Webhook failures',
                    [
                        'tenant',
                        'status',
                    ]
                );
            $db = eZDB::instance();
            $statuses = $db->arrayQuery(
                'SELECT response_status, count(response_status) as total FROM ocwebhook_job WHERE execution_status > 2 GROUP BY response_status ORDER BY response_status;'
            );
            foreach ($statuses as $item) {
                $responseStatus = (int)$item['response_status'];
                $status = 'unknown';
                if ($responseStatus > 99){
                    $status = substr($responseStatus, 0, 1) . 'xx';
                }
                $count = $item['total'];
                if ($count > 0){
                    $errorCounter->incBy($count, [
                        $currentTenant,
                        $status
                    ]);
                }
            }
        }
    }

    public static function instance(): MetricCollector
    {
        if (self::$instance === null) {
            self::$instance = new MetricCollector();
        }

        return self::$instance;
    }


}