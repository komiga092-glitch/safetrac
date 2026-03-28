<?php
session_start();

if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SafeTrack | Construction Safety Inspection System</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

    <style>
    :root {
        --primary: #0b1f3a;
        --secondary: #163a63;
        --accent: #f97316;
        --danger: #dc2626;
        --success: #16a34a;
        --lightbg: #f5f7fa;
        --darktext: #0f172a;
        --muted: #64748b;
    }

    * {
        box-sizing: border-box;
    }

    body {
        margin: 0;
        font-family: 'Segoe UI', sans-serif;
        background: var(--lightbg);
        color: var(--darktext);
    }

    .navbar {
        background: rgba(11, 31, 58, 0.97);
        backdrop-filter: blur(8px);
        box-shadow: 0 4px 16px rgba(0, 0, 0, .12);
    }

    .navbar-brand {
        font-size: 1.5rem;
        font-weight: 700;
        letter-spacing: .4px;
    }

    .nav-link {
        color: #e2e8f0 !important;
        font-weight: 500;
    }

    .nav-link:hover {
        color: #fff !important;
    }

    .btn-main {
        background: linear-gradient(120deg, var(--primary), var(--secondary));
        color: #fff;
        border: none;
        border-radius: 12px;
        padding: 12px 22px;
        font-weight: 600;
    }

    .btn-main:hover {
        color: #fff;
        opacity: .95;
    }

    .btn-accent {
        background: var(--accent);
        color: #fff;
        border: none;
        border-radius: 12px;
        padding: 12px 22px;
        font-weight: 600;
    }

    .btn-accent:hover {
        color: #fff;
        opacity: .95;
    }

    .hero {
        min-height: 100vh;
        background:
            linear-gradient(rgba(11, 31, 58, .84), rgba(22, 58, 99, .84)),
            url('https://images.unsplash.com/photo-1504307651254-35680f356dfd?auto=format&fit=crop&w=1600&q=80') center/cover no-repeat;
        color: #fff;
        display: flex;
        align-items: center;
        position: relative;
    }

    .hero-badge {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        background: rgba(255, 255, 255, .12);
        border: 1px solid rgba(255, 255, 255, .18);
        color: #fff;
        padding: 10px 16px;
        border-radius: 999px;
        font-size: .95rem;
        margin-bottom: 18px;
    }

    .hero h1 {
        font-size: 3rem;
        font-weight: 800;
        line-height: 1.15;
        margin-bottom: 18px;
    }

    .hero p {
        font-size: 1.08rem;
        color: #e2e8f0;
        max-width: 760px;
    }

    .hero-card {
        background: rgba(255, 255, 255, .08);
        border: 1px solid rgba(255, 255, 255, .16);
        backdrop-filter: blur(10px);
        border-radius: 24px;
        padding: 24px;
        box-shadow: 0 14px 32px rgba(0, 0, 0, .20);
    }

    .hero-mini {
        background: rgba(255, 255, 255, .10);
        border-radius: 16px;
        padding: 16px;
        margin-bottom: 14px;
    }

    .section {
        padding: 90px 0;
    }

    .section-title {
        font-size: 2.2rem;
        font-weight: 800;
        color: var(--primary);
        margin-bottom: 12px;
    }

    .section-subtitle {
        color: var(--muted);
        max-width: 760px;
        margin: 0 auto;
    }

    .card-clean {
        border: none;
        border-radius: 22px;
        box-shadow: 0 12px 28px rgba(15, 23, 42, .08);
        overflow: hidden;
        height: 100%;
        background: #fff;
    }

    .feature-card {
        border: none;
        border-radius: 22px;
        padding: 26px;
        background: #fff;
        box-shadow: 0 12px 28px rgba(15, 23, 42, .08);
        height: 100%;
        transition: all .25s ease;
    }

    .feature-card:hover {
        transform: translateY(-6px);
    }

    .feature-icon {
        width: 64px;
        height: 64px;
        border-radius: 18px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        margin-bottom: 18px;
        background: linear-gradient(120deg, var(--primary), var(--secondary));
        color: #fff;
    }

    .workflow-step {
        background: #fff;
        border-radius: 20px;
        padding: 22px;
        box-shadow: 0 12px 28px rgba(15, 23, 42, .08);
        height: 100%;
    }

    .workflow-number {
        width: 48px;
        height: 48px;
        border-radius: 50%;
        background: var(--accent);
        color: #fff;
        font-weight: 700;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 14px;
    }

    .role-card {
        border: none;
        border-radius: 22px;
        box-shadow: 0 12px 28px rgba(15, 23, 42, .08);
        overflow: hidden;
        height: 100%;
    }

    .role-header {
        background: linear-gradient(120deg, var(--primary), var(--secondary));
        color: #fff;
        padding: 20px;
    }

    .role-body {
        padding: 22px;
        background: #fff;
    }

    .role-body ul {
        padding-left: 18px;
        margin-bottom: 0;
    }

    .role-body li {
        margin-bottom: 10px;
        color: #475569;
    }

    .stats-strip {
        background: linear-gradient(120deg, var(--primary), var(--secondary));
        color: #fff;
        border-radius: 28px;
        padding: 26px;
        box-shadow: 0 18px 36px rgba(11, 31, 58, .20);
    }

    .stat-box {
        text-align: center;
        padding: 16px;
    }

    .stat-box h3 {
        font-size: 2rem;
        font-weight: 800;
        margin-bottom: 4px;
    }

    .image-card {
        border: none;
        border-radius: 24px;
        overflow: hidden;
        box-shadow: 0 16px 34px rgba(15, 23, 42, .12);
        background: #fff;
        height: 100%;
    }

    .image-card img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        min-height: 260px;
    }

    .system-point {
        display: flex;
        align-items: flex-start;
        gap: 14px;
        margin-bottom: 18px;
    }

    .system-point i {
        color: var(--success);
        font-size: 1.2rem;
        margin-top: 3px;
    }

    .risk-badge {
        display: inline-block;
        border-radius: 999px;
        padding: 8px 14px;
        font-size: .9rem;
        font-weight: 700;
        color: #fff;
        margin-right: 8px;
        margin-bottom: 8px;
    }

    .safe {
        background: var(--success);
    }

    .moderate {
        background: var(--accent);
    }

    .risk {
        background: var(--danger);
    }

    .cta-section {
        background:
            linear-gradient(rgba(11, 31, 58, .90), rgba(22, 58, 99, .90)),
            url('https://images.unsplash.com/photo-1541888946425-d81bb19240f5?auto=format&fit=crop&w=1600&q=80') center/cover no-repeat;
        color: #fff;
        border-radius: 30px;
        padding: 60px 32px;
        box-shadow: 0 20px 40px rgba(15, 23, 42, .16);
    }

    .footer {
        background: #081426;
        color: #cbd5e1;
        padding: 30px 0;
    }

    .footer a {
        color: #fff;
        text-decoration: none;
    }

    @media (max-width: 991px) {
        .hero {
            min-height: auto;
            padding: 120px 0 80px;
        }

        .hero h1 {
            font-size: 2.3rem;
        }
    }

    @media (max-width: 767px) {
        .section {
            padding: 70px 0;
        }

        .hero h1 {
            font-size: 2rem;
        }
    }
    </style>
