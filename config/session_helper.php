<?php
/**
 * Session Helper
 * Prevents duplicate session_start() calls
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

?>
