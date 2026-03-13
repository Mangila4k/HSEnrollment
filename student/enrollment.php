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
$student_query = $conn->query("SELECT * FROM users WHERE id = '$student_id'");
$student = $student_query ? $student_query->fetch(PDO::FETCH_ASSOC) : null;

// Fetch existing enrollment
$enroll_query = $conn->query("SELECT e.*, g.grade_name, e.strand, e.form_138 
                              FROM enrollments e 
                              LEFT JOIN grade_levels g ON e.grade_id = g.id
                              WHERE student_id = '$student_id'");
$enroll = $enroll_query ? $enroll_query->fetch(PDO::FETCH_ASSOC) : null;

// Fetch grade levels
$grades = $conn->query("SELECT * FROM grade_levels ORDER BY id");

// Define strands for Senior High (Grade 11-12)
$senior_strands = ['STEM','ABM','GAS','HUMSS','ICT','HE','Sports','Arts'];

// Handle enrollment submission
if(isset($_POST['enroll'])){
    $grade_id = $_POST['grade_id'];
    $school_year = $_POST['school_year'];
    $strand = isset($_POST['strand']) && !empty($_POST['strand']) ? $_POST['strand'] : null;

    // Check if student already enrolled
    $check = $conn->query("SELECT * FROM enrollments WHERE student_id='$student_id'");
    if($check && $check->rowCount() > 0){
        $error = "You have already submitted an enrollment.";
    } else {
        // Handle file upload if Grade 11-12
        $grade_result = $conn->query("SELECT grade_name FROM grade_levels WHERE id='$grade_id'");
        $grade_row = $grade_result ? $grade_result->fetch(PDO::FETCH_ASSOC) : null;
        $grade_name = $grade_row ? $grade_row['grade_name'] : '';
        
        $form_138 = null;

        if(in_array($grade_name, ['Grade 11','Grade 12'])){
            if(isset($_FILES['form_138']) && $_FILES['form_138']['error'] == 0){
                $allowed = ['pdf','jpg','jpeg','png'];
                $ext = strtolower(pathinfo($_FILES['form_138']['name'], PATHINFO_EXTENSION));
                if(!in_array($ext, $allowed)){
                    $error = "Form 138 must be PDF or image file.";
                } else {
                    $filename = "uploads/form138_".$student_id."_".time().".".$ext;
                    if(!is_dir("../uploads")) mkdir("../uploads", 0777, true);
                    
                    if(move_uploaded_file($_FILES['form_138']['tmp_name'], "../".$filename)){
                        $form_138 = $filename;
                    } else {
                        $error = "Failed to upload file.";
                    }
                }
            } else {
                $error = "Form 138 is required for Grade 11-12.";
            }
        }

        if(!isset($error)){
            $stmt = $conn->prepare("INSERT INTO enrollments (student_id, grade_id, school_year, status, strand, form_138) 
                                    VALUES (:student_id, :grade_id, :school_year, 'Pending', :strand, :form_138)");
            
            $stmt->bindParam(':student_id', $student_id, PDO::PARAM_INT);
            $stmt->bindParam(':grade_id', $grade_id, PDO::PARAM_INT);
            $stmt->bindParam(':school_year', $school_year, PDO::PARAM_STR);
            $stmt->bindParam(':strand', $strand, PDO::PARAM_STR);
            $stmt->bindParam(':form_138', $form_138, PDO::PARAM_STR);
            
            if($stmt->execute()){
                $success = "Enrollment submitted successfully! Wait for approval.";
                // Refresh enrollment data
                $enroll_query = $conn->query("SELECT e.*, g.grade_name, e.strand, e.form_138 
                                            FROM enrollments e 
                                            LEFT JOIN grade_levels g ON e.grade_id = g.id
                                            WHERE student_id = '$student_id'");
                $enroll = $enroll_query ? $enroll_query->fetch(PDO::FETCH_ASSOC) : null;
            } else {
                $errorInfo = $stmt->errorInfo();
                $error = "Error submitting enrollment: " . ($errorInfo[2] ?? 'Unknown error');
            }
            $stmt = null; // Close statement in PDO
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
            max-width: 800px;
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

        .row {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            margin-bottom: 0;
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

        .input-wrapper input[readonly] {
            background-color: #e9ecef;
            cursor: not-allowed;
            border-color: #ddd;
        }

        .strand-section {
            background: #f8f9fa;
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

        .file-input {
            border: 2px dashed #0B4F2E;
            padding: 20px;
            text-align: center;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.3s;
            background: #f8f9fa;
        }

        .file-input:hover {
            background: #e8f4f8;
        }

        .file-input i {
            font-size: 30px;
            color: #0B4F2E;
            margin-bottom: 10px;
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
            
            .row {
                grid-template-columns: 1fr;
            }
            
            .radio-group {
                flex-direction: column;
                gap: 10px;
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

            <!-- Display Student Information (Auto-fetched) -->
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
                <form method="POST" enctype="multipart/form-data">
                    <!-- Hidden fields with student data -->
                    <input type="hidden" name="first_name" value="<?php echo isset($student['firstname']) ? htmlspecialchars($student['firstname']) : ''; ?>">
                    <input type="hidden" name="middle_name" value="<?php echo isset($student['middlename']) ? htmlspecialchars($student['middlename']) : ''; ?>">
                    <input type="hidden" name="last_name" value="<?php echo isset($student['lastname']) ? htmlspecialchars($student['lastname']) : ''; ?>">
                    <input type="hidden" name="birthdate" value="<?php echo isset($student['birthdate']) ? htmlspecialchars($student['birthdate']) : ''; ?>">
                    <input type="hidden" name="gender" value="<?php echo isset($student['gender']) ? htmlspecialchars($student['gender']) : ''; ?>">

                    <!-- GRADE LEVEL -->
                    <div class="form-group">
                        <label for="grade">Select Grade Level <span class="required">*</span></label>
                        <div class="input-wrapper">
                            <select name="grade_id" id="grade" onchange="toggleStrand()" required>
                                <option value="">-- Select Grade Level --</option>
                                <?php
                                if($grades){
                                    while($g = $grades->fetch(PDO::FETCH_ASSOC)){
                                        echo "<option value='{$g['id']}'>{$g['grade_name']}</option>";
                                    }
                                }
                                ?>
                            </select>
                            <i class="fas fa-chevron-down" style="position: absolute; right: 16px; top: 50%; transform: translateY(-50%); color: #666;"></i>
                        </div>
                    </div>

                    <!-- STRAND SECTION (Initially Hidden) -->
                    <div id="strandDiv" class="strand-section" style="display:none;">
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

                        <div class="form-group">
                            <label>Upload Form 138 (Report Card) <span class="required">*</span></label>
                            <div class="file-input" onclick="document.getElementById('form_138').click()">
                                <i class="fas fa-cloud-upload-alt"></i>
                                <p>Click to upload or drag and drop</p>
                                <p style="font-size: 12px; color: #666;">PDF, JPG, JPEG, or PNG (Max 10MB)</p>
                            </div>
                            <input type="file" name="form_138" id="form_138" accept=".pdf,.jpg,.jpeg,.png" style="display: none;" onchange="updateFileLabel(this)">
                            <div id="file-name" style="font-size: 13px; color: #0B4F2E; margin-top: 10px; text-align: center;"></div>
                        </div>
                    </div>

                    <!-- SCHOOL YEAR -->
                    <div class="form-group">
                        <label for="school_year">School Year <span class="required">*</span></label>
                        <div class="input-wrapper">
                            <input type="text" name="school_year" id="school_year" placeholder="e.g. 2026-2027" required>
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
        function toggleStrand(){
            var gradeSelect = document.getElementById('grade');
            var strandDiv = document.getElementById('strandDiv');
            
            if(gradeSelect && gradeSelect.selectedIndex >= 0) {
                var selectedOption = gradeSelect.options[gradeSelect.selectedIndex];
                var gradeName = selectedOption ? selectedOption.text : '';
                
                if(gradeName == 'Grade 11' || gradeName == 'Grade 12'){
                    strandDiv.style.display = 'block';
                    // Make fields required
                    document.getElementById('strand').setAttribute('required', 'required');
                } else {
                    strandDiv.style.display = 'none';
                    // Remove required attribute
                    document.getElementById('strand').removeAttribute('required');
                }
            }
        }

        function updateFileLabel(input) {
            var fileName = document.getElementById('file-name');
            if(input.files && input.files.length > 0) {
                fileName.innerHTML = '<i class="fas fa-check-circle" style="color: #28a745;"></i> Selected: ' + input.files[0].name;
            } else {
                fileName.innerHTML = '';
            }
        }

        // Auto-populate school year with current year
        window.onload = function() {
            var today = new Date();
            var year = today.getFullYear();
            var nextYear = year + 1;
            var schoolYearInput = document.getElementById('school_year');
            if(schoolYearInput && !schoolYearInput.value) {
                schoolYearInput.value = year + '-' + nextYear;
            }
        }
    </script>
</body>
</html>