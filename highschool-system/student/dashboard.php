<?php
session_start();
include("../config/database.php");
if(!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'Student'){
    header("Location: ../auth/login.php");
    exit();
}

// Get student information
$student_id = $_SESSION['user']['id'];
$student_name = $_SESSION['user']['fullname'];

// Get enrollment status - fixed query without created_at
$enrollment = $conn->query("SELECT e.*, g.grade_name 
                           FROM enrollments e 
                           LEFT JOIN grade_levels g ON e.grade_id = g.id 
                           WHERE e.student_id = '$student_id' 
                           ORDER BY e.id DESC LIMIT 1")->fetch_assoc();

// Get recent activities - simplified without created_at
$recent_activities = $conn->query("
    SELECT 'enrollment' as type, status, id as reference_id, school_year as reference 
    FROM enrollments 
    WHERE student_id = '$student_id' 
    ORDER BY id DESC 
    LIMIT 5
");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - Placido L. Señor Senior High School</title>
    <link rel="stylesheet" href="../assets/css/dashboardstudent.css">
</head>
<body>
    <!-- Header -->
    <div class="header">
        <div class="logo-area">
            <div class="school-logo">PLS</div>
            <div class="school-name">
                <h2>Placido L. Señor Senior High School</h2>
                <p>Excellence • Service • Virtue</p>
            </div>
        </div>
        <div class="user-info">
            <span>Welcome, <strong><?php echo $student_name; ?></strong></span>
            <a href="../auth/logout.php" class="logout-btn">Logout</a>
        </div>
    </div>

    <!-- Main Container -->
    <div class="container">
        <!-- Welcome Card -->
        <div class="welcome-card">
            <h1>Welcome back, <?php echo $student_name; ?>! 👋</h1>
            <p>Manage your enrollment, view schedule, and track your academic progress from your dashboard.</p>
            <?php if($enrollment): ?>
                <p style="margin-top: 10px;"><strong>Current Enrollment:</strong> <?php echo $enrollment['grade_name']; ?> (Status: <span style="color: 
                    <?php 
                        if($enrollment['status'] == 'Pending') echo '#f59e0b';
                        else if($enrollment['status'] == 'Enrolled') echo '#10b981';
                        else echo '#ef4444';
                    ?>
                "><?php echo $enrollment['status']; ?></span>)</p>
                <p><strong>School Year:</strong> <?php echo $enrollment['school_year']; ?></p>
            <?php else: ?>
                <p style="margin-top: 10px; color: #f59e0b;">⚠️ You are not yet enrolled. Please proceed to enrollment.</p>
            <?php endif; ?>
        </div>

        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card" onclick="window.location.href='enrollment.php'">
                <div class="stat-icon">📚</div>
                <h3>Enrollment Status</h3>
                <div class="stat-number"><?php echo $enrollment ? $enrollment['grade_name'] : 'Not Enrolled'; ?></div>
                <div class="stat-label"><?php echo $enrollment ? $enrollment['status'] : 'Pending'; ?></div>
            </div>
            
            <div class="stat-card" onclick="window.location.href='subjects.php'">
                <div class="stat-icon">📊</div>
                <h3>Subjects</h3>
                <div class="stat-number">8</div>
                <div class="stat-label">Current Subjects</div>
            </div>
            
            <div class="stat-card" onclick="window.location.href='attendance.php'">
                <div class="stat-icon">📅</div>
                <h3>Attendance</h3>
                <div class="stat-number">95%</div>
                <div class="stat-label">This Month</div>
            </div>
            
            <div class="stat-card" onclick="window.location.href='grades.php'">
                <div class="stat-icon">⭐</div>
                <h3>Average Grade</h3>
                <div class="stat-number">88%</div>
                <div class="stat-label">1st Semester</div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="section-title">
            <h2>Quick Actions</h2>
        </div>

        <div class="actions-grid">
            <div class="action-card">
                <div class="action-icon">📝</div>
                <h3>Enrollment</h3>
                <p><?php echo $enrollment ? 'Update your enrollment' : 'Enroll now for the current school year'; ?></p>
                <a href="enrollment.php" class="action-btn">
                    <?php echo $enrollment ? 'Update' : 'Enroll Now'; ?>
                </a>
            </div>
            
            <div class="action-card">
                <div class="action-icon">📅</div>
                <h3>View Schedule</h3>
                <p>Check your class schedule and subjects</p>
                <a href="schedule.php" class="action-btn">View Schedule</a>
            </div>
            
            <div class="action-card">
                <div class="action-icon">📊</div>
                <h3>Grades</h3>
                <p>View your grades and academic performance</p>
                <a href="grades.php" class="action-btn">View Grades</a>
            </div>
            
            <div class="action-card">
                <div class="action-icon">📄</div>
                <h3>Documents</h3>
                <p>Request and track school documents</p>
                <a href="documents.php" class="action-btn">Request</a>
            </div>
        </div>

        <!-- Recent Activity -->
        <div class="section-title">
            <h2>Recent Enrollment Activity</h2>
        </div>

        <div class="activity-card">
            <div class="activity-header">
                <h3>Your Enrollment History</h3>
            </div>
            <div class="activity-list">
                <?php if($recent_activities && $recent_activities->num_rows > 0): ?>
                    <?php while($activity = $recent_activities->fetch_assoc()): ?>
                        <div class="activity-item">
                            <div class="activity-dot 
                                <?php 
                                    if($activity['status'] == 'Pending') echo 'dot-pending';
                                    else if($activity['status'] == 'Enrolled') echo 'dot-approved';
                                    else echo 'dot-completed';
                                ?>">
                            </div>
                            <div class="activity-content">
                                <div class="activity-title">
                                    Enrollment Request - <?php echo $activity['reference']; ?>
                                </div>
                                <div class="activity-time">School Year: <?php echo $activity['reference']; ?></div>
                            </div>
                            <div class="activity-status 
                                <?php 
                                    if($activity['status'] == 'Pending') echo 'status-pending';
                                    else if($activity['status'] == 'Enrolled') echo 'status-approved';
                                ?>">
                                <?php echo $activity['status']; ?>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="activity-item">
                        <div class="activity-content" style="text-align: center; padding: 20px; color: #999;">
                            No enrollment history found. 
                            <a href="enrollment.php" style="color: #0B4F2E; text-decoration: none; display: block; margin-top: 10px;">
                                Click here to enroll now →
                            </a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- School Information -->
        <div style="text-align: center; margin-top: 40px; padding: 20px; background: white; border-radius: 12px; box-shadow: 0 8px 20px rgba(0,0,0,0.05);">
            <p style="color: #666; font-size: 13px;">
                PLACIDO L. SEÑOR SENIOR HIGH SCHOOL<br>
                Langtad, City of Naga, Cebu<br>
                📞 (032) 123-4567 · 📧 info@plsshs.edu.ph
            </p>
        </div>
    </div>

    <script>
        // Make stat cards clickable
        document.querySelectorAll('.stat-card').forEach(card => {
            card.addEventListener('click', function() {
                window.location.href = this.getAttribute('onclick').split("'")[1];
            });
        });
    </script>
</body>
</html>