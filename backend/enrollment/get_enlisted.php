<?php
header('Content-Type: application/json');
require_once 'connection.php';

$student_id = $_GET['student_id'] ?? '';
$sem_id = $_GET['sem_id'] ?? '20251';

if (empty($student_id)) {
    echo json_encode(['error' => 'Student ID required']);
    exit;
}

try {
    $stmt = $conn->prepare("
        SELECT enlistment_id 
        FROM enlistment 
        WHERE student_id = ? AND sem_id = ?
    ");
    $stmt->execute([$student_id, $sem_id]);
    $enlistment_id = $stmt->fetchColumn();

    if (!$enlistment_id) {
        echo json_encode([
            'success' => true,
            'enlisted' => []
        ]);
        exit;
    }

    $stmt = $conn->prepare("
        SELECT 
            s.sub_code,
            s.title,
            s.units,
            st.subtype_desc,
            GROUP_CONCAT(
                CONCAT(dd.day_name, ' ', sch.time_start, '-', sch.time_end)
                SEPARATOR ', '
            ) as schedule,
            es.section,
            sch.room
        FROM enlisted_subjects es
        JOIN subjects s ON es.sub_code = s.sub_code
        JOIN subject_type st ON s.subtype_id = st.subtype_id
        JOIN schedule sch ON s.sub_code = sch.sub_code AND es.section = sch.section
        JOIN day_details dd ON sch.day_id = dd.day_id
        WHERE es.enlistment_id = ?
        AND sch.sem_id = ?
        GROUP BY s.sub_code, es.section
    ");
    $stmt->execute([$enlistment_id, $sem_id]);
    $enlisted = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'enlisted' => $enlisted
    ]);

} catch(PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>