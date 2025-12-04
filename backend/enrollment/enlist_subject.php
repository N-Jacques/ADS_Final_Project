<?php
header('Content-Type: application/json');
require_once 'connection.php';

$data = json_decode(file_get_contents('php://input'), true);

$student_id = $data['student_id'] ?? '';
$sub_code = $data['sub_code'] ?? '';
$section = $data['section'] ?? '';
$sem_id = $data['sem_id'] ?? '20251';

if (empty($student_id) || empty($sub_code) || empty($section)) {
    echo json_encode(['error' => 'Missing required fields']);
    exit;
}

try {
    $conn->beginTransaction();

    $stmt = $conn->prepare("
        SELECT COUNT(*) 
        FROM subjects_taken 
        WHERE student_id = ? AND sub_code = ? AND grade < 3.0
    ");
    $stmt->execute([$student_id, $sub_code]);
    if ($stmt->fetchColumn() > 0) {
        echo json_encode(['error' => 'Subject already taken']);
        exit;
    }

    $stmt = $conn->prepare("
        SELECT slots 
        FROM schedule 
        WHERE sub_code = ? AND section = ? AND sem_id = ?
        LIMIT 1
    ");
    $stmt->execute([$sub_code, $section, $sem_id]);
    $slots = $stmt->fetchColumn();

    if ($slots <= 0) {
        echo json_encode(['error' => 'No available slots']);
        exit;
    }

    $stmt = $conn->prepare("
        SELECT enlistment_id 
        FROM enlistment 
        WHERE student_id = ? AND sem_id = ?
    ");
    $stmt->execute([$student_id, $sem_id]);
    $enlistment_id = $stmt->fetchColumn();

    if (!$enlistment_id) {
        $stmt = $conn->prepare("
            SELECT CONCAT('E', LPAD(COALESCE(MAX(CAST(SUBSTRING(enlistment_id, 2) AS UNSIGNED)), 0) + 1, 4, '0'))
            FROM enlistment
        ");
        $stmt->execute();
        $enlistment_id = $stmt->fetchColumn();

        $stmt = $conn->prepare("
            INSERT INTO enlistment (enlistment_id, student_id, sem_id, date_created) 
            VALUES (?, ?, ?, CURDATE())
        ");
        $stmt->execute([$enlistment_id, $student_id, $sem_id]);
    }

    $stmt = $conn->prepare("
        SELECT COUNT(*) 
        FROM enlisted_subjects 
        WHERE enlistment_id = ? AND sub_code = ?
    ");
    $stmt->execute([$enlistment_id, $sub_code]);
    if ($stmt->fetchColumn() > 0) {
        echo json_encode(['error' => 'Subject already in enlistment']);
        exit;
    }

    $stmt = $conn->prepare("
        INSERT INTO enlisted_subjects (enlistment_id, sub_code, section) 
        VALUES (?, ?, ?)
    ");
    $stmt->execute([$enlistment_id, $sub_code, $section]);

    $stmt = $conn->prepare("
        UPDATE schedule 
        SET slots = slots - 1 
        WHERE sub_code = ? AND section = ? AND sem_id = ?
    ");
    $stmt->execute([$sub_code, $section, $sem_id]);

    $conn->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Subject enlisted successfully'
    ]);

} catch(PDOException $e) {
    $conn->rollBack();
    echo json_encode(['error' => $e->getMessage()]);
}