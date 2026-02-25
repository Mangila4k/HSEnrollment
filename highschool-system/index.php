<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EnrollSys - High School Enrollment System</title>
    <link rel="stylesheet" href="assets/css/index.css">
</head>
<body>
    <!-- Navigation Bar -->
    <nav class="navbar">
        <div class="nav-container">
            <div class="logo">
                <span class="logo-text">EnrollSys</span>
            </div>
            <ul class="nav-menu">
                <li><a href="#home" class="active">Home</a></li>
                <li><a href="#features">Features</a></li>
                <li><a href="#about">About</a></li>
                <li><a href="#contact">Contact</a></li>
            </ul>
            <div class="nav-buttons">
                <a href="auth/register.php" class="btn-register">Register</a>
                <a href="auth/login.php" class="btn-login-nav">Login</a>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero" id="home">
        <div class="hero-container">
            <div class="hero-content">
                <h1 class="hero-title">Welcome to <span class="highlight">EnrollSys</span></h1>
                <p class="hero-subtitle">Your seamless gateway to academic enrollment and management</p>
                <div class="hero-buttons">
                    <a href="auth/register.php" class="btn-enroll-now">Enroll Now</a>
                    <a href="auth/register.php" class="btn-hero-register">Register</a>
                    <a href="auth/login.php" class="btn-hero-login">Login</a>
                </div>
            </div>
            <div class="hero-image">
                <div class="illustration">
                    <div class="circle"></div>
                    <div class="circle"></div>
                    <div class="circle"></div>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section class="features" id="features">
        <div class="container">
            <h2 class="section-title">Why Choose <span class="highlight">EnrollSys</span></h2>
            <div class="features-grid">
                <div class="feature-card">
                    <div class="feature-icon">📚</div>
                    <h3>Easy Enrollment</h3>
                    <p>Streamlined online enrollment process for students and parents</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">📊</div>
                    <h3>Real-time Tracking</h3>
                    <p>Monitor enrollment status and requirements in real-time</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">🔒</div>
                    <h3>Secure System</h3>
                    <p>Your data is protected with industry-standard security</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">📱</div>
                    <h3>Mobile Friendly</h3>
                    <p>Access the system anytime, anywhere on any device</p>
                </div>
            </div>
        </div>
    </section>

    <!-- About Section -->
    <section class="about" id="about">
        <div class="container">
            <div class="about-content">
                <div class="about-text">
                    <h2 class="section-title">About <span class="highlight">EnrollSys</span></h2>
                    <p>EnrollSys is a modern enrollment management system designed specifically for Placido L. Señor Senior High School. We streamline the admission process, making it easier for students, parents, and administrators to manage enrollments efficiently.</p>
                    <ul class="about-list">
                        <li>✓ Paperless enrollment process</li>
                        <li>✓ Automated status notifications</li>
                        <li>✓ Integrated document tracking</li>
                        <li>✓ 24/7 accessibility</li>
                    </ul>
                </div>
                <div class="about-stats">
                    <div class="stat-item">
                        <div class="stat-number">500+</div>
                        <div class="stat-label">Students Enrolled</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number">50+</div>
                        <div class="stat-label">Staff Members</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number">98%</div>
                        <div class="stat-label">Satisfaction Rate</div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Contact Section -->
    <section class="contact" id="contact">
        <div class="container">
            <h2 class="section-title">Get In <span class="highlight">Touch</span></h2>
            <div class="contact-content">
                <div class="contact-info">
                    <div class="contact-item">
                        <div class="contact-icon">📍</div>
                        <div>
                            <h4>Address</h4>
                            <p>Langtad, City of Naga, Cebu</p>
                        </div>
                    </div>
                    <div class="contact-item">
                        <div class="contact-icon">📞</div>
                        <div>
                            <h4>Phone</h4>
                            <p>(032) 123-4567</p>
                        </div>
                    </div>
                    <div class="contact-item">
                        <div class="contact-icon">✉️</div>
                        <div>
                            <h4>Email</h4>
                            <p>info@enrollsys.edu.ph</p>
                        </div>
                    </div>
                </div>
                <form class="contact-form">
                    <input type="text" placeholder="Your Name" required>
                    <input type="email" placeholder="Your Email" required>
                    <textarea placeholder="Your Message" rows="4" required></textarea>
                    <button type="submit" class="btn-submit">Send Message</button>
                </form>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-logo">
                    <span class="logo-text">EnrollSys</span>
                    <p>Your seamless gateway to academic enrollment</p>
                </div>
                <div class="footer-links">
                    <h4>Quick Links</h4>
                    <ul>
                        <li><a href="#home">Home</a></li>
                        <li><a href="#features">Features</a></li>
                        <li><a href="#about">About</a></li>
                        <li><a href="#contact">Contact</a></li>
                    </ul>
                </div>
                <div class="footer-links">
                    <h4>Support</h4>
                    <ul>
                        <li><a href="#">FAQ</a></li>
                        <li><a href="#">Privacy Policy</a></li>
                        <li><a href="#">Terms of Service</a></li>
                    </ul>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; 2024 EnrollSys. All rights reserved. | Placido L. Señor Senior High School</p>
            </div>
        </div>
    </footer>
</body>
</html>