<?php

namespace Database\Seeders;

use App\Models\EmailTemplate;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class AdditionalEmailTemplateSeeder extends Seeder
{
    /**
     * Run the database seeder.
     */
    public function run(): void
    {
        $templates = [
            // Educational Institutions Templates
            [
                'name' => 'Educational Institution - Student Support Enhancement',
                'subject' => 'Transform Student Experience with AI Support - {institution_name}',
                'content' => '<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
    <div style="text-align: center; margin-bottom: 30px;">
        <img src="https://images.unsplash.com/photo-1523050854058-8df90110c9f1?w=600&h=200&fit=crop&crop=center" alt="Students on Campus" style="width: 100%; height: 200px; object-fit: cover; border-radius: 8px;">
    </div>
    
    <h2 style="color: #1e40af;">Dear {contact_name},</h2>
    
    <p>Educational institutions worldwide are revolutionizing student support with AI-powered chatbots. <strong>AI Chat Support</strong> helps schools, colleges, and universities provide 24/7 student assistance while reducing administrative workload.</p>
    
    <div style="background: #f0f9ff; padding: 25px; border-radius: 8px; margin: 25px 0; border-left: 4px solid #1e40af;">
        <h3 style="color: #1e40af; margin-top: 0;">🎓 Transform Your Student Services:</h3>
        <ul style="color: #374151; margin: 0;">
            <li><strong>Admission Support:</strong> Guide prospective students through applications</li>
            <li><strong>Course Information:</strong> Instant access to schedules and requirements</li>
            <li><strong>Campus Services:</strong> Housing, dining, and facility information</li>
            <li><strong>Academic Assistance:</strong> Study resources and tutoring connections</li>
            <li><strong>Parent Communication:</strong> Keep families informed and engaged</li>
        </ul>
    </div>
    
    <div style="background: #ecfdf5; padding: 20px; border-radius: 5px; margin: 20px 0;">
        <h4 style="color: #059669; margin-top: 0;">📊 Real Results from Educational Institutions:</h4>
        <ul style="color: #374151; margin: 0;">
            <li>50% reduction in administrative calls</li>
            <li>85% student satisfaction with instant responses</li>
            <li>35% increase in enrollment completion rates</li>
            <li>24/7 availability for student support</li>
        </ul>
    </div>
    
    <div style="text-align: center; margin: 30px 0;">
        <a href="https://ai-chat.support/demo?industry=education" style="background: #1e40af; color: white; padding: 15px 30px; text-decoration: none; border-radius: 5px; font-weight: bold; font-size: 16px;">Request Education Demo</a>
    </div>
    
    <p>Ready to enhance your student support services? Let\'s discuss how AI Chat Support can be customized for {institution_name}\'s specific needs.</p>
    
    <p><strong>Schedule a free consultation:</strong><br>
    📧 Email: demo@ai-chat.support<br>
    🌐 Website: <a href="https://ai-chat.support">ai-chat.support</a><br>
    📞 Call: {contact_phone}</p>
    
    <p>Best regards,<br>
    {sender_name}<br>
    AI Chat Support Team</p>
    
    <hr style="margin: 30px 0;">
    <p style="font-size: 12px; color: #666; text-align: center;">
        <a href="https://ai-chat.support">AI Chat Support</a> - Transforming Education with AI<br>
        <a href="https://ai-chat.support/unsubscribe" style="color: #666;">Unsubscribe</a>
    </p>
</div>',
                'industry_type' => 'education',
                'variables' => ['institution_name', 'contact_name', 'sender_name', 'contact_phone'],
                'description' => 'Outreach template for educational institutions highlighting student support benefits'
            ],

            // Hospitals Templates
            [
                'name' => 'Hospital - Patient Experience Revolution',
                'subject' => 'Revolutionize Patient Care with 24/7 AI Support - {hospital_name}',
                'content' => '<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
    <div style="text-align: center; margin-bottom: 30px;">
        <img src="https://images.unsplash.com/photo-1551601651-2a8555f1a136?w=600&h=200&fit=crop&crop=center" alt="Modern Hospital" style="width: 100%; height: 200px; object-fit: cover; border-radius: 8px;">
    </div>
    
    <h2 style="color: #dc2626;">Dear {contact_name},</h2>
    
    <p>Healthcare providers are transforming patient experiences with AI-powered support systems. <strong>AI Chat Support</strong> helps hospitals deliver exceptional patient care while reducing administrative burden on your staff.</p>
    
    <div style="background: #fef2f2; padding: 25px; border-radius: 8px; margin: 25px 0; border-left: 4px solid #dc2626;">
        <h3 style="color: #dc2626; margin-top: 0;">🏥 Enhance Patient Care:</h3>
        <ul style="color: #374151; margin: 0;">
            <li><strong>24/7 Patient Support:</strong> Instant responses to common inquiries</li>
            <li><strong>Appointment Scheduling:</strong> Automated booking and reminders</li>
            <li><strong>Symptom Triage:</strong> Guide patients to appropriate care levels</li>
            <li><strong>Insurance & Billing:</strong> Answer payment and coverage questions</li>
            <li><strong>HIPAA Compliant:</strong> Secure, encrypted patient interactions</li>
        </ul>
    </div>
    
    <div style="background: #f0fdf4; padding: 20px; border-radius: 5px; margin: 20px 0;">
        <h4 style="color: #059669; margin-top: 0;">📈 Proven Healthcare Results:</h4>
        <ul style="color: #374151; margin: 0;">
            <li>40% reduction in phone call volume</li>
            <li>60% faster response times for patient inquiries</li>
            <li>25% increase in patient satisfaction scores</li>
            <li>30% improvement in appointment scheduling efficiency</li>
        </ul>
    </div>
    
    <div style="text-align: center; margin: 30px 0;">
        <a href="https://ai-chat.support/demo?industry=healthcare" style="background: #dc2626; color: white; padding: 15px 30px; text-decoration: none; border-radius: 5px; font-weight: bold; font-size: 16px;">Request Healthcare Demo</a>
    </div>
    
    <p>Ready to improve patient care while reducing administrative costs? Let\'s explore how AI Chat Support can be tailored for {hospital_name}\'s unique requirements.</p>
    
    <p><strong>Get started today:</strong><br>
    📧 Email: healthcare@ai-chat.support<br>
    🌐 Website: <a href="https://ai-chat.support">ai-chat.support</a><br>
    📞 Call: {contact_phone}</p>
    
    <p>Best regards,<br>
    {sender_name}<br>
    AI Chat Support Healthcare Team</p>
    
    <hr style="margin: 30px 0;">
    <p style="font-size: 12px; color: #666; text-align: center;">
        <a href="https://ai-chat.support">AI Chat Support</a> - Transforming Healthcare Communication<br>
        <a href="https://ai-chat.support/unsubscribe" style="color: #666;">Unsubscribe</a>
    </p>
</div>',
                'industry_type' => 'healthcare',
                'variables' => ['hospital_name', 'contact_name', 'sender_name', 'contact_phone'],
                'description' => 'Outreach template for hospitals emphasizing patient care and operational efficiency'
            ],

            // NGO Templates
            [
                'name' => 'NGO - Donor & Volunteer Engagement',
                'subject' => 'Amplify Your Mission Impact with AI-Powered Engagement - {organization_name}',
                'content' => '<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
    <div style="text-align: center; margin-bottom: 30px;">
        <img src="https://images.unsplash.com/photo-1559027615-cd4628902d4a?w=600&h=200&fit=crop&crop=center" alt="Community Support" style="width: 100%; height: 200px; object-fit: cover; border-radius: 8px;">
    </div>
    
    <h2 style="color: #059669;">Dear {contact_name},</h2>
    
    <p>Non-profit organizations are maximizing their impact with AI-powered supporter engagement. <strong>AI Chat Support</strong> helps NGOs connect with donors, volunteers, and beneficiaries more effectively while optimizing limited resources.</p>
    
    <div style="background: #f0fdf4; padding: 25px; border-radius: 8px; margin: 25px 0; border-left: 4px solid #059669;">
        <h3 style="color: #059669; margin-top: 0;">🤝 Strengthen Community Connections:</h3>
        <ul style="color: #374151; margin: 0;">
            <li><strong>Donor Support:</strong> Instant donation assistance and impact updates</li>
            <li><strong>Volunteer Coordination:</strong> Streamline recruitment and scheduling</li>
            <li><strong>Program Information:</strong> Share services and eligibility details</li>
            <li><strong>Event Management:</strong> Registration and information sharing</li>
            <li><strong>Multilingual Support:</strong> Serve diverse communities effectively</li>
        </ul>
    </div>
    
    <div style="background: #fffbeb; padding: 20px; border-radius: 5px; margin: 20px 0;">
        <h4 style="color: #d97706; margin-top: 0;">💡 Special Benefits for NGOs:</h4>
        <ul style="color: #374151; margin: 0;">
            <li>Maximize volunteer engagement with 24/7 availability</li>
            <li>Reduce administrative costs and redirect to programs</li>
            <li>Improve donor retention with instant communication</li>
            <li>Track community needs and program effectiveness</li>
        </ul>
    </div>
    
    <div style="background: #dbeafe; padding: 15px; border-radius: 5px; margin: 20px 0; text-align: center;">
        <p style="margin: 0; color: #1e40af; font-weight: bold;">🎁 Special NGO Pricing Available</p>
        <p style="margin: 5px 0 0 0; color: #374151; font-size: 14px;">Discounted rates for registered non-profit organizations</p>
    </div>
    
    <div style="text-align: center; margin: 30px 0;">
        <a href="https://ai-chat.support/demo?industry=nonprofit" style="background: #059669; color: white; padding: 15px 30px; text-decoration: none; border-radius: 5px; font-weight: bold; font-size: 16px;">Request NGO Demo</a>
    </div>
    
    <p>Ready to amplify your mission\'s impact? Let\'s discuss how AI Chat Support can help {organization_name} engage more supporters and serve your community better.</p>
    
    <p><strong>Connect with us:</strong><br>
    📧 Email: nonprofit@ai-chat.support<br>
    🌐 Website: <a href="https://ai-chat.support">ai-chat.support</a><br>
    📞 Call: {contact_phone}</p>
    
    <p>Together for impact,<br>
    {sender_name}<br>
    AI Chat Support Community Team</p>
    
    <hr style="margin: 30px 0;">
    <p style="font-size: 12px; color: #666; text-align: center;">
        <a href="https://ai-chat.support">AI Chat Support</a> - Empowering Organizations to Change the World<br>
        <a href="https://ai-chat.support/unsubscribe" style="color: #666;">Unsubscribe</a>
    </p>
</div>',
                'industry_type' => 'nonprofit',
                'variables' => ['organization_name', 'contact_name', 'sender_name', 'contact_phone'],
                'description' => 'Outreach template for NGOs focusing on donor and volunteer engagement'
            ],

            // Car Dealers Templates
            [
                'name' => 'Car Dealership - Sales Acceleration',
                'subject' => 'Accelerate Auto Sales with 24/7 AI Sales Assistant - {dealership_name}',
                'content' => '<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
    <div style="text-align: center; margin-bottom: 30px;">
        <img src="https://images.unsplash.com/photo-1492144534655-ae79c964c9d7?w=600&h=200&fit=crop&crop=center" alt="Modern Car Dealership" style="width: 100%; height: 200px; object-fit: cover; border-radius: 8px;">
    </div>
    
    <h2 style="color: #dc2626;">Dear {contact_name},</h2>
    
    <p>Automotive dealerships are accelerating sales with AI-powered customer engagement. <strong>AI Chat Support</strong> helps car dealers capture leads, qualify prospects, and close more deals by providing instant vehicle information and scheduling support.</p>
    
    <div style="background: #fef2f2; padding: 25px; border-radius: 8px; margin: 25px 0; border-left: 4px solid #dc2626;">
        <h3 style="color: #dc2626; margin-top: 0;">🚗 Drive More Sales:</h3>
        <ul style="color: #374151; margin: 0;">
            <li><strong>Vehicle Information:</strong> Instant specs, pricing, and availability</li>
            <li><strong>Lead Qualification:</strong> Identify serious buyers automatically</li>
            <li><strong>Test Drive Scheduling:</strong> Book appointments 24/7</li>
            <li><strong>Financing Support:</strong> Pre-qualify and explain options</li>
            <li><strong>Service Bookings:</strong> Maintenance and repair scheduling</li>
        </ul>
    </div>
    
    <div style="background: #f0fdf4; padding: 20px; border-radius: 5px; margin: 20px 0;">
        <h4 style="color: #059669; margin-top: 0;">🏆 Automotive Success Metrics:</h4>
        <ul style="color: #374151; margin: 0;">
            <li>65% increase in qualified leads</li>
            <li>40% more test drive appointments</li>
            <li>50% reduction in response time</li>
            <li>30% improvement in customer satisfaction</li>
            <li>25% increase in service bookings</li>
        </ul>
    </div>
    
    <div style="background: #fffbeb; padding: 15px; border-radius: 5px; margin: 20px 0; text-align: center;">
        <p style="margin: 0; color: #d97706; font-weight: bold;">🎯 Never Miss a Lead Again</p>
        <p style="margin: 5px 0 0 0; color: #374151; font-size: 14px;">Capture prospects outside business hours and weekend shoppers</p>
    </div>
    
    <div style="text-align: center; margin: 30px 0;">
        <a href="https://ai-chat.support/demo?industry=automotive" style="background: #dc2626; color: white; padding: 15px 30px; text-decoration: none; border-radius: 5px; font-weight: bold; font-size: 16px;">Request Auto Demo</a>
    </div>
    
    <p>Ready to accelerate your sales performance? Let\'s explore how AI Chat Support can help {dealership_name} capture more leads and close more deals.</p>
    
    <p><strong>Schedule your demo:</strong><br>
    📧 Email: automotive@ai-chat.support<br>
    🌐 Website: <a href="https://ai-chat.support">ai-chat.support</a><br>
    📞 Call: {contact_phone}</p>
    
    <p>Drive forward,<br>
    {sender_name}<br>
    AI Chat Support Automotive Team</p>
    
    <hr style="margin: 30px 0;">
    <p style="font-size: 12px; color: #666; text-align: center;">
        <a href="https://ai-chat.support">AI Chat Support</a> - Accelerating Automotive Success<br>
        <a href="https://ai-chat.support/unsubscribe" style="color: #666;">Unsubscribe</a>
    </p>
</div>',
                'industry_type' => 'automotive',
                'variables' => ['dealership_name', 'contact_name', 'sender_name', 'contact_phone'],
                'description' => 'Outreach template for car dealerships focusing on lead generation and sales acceleration'
            ],

            // E-commerce Templates
            [
                'name' => 'E-commerce - Conversion Optimization',
                'subject' => 'Boost E-commerce Sales by 35% with AI Shopping Assistant - {store_name}',
                'content' => '<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
    <div style="text-align: center; margin-bottom: 30px;">
        <img src="https://images.unsplash.com/photo-1556742049-0cfed4f6a45d?w=600&h=200&fit=crop&crop=center" alt="E-commerce Shopping" style="width: 100%; height: 200px; object-fit: cover; border-radius: 8px;">
    </div>
    
    <h2 style="color: #7c3aed;">Dear {contact_name},</h2>
    
    <p>E-commerce retailers are boosting conversions with AI-powered shopping assistants. <strong>AI Chat Support</strong> helps online stores reduce cart abandonment, increase average order values, and provide exceptional customer service that drives repeat purchases.</p>
    
    <div style="background: #faf5ff; padding: 25px; border-radius: 8px; margin: 25px 0; border-left: 4px solid #7c3aed;">
        <h3 style="color: #7c3aed; margin-top: 0;">🛒 Optimize Your Online Store:</h3>
        <ul style="color: #374151; margin: 0;">
            <li><strong>Smart Product Discovery:</strong> Help customers find perfect products</li>
            <li><strong>Cart Recovery:</strong> Reduce abandonment with instant assistance</li>
            <li><strong>Order Tracking:</strong> Real-time shipping and delivery updates</li>
            <li><strong>Return Support:</strong> Streamline returns and exchanges</li>
            <li><strong>Personalized Recommendations:</strong> Increase average order value</li>
        </ul>
    </div>
    
    <div style="background: #ecfdf5; padding: 20px; border-radius: 5px; margin: 20px 0;">
        <h4 style="color: #059669; margin-top: 0;">📊 E-commerce Performance Boost:</h4>
        <ul style="color: #374151; margin: 0;">
            <li>35% increase in conversion rates</li>
            <li>40% decrease in cart abandonment</li>
            <li>25% increase in average order value</li>
            <li>60% improvement in customer satisfaction</li>
            <li>50% reduction in support tickets</li>
        </ul>
    </div>
    
    <div style="background: #fef3c7; padding: 15px; border-radius: 5px; margin: 20px 0; text-align: center;">
        <p style="margin: 0; color: #d97706; font-weight: bold;">⚡ Quick Implementation</p>
        <p style="margin: 5px 0 0 0; color: #374151; font-size: 14px;">Integrate with Shopify, WooCommerce, Magento, and more</p>
    </div>
    
    <div style="text-align: center; margin: 30px 0;">
        <a href="https://ai-chat.support/demo?industry=ecommerce" style="background: #7c3aed; color: white; padding: 15px 30px; text-decoration: none; border-radius: 5px; font-weight: bold; font-size: 16px;">Request E-commerce Demo</a>
    </div>
    
    <p>Ready to transform your online shopping experience? Let\'s show you how AI Chat Support can help {store_name} increase sales and customer satisfaction.</p>
    
    <p><strong>Get started now:</strong><br>
    📧 Email: ecommerce@ai-chat.support<br>
    🌐 Website: <a href="https://ai-chat.support">ai-chat.support</a><br>
    📞 Call: {contact_phone}</p>
    
    <p>Happy selling,<br>
    {sender_name}<br>
    AI Chat Support E-commerce Team</p>
    
    <hr style="margin: 30px 0;">
    <p style="font-size: 12px; color: #666; text-align: center;">
        <a href="https://ai-chat.support">AI Chat Support</a> - Powering E-commerce Success<br>
        <a href="https://ai-chat.support/unsubscribe" style="color: #666;">Unsubscribe</a>
    </p>
</div>',
                'industry_type' => 'ecommerce',
                'variables' => ['store_name', 'contact_name', 'sender_name', 'contact_phone'],
                'description' => 'Outreach template for e-commerce websites focusing on conversion optimization and sales growth'
            ],

            // Manufacturing Templates
            [
                'name' => 'Manufacturing - B2B Customer Support',
                'subject' => 'Streamline B2B Customer Support for Manufacturing - {company_name}',
                'content' => '<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
    <div style="text-align: center; margin-bottom: 30px;">
        <img src="https://images.unsplash.com/photo-1565793298595-6a879b1d9492?w=600&h=200&fit=crop&crop=center" alt="Manufacturing Facility" style="width: 100%; height: 200px; object-fit: cover; border-radius: 8px;">
    </div>
    
    <h2 style="color: #374151;">Dear {contact_name},</h2>
    
    <p>Manufacturing companies are enhancing B2B customer relationships with AI-powered support systems. <strong>AI Chat Support</strong> helps manufacturers provide instant technical assistance, order support, and product information to business clients worldwide.</p>
    
    <div style="background: #f9fafb; padding: 25px; border-radius: 8px; margin: 25px 0; border-left: 4px solid #374151;">
        <h3 style="color: #374151; margin-top: 0;">🏭 Manufacturing Excellence:</h3>
        <ul style="color: #374151; margin: 0;">
            <li><strong>Technical Documentation:</strong> Instant access to specs and manuals</li>
            <li><strong>Order Management:</strong> Status updates and delivery tracking</li>
            <li><strong>Quality Assurance:</strong> Product certifications and compliance info</li>
            <li><strong>International Support:</strong> Multi-language customer assistance</li>
            <li><strong>Lead Qualification:</strong> Identify high-value prospects</li>
        </ul>
    </div>
    
    <div style="background: #dbeafe; padding: 20px; border-radius: 5px; margin: 20px 0;">
        <h4 style="color: #1e40af; margin-top: 0;">🎯 B2B Benefits:</h4>
        <ul style="color: #374151; margin: 0;">
            <li>Serve global clients across all time zones</li>
            <li>Reduce technical support response times</li>
            <li>Improve customer satisfaction and retention</li>
            <li>Streamline complex B2B sales processes</li>
        </ul>
    </div>
    
    <div style="text-align: center; margin: 30px 0;">
        <a href="https://ai-chat.support/demo?industry=manufacturing" style="background: #374151; color: white; padding: 15px 30px; text-decoration: none; border-radius: 5px; font-weight: bold; font-size: 16px;">Request Manufacturing Demo</a>
    </div>
    
    <p>Ready to enhance your B2B customer relationships? Let\'s explore how AI Chat Support can help {company_name} serve clients more efficiently.</p>
    
    <p><strong>Connect with us:</strong><br>
    📧 Email: manufacturing@ai-chat.support<br>
    🌐 Website: <a href="https://ai-chat.support">ai-chat.support</a><br>
    📞 Call: {contact_phone}</p>
    
    <p>Best regards,<br>
    {sender_name}<br>
    AI Chat Support Industrial Team</p>
    
    <hr style="margin: 30px 0;">
    <p style="font-size: 12px; color: #666; text-align: center;">
        <a href="https://ai-chat.support">AI Chat Support</a> - Supporting Manufacturing Excellence<br>
        <a href="https://ai-chat.support/unsubscribe" style="color: #666;">Unsubscribe</a>
    </p>
</div>',
                'industry_type' => 'manufacturing',
                'variables' => ['company_name', 'contact_name', 'sender_name', 'contact_phone'],
                'description' => 'Outreach template for manufacturing companies focusing on B2B customer support and technical assistance'
            ],

            // Legal Services Templates
            [
                'name' => 'Legal Firm - Client Intake & Support',
                'subject' => 'Modernize Legal Client Services with AI Support - {firm_name}',
                'content' => '<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
    <div style="text-align: center; margin-bottom: 30px;">
        <img src="https://images.unsplash.com/photo-1589829545856-d10d557cf95f?w=600&h=200&fit=crop&crop=center" alt="Legal Office" style="width: 100%; height: 200px; object-fit: cover; border-radius: 8px;">
    </div>
    
    <h2 style="color: #1f2937;">Dear {contact_name},</h2>
    
    <p>Legal firms are modernizing client services with AI-powered support systems. <strong>AI Chat Support</strong> helps law practices streamline client intake, provide instant case information, and deliver exceptional client service while maintaining confidentiality and professionalism.</p>
    
    <div style="background: #f9fafb; padding: 25px; border-radius: 8px; margin: 25px 0; border-left: 4px solid #1f2937;">
        <h3 style="color: #1f2937; margin-top: 0;">⚖️ Legal Practice Enhancement:</h3>
        <ul style="color: #374151; margin: 0;">
            <li><strong>Client Intake:</strong> Automate initial consultations and screening</li>
            <li><strong>Case Updates:</strong> Provide secure status information</li>
            <li><strong>Document Requests:</strong> Guide clients through required paperwork</li>
            <li><strong>Appointment Scheduling:</strong> Coordinate meetings with attorneys</li>
            <li><strong>Billing Inquiries:</strong> Answer payment and invoice questions</li>
        </ul>
    </div>
    
    <div style="background: #fef2f2; padding: 20px; border-radius: 5px; margin: 20px 0;">
        <h4 style="color: #dc2626; margin-top: 0;">🔒 Security & Compliance:</h4>
        <ul style="color: #374151; margin: 0;">
            <li>Attorney-client privilege protection</li>
            <li>Secure encrypted communications</li>
            <li>Compliance with legal industry standards</li>
            <li>Professional tone and language</li>
        </ul>
    </div>
    
    <div style="text-align: center; margin: 30px 0;">
        <a href="https://ai-chat.support/demo?industry=legal" style="background: #1f2937; color: white; padding: 15px 30px; text-decoration: none; border-radius: 5px; font-weight: bold; font-size: 16px;">Request Legal Demo</a>
    </div>
    
    <p>Ready to enhance your legal practice\'s client services? Let\'s discuss how AI Chat Support can help {firm_name} improve client satisfaction while maintaining the highest professional standards.</p>
    
    <p><strong>Schedule consultation:</strong><br>
    📧 Email: legal@ai-chat.support<br>
    🌐 Website: <a href="https://ai-chat.support">ai-chat.support</a><br>
    📞 Call: {contact_phone}</p>
    
    <p>Respectfully,<br>
    {sender_name}<br>
    AI Chat Support Legal Services Team</p>
    
    <hr style="margin: 30px 0;">
    <p style="font-size: 12px; color: #666; text-align: center;">
        <a href="https://ai-chat.support">AI Chat Support</a> - Modernizing Legal Client Services<br>
        <a href="https://ai-chat.support/unsubscribe" style="color: #666;">Unsubscribe</a>
    </p>
</div>',
                'industry_type' => 'legal',
                'variables' => ['firm_name', 'contact_name', 'sender_name', 'contact_phone'],
                'description' => 'Outreach template for legal firms focusing on client intake and professional service delivery'
            ],

            // Hotel Templates
            [
                'name' => 'Hotel - Guest Experience Enhancement',
                'subject' => 'Elevate Guest Experience with 24/7 AI Concierge - {hotel_name}',
                'content' => '<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
    <div style="text-align: center; margin-bottom: 30px;">
        <img src="https://images.unsplash.com/photo-1566073771259-6a8506099945?w=600&h=200&fit=crop&crop=center" alt="Luxury Hotel Lobby" style="width: 100%; height: 200px; object-fit: cover; border-radius: 8px;">
    </div>
    
    <h2 style="color: #d97706;">Dear {contact_name},</h2>
    
    <p>Hotels worldwide are revolutionizing guest services with AI-powered concierge assistants. <strong>AI Chat Support</strong> helps hotels provide exceptional 24/7 guest support while reducing front desk workload and increasing guest satisfaction.</p>
    
    <div style="background: #fffbeb; padding: 25px; border-radius: 8px; margin: 25px 0; border-left: 4px solid #d97706;">
        <h3 style="color: #d97706; margin-top: 0;">🏨 Transform Guest Services:</h3>
        <ul style="color: #374151; margin: 0;">
            <li><strong>Room Reservations:</strong> Instant booking and availability checks</li>
            <li><strong>Concierge Services:</strong> Local recommendations and directions</li>
            <li><strong>Amenities Information:</strong> Pool hours, spa services, dining options</li>
            <li><strong>Room Service:</strong> Order food, request housekeeping, report issues</li>
            <li><strong>Guest Communications:</strong> Multilingual support for international guests</li>
        </ul>
    </div>
    
    <div style="background: #f0fdf4; padding: 20px; border-radius: 5px; margin: 20px 0;">
        <h4 style="color: #059669; margin-top: 0;">📈 Hospitality Success Metrics:</h4>
        <ul style="color: #374151; margin: 0;">
            <li>45% increase in guest satisfaction scores</li>
            <li>60% reduction in front desk call volume</li>
            <li>35% increase in ancillary service bookings</li>
            <li>50% faster response to guest requests</li>
            <li>24/7 multilingual guest support</li>
        </ul>
    </div>
    
    <div style="background: #dbeafe; padding: 15px; border-radius: 5px; margin: 20px 0; text-align: center;">
        <p style="margin: 0; color: #1e40af; font-weight: bold;">🌟 Perfect for Resort Hotels & Boutique Properties</p>
        <p style="margin: 5px 0 0 0; color: #374151; font-size: 14px;">Enhance guest experience from check-in to check-out</p>
    </div>
    
    <div style="text-align: center; margin: 30px 0;">
        <a href="https://ai-chat.support/demo?industry=hospitality" style="background: #d97706; color: white; padding: 15px 30px; text-decoration: none; border-radius: 5px; font-weight: bold; font-size: 16px;">Request Hotel Demo</a>
    </div>
    
    <p>Ready to enhance your guest experience? Let\'s explore how AI Chat Support can help {hotel_name} deliver world-class hospitality services.</p>
    
    <p><strong>Book a demo today:</strong><br>
    📧 Email: hospitality@ai-chat.support<br>
    🌐 Website: <a href="https://ai-chat.support">ai-chat.support</a><br>
    📞 Call: {contact_phone}</p>
    
    <p>Best regards,<br>
    {sender_name}<br>
    AI Chat Support Hospitality Team</p>
    
    <hr style="margin: 30px 0;">
    <p style="font-size: 12px; color: #666; text-align: center;">
        <a href="https://ai-chat.support">AI Chat Support</a> - Elevating Hospitality Excellence<br>
        <a href="https://ai-chat.support/unsubscribe" style="color: #666;">Unsubscribe</a>
    </p>
</div>',
                'industry_type' => 'hospitality',
                'variables' => ['hotel_name', 'contact_name', 'sender_name', 'contact_phone'],
                'description' => 'Outreach template for hotels focusing on guest experience and concierge services'
            ],

            // Real Estate Templates
            [
                'name' => 'Real Estate - Property Sales Acceleration',
                'subject' => 'Accelerate Property Sales with AI Assistant - {agency_name}',
                'content' => '<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
    <div style="text-align: center; margin-bottom: 30px;">
        <img src="https://images.unsplash.com/photo-1560518883-ce09059eeffa?w=600&h=200&fit=crop&crop=center" alt="Modern Real Estate" style="width: 100%; height: 200px; object-fit: cover; border-radius: 8px;">
    </div>
    
    <h2 style="color: #059669;">Dear {contact_name},</h2>
    
    <p>Real estate agencies are accelerating sales with AI-powered property assistants. <strong>AI Chat Support</strong> helps real estate professionals capture leads, qualify prospects, and provide instant property information 24/7.</p>
    
    <div style="background: #f0fdf4; padding: 25px; border-radius: 8px; margin: 25px 0; border-left: 4px solid #059669;">
        <h3 style="color: #059669; margin-top: 0;">🏠 Boost Property Sales:</h3>
        <ul style="color: #374151; margin: 0;">
            <li><strong>Property Search:</strong> Instant listings based on buyer criteria</li>
            <li><strong>Viewing Appointments:</strong> Schedule showings automatically</li>
            <li><strong>Market Analysis:</strong> Provide comparative market data</li>
            <li><strong>Mortgage Guidance:</strong> Connect with financing options</li>
            <li><strong>Neighborhood Info:</strong> Schools, amenities, local insights</li>
        </ul>
    </div>
    
    <div style="background: #dbeafe; padding: 20px; border-radius: 5px; margin: 20px 0;">
        <h4 style="color: #1e40af; margin-top: 0;">🏆 Real Estate Success Results:</h4>
        <ul style="color: #374151; margin: 0;">
            <li>70% increase in qualified leads</li>
            <li>45% more property viewings scheduled</li>
            <li>30% faster sales cycle completion</li>
            <li>60% improvement in client response time</li>
            <li>25% increase in listing inquiries</li>
        </ul>
    </div>
    
    <div style="background: #fef3c7; padding: 15px; border-radius: 5px; margin: 20px 0; text-align: center;">
        <p style="margin: 0; color: #d97706; font-weight: bold;">💰 Perfect for Residential & Commercial Properties</p>
        <p style="margin: 5px 0 0 0; color: #374151; font-size: 14px;">Capture leads outside business hours and weekend shoppers</p>
    </div>
    
    <div style="text-align: center; margin: 30px 0;">
        <a href="https://ai-chat.support/demo?industry=realestate" style="background: #059669; color: white; padding: 15px 30px; text-decoration: none; border-radius: 5px; font-weight: bold; font-size: 16px;">Request Real Estate Demo</a>
    </div>
    
    <p>Ready to accelerate your property sales? Let\'s show you how AI Chat Support can help {agency_name} convert more leads and close more deals.</p>
    
    <p><strong>Schedule your consultation:</strong><br>
    📧 Email: realestate@ai-chat.support<br>
    🌐 Website: <a href="https://ai-chat.support">ai-chat.support</a><br>
    📞 Call: {contact_phone}</p>
    
    <p>Building success together,<br>
    {sender_name}<br>
    AI Chat Support Real Estate Team</p>
    
    <hr style="margin: 30px 0;">
    <p style="font-size: 12px; color: #666; text-align: center;">
        <a href="https://ai-chat.support">AI Chat Support</a> - Powering Real Estate Success<br>
        <a href="https://ai-chat.support/unsubscribe" style="color: #666;">Unsubscribe</a>
    </p>
</div>',
                'industry_type' => 'realestate',
                'variables' => ['agency_name', 'contact_name', 'sender_name', 'contact_phone'],
                'description' => 'Outreach template for real estate agencies focusing on lead generation and property sales'
            ],
        ];

        foreach ($templates as $template) {
            EmailTemplate::updateOrCreate(
                [
                    'name' => $template['name'],
                    'industry_type' => $template['industry_type'],
                ],
                $template
            );
        }
    }
}