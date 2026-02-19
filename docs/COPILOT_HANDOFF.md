# Copilot Handoff

Use this file when switching devices (Windows ↔ MacBook) so a new Copilot chat can continue with full context.

## 1) Quick Start Prompt (copy/paste first)

I am continuing work on ai-chat.support multi-tenant AI support system.
Please read docs/COPILOT_HANDOFF.md first, then continue from "Current Focus" and "Open Tasks".
Follow project instructions in .github/instructions/ai-agent-project.instructions.md and .github/copilot-instructions.md.
Use Laravel + Livewire for frontend interactions, FastAPI for AI backend, Qdrant for vectors.

## 2) Environment Snapshot

- Server: Ubuntu 22.04 (remote workspace)
- Main URL for testing: https://ai-chat.support
- Laravel app path: /var/www/clients/client1/web64/web/laravel
- FastAPI internal URL: http://localhost:8111
- Qdrant URL: http://localhost:6333
- FastAPI service: ai-fastapi.service

## 3) Credentials (for testing)

- Admin: admin@example.com / password123
- Customer: customer@ai-chat.support / 4NAWhgQ5PskpQ2b

## 4) Current Focus

- CSV-first data ingestion is active (Google Sheets not required for Odyssey flow).
- Global importer command is available:
  - php artisan orgdata:import-csv {organization} {file} [options]
- Added Admin and Customer CSV import pages.
- Added widget setting for mandatory contact capture with email/phone regex validation.
- Added cross-device resume support for website widget chats by contact info (email/phone).
- Added/updated Odyssey model and FAQ data for Grand Vitara vs eVITARA separation.

## 5) Important Commands

### CSV import + Qdrant sync

php artisan orgdata:import-csv odyssey-motors ../sample_files/odyssey_motors_sheet_import/models_master.csv --dataset=models_master --type=pricing --qdrant-type=info --key-columns=make,brand_channel,model,variant,fuel_type,transmission --name-columns=model,variant --description-columns=design,comfort,performance,safety,status --content-columns=make,brand_channel,model,variant,fuel_type,transmission,body_type,seating,new_model_launch_date,ex_showroom_price_inr,approx_on_road_price_inr,design,comfort,performance,safety,status --category-column=body_type --default-category=models

php artisan orgdata:import-csv odyssey-motors ../sample_files/odyssey_motors_sheet_import/faq_from_sheet.csv --dataset=faq_from_sheet --type=faq --qdrant-type=faq --key-columns=question --name-columns=question --description-columns=answer --content-columns=category,question,answer,keywords,source_sheet,priority --category-column=category --default-category=general

### Service checks

systemctl restart ai-fastapi.service
systemctl status ai-fastapi.service

### Useful diagnostics

php artisan route:list --name=customer.csv-import
php artisan route:list --name=admin.csv-import

## 6) Key Files Changed Recently

- laravel/app/Console/Commands/ImportOrganizationCsvData.php
- laravel/app/Livewire/Admin/CsvImportManager.php
- laravel/resources/views/livewire/admin/csv-import-manager.blade.php
- laravel/app/Livewire/Customer/CsvImportManager.php
- laravel/resources/views/livewire/customer/csv-import-manager.blade.php
- laravel/app/Http/Controllers/Customer/WidgetSettingsController.php
- laravel/resources/views/customer/widget.blade.php
- laravel/resources/views/widget/script.blade.php
- laravel/app/Http/Controllers/WidgetController.php
- laravel/routes/web.php
- sample_files/odyssey_motors_sheet_import/models_master.csv
- sample_files/odyssey_motors_sheet_import/faq_from_sheet.csv

## 7) Open Tasks (if continuing)

- Optional: add inline widget form validation messages (instead of alerts).
- Optional: add admin/customer CSV sample header generator per dataset.
- Optional: schedule periodic CSV re-import jobs.

## 8) Session Notes Format (update each time)

- Date:
- Branch:
- What was done:
- Validation done:
- Any blockers:
- Next immediate action:

## 9) Last Updated

- Date: 2026-02-17
- Note: This handoff is for quick context transfer between devices for Copilot chats.
