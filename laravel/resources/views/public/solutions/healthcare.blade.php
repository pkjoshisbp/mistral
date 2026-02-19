@extends('layouts.public')

@section('title', 'AI Chatbot for Healthcare & Hospitals | Medical AI Assistant')
@section('description', 'Transform patient experience with AI chatbots for healthcare. Book appointments, check symptoms, and answer medical FAQs 24/7. HIPAA-compliant with automated WhatsApp reminders for hospitals and clinics.')
@section('keywords', 'AI chatbot for healthcare, hospital chatbot, medical AI assistant, patient support AI, healthcare virtual assistant, clinic chatbot, HIPAA compliant AI, healthcare appointments, WhatsApp appointment reminders, healthcare automation')

@section('og_title', 'AI Chatbot for Healthcare - Improve Patient Experience & Reduce Staff Workload')
@section('og_description', 'Automate appointment bookings, answer medical queries, and provide 24/7 patient support with HIPAA-compliant AI chatbots.')

@section('content')
<style>
    .hero-healthcare {
        background: linear-gradient(135deg, #4ECDC4 0%, #44A08D 100%);
        color: white;
        padding: 100px 0 80px;
    }
    .feature-icon {
        font-size: 3rem;
        color: #4ECDC4;
        margin-bottom: 1.5rem;
    }
    .stats-section {
        background: #f8f9fa;
        padding: 60px 0;
    }
    .use-case-card {
        border-left: 4px solid #4ECDC4;
        padding: 1.5rem;
        margin-bottom: 1.5rem;
        background: white;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }
    .security-badge {
        display: inline-block;
        padding: 0.5rem 1rem;
        background: white;
        border-radius: 50px;
        margin: 0.5rem;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }
</style>

<!-- Hero Section -->
<section class="hero-healthcare">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-6">
                <h1 class="display-4 fw-bold mb-4">AI Chatbot for Healthcare & Hospitals</h1>
                <p class="lead mb-4">Enhance patient experience, reduce administrative burden, and provide 24/7 healthcare support with HIPAA-compliant AI assistants.</p>
                <div class="mb-4">
                    <span class="security-badge"><i class="fas fa-shield-alt text-success me-2"></i>HIPAA Compliant</span>
                    <span class="security-badge"><i class="fas fa-lock text-primary me-2"></i>Secure & Encrypted</span>
                    <span class="security-badge"><i class="fas fa-certificate text-warning me-2"></i>Healthcare Certified</span>
                </div>
                <a href="{{ route('register') }}" class="btn btn-light btn-lg me-3">
                    <i class="fas fa-rocket me-2"></i>Start Free Trial
                </a>
                <a href="{{ route('contact') }}" class="btn btn-outline-light btn-lg">
                    <i class="fas fa-calendar me-2"></i>Book Healthcare Demo
                </a>
            </div>
            <div class="col-lg-6 text-center">
                <i class="fas fa-hospital" style="font-size: 15rem; opacity: 0.2;"></i>
            </div>
        </div>
    </div>
</section>

<!-- Automation Benefits Banner -->
<section class="py-4" style="background: white; border-top: 3px solid #4ECDC4;">
    <div class="container">
        <div class="row text-center align-items-center">
            <div class="col-md-3">
                <i class="fas fa-robot text-info fa-2x mb-2"></i>
                <small class="d-block"><strong>AI Chat Support</strong></small>
                <small class="text-muted">Answer patient questions instantly</small>
            </div>
            <div class="col-md-3">
                <i class="fab fa-whatsapp text-success fa-2x mb-2"></i>
                <small class="d-block"><strong>WhatsApp Reminders</strong></small>
                <small class="text-muted">Automated appointment notifications</small>
            </div>
            <div class="col-md-3">
                <i class="fas fa-clock text-primary fa-2x mb-2"></i>
                <small class="d-block"><strong>24/7 Availability</strong></small>
                <small class="text-muted">Always ready to help patients</small>
            </div>
            <div class="col-md-3">
                <i class="fas fa-users text-warning fa-2x mb-2"></i>
                <small class="d-block"><strong>Reduce Workload</strong></small>
                <small class="text-muted">Staff focuses on patient care</small>
            </div>
        </div>
    </div>
</section>

<!-- Stats Section -->
<section class="stats-section">
    <div class="container">
        <div class="row text-center">
            <div class="col-md-3 mb-4">
                <h2 class="display-4 fw-bold text-info">70%</h2>
                <p class="text-muted">Reduction in call volume</p>
            </div>
            <div class="col-md-3 mb-4">
                <h2 class="display-4 fw-bold text-info">24/7</h2>
                <p class="text-muted">Patient support availability</p>
            </div>
            <div class="col-md-3 mb-4">
                <h2 class="display-4 fw-bold text-info">90%</h2>
                <p class="text-muted">Patient satisfaction rate</p>
            </div>
            <div class="col-md-3 mb-4">
                <h2 class="display-4 fw-bold text-info">50%</h2>
                <p class="text-muted">Faster appointment booking</p>
            </div>
        </div>
    </div>
</section>

<!-- Key Features -->
<section class="py-5">
    <div class="container">
        <div class="text-center mb-5">
            <h2 class="display-5 fw-bold">Healthcare AI Chatbot Features</h2>
            <p class="lead text-muted">Purpose-built for hospitals, clinics, and healthcare providers</p>
        </div>
        
        <div class="row g-4">
            <div class="col-md-4 text-center">
                <div class="feature-icon"><i class="fas fa-calendar-check"></i></div>
                <h3>Appointment Scheduling</h3>
                <p class="text-muted">Automated booking, rescheduling, and cancellations. Sync with hospital management systems and send appointment reminders.</p>
            </div>
            <div class="col-md-4 text-center">
                <div class="feature-icon"><i class="fas fa-stethoscope"></i></div>
                <h3>Symptom Checker</h3>
                <p class="text-muted">Preliminary symptom assessment to guide patients to appropriate departments. Not a replacement for medical advice.</p>
            </div>
            <div class="col-md-4 text-center">
                <div class="feature-icon"><i class="fas fa-prescription-bottle"></i></div>
                <h3>Medication Information</h3>
                <p class="text-muted">Answer questions about prescriptions, dosage instructions, side effects, and pharmacy locations.</p>
            </div>
            <div class="col-md-4 text-center">
                <div class="feature-icon"><i class="fas fa-file-medical"></i></div>
                <h3>Medical Records Access</h3>
                <p class="text-muted">Help patients access test results, discharge summaries, and medical reports securely through patient portals.</p>
            </div>
            <div class="col-md-4 text-center">
                <div class="feature-icon"><i class="fas fa-money-bill-wave"></i></div>
                <h3>Insurance & Billing</h3>
                <p class="text-muted">Explain insurance coverage, billing procedures, payment options, and help with insurance pre-authorization questions.</p>
            </div>
            <div class="col-md-4 text-center">
                <div class="feature-icon"><i class="fas fa-ambulance"></i></div>
                <h3>Emergency Guidance</h3>
                <p class="text-muted">Triage urgent cases, provide emergency contact numbers, and direct patients to nearest emergency services when needed.</p>
            </div>
        </div>
    </div>
</section>

<!-- Use Cases -->
<section class="py-5 bg-light">
    <div class="container">
        <h2 class="display-5 fw-bold text-center mb-5">Healthcare AI Chatbot Use Cases</h2>
        
        <div class="row">
            <div class="col-lg-6">
                <div class="use-case-card">
                    <h4><i class="fas fa-hospital-user text-success me-2"></i>Patient Registration</h4>
                    <p class="mb-0">Collect patient information, demographics, insurance details, and medical history before appointments to save staff time.</p>
                </div>
                
                <div class="use-case-card">
                    <h4><i class="fas fa-user-md text-success me-2"></i>Doctor Directory</h4>
                    <p class="mb-0">Help patients find specialists, check doctor availability, view credentials, and read patient reviews.</p>
                </div>
                
                <div class="use-case-card">
                    <h4><i class="fas fa-clipboard-check text-success me-2"></i>Pre-Appointment Instructions</h4>
                    <p class="mb-0">Send automated pre-appointment guidelines like fasting requirements, documents to bring, and arrival time.</p>
                </div>
                
                <div class="use-case-card">
                    <h4><i class="fas fa-heartbeat text-success me-2"></i>Health Monitoring</h4>
                    <p class="mb-0">Remind patients about medications, follow-up appointments, and health check-ups for chronic disease management.</p>
                </div>
            </div>
            
            <div class="col-lg-6">
                <div class="use-case-card">
                    <h4><i class="fas fa-map-marker-alt text-success me-2"></i>Facility Navigation</h4>
                    <p class="mb-0">Guide visitors to departments, parking, cafeteria, and other hospital facilities with interactive directions.</p>
                </div>
                
                <div class="use-case-card">
                    <h4><i class="fas fa-vial text-success me-2"></i>Lab Results Notification</h4>
                    <p class="mb-0">Notify patients when test results are ready and guide them through accessing reports securely.</p>
                </div>
                
                <div class="use-case-card">
                    <h4><i class="fas fa-comments text-success me-2"></i>Post-Discharge Support</h4>
                    <p class="mb-0">Answer post-surgery questions, wound care instructions, and when to seek follow-up care.</p>
                </div>
                
                <div class="use-case-card">
                    <h4><i class="fas fa-globe text-success me-2"></i>Multilingual Patient Support</h4>
                    <p class="mb-0">Serve diverse patient populations with AI chat in 50+ languages for inclusive healthcare access.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Security & Compliance -->
<section class="py-5">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-6">
                <h2 class="display-5 fw-bold mb-4">Enterprise-Grade Security & Compliance</h2>
                <ul class="list-unstyled fs-5">
                    <li class="mb-3"><i class="fas fa-check-circle text-success me-3"></i>HIPAA Compliant Architecture</li>
                    <li class="mb-3"><i class="fas fa-check-circle text-success me-3"></i>End-to-End Encryption (AES-256)</li>
                    <li class="mb-3"><i class="fas fa-check-circle text-success me-3"></i>Role-Based Access Control</li>
                    <li class="mb-3"><i class="fas fa-check-circle text-success me-3"></i>Audit Logs & Compliance Reports</li>
                    <li class="mb-3"><i class="fas fa-check-circle text-success me-3"></i>Data Residency Options</li>
                    <li class="mb-3"><i class="fas fa-check-circle text-success me-3"></i>BAA (Business Associate Agreement)</li>
                    <li class="mb-3"><i class="fas fa-check-circle text-success me-3"></i>Regular Security Audits</li>
                </ul>
            </div>
            <div class="col-lg-6 text-center">
                <i class="fas fa-shield-alt" style="font-size: 12rem; color: #4ECDC4; opacity: 0.3;"></i>
            </div>
        </div>
    </div>
</section>

<!-- Integration Section -->
<section class="py-5 bg-light">
    <div class="container">
        <div class="text-center mb-5">
            <h2 class="display-5 fw-bold">Integrates With Healthcare Systems</h2>
            <p class="lead text-muted">Seamless connection to your existing infrastructure</p>
        </div>
        
        <div class="row g-4 text-center">
            <div class="col-md-3">
                <div class="p-4 bg-white rounded shadow-sm">
                    <i class="fas fa-hospital-alt fa-3x mb-3 text-info"></i>
                    <h5>EMR/EHR Systems</h5>
                    <p class="text-muted small">Epic, Cerner, Allscripts</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="p-4 bg-white rounded shadow-sm">
                    <i class="fas fa-calendar-alt fa-3x mb-3 text-info"></i>
                    <h5>Scheduling Systems</h5>
                    <p class="text-muted small">Appointment management</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="p-4 bg-white rounded shadow-sm">
                    <i class="fas fa-database fa-3x mb-3 text-info"></i>
                    <h5>Patient Portals</h5>
                    <p class="text-muted small">MyChart, FollowMyHealth</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="p-4 bg-white rounded shadow-sm">
                    <i class="fas fa-phone-alt fa-3x mb-3 text-info"></i>
                    <h5>Telehealth Platforms</h5>
                    <p class="text-muted small">Video consultation systems</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- CTA Section -->
<section class="py-5 bg-info text-white">
    <div class="container text-center">
        <h2 class="display-5 fw-bold mb-4">Ready to Transform Patient Experience?</h2>
        <p class="lead mb-4">Join leading healthcare providers using AI to improve patient satisfaction and reduce costs</p>
        <a href="{{ route('register') }}" class="btn btn-light btn-lg me-3">
            <i class="fas fa-rocket me-2"></i>Start Free Trial
        </a>
        <a href="{{ route('contact') }}" class="btn btn-outline-light btn-lg">
            <i class="fas fa-comments me-2"></i>Talk to Healthcare Specialist
        </a>
    </div>
</section>
@endsection
