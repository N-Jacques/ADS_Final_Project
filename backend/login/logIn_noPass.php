<?php
session_start(); //

// Database credentials
$host = 'localhost';
$dbname = 'adsDB';
$username = 'root';
$password = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // 1. Get the Input
    // MAKE SURE your HTML input has name="studentid"
    $studentID = trim($_POST['studentid']);

    try {
        $conn = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // 2. Query the 'credentials' table
        $stmt = $conn->prepare("SELECT * FROM credentials WHERE student_id = :studentid");
        $stmt->bindParam(":studentid", $studentID);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            // 3. Login Success
            
            // --- THE FIX 1: Save to Session (Fixes "didn't fetch details") ---
            $_SESSION['student_id'] = $row['student_id'];
            $_SESSION['user_role'] = 'student'; 
            // --------------------------------

            // --- THE FIX 2: Use Absolute Path (Fixes "Not Found" error) ---
            // We use the path that worked in your previous version
            header("Location: /ADS_Final_Project/frontend/studentProfile.html");
            exit();
        } else {
            // 4. Login Failed
            echo "<script>
                alert('Invalid Student ID! Please try again.');
                // Use Absolute Path here too
                window.location.href = '/ADS_Final_Project/frontend/logIn_noPass.html';
            </script>";
            exit();
        }
    } catch(PDOException $e) {
        die("Connection failed: " . $e->getMessage());
    }
}
?>