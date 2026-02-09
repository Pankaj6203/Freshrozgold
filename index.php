<?php

/**
 * Rozgold API - Production Entry Point
 * 
 * This file redirects all requests to the public folder
 * for proper Laravel application handling.
 */

// Redirect to public folder
header('Location: /public/index.php');
exit();
