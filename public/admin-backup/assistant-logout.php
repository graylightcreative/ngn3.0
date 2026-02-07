<?php
/**
 * Assistant Logout Handler
 *
 * Destroys the assistant session and redirects to login page.
 */

session_start();
session_destroy();
header('Location: assistant-login.php?logout=success');
exit;
