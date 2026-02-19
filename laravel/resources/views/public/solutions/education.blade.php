@extends('layouts.public')

@section('title', 'AI Chatbot for Educational Institutions | AI Chat Support for Schools & Universities')
@section('description', 'Transform student support with AI chatbots for education. Answer admissions questions, provide course information, and support students 24/7. Perfect for schools, colleges, and universities with WhatsApp automation.')
@section('keywords', 'AI chatbot for education, AI chat for schools, university chatbot, student support AI, educational chatbot, college AI assistant, admissions automation, education customer support, WhatsApp education')

@section('og_title', 'AI Chatbot for Educational Institutions - 24/7 Student Support')
@section('og_description', 'Automate student inquiries, admissions support, and campus information with intelligent AI chatbots designed for educational institutions.')

@section('content')
<style>
    .hero-education {
        background: linear-gradient(135deg, #4A90E2 0%, #357ABD 100%);
        color: white;
        padding: 100px 0 80px;
    }
    .feature-icon {
        font-size: 3rem;
        color: #4A90E2;
        margin-bottom: 1.5rem;
    }
    .stats-section {
        background: #f8f9fa;
        padding: 60px 0;
    }
    .use-case-card {
        border-left: 4px solid #4A90E2;
        padding: 1.5rem;
        margin-bottom: 1.5rem;
        background: white;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }
</style>

<!-- Hero Section -->
<section class="hero-education">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-6">
                <h1 class="display-4 fw-bold mb-4">AI Chatbot for Educational Institutions</h1>
                <p class="lead mb-4">Empower students, faculty, and staff with 24/7 intelligent AI support. Automate admissions inquiries, course information, campus services, and more.</p>
                <div class="mb-4">
                    <span class="badge bg-light text-dark me-2 mb-2 fs-6">School AI Assistant</span>
                    <span class="badge bg-light text-dark me-2 mb-2 fs-6">University Chatbot</span>
                    <span class="badge bg-light text-dark me-2 mb-2 fs-6">Student Support AI</span>
                </div>
                <a href="{{ route('register') }}" class="btn btn-light btn-lg me-3">
                    <i class="fas fa-rocket me-2"></i>Start Free Trial
                </a>
                <a href="{{ route('contact') }}" class="btn btn-outline-light btn-lg">
                    <i class="fas fa-calendar me-2"></i>Book Demo
                </a>
            </div>
            <div class="col-lg-6 text-center">
                <i class="fas fa-graduation-cap" style="font-size: 15rem; opacity: 0.2;"></i>
            </div>
        </div>
    </div>
</section>

<!-- Stats Section -->
<section class="stats-section">
    <div class="container">
        <div class="row text-center">
            <div class="col-md-3 mb-4">
                <h2 class="display-4 fw-bold text-primary">85%</h2>
                <p class="text-muted">Reduction in repetitive queries</p>
            </div>
            <div class="col-md-3 mb-4">
                <h2 class="display-4 fw-bold text-primary">24/7</h2>
                <p class="text-muted">Student support availability</p>
            </div>
            <div class="col-md-3 mb-4">
                <h2 class="display-4 fw-bold text-primary">60%</h2>
                <p class="text-muted">Faster response times</p>
            </div>
            <div class="col-md-3 mb-4">
                <h2 class="display-4 fw-bold text-primary">50+</h2>
                <p class="text-muted">Languages supported</p>
            </div>
        </div>
    </div>
</section>

<!-- Key Features -->
<section class="py-5">
    <div class="container">
        <div class="text-center mb-5">
            <h2 class="display-5 fw-bold">Why Educational Institutions Choose Our AI Chatbot</h2>
            <p class="lead text-muted">Purpose-built features for schools, colleges, and universities</p>
        </div>
        
        <div class="row g-4">
            <div class="col-md-4 text-center">
                <div class="feature-icon"><i class="fas fa-user-graduate"></i></div>
                <h3>Admissions Support</h3>
                <p class="text-muted">Automatically answer application questions, program requirements, deadlines, and entrance criteria. Guide prospective students through the enrollment process.</p>
            </div>
            <div class="col-md-4 text-center">
                <div class="feature-icon"><i class="fas fa-book-open"></i></div>
                <h3>Course Information</h3>
                <p class="text-muted">Provide instant information about course schedules, syllabi, prerequisites, faculty details, and academic calendars across all departments.</p>
            </div>
            <div class="col-md-4 text-center">
                <div class="feature-icon"><i class="fas fa-calendar-check"></i></div>
                <h3>Event & Schedule Management</h3>
                <p class="text-muted">Share campus event details, exam schedules, registration deadlines, and important academic dates with automated reminders.</p>
            </div>
            <div class="col-md-4 text-center">
                <div class="feature-icon"><i class="fas fa-building"></i></div>
                <h3>Campus Services</h3>
                <p class="text-muted">Help students navigate library services, housing, dining, transportation, sports facilities, and student organizations.</p>
            </div>
            <div class="col-md-4 text-center">
                <div class="feature-icon"><i class="fas fa-headset"></i></div>
                <h3>IT & Technical Support</h3>
                <p class="text-muted">Assist with LMS access, WiFi troubleshooting, email setup, portal login issues, and common technical problems.</p>
            </div>
            <div class="col-md-4 text-center">
                <div class="feature-icon"><i class="fas fa-globe"></i></div>
                <h3>Multilingual Support</h3>
                <p class="text-muted">Support international students with AI chat in 50+ languages, breaking language barriers for diverse student populations.</p>
            </div>
        </div>
    </div>
</section>

<!-- Use Cases -->
<section class="py-5 bg-light">
    <div class="container">
        <h2 class="display-5 fw-bold text-center mb-5">Common Use Cases for Educational AI Chatbots</h2>
        
        <div class="row">
            <div class="col-lg-6">
                <div class="use-case-card">
                    <h4><i class="fas fa-check-circle text-success me-2"></i>Admissions & Enrollment</h4>
                    <p class="mb-0">Answer questions about application processes, program offerings, tuition fees, financial aid, scholarships, and admission requirements 24/7.</p>
                </div>
                
                <div class="use-case-card">
                    <h4><i class="fas fa-check-circle text-success me-2"></i>Student Onboarding</h4>
                    <p class="mb-0">Guide new students through orientation, registration, campus tours, ID card issuance, and first-day preparations.</p>
                </div>
                
                <div class="use-case-card">
                    <h4><i class="fas fa-check-circle text-success me-2"></i>Academic Advising</h4>
                    <p class="mb-0">Provide information on degree requirements, course selection, major/minor options, and graduation requirements.</p>
                </div>
            </div>
            
            <div class="col-lg-6">
                <div class="use-case-card">
                    <h4><i class="fas fa-check-circle text-success me-2"></i>Library Services</h4>
                    <p class="mb-0">Help students find resources, check book availability, understand borrowing policies, and access digital databases.</p>
                </div>
                
                <div class="use-case-card">
                    <h4><i class="fas fa-check-circle text-success me-2"></i>Parent Communication</h4>
                    <p class="mb-0">Answer parent inquiries about student progress, school policies, events, and administrative procedures.</p>
                </div>
                
                <div class="use-case-card">
                    <h4><i class="fas fa-check-circle text-success me-2"></i>Alumni Relations</h4>
                    <p class="mb-0">Engage alumni with event information, donation processes, transcript requests, and networking opportunities.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Integration Section -->
<section class="py-5">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-6">
                <h2 class="display-5 fw-bold mb-4">Seamless Integration with Your Systems</h2>
                <ul class="list-unstyled fs-5">
                    <li class="mb-3"><i class="fas fa-check text-success me-3"></i>Student Information Systems (SIS)</li>
                    <li class="mb-3"><i class="fas fa-check text-success me-3"></i>Learning Management Systems (Canvas, Moodle, Blackboard)</li>
                    <li class="mb-3"><i class="fas fa-check text-success me-3"></i>School Websites & Portals</li>
                    <li class="mb-3"><i class="fas fa-check text-success me-3"></i>CRM Systems (Salesforce Education Cloud)</li>
                    <li class="mb-3"><i class="fas fa-check text-success me-3"></i>WordPress & Custom Websites</li>
                    <li class="mb-3"><i class="fas fa-check text-success me-3"></i>Knowledge Base & FAQ Systems</li>
                </ul>
            </div>
            <div class="col-lg-6 text-center">
                <i class="fas fa-plug" style="font-size: 12rem; color: #4A90E2; opacity: 0.3;"></i>
            </div>
        </div>
    </div>
</section>

<!-- CTA Section -->
<section class="py-5 bg-primary text-white">
    <div class="container text-center">
        <h2 class="display-5 fw-bold mb-4">Ready to Transform Student Support?</h2>
        <p class="lead mb-4">Join hundreds of educational institutions using AI chatbots to improve student satisfaction</p>
        <a href="{{ route('register') }}" class="btn btn-light btn-lg me-3">
            <i class="fas fa-rocket me-2"></i>Start Free Trial
        </a>
        <a href="{{ route('contact') }}" class="btn btn-outline-light btn-lg">
            <i class="fas fa-comments me-2"></i>Talk to Education Specialist
        </a>
    </div>
</section>
@endsection
