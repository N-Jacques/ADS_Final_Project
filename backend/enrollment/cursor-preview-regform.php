<?php
require_once('../tcpdf/tcpdf.php');

// --- DB connection ---
$host = 'localhost';
$db   = 'adsDB';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';
$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, 
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    die("DB connection failed: " . $e->getMessage());
}

$data = json_decode(file_get_contents('php://input'), true);
$student_id = $data['student_id'] ?? null;
$sem_id     = $data['sem_id'] ?? null;

if (!$student_id || !$sem_id) {
    die("Missing student_id or sem_id");
}

// --- Fetch data ---
$sql = "
    SELECT
        stu.student_id,
        CONCAT(stu.lastname, ', ', stu.firstname, ' ', COALESCE(stu.middlename, '')) AS full_name,
        stu.yrlevel AS year_level,
        pr.program_id AS course,
        cl.college_id AS college,
        stt.status_name AS student_type, 
        en.enlistment_id,
        en.date_created AS enlistment_date,
        sem.sem_id,
        sem.date_start AS semester_start,
        sem.date_end AS semester_end,
        subj.sub_code,
        subj.title AS subject_title,
        subj.units,
        sch.section,
        GROUP_CONCAT(
            CONCAT(dd.day_id, ' ', sch.time_start, '-', sch.time_end)
            ORDER BY dd.day_id, sch.time_start
            SEPARATOR ', '
        ) AS scheduledata,
        sch.room
    FROM enlistment en
    JOIN students stu ON stu.student_id = en.student_id
    JOIN programs pr ON pr.program_id = stu.program_id
    JOIN colleges cl ON cl.college_id = pr.college_id
    JOIN status stt ON stt.status_id = stu.status_id   
    JOIN semester sem ON sem.sem_id = en.sem_id
    JOIN enlisted_subjects es ON es.enlistment_id = en.enlistment_id
    JOIN subjects subj ON subj.sub_code = es.sub_code
    LEFT JOIN schedule sch ON sch.sub_code = es.sub_code AND sch.section = es.section AND sch.sem_id = en.sem_id
    LEFT JOIN day_details dd ON dd.day_id = sch.day_id
    WHERE en.student_id = :student_id AND en.sem_id = :sem_id
    GROUP BY subj.sub_code, sch.section
    ORDER BY subj.sub_code, sch.section
";

$stmt = $pdo->prepare($sql);
$stmt->execute([':student_id' => $student_id, ':sem_id' => $sem_id]);

$header = null;
$subjects = [];
while ($row = $stmt->fetch()) {
    if (!$header) {
        $header = [
            'student_id' => $row['student_id'],
            'full_name' => $row['full_name'],
            'year_level' => $row['year_level'],
            'course' => $row['course'],
            'college' => $row['college'],
            'student_type' => $row['student_type'],
            'sem_id' => $row['sem_id'],
        ];
    }

    $subjects[] = [
        'sub_code' => $row['sub_code'] ?? '',
        'subject_title' => $row['subject_title'] ?? '',
        'units' => $row['units'] ?? '',
        'section' => $row['section'] ?? '',
        'scheduledata' => $row['scheduledata'] ?? '',
        'room' => $row['room'] ?? ''
    ];
}

// --- Generate PDF ---
$pdf = new TCPDF();
$pdf->SetCreator(PDF_CREATOR);
$pdf->SetAuthor('ADS Portal');
$pdf->SetTitle('Student Registration Form');
$pdf->SetMargins(10, 10, 10);
$pdf->AddPage();

// --- Header with inline logo ---
$logo_file = '../../assets/plm-logo.jpg'; // JPEG recommended
$pdf->Image($logo_file, 10, 10, 17, 17, 'JPG'); // Logo
$pdf->SetXY(30, 10); // Right of logo, aligned vertically
$pdf->SetFont('times', 'B', 16);
$pdf->Cell(0, 7, 'PAMANTASAN NG LUNGSOD NG MAYNILA', 0, 1, 'L'); // border=0
$pdf->SetFont('times', '', 14);
$pdf->SetX(30);
$pdf->Cell(0, 7, 'Student Registration Form', 0, 1, 'L');
$pdf->Ln(5);

// --- Student info table (compact) ---
$pdf->SetFont('times', '', 12);
$info_table_html = '<table cellpadding="1" cellspacing="0" border="0" style="width:100%; margin-top:2px;">';
$info_table_html .= '<tr>
<td width="50%"><b>Name:</b> '.$header['full_name'].'</td>
<td width="50%"><b>Student ID:</b> '.$header['student_id'].'</td>
</tr>';
$info_table_html .= '<tr>
<td width="50%"><b>Semester:</b> '.$header['sem_id'].'</td>
<td width="50%"><b>Student Type:</b> '.$header['student_type'].'</td>
</tr>';
$info_table_html .= '<tr>
<td width="50%"><b>College:</b> '.$header['college'].'</td>
<td width="50%"><b>Course:</b> '.$header['course'].'</td>
</tr>';
$info_table_html .= '<tr>
<td width="50%"><b>Year Level:</b> '.$header['year_level'].'</td>
<td width="50%"></td>
</tr>';
$info_table_html .= '</table>';

$pdf->writeHTML($info_table_html, true, false, false, false, '');

// --- Schedule table ---
$schedule_html = '<table cellpadding="4" cellspacing="0" border="0" style="width:100%; border-collapse:collapse; margin-top:2px;">';
$schedule_html .= '<thead>
<tr style="background-color:#c0392b; color:#fff; text-align:center;">
<th style="border-bottom:2px solid #000;">Subject Code</th>
<th style="border-bottom:2px solid #000;">Title</th>
<th style="border-bottom:2px solid #000;">Units</th>
<th style="border-bottom:2px solid #000;">Section</th>
<th style="border-bottom:2px solid #000;">Schedule</th>
<th style="border-bottom:2px solid #000;">Room</th>
</tr>
</thead><tbody>';

foreach ($subjects as $s) {
    $schedule_html .= '<tr style="text-align:center;">
<td>'.$s['sub_code'].'</td>
<td style="text-align:left;">'.$s['subject_title'].'</td>
<td>'.$s['units'].'</td>
<td>'.$s['section'].'</td>
<td style="text-align:left;">'.$s['scheduledata'].'</td>
<td>'.$s['room'].'</td>
</tr>';
}

$schedule_html .= '</tbody></table>';
$pdf->writeHTML($schedule_html, true, false, false, false, '');

// Optional auto-print
$pdf->IncludeJS("print(true);");
$pdf->Output('registration_form.pdf', 'I');
?>