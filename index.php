<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* Redirect if already logged in */
if (!empty($_SESSION['user_id'])) {
    header("Location: /safetrac/dashboard.php");
    exit;
}

$pageTitle = "SafeTrack | Construction Safety Inspection System";
include 'includes/header.php';
?>

<!-- NAVBAR -->
<nav class="landing-navbar navbar navbar-expand-lg navbar-dark fixed-top">
    <div class="container">
        <a class="navbar-brand fw-bold" href="/safetrac/">
            <i class="bi bi-shield-check me-2"></i>SafeTrack
        </a>

        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#landingNav">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="landingNav">
            <ul class="navbar-nav ms-auto me-3">
                <li class="nav-item"><a class="nav-link" href="#about">About</a></li>
                <li class="nav-item"><a class="nav-link" href="#workflow">Workflow</a></li>
                <li class="nav-item"><a class="nav-link" href="#features">Features</a></li>
                <li class="nav-item"><a class="nav-link" href="#roles">Roles</a></li>
                <li class="nav-item"><a class="nav-link" href="#reports">Reports</a></li>
            </ul>

            <div class="d-flex gap-2">
                <a href="/safetrac/login.php" class="btn btn-outline-light px-4">Login</a>
                <a href="/safetrac/register.php" class="btn btn-warning px-4 fw-semibold">Register</a>
            </div>
        </div>
    </div>
</nav>

<!-- HERO -->
<section class="landing-hero">
    <div class="container">
        <div class="row align-items-center g-5">

            <div class="col-lg-7">
                <span class="landing-badge">
                    <i class="bi bi-cone-striped me-2"></i>
                    Professional Construction Safety Platform
                </span>

                <h1 class="landing-title">
                    Digital Construction Safety Inspection & Issue Tracking System
                </h1>

                <p class="landing-text">
                    SafeTrack is a multi-company construction safety platform.
                    Company admins can manage projects and teams, safety officers perform inspections,
                    failed checklist items automatically create issues, and supervisors update corrective actions.
                    Recheck workflows ensure proper closure or reopening of issues.
                </p>

                <div class="d-flex flex-wrap gap-3 mt-4">
                    <a href="/safetrac/register.php" class="btn btn-main btn-lg">Get Started</a>
                    <a href="/safetrac/login.php" class="btn btn-outline-light btn-lg">Login</a>
                </div>

                <div class="landing-risk-row mt-4">
                    <span class="risk-pill risk-safe">90%+ Safe</span>
                    <span class="risk-pill risk-moderate">75%–89% Moderate</span>
                    <span class="risk-pill risk-danger">Below 75% Risk</span>
                </div>
            </div>

            <div class="col-lg-5">
                <div class="hero-glass-card">

                    <div class="hero-mini-card">
                        <h5><i class="bi bi-clipboard-check me-2"></i>Inspection Checklist</h5>
                        <p>Pass / Fail / NA based inspection with notes, severity, due dates and photo evidence.</p>
                    </div>

                    <div class="hero-mini-card">
                        <h5><i class="bi bi-exclamation-triangle me-2"></i>Issue Tracking</h5>
                        <p>Failed checklist items automatically create issues with supervisor assignment.</p>
                    </div>

                    <div class="hero-mini-card mb-0">
                        <h5><i class="bi bi-bar-chart-line me-2"></i>Analytics & Reports</h5>
                        <p>Dashboard metrics, safety score, charts, notifications and professional reports.</p>
                    </div>

                </div>
            </div>

        </div>
    </div>
</section>

<!-- ABOUT -->
<section class="landing-section bg-white" id="about">
    <div class="container text-center">
        <h2 class="section-title-main">About SafeTrack</h2>
        <p class="section-sub-main">
            SafeTrack replaces manual safety inspection processes with a digital system.
            It provides inspection tracking, issue management, evidence collection,
            recheck workflows, analytics dashboards and real-time notifications.
        </p>
    </div>
</section>

<!-- WORKFLOW -->
<section class="landing-section" id="workflow">
    <div class="container text-center">
        <h2 class="section-title-main">System Workflow</h2>
        <p class="section-sub-main">
            End-to-end digital safety process for construction sites.
        </p>

        <div class="row g-4 mt-4">

            <div class="col-md-4">
                <div class="workflow-card">
                    <div class="workflow-number">1</div>
                    <h5>Company Setup</h5>
                    <p>Register company and create admin account.</p>
                </div>
            </div>

            <div class="col-md-4">
                <div class="workflow-card">
                    <div class="workflow-number">2</div>
                    <h5>Inspection</h5>
                    <p>Safety officer performs checklist-based inspection.</p>
                </div>
            </div>

            <div class="col-md-4">
                <div class="workflow-card">
                    <div class="workflow-number">3</div>
                    <h5>Issue Handling</h5>
                    <p>Supervisor fixes issues and safety officer verifies closure.</p>
                </div>
            </div>

        </div>
    </div>
</section>

<!-- CTA -->
<section class="landing-section">
    <div class="container">
        <div class="landing-cta-box text-center">
            <h2>Ready to Digitize Safety?</h2>
            <p>
                Manage inspections, issues, corrective actions and reports
                in one powerful system.
            </p>

            <div class="mt-4">
                <a href="/safetrac/register.php" class="btn btn-warning btn-lg fw-semibold">Register Now</a>
                <a href="/safetrac/login.php" class="btn btn-outline-light btn-lg ms-2">Login</a>
            </div>
        </div>
    </div>
</section>

<!-- FOOTER -->
<footer class="landing-footer">
    <div class="container d-flex justify-content-between">
        <div><strong>SafeTrack</strong> © <?= date('Y'); ?></div>
        <div>
            <a href="/safetrac/login.php">Login</a> |
            <a href="/safetrac/register.php">Register</a>
        </div>
    </div>
</footer>

<?php include 'includes/auth_footer.php'; ?>