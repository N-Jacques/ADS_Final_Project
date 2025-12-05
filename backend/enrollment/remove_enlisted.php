<?php
header('Content-Type: application/json');
require_once 'connection.php';

$data = json_decode(file_get_contents('php://input'), true);

$student_id = $data['student_id'] ?? '';
$sub_code = $data['sub_code'] ?? '';
$section = $data['section'] ?? '';
$sem_id = $data['sem_id'] ?? '20251';

try {
    $conn->beginTransaction();

    $stmt = $conn->prepare("
        SELECT enlistment_id 
        FROM enlistment 
        WHERE student_id = ? AND sem_id = ?
    ");
    $stmt->execute([$student_id, $sem_id]);
    $enlistment_id = $stmt->fetchColumn();

    if (!$enlistment_id) {
        echo json_encode(['error' => 'No enlistment found']);
        exit;
    }

    $stmt = $conn->prepare("
        DELETE FROM enlisted_subjects 
        WHERE enlistment_id = ? AND sub_code = ? AND section = ?
    ");
    $stmt->execute([$enlistment_id, $sub_code, $section]);

    $stmt = $conn->prepare("
        UPDATE schedule 
        SET slots = slots + 1 
        WHERE sub_code = ? AND section = ? AND sem_id = ?
    ");
    $stmt->execute([$sub_code, $section, $sem_id]);

    $conn->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Subject removed successfully'
    ]);

} catch(PDOException $e) {
    $conn->rollBack();
    echo json_encode(['error' => $e->getMessage()]);
}
?>