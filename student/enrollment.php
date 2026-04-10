<?php
session_start();
include("../config/database.php");

// Only students can access
if(!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'Student'){
    header("Location: ../auth/login.php");
    exit();
}

$student_id = $_SESSION['user']['id'];

// Fetch student details from database
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$student_id]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);

// Fetch existing enrollment
$stmt = $conn->prepare("SELECT e.*, g.grade_name 
                        FROM enrollments e 
                        LEFT JOIN grade_levels g ON e.grade_id = g.id
                        WHERE e.student_id = ?");
$stmt->execute([$student_id]);
$enroll = $stmt->fetch(PDO::FETCH_ASSOC);

// Fetch grade levels
$grades = $conn->query("SELECT * FROM grade_levels ORDER BY id");

// Define strands for Senior High (Grade 11-12)
$senior_strands = ['STEM','ABM','GAS','HUMSS','ICT','HE','Sports','Arts'];

// Get student type options based on grade level
function getStudentTypeOptions($grade_name) {
    $options = [];
    
    switch($grade_name) {
        case 'Grade 7':
            $options = ['new'];
            break;
        case 'Grade 8':
        case 'Grade 9':
        case 'Grade 10':
            $options = ['continuing', 'transferee'];
            break;
        case 'Grade 11':
            $options = ['same_school', 'different_school'];
            break;
        case 'Grade 12':
            $options = ['continuing', 'transferee'];
            break;
        default:
            $options = ['new', 'continuing', 'transferee'];
    }
    
    return $options;
}

// Handle enrollment submission
if(isset($_POST['enroll'])){
    $grade_id = $_POST['grade_id'];
    $school_year = $_POST['school_year'];
    $strand = isset($_POST['strand']) && !empty($_POST['strand']) ? $_POST['strand'] : null;
    $student_type = $_POST['student_type'];
    
    // Get grade name
    $stmt = $conn->prepare("SELECT grade_name FROM grade_levels WHERE id = ?");
    $stmt->execute([$grade_id]);
    $grade_row = $stmt->fetch(PDO::FETCH_ASSOC);
    $grade_name = $grade_row['grade_name'];
    
    // Check if student already enrolled for this school year
    $check = $conn->prepare("SELECT * FROM enrollments WHERE student_id = ? AND school_year = ?");
    $check->execute([$student_id, $school_year]);
    if($check->rowCount() > 0){
        $error = "You have already submitted an enrollment for this school year.";
    } else {
        // Create uploads directory if not exists
        if(!is_dir("../uploads/enrollment_docs")) {
            mkdir("../uploads/enrollment_docs", 0777, true);
        }
        
        $uploaded_files = [];
        $errors = [];
        
        // Requirements data with correct document mappings
        $requirementsData = [
            'Grade 7' => [
                'new' => [
                    'form_137' => ['required' => true, 'can_follow' => false, 'label' => 'Form 137 (Permanent Record)'],
                    'certificate_of_completion' => ['required' => true, 'can_follow' => false, 'label' => 'Certificate of Completion (Elementary)'],
                    'psa_birth_cert' => ['required' => true, 'can_follow' => false, 'label' => 'PSA Birth Certificate'],
                    'id_pictures' => ['required' => true, 'can_follow' => false, 'label' => '2x2 ID Pictures'],
                    'good_moral_cert' => ['required' => true, 'can_follow' => false, 'label' => 'Good Moral Certificate'],
                    'medical_cert' => ['required' => false, 'can_follow' => true, 'label' => 'Medical/Dental Certificate']
                ]
            ],
            'Grade 8' => [
                'continuing' => [
                    'form_138' => ['required' => true, 'can_follow' => false, 'label' => 'Form 138 (Grade 7 Report Card)']
                ],
                'transferee' => [
                    'form_138' => ['required' => true, 'can_follow' => false, 'label' => 'Form 138 (Latest Report Card)'],
                    'form_137' => ['required' => true, 'can_follow' => true, 'label' => 'Form 137 (Permanent Record - to follow)'],
                    'psa_birth_cert' => ['required' => true, 'can_follow' => false, 'label' => 'PSA Birth Certificate'],
                    'good_moral_cert' => ['required' => true, 'can_follow' => false, 'label' => 'Good Moral Certificate'],
                    'id_pictures' => ['required' => true, 'can_follow' => false, 'label' => '2x2 ID Pictures'],
                    'entrance_exam_result' => ['required' => false, 'can_follow' => true, 'label' => 'Entrance Exam / Interview Result']
                ]
            ],
            'Grade 9' => [
                'continuing' => [
                    'form_138' => ['required' => true, 'can_follow' => false, 'label' => 'Form 138 (Grade 8 Report Card)']
                ],
                'transferee' => [
                    'form_138' => ['required' => true, 'can_follow' => false, 'label' => 'Form 138 (Latest Report Card)'],
                    'form_137' => ['required' => true, 'can_follow' => true, 'label' => 'Form 137 (Permanent Record - to follow)'],
                    'psa_birth_cert' => ['required' => true, 'can_follow' => false, 'label' => 'PSA Birth Certificate'],
                    'good_moral_cert' => ['required' => true, 'can_follow' => false, 'label' => 'Good Moral Certificate'],
                    'id_pictures' => ['required' => true, 'can_follow' => false, 'label' => '2x2 ID Pictures'],
                    'entrance_exam_result' => ['required' => false, 'can_follow' => true, 'label' => 'Entrance Exam / Interview Result']
                ]
            ],
            'Grade 10' => [
                'continuing' => [
                    'form_138' => ['required' => true, 'can_follow' => false, 'label' => 'Form 138 (Grade 9 Report Card)']
                ],
                'transferee' => [
                    'form_138' => ['required' => true, 'can_follow' => false, 'label' => 'Form 138 (Latest Report Card)'],
                    'form_137' => ['required' => true, 'can_follow' => true, 'label' => 'Form 137 (Permanent Record - to follow)'],
                    'psa_birth_cert' => ['required' => true, 'can_follow' => false, 'label' => 'PSA Birth Certificate'],
                    'good_moral_cert' => ['required' => true, 'can_follow' => false, 'label' => 'Good Moral Certificate'],
                    'id_pictures' => ['required' => true, 'can_follow' => false, 'label' => '2x2 ID Pictures'],
                    'entrance_exam_result' => ['required' => false, 'can_follow' => true, 'label' => 'Entrance Exam / Interview Result']
                ]
            ],
            'Grade 11' => [
                'same_school' => [
                    'form_138' => ['required' => true, 'can_follow' => false, 'label' => 'Form 138 (Grade 10 Report Card)'],
                    'certificate_of_completion' => ['required' => true, 'can_follow' => false, 'label' => 'Certificate of Completion (Junior High)'],
                    'psa_birth_cert' => ['required' => true, 'can_follow' => false, 'label' => 'PSA Birth Certificate'],
                    'good_moral_cert' => ['required' => true, 'can_follow' => false, 'label' => 'Good Moral Certificate']
                ],
                'different_school' => [
                    'form_137' => ['required' => true, 'can_follow' => false, 'label' => 'Form 137 (Permanent Record)'],
                    'form_138' => ['required' => true, 'can_follow' => false, 'label' => 'Form 138 (Grade 10 Report Card)'],
                    'certificate_of_completion' => ['required' => true, 'can_follow' => false, 'label' => 'Certificate of Completion (Junior High)'],
                    'psa_birth_cert' => ['required' => true, 'can_follow' => false, 'label' => 'PSA Birth Certificate'],
                    'good_moral_cert' => ['required' => true, 'can_follow' => false, 'label' => 'Good Moral Certificate'],
                    'entrance_exam_result' => ['required' => false, 'can_follow' => true, 'label' => 'Entrance Exam / Screening Result']
                ]
            ],
            'Grade 12' => [
                'continuing' => [
                    'form_138' => ['required' => true, 'can_follow' => false, 'label' => 'Form 138 (Grade 11 Report Card)']
                ],
                'transferee' => [
                    'form_138' => ['required' => true, 'can_follow' => false, 'label' => 'Form 138 (Grade 11 Report Card)'],
                    'form_137' => ['required' => true, 'can_follow' => false, 'label' => 'Form 137 (Permanent Record)'],
                    'psa_birth_cert' => ['required' => true, 'can_follow' => false, 'label' => 'PSA Birth Certificate'],
                    'good_moral_cert' => ['required' => true, 'can_follow' => false, 'label' => 'Good Moral Certificate'],
                    'id_pictures' => ['required' => true, 'can_follow' => false, 'label' => '2x2 ID Pictures']
                ]
            ]
        ];
        
        // Process each required document
        if(isset($requirementsData[$grade_name][$student_type])) {
            $requirements = $requirementsData[$grade_name][$student_type];
            
            foreach($requirements as $field_name => $req) {
                if(isset($_FILES[$field_name]) && $_FILES[$field_name]['error'] == 0) {
                    $allowed = ['pdf','jpg','jpeg','png'];
                    $ext = strtolower(pathinfo($_FILES[$field_name]['name'], PATHINFO_EXTENSION));
                    if(in_array($ext, $allowed)) {
                        $filename = "uploads/enrollment_docs/".$student_id."_".$field_name."_".time().".".$ext;
                        if(move_uploaded_file($_FILES[$field_name]['tmp_name'], "../".$filename)) {
                            $uploaded_files[$field_name] = $filename;
                        } else {
                            $errors[] = "Failed to upload " . $req['label'];
                        }
                    } else {
                        $errors[] = $req['label'] . " must be PDF or image file.";
                    }
                } elseif($req['required'] && !$req['can_follow']) {
                    $errors[] = $req['label'] . " is required.";
                }
            }
        }
        
        if(empty($errors)){
            // Prepare insert statement
            $sql = "INSERT INTO enrollments (student_id, grade_id, school_year, status, strand, student_type, 
                    form_138, form_137, psa_birth_cert, good_moral_cert, certificate_of_completion, 
                    id_pictures, medical_cert, entrance_exam_result) 
                    VALUES (?, ?, ?, 'Pending', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $conn->prepare($sql);
            $result = $stmt->execute([
                $student_id, $grade_id, $school_year, $strand, $student_type,
                $uploaded_files['form_138'] ?? null,
                $uploaded_files['form_137'] ?? null,
                $uploaded_files['psa_birth_cert'] ?? null,
                $uploaded_files['good_moral_cert'] ?? null,
                $uploaded_files['certificate_of_completion'] ?? null,
                $uploaded_files['id_pictures'] ?? null,
                $uploaded_files['medical_cert'] ?? null,
                $uploaded_files['entrance_exam_result'] ?? null
            ]);
            
            if($result){
                $success = "Enrollment submitted successfully! Wait for approval.";
                // Refresh enrollment data
                $stmt = $conn->prepare("SELECT e.*, g.grade_name 
                                        FROM enrollments e 
                                        LEFT JOIN grade_levels g ON e.grade_id = g.id
                                        WHERE e.student_id = ?");
                $stmt->execute([$student_id]);
                $enroll = $stmt->fetch(PDO::FETCH_ASSOC);
            } else {
                $errorInfo = $stmt->errorInfo();
                $error = "Error submitting enrollment: " . ($errorInfo[2] ?? 'Unknown error');
            }
        } else {
            $error = implode("<br>", $errors);
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enrollment Form - Placido L. Señor Senior High School</title>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #0B4F2E, #1a7a42);
            min-height: 100vh;
            padding: 40px 20px;
        }

        .container {
            max-width: 900px;
            margin: 0 auto;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            color: white;
        }

        .header h1 {
            font-size: 28px;
            font-weight: 700;
        }

        .back-btn {
            background: rgba(255,255,255,0.2);
            color: white;
            padding: 10px 20px;
            border-radius: 10px;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
        }

        .back-btn:hover {
            background: rgba(255,255,255,0.3);
        }

        .form-container {
            background: white;
            border-radius: 30px;
            padding: 40px;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.3);
            animation: slideUp 0.6s ease;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .school-header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #f0f0f0;
        }

        .school-header h2 {
            font-size: 24px;
            color: #0B4F2E;
            margin-bottom: 5px;
        }

        .school-header p {
            color: #666;
            font-style: italic;
        }

        .student-info {
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 30px;
            border-left: 4px solid #0B4F2E;
        }

        .student-info h3 {
            color: #0B4F2E;
            margin-bottom: 15px;
            font-size: 18px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }

        .info-item {
            background: white;
            padding: 12px 15px;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }

        .info-item .label {
            font-size: 12px;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 5px;
        }

        .info-item .value {
            font-size: 16px;
            font-weight: 600;
            color: #333;
        }

        .alert {
            padding: 15px 20px;
            border-radius: 12px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: slideIn 0.3s ease;
        }

        .alert-error {
            background: #fee2e2;
            color: #dc2626;
            border-left: 4px solid #dc2626;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border-left: 4px solid #28a745;
        }

        .alert-info {
            background: #e8f4f8;
            color: #0c5460;
            border-left: 4px solid #17a2b8;
        }

        .alert i {
            font-size: 18px;
        }

        .existing-enrollment {
            background: #e8f4f8;
            border-radius: 15px;
            padding: 25px;
            text-align: center;
        }

        .enrollment-badge {
            display: inline-block;
            padding: 8px 20px;
            border-radius: 50px;
            font-weight: 600;
            margin: 15px 0;
        }

        .badge-pending {
            background: #fff3cd;
            color: #856404;
        }

        .badge-approved {
            background: #d4edda;
            color: #155724;
        }

        .badge-rejected {
            background: #f8d7da;
            color: #721c24;
        }

        .btn-dashboard {
            display: inline-block;
            background: #0B4F2E;
            color: white;
            padding: 12px 30px;
            border-radius: 10px;
            text-decoration: none;
            margin-top: 20px;
            transition: all 0.3s;
        }

        .btn-dashboard:hover {
            background: #1a7a42;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(11, 79, 46, 0.3);
        }

        .form-group {
            margin-bottom: 25px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #555;
            font-weight: 600;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .form-group label .required {
            color: #dc3545;
            margin-left: 3px;
        }

        .input-wrapper {
            position: relative;
        }

        .input-wrapper input,
        .input-wrapper select {
            width: 100%;
            padding: 14px 16px;
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            font-size: 15px;
            transition: all 0.3s;
            background-color: #f8f9fa;
        }

        .input-wrapper input:focus,
        .input-wrapper select:focus {
            border-color: #0B4F2E;
            background-color: #ffffff;
            outline: none;
            box-shadow: 0 0 0 4px rgba(11, 79, 46, 0.1);
        }

        .requirements-section {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 25px;
            margin: 25px 0;
            border: 2px solid #0B4F2E;
        }

        .requirements-section h3 {
            color: #0B4F2E;
            margin-bottom: 15px;
            font-size: 18px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .requirements-list {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 15px;
        }

        .requirement-item {
            background: white;
            border-radius: 10px;
            padding: 15px;
            border-left: 3px solid #0B4F2E;
        }

        .requirement-name {
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }

        .requirement-badge {
            font-size: 11px;
            padding: 3px 8px;
            border-radius: 12px;
        }

        .badge-required {
            background: #dc3545;
            color: white;
        }

        .badge-optional {
            background: #6c757d;
            color: white;
        }

        .badge-follow {
            background: #ffc107;
            color: #333;
        }

        .file-upload-area {
            margin-top: 10px;
            border: 2px dashed #ddd;
            border-radius: 8px;
            padding: 10px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
        }

        .file-upload-area:hover {
            border-color: #0B4F2E;
            background: #f8f9fa;
        }

        .file-upload-area i {
            font-size: 24px;
            color: #0B4F2E;
            margin-right: 10px;
        }

        .file-name {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }

        .strand-section {
            background: #e8f4f8;
            border-radius: 15px;
            padding: 20px;
            margin: 20px 0;
            border: 2px dashed #0B4F2E;
            animation: fadeIn 0.5s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .info-note {
            background: #e8f4f8;
            border-left: 4px solid #17a2b8;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 13px;
            color: #0c5460;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .info-note i {
            font-size: 18px;
            color: #17a2b8;
        }

        .submit-btn {
            width: 100%;
            background: linear-gradient(135deg, #0B4F2E, #1a7a42);
            color: white;
            padding: 16px;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            cursor: pointer;
            transition: all 0.3s;
            margin-top: 30px;
        }

        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(11, 79, 46, 0.3);
        }

        .form-footer {
            text-align: center;
            margin-top: 20px;
        }

        .form-footer a {
            color: #666;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: color 0.3s;
        }

        .form-footer a:hover {
            color: #0B4F2E;
        }

        @media (max-width: 600px) {
            .form-container {
                padding: 20px;
            }
            
            .requirements-list {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🏫 Enrollment Form</h1>
            <a href="dashboard.php" class="back-btn">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>

        <div class="form-container">
            <div class="school-header">
                <h2>Placido L. Señor Senior High School</h2>
                <p>Student Enrollment Application</p>
            </div>

            <!-- Display Student Information -->
            <?php if($student): ?>
            <div class="student-info">
                <h3><i class="fas fa-user-graduate"></i> Student Information</h3>
                <div class="info-grid">
                    <div class="info-item">
                        <div class="label">Full Name</div>
                        <div class="value">
                            <?php 
                            if(isset($student['firstname']) && isset($student['lastname'])) {
                                $fullname = $student['firstname'] . ' ' . ($student['middlename'] ? $student['middlename'] . ' ' : '') . $student['lastname'];
                            } else {
                                $fullname = $student['fullname'] ?? 'N/A';
                            }
                            echo htmlspecialchars($fullname);
                            ?>
                        </div>
                    </div>
                    <div class="info-item">
                        <div class="label">Student ID</div>
                        <div class="value"><?php echo isset($student['id_number']) && $student['id_number'] ? htmlspecialchars($student['id_number']) : 'Not Assigned'; ?></div>
                    </div>
                    <div class="info-item">
                        <div class="label">Birthdate</div>
                        <div class="value"><?php echo isset($student['birthdate']) && $student['birthdate'] ? date('F d, Y', strtotime($student['birthdate'])) : 'Not Provided'; ?></div>
                    </div>
                    <div class="info-item">
                        <div class="label">Gender</div>
                        <div class="value"><?php echo isset($student['gender']) && $student['gender'] ? htmlspecialchars($student['gender']) : 'Not Provided'; ?></div>
                    </div>
                    <div class="info-item">
                        <div class="label">Email</div>
                        <div class="value"><?php echo htmlspecialchars($student['email'] ?? 'N/A'); ?></div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <?php if(isset($error)): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <?php if(isset($success)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?php echo $success; ?>
                </div>
            <?php endif; ?>

            <?php if($enroll && isset($enroll['id'])): ?>
                <div class="existing-enrollment">
                    <i class="fas fa-file-alt" style="font-size: 48px; color: #0B4F2E; margin-bottom: 15px;"></i>
                    <h3>Enrollment Already Submitted</h3>
                    <div class="enrollment-badge badge-<?php echo isset($enroll['status']) ? strtolower($enroll['status']) : 'pending'; ?>">
                        Status: <?php echo isset($enroll['status']) ? $enroll['status'] : 'Pending'; ?>
                    </div>
                    <p><strong>Grade Level:</strong> <?php echo isset($enroll['grade_name']) ? $enroll['grade_name'] : 'N/A'; ?></p>
                    <?php if(isset($enroll['strand']) && $enroll['strand']): ?>
                        <p><strong>Strand:</strong> <?php echo $enroll['strand']; ?></p>
                    <?php endif; ?>
                    <p><strong>School Year:</strong> <?php echo isset($enroll['school_year']) ? $enroll['school_year'] : 'N/A'; ?></p>
                    <a href="dashboard.php" class="btn-dashboard">
                        <i class="fas fa-tachometer-alt"></i> Go to Dashboard
                    </a>
                </div>
            <?php else: ?>
                <form method="POST" enctype="multipart/form-data" id="enrollmentForm">
                    <!-- GRADE LEVEL -->
                    <div class="form-group">
                        <label for="grade">Select Grade Level <span class="required">*</span></label>
                        <div class="input-wrapper">
                            <select name="grade_id" id="grade" onchange="updateStudentTypeOptions()" required>
                                <option value="">-- Select Grade Level --</option>
                                <?php
                                $grades->execute();
                                while($g = $grades->fetch(PDO::FETCH_ASSOC)){
                                    echo "<option value='{$g['id']}' data-grade='{$g['grade_name']}'>{$g['grade_name']}</option>";
                                }
                                ?>
                            </select>
                            <i class="fas fa-chevron-down" style="position: absolute; right: 16px; top: 50%; transform: translateY(-50%); color: #666;"></i>
                        </div>
                    </div>

                    <!-- STUDENT TYPE -->
                    <div class="form-group">
                        <label for="student_type">Student Type <span class="required">*</span></label>
                        <div class="input-wrapper">
                            <select name="student_type" id="student_type" onchange="updateRequirements()" required>
                                <option value="">-- Select Student Type --</option>
                            </select>
                            <i class="fas fa-chevron-down" style="position: absolute; right: 16px; top: 50%; transform: translateY(-50%); color: #666;"></i>
                        </div>
                    </div>

                    <!-- REQUIREMENTS SECTION -->
                    <div id="requirementsSection" class="requirements-section" style="display: none;">
                        <h3><i class="fas fa-file-alt"></i> Enrollment Requirements</h3>
                        <div id="requirementsList" class="requirements-list"></div>
                    </div>

                    <!-- STRAND SECTION (For Grade 11-12) -->
                    <div id="strandDiv" class="strand-section" style="display: none;">
                        <div class="form-group">
                            <label>Select Strand (Required for Grade 11-12)</label>
                            <div class="input-wrapper">
                                <select name="strand" id="strand">
                                    <option value="">-- Select Strand --</option>
                                    <?php foreach($senior_strands as $s): ?>
                                        <option value="<?php echo $s; ?>"><?php echo $s; ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <i class="fas fa-chevron-down" style="position: absolute; right: 16px; top: 50%; transform: translateY(-50%); color: #666;"></i>
                            </div>
                        </div>
                    </div>

                    <!-- SCHOOL YEAR -->
                    <div class="form-group">
                        <label for="school_year">School Year <span class="required">*</span></label>
                        <div class="input-wrapper">
                            <input type="text" name="school_year" id="school_year" placeholder="e.g., 2026-2027" required>
                            <i class="fas fa-calendar" style="position: absolute; right: 16px; top: 50%; transform: translateY(-50%); color: #666;"></i>
                        </div>
                    </div>

                    <button type="submit" name="enroll" class="submit-btn">
                        <i class="fas fa-paper-plane"></i> Submit Enrollment
                    </button>
                </form>

                <div class="form-footer">
                    <a href="dashboard.php">
                        <i class="fas fa-arrow-left"></i> Cancel and return to Dashboard
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Complete requirements data with correct document mappings
        const requirementsData = {
            'Grade 7': {
                'new': [
                    { name: 'Form 137 (Permanent Record)', required: true, can_follow: false, field: 'form_137' },
                    { name: 'Certificate of Completion (Elementary)', required: true, can_follow: false, field: 'certificate_of_completion' },
                    { name: 'PSA Birth Certificate', required: true, can_follow: false, field: 'psa_birth_cert' },
                    { name: '2x2 ID Pictures', required: true, can_follow: false, field: 'id_pictures' },
                    { name: 'Good Moral Certificate', required: true, can_follow: false, field: 'good_moral_cert' },
                    { name: 'Medical/Dental Certificate', required: false, can_follow: true, field: 'medical_cert' }
                ]
            },
            'Grade 8': {
                'continuing': [
                    { name: 'Form 138 (Grade 7 Report Card)', required: true, can_follow: false, field: 'form_138' }
                ],
                'transferee': [
                    { name: 'Form 138 (Latest Report Card)', required: true, can_follow: false, field: 'form_138' },
                    { name: 'Form 137 (Permanent Record - to follow)', required: true, can_follow: true, field: 'form_137' },
                    { name: 'PSA Birth Certificate', required: true, can_follow: false, field: 'psa_birth_cert' },
                    { name: 'Good Moral Certificate', required: true, can_follow: false, field: 'good_moral_cert' },
                    { name: '2x2 ID Pictures', required: true, can_follow: false, field: 'id_pictures' },
                    { name: 'Entrance Exam / Interview Result', required: false, can_follow: true, field: 'entrance_exam_result' }
                ]
            },
            'Grade 9': {
                'continuing': [
                    { name: 'Form 138 (Grade 8 Report Card)', required: true, can_follow: false, field: 'form_138' }
                ],
                'transferee': [
                    { name: 'Form 138 (Latest Report Card)', required: true, can_follow: false, field: 'form_138' },
                    { name: 'Form 137 (Permanent Record - to follow)', required: true, can_follow: true, field: 'form_137' },
                    { name: 'PSA Birth Certificate', required: true, can_follow: false, field: 'psa_birth_cert' },
                    { name: 'Good Moral Certificate', required: true, can_follow: false, field: 'good_moral_cert' },
                    { name: '2x2 ID Pictures', required: true, can_follow: false, field: 'id_pictures' },
                    { name: 'Entrance Exam / Interview Result', required: false, can_follow: true, field: 'entrance_exam_result' }
                ]
            },
            'Grade 10': {
                'continuing': [
                    { name: 'Form 138 (Grade 9 Report Card)', required: true, can_follow: false, field: 'form_138' }
                ],
                'transferee': [
                    { name: 'Form 138 (Latest Report Card)', required: true, can_follow: false, field: 'form_138' },
                    { name: 'Form 137 (Permanent Record - to follow)', required: true, can_follow: true, field: 'form_137' },
                    { name: 'PSA Birth Certificate', required: true, can_follow: false, field: 'psa_birth_cert' },
                    { name: 'Good Moral Certificate', required: true, can_follow: false, field: 'good_moral_cert' },
                    { name: '2x2 ID Pictures', required: true, can_follow: false, field: 'id_pictures' },
                    { name: 'Entrance Exam / Interview Result', required: false, can_follow: true, field: 'entrance_exam_result' }
                ]
            },
            'Grade 11': {
                'same_school': [
                    { name: 'Form 138 (Grade 10 Report Card)', required: true, can_follow: false, field: 'form_138' },
                    { name: 'Certificate of Completion (Junior High)', required: true, can_follow: false, field: 'certificate_of_completion' },
                    { name: 'PSA Birth Certificate', required: true, can_follow: false, field: 'psa_birth_cert' },
                    { name: 'Good Moral Certificate', required: true, can_follow: false, field: 'good_moral_cert' }
                ],
                'different_school': [
                    { name: 'Form 137 (Permanent Record)', required: true, can_follow: false, field: 'form_137' },
                    { name: 'Form 138 (Grade 10 Report Card)', required: true, can_follow: false, field: 'form_138' },
                    { name: 'Certificate of Completion (Junior High)', required: true, can_follow: false, field: 'certificate_of_completion' },
                    { name: 'PSA Birth Certificate', required: true, can_follow: false, field: 'psa_birth_cert' },
                    { name: 'Good Moral Certificate', required: true, can_follow: false, field: 'good_moral_cert' },
                    { name: 'Entrance Exam / Screening Result', required: false, can_follow: true, field: 'entrance_exam_result' }
                ]
            },
            'Grade 12': {
                'continuing': [
                    { name: 'Form 138 (Grade 11 Report Card)', required: true, can_follow: false, field: 'form_138' }
                ],
                'transferee': [
                    { name: 'Form 138 (Grade 11 Report Card)', required: true, can_follow: false, field: 'form_138' },
                    { name: 'Form 137 (Permanent Record)', required: true, can_follow: false, field: 'form_137' },
                    { name: 'PSA Birth Certificate', required: true, can_follow: false, field: 'psa_birth_cert' },
                    { name: 'Good Moral Certificate', required: true, can_follow: false, field: 'good_moral_cert' },
                    { name: '2x2 ID Pictures', required: true, can_follow: false, field: 'id_pictures' }
                ]
            }
        };

        function updateStudentTypeOptions() {
            const gradeSelect = document.getElementById('grade');
            const studentTypeSelect = document.getElementById('student_type');
            const selectedOption = gradeSelect.options[gradeSelect.selectedIndex];
            const gradeName = selectedOption ? selectedOption.getAttribute('data-grade') : '';
            
            // Clear existing options
            studentTypeSelect.innerHTML = '<option value="">-- Select Student Type --</option>';
            
            if(gradeName) {
                let options = [];
                
                switch(gradeName) {
                    case 'Grade 7':
                        options = [
                            { value: 'new', label: 'New Student (From Elementary)' }
                        ];
                        break;
                    case 'Grade 8':
                    case 'Grade 9':
                    case 'Grade 10':
                        options = [
                            { value: 'continuing', label: 'Continuing Student (Moving to next grade)' },
                            { value: 'transferee', label: 'Transferee (From another school)' }
                        ];
                        break;
                    case 'Grade 11':
                        options = [
                            { value: 'same_school', label: 'From the same school (Placido L. Señor SHS - Junior High)' },
                            { value: 'different_school', label: 'From a different school (Transferee)' }
                        ];
                        break;
                    case 'Grade 12':
                        options = [
                            { value: 'continuing', label: 'Continuing Student (From Grade 11)' },
                            { value: 'transferee', label: 'Transferee (From another school)' }
                        ];
                        break;
                }
                
                options.forEach(opt => {
                    const option = document.createElement('option');
                    option.value = opt.value;
                    option.textContent = opt.label;
                    studentTypeSelect.appendChild(option);
                });
            }
            
            // Reset requirements section
            document.getElementById('requirementsSection').style.display = 'none';
            document.getElementById('requirementsList').innerHTML = '';
            
            // Update strand visibility
            updateStrandVisibility(gradeName);
        }
        
        function updateStrandVisibility(gradeName) {
            const strandDiv = document.getElementById('strandDiv');
            if(gradeName === 'Grade 11' || gradeName === 'Grade 12') {
                strandDiv.style.display = 'block';
                document.getElementById('strand').setAttribute('required', 'required');
            } else {
                strandDiv.style.display = 'none';
                document.getElementById('strand').removeAttribute('required');
            }
        }

        function updateRequirements() {
            const gradeSelect = document.getElementById('grade');
            const studentTypeSelect = document.getElementById('student_type');
            const requirementsSection = document.getElementById('requirementsSection');
            const requirementsList = document.getElementById('requirementsList');
            
            const selectedOption = gradeSelect.options[gradeSelect.selectedIndex];
            const gradeName = selectedOption ? selectedOption.getAttribute('data-grade') : '';
            const studentType = studentTypeSelect.value;
            
            if(gradeName && studentType && requirementsData[gradeName] && requirementsData[gradeName][studentType]) {
                requirementsSection.style.display = 'block';
                const requirements = requirementsData[gradeName][studentType];
                
                requirementsList.innerHTML = '';
                requirements.forEach(req => {
                    const reqDiv = document.createElement('div');
                    reqDiv.className = 'requirement-item';
                    
                    let badgeHtml = '';
                    if(req.required) {
                        badgeHtml = '<span class="requirement-badge badge-required">Required</span>';
                    } else {
                        badgeHtml = '<span class="requirement-badge badge-optional">Optional</span>';
                    }
                    
                    if(req.can_follow) {
                        badgeHtml += ' <span class="requirement-badge badge-follow">Can be followed up</span>';
                    }
                    
                    reqDiv.innerHTML = `
                        <div class="requirement-name">
                            ${req.name}
                            <div>${badgeHtml}</div>
                        </div>
                        <div class="file-upload-area" onclick="document.getElementById('${req.field}').click()">
                            <i class="fas fa-cloud-upload-alt"></i> Click to upload
                        </div>
                        <input type="file" name="${req.field}" id="${req.field}" accept=".pdf,.jpg,.jpeg,.png" style="display: none;" 
                               ${req.required ? 'required' : ''} onchange="updateFileName(this)">
                        <div class="file-name" id="${req.field}_name"></div>
                    `;
                    requirementsList.appendChild(reqDiv);
                });
            } else {
                requirementsSection.style.display = 'none';
            }
        }
        
        function updateFileName(input) {
            const fileNameDiv = document.getElementById(input.id + '_name');
            if(input.files && input.files.length > 0) {
                fileNameDiv.innerHTML = '<i class="fas fa-check-circle" style="color: #28a745;"></i> ' + input.files[0].name;
            } else {
                fileNameDiv.innerHTML = '';
            }
        }
        
        // Auto-populate school year
        window.onload = function() {
            const today = new Date();
            const year = today.getFullYear();
            const nextYear = year + 1;
            const schoolYearInput = document.getElementById('school_year');
            if(schoolYearInput && !schoolYearInput.value) {
                schoolYearInput.value = year + '-' + nextYear;
            }
        }
    </script>
</body>
</html>