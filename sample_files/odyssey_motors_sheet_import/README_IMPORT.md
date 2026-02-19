# Odyssey Motors Google Sheet Import Pack

This folder contains CSV files ready to import into Google Sheets for org `odyssey_motors`.

## Suggested sheet tabs
1. `Models_Master` → import `models_master.csv`
2. `Nexa_Premium_Only` → import `premium_only_models.csv`
3. `Location_Availability` → import `location_availability.csv`
4. `Test_Drive` → import `test_drive_availability.csv`
5. `Special_Offers` → import `special_offers.csv`
6. `FAQ_From_Sheet` → import `faq_from_sheet.csv`

## Notes
- Price columns are intentionally left blank in many rows so your client can fill exact ex-showroom and on-road pricing.
- `new_model_launch_date` should be filled only when launch is officially announced.
- `availability_status` should be one of: `In Stock`, `Limited Stock`, `Pre-Booking`, `Out of Stock`.
- `estimated_wait_weeks` should be numeric (0 means ready stock).

## Google Sheet link already connected to live actions
https://docs.google.com/spreadsheets/d/1id_jQDzQstDvyKFOP-f69MN--6sYizOxXhmA-0ppBWM/edit?usp=sharing

Live actions created for Odyssey Motors:
- Odyssey Car Models & Pricing
- Odyssey Test Drive Availability
- Odyssey Special Offers
