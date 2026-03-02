-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Mar 02, 2026 at 07:16 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `hs_enrollment`
--

-- --------------------------------------------------------

--
-- Table structure for table `attendance`
--

CREATE TABLE `attendance` (
  `id` int(11) NOT NULL,
  `student_id` int(11) DEFAULT NULL,
  `subject_id` int(11) DEFAULT NULL,
  `date` date DEFAULT NULL,
  `status` enum('Present','Absent','Late') DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `attendance`
--

INSERT INTO `attendance` (`id`, `student_id`, `subject_id`, `date`, `status`, `created_at`) VALUES
(3, 2, 3, '2026-02-25', 'Absent', '2026-02-25 21:11:20'),
(4, 2, 11, '2026-02-25', 'Late', '2026-02-25 22:53:44'),
(5, 6, 14, '2026-03-02', 'Late', '2026-03-02 16:36:54'),
(6, 2, 14, '2026-03-02', 'Present', '2026-03-02 16:37:12'),
(7, 11, 14, '2026-03-02', 'Present', '2026-03-02 16:37:12');

-- --------------------------------------------------------

--
-- Table structure for table `chatbot_knowledge`
--

CREATE TABLE `chatbot_knowledge` (
  `id` int(11) NOT NULL,
  `category` varchar(100) NOT NULL,
  `keywords` text NOT NULL,
  `response` text NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `chatbot_knowledge`
--

INSERT INTO `chatbot_knowledge` (`id`, `category`, `keywords`, `response`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'Enrollment', 'enroll,enrollment,register,sign up,admission', 'To enroll in courses, please visit the Enrollment page. You can submit your enrollment request online or visit the registrar\'s office during office hours.', 1, '2026-02-26 17:43:21', '2026-02-26 17:43:21'),
(2, 'Schedule', 'schedule,class schedule,timetable,classes timing', 'You can view your complete class schedule on the Schedule page. For personalized schedule, please log in to your student portal.', 1, '2026-02-26 17:43:21', '2026-02-26 17:43:21'),
(3, 'Attendance', 'attendance,absent,present,leave,absence', 'Your attendance is tracked by your teachers. You can check your attendance status on the Attendance page.', 1, '2026-02-26 17:43:21', '2026-02-26 17:43:21'),
(4, 'Office Hours', 'office hours,teacher hours,faculty hours,meeting time', 'Office hours vary by teacher. Check the specific teacher\'s schedule in the faculty directory.', 1, '2026-02-26 17:43:21', '2026-02-26 17:43:21');

-- --------------------------------------------------------

--
-- Table structure for table `chatbot_logs`
--

CREATE TABLE `chatbot_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `user_type` enum('student','teacher','registrar','admin') DEFAULT NULL,
  `question` text NOT NULL,
  `answer` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `class_schedules`
--

CREATE TABLE `class_schedules` (
  `id` int(11) NOT NULL,
  `section_id` int(11) NOT NULL,
  `subject_id` int(11) NOT NULL,
  `teacher_id` int(11) NOT NULL,
  `day_id` int(11) NOT NULL,
  `time_slot_id` int(11) NOT NULL,
  `room` varchar(50) DEFAULT NULL,
  `school_year` varchar(20) NOT NULL,
  `quarter` int(11) NOT NULL DEFAULT 1,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `class_schedules`
--

INSERT INTO `class_schedules` (`id`, `section_id`, `subject_id`, `teacher_id`, `day_id`, `time_slot_id`, `room`, `school_year`, `quarter`, `status`, `created_at`, `updated_at`) VALUES
(1, 2, 16, 9, 1, 1, 'RM 301', '2026-2027', 1, 'active', '2026-02-26 11:17:28', '2026-02-26 11:17:28'),
(2, 2, 18, 9, 1, 2, 'RM 302', '2026-2027', 1, 'active', '2026-02-26 11:30:21', '2026-02-26 11:30:21');

-- --------------------------------------------------------

--
-- Table structure for table `days_of_week`
--

CREATE TABLE `days_of_week` (
  `id` int(11) NOT NULL,
  `day_name` varchar(20) NOT NULL,
  `day_order` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `days_of_week`
--

INSERT INTO `days_of_week` (`id`, `day_name`, `day_order`) VALUES
(1, 'Monday', 1),
(2, 'Tuesday', 2),
(3, 'Wednesday', 3),
(4, 'Thursday', 4),
(5, 'Friday', 5),
(6, 'Saturday', 6);

-- --------------------------------------------------------

--
-- Table structure for table `enrollments`
--

CREATE TABLE `enrollments` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `grade_id` int(11) NOT NULL,
  `section_id` int(11) DEFAULT NULL,
  `school_year` varchar(20) NOT NULL,
  `status` enum('Pending','Enrolled','Rejected') DEFAULT 'Pending',
  `strand` varchar(50) DEFAULT NULL,
  `form_138` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `enrollments`
--

INSERT INTO `enrollments` (`id`, `student_id`, `grade_id`, `section_id`, `school_year`, `status`, `strand`, `form_138`, `created_at`) VALUES
(1, 3, 6, 1, '2026-2027', 'Enrolled', 'ICT', 'uploads/form138_3_1771866305.png', '2026-02-23 18:02:00'),
(2, 2, 5, 3, '2026-2027', 'Enrolled', 'HE', 'uploads/form138_2_1771871063.png', '2026-02-23 18:24:23'),
(3, 6, 4, 2, '2026-2027', 'Enrolled', '', NULL, '2026-02-25 05:09:30'),
(4, 10, 6, 1, '2026-2027', 'Enrolled', 'ABM', 'uploads/form138_10_1772102614.png', '2026-02-26 10:43:34'),
(5, 11, 5, NULL, '2026-2027', 'Enrolled', 'ABM', 'uploads/form138_11_1772469260.jpg', '2026-03-02 16:34:20');

-- --------------------------------------------------------

--
-- Table structure for table `grades`
--

CREATE TABLE `grades` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `subject_id` int(11) NOT NULL,
  `quarter` int(11) NOT NULL,
  `grade` decimal(5,2) DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `teacher_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `grade_levels`
--

CREATE TABLE `grade_levels` (
  `id` int(11) NOT NULL,
  `grade_name` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `grade_levels`
--

INSERT INTO `grade_levels` (`id`, `grade_name`) VALUES
(1, 'Grade 7'),
(2, 'Grade 8'),
(3, 'Grade 9'),
(4, 'Grade 10'),
(5, 'Grade 11'),
(6, 'Grade 12');

-- --------------------------------------------------------

--
-- Table structure for table `sections`
--

CREATE TABLE `sections` (
  `id` int(11) NOT NULL,
  `section_name` varchar(50) DEFAULT NULL,
  `grade_id` int(11) DEFAULT NULL,
  `adviser_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sections`
--

INSERT INTO `sections` (`id`, `section_name`, `grade_id`, `adviser_id`) VALUES
(1, 'Narra', 6, 8),
(2, 'Nangka', 4, 9),
(3, 'Cino', 5, 9);

-- --------------------------------------------------------

--
-- Table structure for table `strands`
--

CREATE TABLE `strands` (
  `id` int(11) NOT NULL,
  `strand_name` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `subjects`
--

CREATE TABLE `subjects` (
  `id` int(11) NOT NULL,
  `subject_name` varchar(100) DEFAULT NULL,
  `grade_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `subjects`
--

INSERT INTO `subjects` (`id`, `subject_name`, `grade_id`) VALUES
(1, 'Mathematics', 1),
(2, 'Filipino', 1),
(3, 'English', 1),
(4, 'Araling Panlipunan', 1),
(5, 'Edukasyon sa Pagpapakatao', 1),
(6, 'MAPEH', 1),
(7, 'Science', 1),
(8, 'Mathematics', 2),
(9, 'Science', 2),
(10, 'English', 2),
(11, 'Filipino', 2),
(12, 'Araling Panlipunan', 2),
(13, 'MAPEH', 2),
(14, 'Edukasyon sa Pagpapakatao', 2),
(15, 'Technology and Livelihood Education', 2),
(16, 'Mathematics', 4),
(17, 'Science', 4),
(18, 'Filipino', 4);

-- --------------------------------------------------------

--
-- Table structure for table `teacher_attendance`
--

CREATE TABLE `teacher_attendance` (
  `id` int(11) NOT NULL,
  `teacher_id` int(11) NOT NULL,
  `date` date NOT NULL,
  `time_in` time DEFAULT NULL,
  `time_out` time DEFAULT NULL,
  `status` enum('Present','Absent','Late') NOT NULL,
  `remarks` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `teacher_attendance`
--

INSERT INTO `teacher_attendance` (`id`, `teacher_id`, `date`, `time_in`, `time_out`, `status`, `remarks`, `created_at`) VALUES
(1, 9, '2026-03-02', '08:08:00', '17:00:00', 'Present', '', '2026-03-02 17:52:06');

-- --------------------------------------------------------

--
-- Table structure for table `time_slots`
--

CREATE TABLE `time_slots` (
  `id` int(11) NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `slot_name` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `time_slots`
--

INSERT INTO `time_slots` (`id`, `start_time`, `end_time`, `slot_name`) VALUES
(1, '07:30:00', '08:30:00', '1st Period'),
(2, '08:30:00', '09:30:00', '2nd Period'),
(3, '09:30:00', '10:00:00', 'Morning Break'),
(4, '10:00:00', '11:00:00', '3rd Period'),
(5, '11:00:00', '12:00:00', '4th Period'),
(6, '12:00:00', '13:00:00', 'Lunch Break'),
(7, '13:00:00', '14:00:00', '5th Period'),
(8, '14:00:00', '15:00:00', '6th Period'),
(9, '15:00:00', '15:30:00', 'Afternoon Break'),
(10, '15:30:00', '16:30:00', '7th Period'),
(11, '16:30:00', '17:30:00', '8th Period');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `id_number` varchar(50) DEFAULT NULL,
  `fullname` varchar(100) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `role` enum('Admin','Registrar','Teacher','Student') DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `id_number`, `fullname`, `email`, `password`, `role`, `created_at`) VALUES
(1, NULL, 'John Micole S. Mangila', 'amawko@gmail.com', '$2y$10$5aFgUh3Hmp1IUbquad28oOYC55ZKir9Dk3a0ymX2ZKPmq2dMCe9iq', 'Student', '2026-02-23 16:41:27'),
(2, NULL, 'Mikhail Ryan Muralla', 'kail@gmail.com', '$2y$10$NRl4dv4dZw.ENqvL4LRIM.EYJz4NCrFr5/zyfUN/iwTEm4k9aDO0K', 'Student', '2026-02-23 16:43:59'),
(3, NULL, 'Jesriel', 'alegado@gmail.com', '$2y$10$NRl4dv4dZw.ENqvL4LRIM.EYJz4NCrFr5/zyfUN/iwTEm4k9aDO0K', 'Student', '2026-02-23 16:44:50'),
(4, NULL, 'John Micole Mangila', 'micole@gmail.com', '123456789', 'Registrar', '2026-02-23 17:11:15'),
(5, NULL, 'Registrar', 'registrar@gmail.com', '$2y$10$yZG8cjP.KMeYHlU/CghCv.nROjmtimWDqrYZzWhB2UkB.PVRZ2PrW', 'Registrar', '2026-02-23 18:15:15'),
(6, NULL, 'Clyde Undang', 'undang@gmail.com', '$2y$10$ZN2zham67XphEjnMq9OKyOMuGv2Ssd9w.ucu6kGPhFK2Kt8hWAgFG', 'Student', '2026-02-25 05:08:16'),
(7, NULL, 'AdminKo', 'admin@gmail.com', '$2y$10$ZN2zham67XphEjnMq9OKyOMuGv2Ssd9w.ucu6kGPhFK2Kt8hWAgFG', 'Admin', '2026-02-25 17:31:12'),
(8, NULL, 'Twengcle Deguma', 'deguma@gmail.com', '$2y$10$QNz6RxhiCB0d/BgNwi7uhOEbxZNBgo3VwLwmiZuDp0r3zGcjagqlq', 'Teacher', '2026-02-25 17:43:14'),
(9, NULL, 'Ashly Balbon', 'ash@gmail.com', '$2y$10$mlaBuK5GfSISJMKwXnuqO.rjroCewFjw.KXTlJoR0afmdvHhaVt.i', 'Teacher', '2026-02-25 20:05:28'),
(10, NULL, 'John Phil Esconde', 'phil@gmail.com', '$2y$10$oQ/Lj4.yIP79cJVSz2uX3u7r8GALUHXx7TAg3z5Dpda9JwUl7HFnO', 'Student', '2026-02-26 10:41:29'),
(11, NULL, 'Quency Frecel Waskin', 'waskin@gmail.com', '$2y$10$swVj9QegLtRb.g5KA29EJug5oxqKjHgMKhsn6fuKzQ5EuB/7oaVP.', 'Student', '2026-03-02 16:33:29');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `attendance`
--
ALTER TABLE `attendance`
  ADD PRIMARY KEY (`id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `subject_id` (`subject_id`);

--
-- Indexes for table `chatbot_knowledge`
--
ALTER TABLE `chatbot_knowledge`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `chatbot_logs`
--
ALTER TABLE `chatbot_logs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `class_schedules`
--
ALTER TABLE `class_schedules`
  ADD PRIMARY KEY (`id`),
  ADD KEY `section_id` (`section_id`),
  ADD KEY `subject_id` (`subject_id`),
  ADD KEY `teacher_id` (`teacher_id`),
  ADD KEY `day_id` (`day_id`),
  ADD KEY `time_slot_id` (`time_slot_id`);

--
-- Indexes for table `days_of_week`
--
ALTER TABLE `days_of_week`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `enrollments`
--
ALTER TABLE `enrollments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `grade_id` (`grade_id`),
  ADD KEY `section_id` (`section_id`);

--
-- Indexes for table `grades`
--
ALTER TABLE `grades`
  ADD PRIMARY KEY (`id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `subject_id` (`subject_id`),
  ADD KEY `teacher_id` (`teacher_id`);

--
-- Indexes for table `grade_levels`
--
ALTER TABLE `grade_levels`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `sections`
--
ALTER TABLE `sections`
  ADD PRIMARY KEY (`id`),
  ADD KEY `grade_id` (`grade_id`),
  ADD KEY `adviser_id` (`adviser_id`);

--
-- Indexes for table `strands`
--
ALTER TABLE `strands`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `subjects`
--
ALTER TABLE `subjects`
  ADD PRIMARY KEY (`id`),
  ADD KEY `grade_id` (`grade_id`);

--
-- Indexes for table `teacher_attendance`
--
ALTER TABLE `teacher_attendance`
  ADD PRIMARY KEY (`id`),
  ADD KEY `teacher_id` (`teacher_id`);

--
-- Indexes for table `time_slots`
--
ALTER TABLE `time_slots`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `id_number` (`id_number`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `attendance`
--
ALTER TABLE `attendance`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `chatbot_knowledge`
--
ALTER TABLE `chatbot_knowledge`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `chatbot_logs`
--
ALTER TABLE `chatbot_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `class_schedules`
--
ALTER TABLE `class_schedules`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `days_of_week`
--
ALTER TABLE `days_of_week`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `enrollments`
--
ALTER TABLE `enrollments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `grades`
--
ALTER TABLE `grades`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `grade_levels`
--
ALTER TABLE `grade_levels`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `sections`
--
ALTER TABLE `sections`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `strands`
--
ALTER TABLE `strands`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `subjects`
--
ALTER TABLE `subjects`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `teacher_attendance`
--
ALTER TABLE `teacher_attendance`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `time_slots`
--
ALTER TABLE `time_slots`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `attendance`
--
ALTER TABLE `attendance`
  ADD CONSTRAINT `attendance_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `attendance_ibfk_2` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`);

--
-- Constraints for table `class_schedules`
--
ALTER TABLE `class_schedules`
  ADD CONSTRAINT `class_schedules_ibfk_1` FOREIGN KEY (`section_id`) REFERENCES `sections` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `class_schedules_ibfk_2` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `class_schedules_ibfk_3` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `class_schedules_ibfk_4` FOREIGN KEY (`day_id`) REFERENCES `days_of_week` (`id`),
  ADD CONSTRAINT `class_schedules_ibfk_5` FOREIGN KEY (`time_slot_id`) REFERENCES `time_slots` (`id`);

--
-- Constraints for table `enrollments`
--
ALTER TABLE `enrollments`
  ADD CONSTRAINT `enrollments_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `enrollments_ibfk_2` FOREIGN KEY (`grade_id`) REFERENCES `grade_levels` (`id`),
  ADD CONSTRAINT `enrollments_ibfk_3` FOREIGN KEY (`section_id`) REFERENCES `sections` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `grades`
--
ALTER TABLE `grades`
  ADD CONSTRAINT `grades_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `grades_ibfk_2` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `grades_ibfk_3` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `sections`
--
ALTER TABLE `sections`
  ADD CONSTRAINT `sections_ibfk_1` FOREIGN KEY (`grade_id`) REFERENCES `grade_levels` (`id`),
  ADD CONSTRAINT `sections_ibfk_2` FOREIGN KEY (`adviser_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `subjects`
--
ALTER TABLE `subjects`
  ADD CONSTRAINT `subjects_ibfk_1` FOREIGN KEY (`grade_id`) REFERENCES `grade_levels` (`id`);

--
-- Constraints for table `teacher_attendance`
--
ALTER TABLE `teacher_attendance`
  ADD CONSTRAINT `teacher_attendance_ibfk_1` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
