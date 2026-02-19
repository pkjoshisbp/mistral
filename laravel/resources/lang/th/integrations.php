<?php
return [
    'page_title' => 'การเชื่อมต่อ',
    'page_subtitle' => 'การเชื่อมต่อที่ง่ายดายกับแพลตฟอร์มที่คุณชื่นชอบ ติดตั้งระบบสนับสนุนแชท AI ของเราบน WordPress, WooCommerce, Magento และ Shopify ด้วยการคลิกเพียงไม่กี่ครั้ง',
    
    'description' => 'คำอธิบาย',
    'features' => 'คุณสมบัติ:',
    'requirements' => 'ความต้องการ:',
    
    'download_button' => 'ดาวน์โหลดปลั๊กอิน :platform',
    'download_size' => ':size',
    'install_button' => 'ติดตั้งแอป :platform',
    
    'wordpress_note' => '<strong>หมายเหตุ:</strong> คุณยังสามารถหาปลั๊กอินนี้ได้ในที่เก็บข้อมูลอย่างเป็นทางการของ WordPress.org',
    'magento_note' => '<strong>หมายเหตุ:</strong> อัปโหลดเนื้อหา ZIP ไปยังไดเรกทอรีรูทของ Magento',
    'magento_composer_button' => 'ดาวน์โหลดแพ็คเกจ Composer',
    'shopify_note' => '<strong>หมายเหตุ:</strong> มีให้บริการใน Shopify App Store สำหรับการค้นหาที่ง่ายดาย',
    
    'installation_guide' => 'คู่มือการติดตั้ง',
    'step' => 'ขั้นตอนที่ :number',
    
    'wordpress_install_steps' => [
        'ดาวน์โหลดไฟล์ ZIP ของปลั๊กอิน',
        'ไปที่ผู้ดูแลระบบ WordPress → ปลั๊กอิน → เพิ่มใหม่',
        'คลิก "อัปโหลดปลั๊กอิน" และเลือก ZIP ที่ดาวน์โหลด',
        'เปิดใช้งานปลั๊กอิน',
        'กำหนดค่าการตั้งค่า API ของคุณในการตั้งค่า → AI Chat Support'
    ],
    
    'magento_install_steps' => [
        'ดาวน์โหลดไฟล์ ZIP ส่วนขยาย',
        'แตกไฟล์เนื้อหาไปยังไดเรกทอรีรูทของ Magento',
        'รัน: php bin/magento setup:upgrade',
        'รัน: php bin/magento cache:flush',
        'กำหนดค่าในร้านค้า → การกำหนดค่า → AI Chat Support'
    ],
    
    'shopify_install_steps' => [
        'คลิกปุ่ม "ติดตั้งแอป Shopify" ด้านบน',
        'อนุญาตแอปในผู้ดูแลระบบ Shopify ของคุณ',
        'กำหนดค่า slug องค์กรและการตั้งค่าของคุณ',
        'วิดเจ็ตแชทจะปรากฏโดยอัตโนมัติในร้านค้าของคุณ'
    ],
];
