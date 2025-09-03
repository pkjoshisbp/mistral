@extends('layouts.public')
@section('content')
    <div class="text-center mt-4 mb-2">
        <small>ai-chat.support is owned and operated by MYWEB SOLUTIONS.</small>
    </div>
    <div style="margin-top: 80px;"></div>
    <section class="hero-section">
        <div class="container">
            <div class="row">
                <div class="col-lg-8 mx-auto text-center">
                    <h1 class="display-4 fw-bold mb-4">Contact Us</h1>
                    <p class="lead mb-0">Have questions about our AI chat support solutions? We're here to help!</p>
                </div>
            </div>
        </div>
    </section>
    <section class="py-5">
        <div class="container">
            <div class="row g-5">
                <div class="col-lg-8">
                    <div class="contact-card p-4">
                        <h3 class="mb-4">Send us a Message</h3>
                        @livewire('contact-page-manager')
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="contact-info-item">
                        <i class="fas fa-envelope"></i>
                        <h5>Email Us</h5>
                        <p class="mb-0">info@ai-chat.support</p>
                    </div>
                    <div class="contact-info-item">
                        <i class="fas fa-phone"></i>
                        <h5>Call Us</h5>
                        <p class="mb-0">+91 9937253528</p>
                    </div>
                    <div class="contact-info-item">
                        <i class="fas fa-map-marker-alt"></i>
                        <h5>Address</h5>
                        <p class="mb-1">Villa No.10, Sriram Villa,</p>
                        <p class="mb-1">AN Guha Lane,</p>
                        <p class="mb-0">Sambalpur - 768001</p>
                    </div>
                    <div class="contact-info-item">
                        <i class="fas fa-comments"></i>
                        <h5>Live Chat</h5>
                        <p class="mb-0">Chat with our AI assistant 24/7 using the chat widget on this page</p>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <section class="py-5 bg-light">
        <div class="container">
            <div class="row">
                <div class="col-lg-8 mx-auto">
                    <h3 class="text-center mb-5">Frequently Asked Questions</h3>
                    <div class="accordion" id="faqAccordion">
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#faq1">
                                    How does AI Chat Support work?
                                </button>
                            </h2>
                            <div id="faq1" class="accordion-collapse collapse show" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    Our AI Chat Support uses advanced natural language processing to understand customer inquiries and provide instant, accurate responses 24/7. It integrates seamlessly with your website and can handle multiple conversations simultaneously.
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq2">
                                    Can I customize the AI responses?
                                </button>
                            </h2>
                            <div id="faq2" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    Yes! You can train the AI with your specific business information, FAQs, and knowledge base. Our platform allows you to customize responses, add new information, and continuously improve the AI's performance.
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq3">
                                    What if the AI can't answer a question?
                                </button>
                            </h2>
                            <div id="faq3" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    When the AI encounters a question it cannot answer, it gracefully escalates the conversation to your human support team. The AI provides context about the conversation to ensure a smooth handoff.
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq4">
                                    How quickly can I set up AI Chat Support?
                                </button>
                            </h2>
                            <div id="faq4" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    Setup is quick and easy! Most businesses can have their AI chat support running within 24-48 hours. This includes adding your knowledge base, customizing responses, and integrating the chat widget on your website.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
    @include('partials.footer')
@endsection
