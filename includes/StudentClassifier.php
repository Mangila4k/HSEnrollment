<?php
// file: includes/StudentClassifier.php
// This file must be created first

require_once __DIR__ . '/../config/database.php';

class StudentClassifier {
    private $conn;
    private $current_school_year = '2026-2027';
    
    public function __construct($db_connection = null) {
        if ($db_connection) {
            $this->conn = $db_connection;
        } else {
            $database = new Database();
            $this->conn = $database->getConnection();
        }
    }
    
    /**
     * Check if student is old (has previous enrollments)
     */
    public function isOldStudent($student_id) {
        try {
            $query = "SELECT COUNT(*) as prev_count
                      FROM enrollments e
                      WHERE e.student_id = :student_id
                      AND e.school_year < (
                          SELECT MAX(school_year)
                          FROM enrollments
                          WHERE student_id = :student_id2
                      )";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':student_id', $student_id, PDO::PARAM_INT);
            $stmt->bindParam(':student_id2', $student_id, PDO::PARAM_INT);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return $result['prev_count'] > 0;
        } catch (Exception $e) {
            error_log("Error in isOldStudent: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get student type with badge HTML
     */
    public function getStudentTypeBadge($student_id) {
        $is_old = $this->isOldStudent($student_id);
        
        if ($is_old) {
            return [
                'type' => 'Old Student',
                'badge' => '<span class="badge" style="background: #28a745; color: white; padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 600;"><i class="fas fa-undo-alt"></i> Old Student</span>',
                'class' => 'old-student',
                'color' => '#28a745'
            ];
        } else {
            return [
                'type' => 'New Student',
                'badge' => '<span class="badge" style="background: #007bff; color: white; padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 600;"><i class="fas fa-star"></i> New Student</span>',
                'class' => 'new-student',
                'color' => '#007bff'
            ];
        }
    }
    
    /**
     * Get enrollment history for a student
     */
    public function getEnrollmentHistory($student_id) {
        try {
            $query = "SELECT 
                        e.school_year,
                        e.status,
                        e.created_at,
                        g.grade_name,
                        s.section_name,
                        e.strand
                      FROM enrollments e
                      LEFT JOIN grade_levels g ON e.grade_id = g.id
                      LEFT JOIN sections s ON e.section_id = s.id
                      WHERE e.student_id = :student_id
                      ORDER BY e.school_year DESC, e.created_at DESC";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':student_id', $student_id, PDO::PARAM_INT);
            $stmt->execute();
            
            $history = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $history[] = $row;
            }
            
            return $history;
        } catch (Exception $e) {
            error_log("Error in getEnrollmentHistory: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get student classification with full details
     */
    public function getStudentClassification($student_id) {
        try {
            // Get student basic info
            $query = "SELECT 
                        u.*,
                        e.id as enrollment_id,
                        e.grade_id,
                        e.section_id,
                        e.status as enrollment_status,
                        e.strand,
                        e.school_year,
                        e.created_at as enrolled_date,
                        g.grade_name,
                        s.section_name
                      FROM users u
                      LEFT JOIN enrollments e ON u.id = e.student_id AND e.school_year = :current_year
                      LEFT JOIN grade_levels g ON e.grade_id = g.id
                      LEFT JOIN sections s ON e.section_id = s.id
                      WHERE u.id = :student_id AND u.role = 'Student'
                      LIMIT 1";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':current_year', $this->current_school_year, PDO::PARAM_STR);
            $stmt->bindParam(':student_id', $student_id, PDO::PARAM_INT);
            $stmt->execute();
            $student = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($student) {
                // Add student type
                $type_info = $this->getStudentTypeBadge($student_id);
                $student['student_type'] = $type_info['type'];
                $student['student_badge'] = $type_info['badge'];
                $student['student_type_color'] = $type_info['color'];
                
                // Add enrollment history
                $student['enrollment_history'] = $this->getEnrollmentHistory($student_id);
                $student['total_enrollments'] = count($student['enrollment_history']);
                
                // Check if first time
                $student['is_first_time'] = $student['total_enrollments'] <= 1;
                $student['is_old'] = !$student['is_first_time'];
            }
            
            return $student;
        } catch (Exception $e) {
            error_log("Error in getStudentClassification: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get statistics for dashboard
     */
    public function getStudentStats() {
        try {
            $stats = [];
            
            // Total students
            $stmt = $this->conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'Student'");
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $stats['total'] = $row['count'];
            
            // Old students (with previous enrollments)
            $query = "SELECT COUNT(DISTINCT u.id) as count
                      FROM users u
                      WHERE u.role = 'Student'
                      AND EXISTS (
                          SELECT 1 FROM enrollments e1 
                          WHERE e1.student_id = u.id
                      )
                      AND EXISTS (
                          SELECT 1 FROM enrollments e2 
                          WHERE e2.student_id = u.id 
                          AND e2.school_year < (
                              SELECT MAX(school_year) 
                              FROM enrollments e3 
                              WHERE e3.student_id = u.id
                          )
                      )";
            $stmt = $this->conn->query($query);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $stats['old_students'] = $row['count'];
            
            // New students (first time)
            $stats['new_students'] = $stats['total'] - $stats['old_students'];
            
            // By grade level for current year
            $query = "SELECT 
                        g.grade_name,
                        COUNT(*) as count
                      FROM enrollments e
                      JOIN grade_levels g ON e.grade_id = g.id
                      WHERE e.school_year = :current_year AND e.status = 'Enrolled'
                      GROUP BY g.id
                      ORDER BY g.id";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':current_year', $this->current_school_year, PDO::PARAM_STR);
            $stmt->execute();
            
            $stats['by_grade'] = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $stats['by_grade'][$row['grade_name']] = $row['count'];
            }
            
            return $stats;
        } catch (Exception $e) {
            error_log("Error in getStudentStats: " . $e->getMessage());
            return [
                'total' => 0,
                'old_students' => 0,
                'new_students' => 0,
                'by_grade' => []
            ];
        }
    }
    
    /**
     * Get all students with classification (for registrar page)
     */
    public function getAllStudentsWithClassification($filters = []) {
        try {
            $query = "SELECT 
                        u.*,
                        e.id as enrollment_id,
                        e.grade_id,
                        e.section_id,
                        e.status as enrollment_status,
                        e.strand,
                        e.school_year,
                        e.created_at as enrolled_date,
                        g.grade_name,
                        s.section_name,
                        (
                            SELECT COUNT(*) 
                            FROM enrollments e_count 
                            WHERE e_count.student_id = u.id
                        ) as total_enrollments,
                        (
                            SELECT GROUP_CONCAT(school_year ORDER BY school_year SEPARATOR ', ')
                            FROM enrollments e_years 
                            WHERE e_years.student_id = u.id
                        ) as enrollment_years
                      FROM users u
                      LEFT JOIN enrollments e ON u.id = e.student_id AND e.school_year = :current_year
                      LEFT JOIN grade_levels g ON e.grade_id = g.id
                      LEFT JOIN sections s ON e.section_id = s.id
                      WHERE u.role = 'Student'";
            
            $params = [':current_year' => $this->current_school_year];
            
            // Apply filters
            if (!empty($filters['grade'])) {
                $query .= " AND e.grade_id = :grade";
                $params[':grade'] = $filters['grade'];
            }
            
            if (!empty($filters['status'])) {
                $query .= " AND e.status = :status";
                $params[':status'] = $filters['status'];
            }
            
            if (!empty($filters['search'])) {
                $query .= " AND (u.fullname LIKE :search OR u.email LIKE :search OR u.id_number LIKE :search)";
                $params[':search'] = "%{$filters['search']}%";
            }
            
            $query .= " ORDER BY u.lastname ASC, u.firstname ASC";
            
            $stmt = $this->conn->prepare($query);
            
            // Bind all parameters
            foreach ($params as $key => $value) {
                $param_type = is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR;
                $stmt->bindValue($key, $value, $param_type);
            }
            
            $stmt->execute();
            
            $students = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                // Add student type
                $type_info = $this->getStudentTypeBadge($row['id']);
                $row['student_type'] = $type_info['type'];
                $row['student_badge'] = $type_info['badge'];
                $row['is_old'] = ($type_info['type'] == 'Old Student');
                
                $students[] = $row;
            }
            
            return $students;
        } catch (Exception $e) {
            error_log("Error in getAllStudentsWithClassification: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get student counts by type for dashboard widgets
     */
    public function getStudentCountsByType() {
        try {
            $stats = $this->getStudentStats();
            
            return [
                'total' => $stats['total'],
                'old' => $stats['old_students'],
                'new' => $stats['new_students'],
                'old_percentage' => $stats['total'] > 0 ? round(($stats['old_students'] / $stats['total']) * 100, 1) : 0,
                'new_percentage' => $stats['total'] > 0 ? round(($stats['new_students'] / $stats['total']) * 100, 1) : 0
            ];
        } catch (Exception $e) {
            error_log("Error in getStudentCountsByType: " . $e->getMessage());
            return [
                'total' => 0,
                'old' => 0,
                'new' => 0,
                'old_percentage' => 0,
                'new_percentage' => 0
            ];
        }
    }

    /**
     * Simple function to just get student type as string
     */
    public function getStudentType($student_id) {
        return $this->isOldStudent($student_id) ? 'Old Student' : 'New Student';
    }
}
?>