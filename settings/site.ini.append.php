<?php /* #?ini charset="utf-8"?

[RoleSettings]
PolicyOmitList[]=metrics

[Event]
Listeners[]=metrics/output@MetricCollector::getWebhookLatencyAndErrors
Listeners[]=content/cache/all@OCSupportListeners::logOnClearAllCache
*/ ?>