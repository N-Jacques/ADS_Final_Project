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
header("Location: frontend/logIn_wPass.html");
exit();
?>