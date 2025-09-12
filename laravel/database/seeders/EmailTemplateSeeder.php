<?php

namespace Database\Seeders;

use App\Models\EmailTemplate;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class EmailTemplateSeeder extends Seeder
{
    /**
     * Run the database seeder.
     */
    public function run(): void
    {
        $templates = [
            // Healthcare Templates
            [
                'name' => 'Health Checkup Reminder',
                'subject' => 'Time for Your Regular Health Checkup - {company_name}',
                'content' => '<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
    <h2 style="color: #2c5aa0;">Dear {recipient_name},</h2>
    
    <p>We hope this message finds you in good health. This is a friendly reminder that it\'s time for your regular health checkup.</p>
    
    <div style="background: #f8f9fa; padding: 20px; border-radius: 5px; margin: 20px 0;">
        <h3 style="color: #28a745;">Why Regular Checkups Matter:</h3>
        <ul>
            <li>Early detection of health issues</li>
            <li>Monitoring of chronic conditions</li>
            <li>Preventive care recommendations</li>
            <li>Peace of mind for you and your family</li>
        </ul>
    </div>
    
    <p><strong>To schedule your appointment:</strong></p>
    <ul>
        <li>Call us at {phone_number}</li>
        <li>Visit our website at {website_url}</li>
        <li>Email us at {contact_email}</li>
    </ul>
    
    <p>Best regards,<br>The {company_name} Team</p>
    
    <hr style="margin: 30px 0;">
    <p style="font-size: 12px; color: #666;">
        {company_name}<br>
        {company_address}<br>
        If you no longer wish to receive these emails, please contact us.
    </p>
</div>',
                'industry_type' => 'healthcare',
                'variables' => ['recipient_name', 'company_name', 'phone_number', 'website_url', 'contact_email', 'company_address'],
                'description' => 'Reminder template for regular health checkups and screenings'
            ],
            
            // Technology Templates
            [
                'name' => 'Product Launch Announcement',
                'subject' => 'Introducing {product_name} - Revolutionary Innovation from {company_name}',
                'content' => '<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
    <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 40px 20px; text-align: center;">
        <h1 style="margin: 0; font-size: 28px;">🚀 {product_name} is Here!</h1>
        <p style="margin: 10px 0 0 0; font-size: 18px;">The future of {industry_focus} starts now</p>
    </div>
    
    <div style="padding: 30px 20px;">
        <h2 style="color: #333;">Hello {recipient_name},</h2>
        
        <p>We\'re thrilled to announce the launch of {product_name}, our latest innovation that will transform how you {primary_benefit}.</p>
        
        <div style="background: #f8f9fa; padding: 25px; border-radius: 8px; margin: 25px 0;">
            <h3 style="color: #495057; margin-top: 0;">Key Features:</h3>
            <ul style="color: #6c757d;">
                <li>{feature_1}</li>
                <li>{feature_2}</li>
                <li>{feature_3}</li>
                <li>And much more!</li>
            </ul>
        </div>
        
        <div style="text-align: center; margin: 30px 0;">
            <a href="{product_url}" style="background: #667eea; color: white; padding: 15px 30px; text-decoration: none; border-radius: 5px; font-weight: bold;">Explore {product_name}</a>
        </div>
        
        <p><strong>Special Launch Offer:</strong> {special_offer}</p>
        
        <p>Ready to get started? Visit {website_url} or contact our team at {contact_email}.</p>
        
        <p>Best regards,<br>The {company_name} Team</p>
    </div>
    
    <div style="background: #f8f9fa; padding: 20px; text-align: center; font-size: 12px; color: #666;">
        {company_name} | {company_address}<br>
        <a href="{unsubscribe_url}" style="color: #666;">Unsubscribe</a>
    </div>
</div>',
                'industry_type' => 'technology',
                'variables' => ['recipient_name', 'company_name', 'product_name', 'industry_focus', 'primary_benefit', 'feature_1', 'feature_2', 'feature_3', 'product_url', 'special_offer', 'website_url', 'contact_email', 'company_address', 'unsubscribe_url'],
                'description' => 'Announcement template for new product launches'
            ],

            // Retail Templates
            [
                'name' => 'Seasonal Sale Promotion',
                'subject' => '🔥 {sale_percentage}% OFF Everything - {sale_name} at {company_name}',
                'content' => '<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
    <div style="background: #dc3545; color: white; text-align: center; padding: 20px;">
        <h1 style="margin: 0; font-size: 32px;">{sale_name}</h1>
        <h2 style="margin: 10px 0; font-size: 24px;">Save {sale_percentage}% on Everything!</h2>
        <p style="margin: 0; font-size: 16px;">Limited Time Only - Ends {sale_end_date}</p>
    </div>
    
    <div style="padding: 30px 20px;">
        <h2 style="color: #333;">Hi {recipient_name}! 👋</h2>
        
        <p>Our biggest sale of the season is here! Don\'t miss out on incredible savings across our entire collection.</p>
        
        <div style="background: #fff3cd; border: 1px solid #ffeaa7; padding: 20px; border-radius: 5px; margin: 20px 0;">
            <h3 style="color: #856404; margin-top: 0;">🎯 Sale Highlights:</h3>
            <ul style="color: #856404;">
                <li><strong>{category_1}:</strong> Up to {category_1_discount}% off</li>
                <li><strong>{category_2}:</strong> Up to {category_2_discount}% off</li>
                <li><strong>{category_3}:</strong> Up to {category_3_discount}% off</li>
                <li><strong>Free shipping</strong> on orders over {free_shipping_minimum}</li>
            </ul>
        </div>
        
        <div style="text-align: center; margin: 30px 0;">
            <a href="{shop_url}" style="background: #dc3545; color: white; padding: 15px 30px; text-decoration: none; border-radius: 5px; font-weight: bold; font-size: 16px;">SHOP NOW</a>
        </div>
        
        <p style="text-align: center; color: #666;"><strong>Use code:</strong> <span style="background: #f8f9fa; padding: 5px 10px; border-radius: 3px; font-family: monospace;">{promo_code}</span></p>
        
        <p>Hurry! This amazing offer ends on {sale_end_date}. Shop now and save big!</p>
        
        <p>Happy shopping!<br>The {company_name} Team</p>
    </div>
    
    <div style="background: #f8f9fa; padding: 20px; text-align: center; font-size: 12px; color: #666;">
        {company_name} | {company_address}<br>
        <a href="{unsubscribe_url}" style="color: #666;">Unsubscribe</a> | <a href="{website_url}" style="color: #666;">Visit Website</a>
    </div>
</div>',
                'industry_type' => 'retail',
                'variables' => ['recipient_name', 'company_name', 'sale_name', 'sale_percentage', 'sale_end_date', 'category_1', 'category_1_discount', 'category_2', 'category_2_discount', 'category_3', 'category_3_discount', 'free_shipping_minimum', 'shop_url', 'promo_code', 'company_address', 'unsubscribe_url', 'website_url'],
                'description' => 'Promotional template for seasonal sales and discounts'
            ],

            // General Business Templates
            [
                'name' => 'Welcome New Customer',
                'subject' => 'Welcome to {company_name} - We\'re excited to have you!',
                'content' => '<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
    <div style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%); color: white; text-align: center; padding: 40px 20px;">
        <h1 style="margin: 0; font-size: 28px;">Welcome to {company_name}! 🎉</h1>
        <p style="margin: 15px 0 0 0; font-size: 16px;">We\'re thrilled to have you join our community</p>
    </div>
    
    <div style="padding: 30px 20px;">
        <h2 style="color: #333;">Hello {recipient_name},</h2>
        
        <p>Welcome to the {company_name} family! We\'re excited to begin this journey with you and help you {primary_benefit}.</p>
        
        <div style="background: #f8f9fa; padding: 25px; border-radius: 8px; margin: 25px 0;">
            <h3 style="color: #495057; margin-top: 0;">🚀 Getting Started:</h3>
            <ol style="color: #6c757d;">
                <li><strong>Access your account:</strong> {login_url}</li>
                <li><strong>Complete your profile:</strong> Add your preferences</li>
                <li><strong>Explore our features:</strong> {features_overview}</li>
                <li><strong>Join our community:</strong> {community_link}</li>
            </ol>
        </div>
        
        <p><strong>Need help getting started?</strong> Our support team is here for you:</p>
        <ul>
            <li>📧 Email: {support_email}</li>
            <li>📞 Phone: {support_phone}</li>
            <li>💬 Live Chat: Available on our website</li>
            <li>📚 Help Center: {help_center_url}</li>
        </ul>
        
        <div style="text-align: center; margin: 30px 0;">
            <a href="{get_started_url}" style="background: #28a745; color: white; padding: 15px 30px; text-decoration: none; border-radius: 5px; font-weight: bold;">Get Started Now</a>
        </div>
        
        <p>Once again, welcome to {company_name}! We can\'t wait to see what you\'ll achieve.</p>
        
        <p>Best regards,<br>The {company_name} Team</p>
    </div>
    
    <div style="background: #f8f9fa; padding: 20px; text-align: center; font-size: 12px; color: #666;">
        {company_name} | {company_address}<br>
        <a href="{website_url}" style="color: #666;">Visit Website</a> | <a href="{social_media}" style="color: #666;">Follow Us</a>
    </div>
</div>',
                'industry_type' => 'general',
                'variables' => ['recipient_name', 'company_name', 'primary_benefit', 'login_url', 'features_overview', 'community_link', 'support_email', 'support_phone', 'help_center_url', 'get_started_url', 'company_address', 'website_url', 'social_media'],
                'description' => 'Welcome template for new customers or users'
            ],
        ];

        foreach ($templates as $template) {
            EmailTemplate::create($template);
        }
    }
}