</head>

<body>

    <nav class="navbar navbar-expand-lg navbar-dark fixed-top">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="bi bi-shield-check me-2"></i>SafeTrack
            </a>

            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="mainNav">
                <ul class="navbar-nav ms-auto me-3">
                    <li class="nav-item"><a class="nav-link" href="#about">About</a></li>
                    <li class="nav-item"><a class="nav-link" href="#workflow">Workflow</a></li>
                    <li class="nav-item"><a class="nav-link" href="#features">Features</a></li>
                    <li class="nav-item"><a class="nav-link" href="#roles">Roles</a></li>
                    <li class="nav-item"><a class="nav-link" href="#reports">Reports</a></li>
                </ul>

                <div class="d-flex gap-2">
                    <a href="login.php" class="btn btn-outline-light rounded-3 px-4">Login</a>
                    <a href="register.php" class="btn btn-accent">Register Company</a>
                </div>
            </div>
        </div>
    </nav>

    <section class="hero">
        <div class="container">
            <div class="row align-items-center g-5">
                <div class="col-lg-7">
                    <div class="hero-badge">
                        <i class="bi bi-cone-striped"></i>
                        Professional Construction Safety Inspection Platform
                    </div>

                    <h1>Digital Construction Safety Checkup and Issue Tracking System</h1>

                    <p>
                        SafeTrack என்பது multi-company construction safety inspection web application.
                        Company admin project/site create பண்ணலாம், Safety Officer site inspection செய்யலாம்,
                        fail items auto issue ஆக create ஆகும், Supervisor corrective action update செய்யலாம்,
                        Safety Officer recheck செய்து close அல்லது reopen செய்யலாம்.
                    </p>

                    <div class="d-flex flex-wrap gap-3 mt-4">
                        <a href="register.php" class="btn btn-accent btn-lg">Get Started</a>
                        <a href="login.php" class="btn btn-outline-light btn-lg">Login Now</a>
                    </div>

                    <div class="mt-4">
                        <span class="risk-badge safe">90%+ Safe</span>
                        <span class="risk-badge moderate">75%–89% Moderate</span>
                        <span class="risk-badge risk">Below 75% Risk</span>
                    </div>
                </div>

                <div class="col-lg-5">
                    <div class="hero-card">
                        <div class="hero-mini">
                            <h5 class="mb-2"><i class="bi bi-clipboard-check me-2"></i>Inspection Control</h5>
                            <p class="mb-0 text-light-emphasis">
                                Default checklist, pass/fail/NA responses, mandatory notes, severity, photo, due date.
                            </p>
                        </div>

                        <div class="hero-mini">
                            <h5 class="mb-2"><i class="bi bi-exclamation-triangle me-2"></i>Issue Management</h5>
                            <p class="mb-0 text-light-emphasis">
                                Fail item auto issue creation, supervisor action tracking, overdue alerts, recheck
                                workflow.
                            </p>
                        </div>

                        <div class="hero-mini mb-0">
                            <h5 class="mb-2"><i class="bi bi-bar-chart-line me-2"></i>Analytics & Reports</h5>
                            <p class="mb-0 text-light-emphasis">
                                Site-wise safety score, dashboard charts, notifications, printable PDF-style reports.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="section bg-white" id="about">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="section-title">About the System</h2>
                <p class="section-subtitle">
                    Construction sitesல manual safety inspection process slow-aa இருக்கும்.
                    SafeTrack அதைப் digital system-aa மாற்றி professional monitoring, issue tracking, evidence handling,
                    safety score analysis, and reporting support கொடுக்கும்.
                </p>
            </div>

            <div class="row g-4 align-items-center">
                <div class="col-lg-6">
                    <div class="image-card">
                        <img src="https://images.unsplash.com/photo-1517048676732-d65bc937f952?auto=format&fit=crop&w=1200&q=80"
                            alt="Construction Safety Team">
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="card-clean p-4">
                        <div class="system-point">
                            <i class="bi bi-check-circle-fill"></i>
                            <div>
                                <h5 class="mb-1">Multi-Company Platform</h5>
                                <p class="mb-0 text-muted">ஒரே system-ல பல construction companies register பண்ணி
                                    தனித்தனி projects manage பண்ணலாம்.</p>
                            </div>
                        </div>

                        <div class="system-point">
                            <i class="bi bi-check-circle-fill"></i>
                            <div>
                                <h5 class="mb-1">Digital Safety Inspection</h5>
                                <p class="mb-0 text-muted">Checklist-based inspection மூலம் site safety condition
                                    structured-aa record ஆகும்.</p>
                            </div>
                        </div>

                        <div class="system-point">
                            <i class="bi bi-check-circle-fill"></i>
                            <div>
                                <h5 class="mb-1">Corrective Action Flow</h5>
                                <p class="mb-0 text-muted">Fail items supervisorக்கு assign ஆகி, action taken + after
                                    photo upload பண்ண முடியும்.</p>
                            </div>
                        </div>

                        <div class="system-point mb-0">
                            <i class="bi bi-check-circle-fill"></i>
                            <div>
                                <h5 class="mb-1">Recheck and Closure</h5>
                                <p class="mb-0 text-muted">Safety Officer issue-ஐ verify செய்து closed அல்லது reopened
                                    decision எடுக்கலாம்.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="section" id="workflow">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="section-title">System Workflow</h2>
                <p class="section-subtitle">
                    Site safety process end-to-end digital workflow மூலம் controlled and traceable ஆக இருக்கும்.
                </p>
            </div>

            <div class="row g-4">
                <div class="col-md-6 col-lg-3">
                    <div class="workflow-step">
                        <div class="workflow-number">1</div>
                        <h5>Company Registration</h5>
                        <p class="text-muted mb-0">Company register செய்து admin account create ஆகும்.</p>
                    </div>
                </div>

                <div class="col-md-6 col-lg-3">
                    <div class="workflow-step">
                        <div class="workflow-number">2</div>
                        <h5>Project & Site Setup</h5>
                        <p class="text-muted mb-0">Admin project/site create பண்ணி Safety Officer மற்றும் Supervisor
                            assign பண்ணுவார்.</p>
                    </div>
                </div>

                <div class="col-md-6 col-lg-3">
                    <div class="workflow-step">
                        <div class="workflow-number">3</div>
                        <h5>Inspection Start</h5>
                        <p class="text-muted mb-0">Safety Officer default checklist open பண்ணி Pass / Fail / NA mark
                            பண்ணுவார்.</p>
                    </div>
                </div>

                <div class="col-md-6 col-lg-3">
                    <div class="workflow-step">
                        <div class="workflow-number">4</div>
                        <h5>Issue Auto Creation</h5>
                        <p class="text-muted mb-0">Fail item இருந்தால் note, severity, photo, due date, supervisor add
                            செய்து issue create ஆகும்.</p>
                    </div>
                </div>

                <div class="col-md-6 col-lg-4">
                    <div class="workflow-step">
                        <div class="workflow-number">5</div>
                        <h5>Supervisor Corrective Action</h5>
                        <p class="text-muted mb-0">Supervisor action taken, fixed date, after photo, comment update
                            பண்ணுவார்.</p>
                    </div>
                </div>

                <div class="col-md-6 col-lg-4">
                    <div class="workflow-step">
                        <div class="workflow-number">6</div>
                        <h5>Recheck Pending</h5>
                        <p class="text-muted mb-0">System status recheck pending ஆக update செய்து Safety Officerக்கு
                            notification அனுப்பும்.</p>
                    </div>
                </div>

                <div class="col-md-12 col-lg-4">
                    <div class="workflow-step">
                        <div class="workflow-number">7</div>
                        <h5>Close or Reopen</h5>
                        <p class="text-muted mb-0">Safety Officer issue சரி என்றால் close, இன்னும் problem என்றால்
                            reopen பண்ணுவார்.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="section bg-white" id="features">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="section-title">Core Safety Features</h2>
                <p class="section-subtitle">
                    Construction safety fieldக்கு professional monitoring, accountability, and reporting support
                    கிடைக்கும்.
                </p>
            </div>

            <div class="row g-4">
                <div class="col-md-6 col-lg-4">
                    <div class="feature-card">
                        <div class="feature-icon"><i class="bi bi-shield-check"></i></div>
                        <h5>Site-wise Safety Score</h5>
                        <p class="text-muted mb-0">
                            Inspection முடிந்த பிறகு score calculate ஆகும். Pass = 1, Fail = 0, NA ignored.
                        </p>
                    </div>
                </div>

                <div class="col-md-6 col-lg-4">
                    <div class="feature-card">
                        <div class="feature-icon"><i class="bi bi-exclamation-octagon"></i></div>
                        <h5>Severity Priority</h5>
                        <p class="text-muted mb-0">
                            Low, Medium, High, Critical levels use பண்ணி important issues dashboardல highlight ஆகும்.
                        </p>
                    </div>
                </div>

                <div class="col-md-6 col-lg-4">
                    <div class="feature-card">
                        <div class="feature-icon"><i class="bi bi-calendar-x"></i></div>
                        <h5>Due Date & Overdue Alerts</h5>
                        <p class="text-muted mb-0">
                            Supervisor fix deadline cross ஆனால் overdue alert visible ஆகும்.
                        </p>
                    </div>
                </div>

                <div class="col-md-6 col-lg-4">
                    <div class="feature-card">
                        <div class="feature-icon"><i class="bi bi-images"></i></div>
                        <h5>Before / After Photo Evidence</h5>
                        <p class="text-muted mb-0">
                            Fail photo மற்றும் corrective after photo மூலம் proof-based safety tracking கிடைக்கும்.
                        </p>
                    </div>
                </div>

                <div class="col-md-6 col-lg-4">
                    <div class="feature-card">
                        <div class="feature-icon"><i class="bi bi-bell"></i></div>
                        <h5>Notification System</h5>
                        <p class="text-muted mb-0">
                            New issue, recheck pending, reopened issue, closed issue போன்ற alerts role-wise கிடைக்கும்.
                        </p>
                    </div>
                </div>

                <div class="col-md-6 col-lg-4">
                    <div class="feature-card">
                        <div class="feature-icon"><i class="bi bi-file-earmark-text"></i></div>
                        <h5>Reports & Analytics</h5>
                        <p class="text-muted mb-0">
                            Inspection reports, issue reports, charts, dashboard metrics and print-ready PDF style
                            pages.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="section" id="roles">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="section-title">Role-Based Access</h2>
                <p class="section-subtitle">
                    ஒவ்வொரு userக்கும் அவரவர் வேலைக்கு ஏற்ற dashboard மற்றும் modules கிடைக்கும்.
                </p>
            </div>

            <div class="row g-4">
                <div class="col-md-6 col-lg-4">
                    <div class="role-card">
                        <div class="role-header">
                            <h4 class="mb-1"><i class="bi bi-building me-2"></i>Company Admin</h4>
                            <small>System Control & Monitoring</small>
                        </div>
                        <div class="role-body">
                            <ul>
                                <li>Company register and admin login</li>
                                <li>Create projects and sites</li>
                                <li>Add Safety Officer and Supervisor</li>
                                <li>Assign staff to projects</li>
                                <li>Monitor analytics and safety score</li>
                                <li>View reports and alerts</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="col-md-6 col-lg-4">
                    <div class="role-card">
                        <div class="role-header">
                            <h4 class="mb-1"><i class="bi bi-clipboard-check me-2"></i>Safety Officer</h4>
                            <small>Inspection & Recheck</small>
                        </div>
                        <div class="role-body">
                            <ul>
                                <li>Start inspections for assigned projects</li>
                                <li>Use default checklist items</li>
                                <li>Mark Pass / Fail / NA</li>
                                <li>Create issue through failed response</li>
                                <li>Review supervisor correction</li>
                                <li>Close or reopen issue after recheck</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="col-md-6 col-lg-4">
                    <div class="role-card">
                        <div class="role-header">
                            <h4 class="mb-1"><i class="bi bi-tools me-2"></i>Supervisor</h4>
                            <small>Corrective Action Handler</small>
                        </div>
                        <div class="role-body">
                            <ul>
                                <li>Receive assigned issue alerts</li>
                                <li>View failed item details</li>
                                <li>Add action taken and fixed date</li>
                                <li>Upload after photo evidence</li>
                                <li>Send item for recheck</li>
                                <li>Handle reopened issues again</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="section">
        <div class="container">
            <div class="stats-strip">
                <div class="row g-4">
                    <div class="col-md-3">
                        <div class="stat-box">
                            <h3>100%</h3>
                            <p class="mb-0">Digital Inspection Flow</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-box">
                            <h3>3</h3>
                            <p class="mb-0">Main User Roles</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-box">
                            <h3>24/7</h3>
                            <p class="mb-0">Issue Monitoring</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-box">
                            <h3>PDF</h3>
                            <p class="mb-0">Printable Reports</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="section bg-white" id="reports">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="section-title">Reports, Alerts and Safety Monitoring</h2>
                <p class="section-subtitle">
                    Managementக்கு quick decision எடுக்க useful ஆன analytics, charts, reports, alerts system support
                    கிடைக்கும்.
                </p>
            </div>

            <div class="row g-4 align-items-center">
                <div class="col-lg-6 order-lg-2">
                    <div class="image-card">
                        <img src="https://images.unsplash.com/photo-1503387762-592deb58ef4e?auto=format&fit=crop&w=1200&q=80"
                            alt="Construction Site Safety Report">
                    </div>
                </div>

                <div class="col-lg-6 order-lg-1">
                    <div class="card-clean p-4">
                        <div class="system-point">
                            <i class="bi bi-bar-chart-fill"></i>
                            <div>
                                <h5 class="mb-1">Dashboard Analytics</h5>
                                <p class="mb-0 text-muted">Total inspections, open issues, closed issues, overdue
                                    issues, critical issues, project-wise safety score.</p>
                            </div>
                        </div>

                        <div class="system-point">
                            <i class="bi bi-speedometer2"></i>
                            <div>
                                <h5 class="mb-1">Safety Meter</h5>
                                <p class="mb-0 text-muted">Needle style safety score meter மூலம் current safety
                                    condition quick-aa visible ஆகும்.</p>
                            </div>
                        </div>

                        <div class="system-point">
                            <i class="bi bi-file-earmark-bar-graph-fill"></i>
                            <div>
                                <h5 class="mb-1">Inspection & Issue Reports</h5>
                                <p class="mb-0 text-muted">Print-ready inspection reports மற்றும் issue reports generate
                                    பண்ணலாம்.</p>
                            </div>
                        </div>

                        <div class="system-point mb-0">
                            <i class="bi bi-clock-history"></i>
                            <div>
                                <h5 class="mb-1">Audit Trail</h5>
                                <p class="mb-0 text-muted">யார் என்ன update பண்ணினாங்க என்பதை activity log மூலம் trace
                                    பண்ண முடியும்.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="section">
        <div class="container">
            <div class="cta-section text-center">
                <h2 class="fw-bold mb-3">Ready to digitize construction safety inspections?</h2>
                <p class="mb-4 text-light-emphasis" style="max-width:780px; margin:auto;">
                    SafeTrack மூலம் site safety inspections, failed issue tracking, supervisor correction, recheck
                    workflow,
                    dashboard analytics, notifications, and reporting அனைத்தையும் ஒரு professional web system-ல manage
                    பண்ணலாம்.
                </p>

                <div class="d-flex justify-content-center flex-wrap gap-3">
                    <a href="register.php" class="btn btn-accent btn-lg">
                        <i class="bi bi-person-plus me-2"></i>Register Company
                    </a>
                    <a href="login.php" class="btn btn-outline-light btn-lg">
                        <i class="bi bi-box-arrow-in-right me-2"></i>Login
                    </a>
                </div>
            </div>
        </div>
    </section>

    <footer class="footer">
        <div class="container">
            <div class="row g-3 align-items-center">
                <div class="col-md-6">
                    <strong>SafeTrack</strong> — Construction Safety Inspection and Issue Tracking System
                </div>
                <div class="col-md-6 text-md-end">
                    <a href="login.php" class="me-3">Login</a>
                    <a href="register.php">Register</a>
                </div>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>