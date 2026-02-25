<?php
session_start();
include("../config/database.php");

// Only students can access
if(!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'Student'){
    header("Location: ../auth/login.php");
    exit();
}

$student_id = $_SESSION['user']['id'];

// Fetch existing enrollment
$enroll = $conn->query("SELECT e.*, g.grade_name, e.strand, e.form_138 
                        FROM enrollments e 
                        LEFT JOIN grade_levels g ON e.grade_id=g.id
                        WHERE student_id='$student_id'")->fetch_assoc();

// Fetch grade levels
$grades = $conn->query("SELECT * FROM grade_levels");

// Define strands for Senior High (Grade 11-12)
$senior_strands = ['STEM','ABM','GAS','HUMSS','ICT','HE','Sports','Arts'];

// Handle enrollment submission
if(isset($_POST['enroll'])){
    $grade_id = $_POST['grade_id'];
    $school_year = $_POST['school_year'];
    $strand = isset($_POST['strand']) ? $_POST['strand'] : NULL;

    // Check if student already enrolled
    $check = $conn->query("SELECT * FROM enrollments WHERE student_id='$student_id'");
    if($check->num_rows > 0){
        $error = "You have already submitted an enrollment.";
    } else {
        // Handle file upload if Grade 11-12
        $grade_name = $conn->query("SELECT grade_name FROM grade_levels WHERE id='$grade_id'")->fetch_assoc()['grade_name'];
        $form_138 = NULL;

        if(in_array($grade_name, ['Grade 11','Grade 12'])){
            if(isset($_FILES['form_138']) && $_FILES['form_138']['error'] == 0){
                $allowed = ['pdf','jpg','jpeg','png'];
                $ext = strtolower(pathinfo($_FILES['form_138']['name'], PATHINFO_EXTENSION));
                if(!in_array($ext,$allowed)){
                    $error = "Form 138 must be PDF or image file.";
                } else {
                    $filename = "uploads/form138_".$student_id."_".time().".".$ext;
                    if(!is_dir("../uploads")) mkdir("../uploads");
                    move_uploaded_file($_FILES['form_138']['tmp_name'], "../".$filename);
                    $form_138 = $filename;
                }
            } else {
                $error = "Form 138 is required for Grade 11-12.";
            }
        }

        if(!isset($error)){
            $stmt = $conn->prepare("INSERT INTO enrollments (student_id, grade_id, school_year, status, strand, form_138) 
                                    VALUES (?, ?, ?, 'Pending', ?, ?)");
            $stmt->bind_param("iisss", $student_id, $grade_id, $school_year, $strand, $form_138);
            $stmt->execute();
            $success = "Enrollment submitted successfully! Wait for approval.";
        }
    }
}
?>

<!-- Include the styled CSS -->
<link rel="stylesheet" href="../assets/css/enrollment.css">

<div class="form-container">
    <h1>Admissions Form</h1>
    <p class="subtitle">Enter your admission information below</p>

    <?php
    if(isset($error)) echo "<p class='error'>$error</p>";
    if(isset($success)) echo "<p class='success'>$success</p>";
    ?>

    <?php if($enroll): ?>
        <p>You have already submitted enrollment in <b><?php echo $enroll['grade_name']; ?></b> 
        <?php if($enroll['strand']) echo "(Strand: ".$enroll['strand'].")"; ?> 
        (Status: <?php echo $enroll['status']; ?>)</p>
        <a href="dashboard.php">Back to Dashboard</a>
    <?php else: ?>
        <form method="POST" enctype="multipart/form-data">
            <!-- NAME -->
            <label>Name <span class="required">*</span></label>
            <div class="row">
                <input type="text" name="first_name" placeholder="First Name" required>
                <input type="text" name="middle_name" placeholder="Middle Initial">
                <input type="text" name="last_name" placeholder="Last Name" required>
            </div>

            <!-- BIRTHDATE -->
            <label>Birth Date <span class="required">*</span></label>
            <div class="row">
                <input type="date" name="birthdate" required>
            </div>

            <!-- GENDER -->
            <label>Gender <span class="required">*</span></label>
            <div class="radio-group">
                <label><input type="radio" name="gender" value="Male" required> Male</label>
                <label><input type="radio" name="gender" value="Female"> Female</label>
                <label><input type="radio" name="gender" value="Decline"> Decline to Answer</label>
            </div>

            <!-- GRADE LEVEL -->
            <label>Select Grade Level <span class="required">*</span></label>
            <select name="grade_id" id="grade" onchange="toggleStrand()" required>
                <option value="">-- Select Grade --</option>
                <?php
                $grades = $conn->query("SELECT * FROM grade_levels");
                while($g = $grades->fetch_assoc()){
                    echo "<option value='{$g['id']}'>{$g['grade_name']}</option>";
                }
                ?>
            </select>

            <!-- STRAND -->
            <div id="strandDiv" style="display:none;">
                <label>Select Strand (Grade 11-12)</label>
                <select name="strand">
                    <option value="">-- Select Strand --</option>
                    <?php foreach($senior_strands as $s): ?>
                        <option value="<?php echo $s; ?>"><?php echo $s; ?></option>
                    <?php endforeach; ?>
                </select>

                <label>Upload Form 138 (Required for G11-12)</label>
                <input type="file" name="form_138" accept=".pdf,.jpg,.jpeg,.png">
            </div>

            <!-- SCHOOL YEAR -->
            <label>School Year <span class="required">*</span></label>
            <input type="text" name="school_year" placeholder="e.g. 2026-2027" required>

            <button type="submit" name="enroll" class="submit-btn">Submit Form</button>
        </form>
    <?php endif; ?>
</div>

<script>
function toggleStrand(){
    var gradeSelect = document.getElementById('grade');
    var strandDiv = document.getElementById('strandDiv');
    var gradeName = gradeSelect.options[gradeSelect.selectedIndex].text;

    if(gradeName == 'Grade 11' || gradeName == 'Grade 12'){
        strandDiv.style.display = 'block';
    } else {
        strandDiv.style.display = 'none';
    }
}
</script>