<?php
// =====================================================================
// health.php (Root health check endpoint)
// Direct forwarder to api/keepalive.php without HTTP redirects
// Compatible with all uptime monitors (cron-job.org, UptimeRobot, Render)
// =====================================================================

declare(strict_types=1);

require_once __DIR__ . '/api/keepalive.php';
