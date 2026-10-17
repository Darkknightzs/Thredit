<?php
/**
 * =====================================================================
 * User Logout Handler
 * =====================================================================
 * Destroys session data, invalidates session cookie, and redirects.
 * =====================================================================
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

logout_user();
set_flash('info', 'You have been signed out successfully.');
redirect('public/login.php');
