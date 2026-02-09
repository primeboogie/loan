<?php

/**
 * Branch Emergency Loans - API Entry Point
 * Redirects all requests to main router
 *
 * URL Format: /api/index.php?action=basicdetails
 *           or /api/basicdetails (with .htaccess)
 */

// Forward to main index.php
require_once dirname(__DIR__) . '/index.php';
