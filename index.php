<?php
/*
 * ROOT ENTRY POINT
 */

session_start();

// 1. Check if the user is already logged in
// If yes, send them straight to their profile
if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'student') {
    header("Location: frontend/studentProfile.html");
    exit();
}

// 2. If not logged in, redirect immediately to the Login Page
// Note: Based on your directory listing, ensure the casing matches your file 'login_wPass.html'
header("Location: frontend/login_wPass.html");
exit();
?>