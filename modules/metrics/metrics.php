<?php

use Prometheus\RenderTextFormat;

ezpEvent::getInstance()->notify('metrics/output');

try {
    $registry = MetricCollector::instance()->getRegistry();

    $renderer = new RenderTextFormat();
    $result = $renderer->render($registry->getMetricFamilySamples());

    header('Content-type: ' . RenderTextFormat::MIME_TYPE);
    echo $result;
} catch (Throwable $e) {
    echo $e->getMessage();
}
//eZDisplayDebug();
eZExecution::cleanExit();
