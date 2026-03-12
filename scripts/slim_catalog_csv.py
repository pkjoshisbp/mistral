#!/usr/bin/env python3
"""
slim_catalog_csv.py
───────────────────
Strips a Magento-style catalog CSV down to only the columns that
are actually useful for the AI chat system.

Usage:
    python3 scripts/slim_catalog_csv.py \
        --input  laravel/catalog_product_20260221_073104.csv \
        --output laravel/catalog_slim.csv

Options:
    --input   PATH   Input CSV (default: looks in laravel/ folder)
    --output  PATH   Output CSV (default: same dir as input, suffix _slim.csv)
    --extra   COL    Additional columns to keep (repeat as needed)
    --drop    COL    Columns to force-drop (repeat as needed)
"""

import argparse
import csv
import os
import sys

# ─── Columns to KEEP ───────────────────────────────────────────────────────────
# Adjust this list to your needs.
KEEP_COLUMNS = [
    "sku",
    "name",
    "description",
    "short_description",
    "price",
    "special_price",
    "url_key",
    "categories",
    "product_type",
    "product_online",
    "is_in_stock",
    "qty",
    "additional_attributes",   # contains artist_price, medium, room, etc.
    "visibility",
    "created_at",
    "updated_at",
]

# ─── Columns to ALWAYS DROP (even if listed in KEEP_COLUMNS) ───────────────────
FORCE_DROP = {
    # SEO / meta
    "meta_title", "meta_keywords", "meta_description",
    # Images (handled separately)
    "base_image", "base_image_label",
    "small_image", "small_image_label",
    "thumbnail_image", "thumbnail_image_label",
    "swatch_image", "swatch_image_label",
    "additional_images", "additional_image_labels",
    "hide_from_product_page",
    # Sale dates
    "special_price_from_date", "special_price_to_date",
    "new_from_date", "new_to_date",
    # Layout / design
    "custom_design", "custom_design_from", "custom_design_to",
    "custom_layout_update", "page_layout", "product_options_container",
    "display_product_options_in",
    # MSRP / MAP
    "map_price", "msrp_price", "map_enabled", "msrp_display_actual_price_type",
    # Qty config — rarely queried
    "out_of_stock_qty", "use_config_min_qty", "is_qty_decimal",
    "allow_backorders", "use_config_backorders", "min_cart_qty",
    "use_config_min_sale_qty", "max_cart_qty", "use_config_max_sale_qty",
    "use_config_notify_stock_qty", "notify_on_stock_below",
    "manage_stock", "use_config_manage_stock",
    "use_config_qty_increments", "qty_increments",
    "use_config_enable_qty_inc", "enable_qty_increments", "is_decimal_divided",
    # Related / upsell / cross-sell
    "related_skus", "related_position",
    "crosssell_skus", "crosssell_position",
    "upsell_skus", "upsell_position",
    # Bundle fields
    "bundle_price_type", "bundle_sku_type", "bundle_price_view",
    "bundle_weight_type", "bundle_values", "bundle_shipment_type",
    # Configurable
    "configurable_variations", "configurable_variation_labels",
    "associated_skus",
    # Misc
    "gift_message_available", "custom_options",
    "website_id", "product_websites", "store_view_code",
    "attribute_set_code",
    "tax_class_name", "weight",
    "country_of_manufacture",
}


def main():
    parser = argparse.ArgumentParser(description="Slim down a Magento catalog CSV")
    parser.add_argument("--input",  default=None, help="Input CSV path")
    parser.add_argument("--output", default=None, help="Output CSV path")
    parser.add_argument("--extra",  action="append", default=[], help="Extra columns to keep")
    parser.add_argument("--drop",   action="append", default=[], help="Extra columns to drop")
    args = parser.parse_args()

    # Resolve input
    if args.input:
        in_path = args.input
    else:
        # Try to find it automatically
        candidates = [
            "laravel/catalog_product_20260221_073104.csv",
            "catalog_product_20260221_073104.csv",
        ]
        in_path = next((p for p in candidates if os.path.exists(p)), None)
        if not in_path:
            print("ERROR: Cannot find input CSV. Use --input PATH", file=sys.stderr)
            sys.exit(1)

    # Resolve output
    if args.output:
        out_path = args.output
    else:
        base, ext = os.path.splitext(in_path)
        out_path = base + "_slim" + ext

    keep = set(KEEP_COLUMNS) | set(args.extra)
    drop = FORCE_DROP | set(args.drop)
    # force_drop wins over keep
    keep -= drop

    print(f"Input : {in_path}")
    print(f"Output: {out_path}")

    with open(in_path, newline="", encoding="utf-8-sig") as fin, \
         open(out_path, "w", newline="", encoding="utf-8") as fout:

        reader = csv.DictReader(fin)
        all_cols = reader.fieldnames or []

        # Columns present in the file that we want
        final_cols = [c for c in all_cols if c in keep]
        # Add "extra" columns if explicitly requested, even if not in KEEP_COLUMNS
        for col in args.extra:
            if col in all_cols and col not in final_cols and col not in drop:
                final_cols.append(col)

        if not final_cols:
            print("ERROR: No columns matched. Check --extra / --drop.", file=sys.stderr)
            sys.exit(1)

        print(f"\nKeeping {len(final_cols)} of {len(all_cols)} columns:")
        for c in final_cols:
            print(f"  + {c}")
        removed = [c for c in all_cols if c not in final_cols]
        print(f"\nDropping {len(removed)} columns:")
        for c in removed:
            print(f"  - {c}")

        writer = csv.DictWriter(fout, fieldnames=final_cols, extrasaction="ignore")
        writer.writeheader()

        rows_in  = 0
        rows_out = 0
        for row in reader:
            rows_in += 1
            # Only write rows that have a non-empty SKU (skip store-view rows)
            if not row.get("sku", "").strip():
                continue
            writer.writerow({k: row.get(k, "") for k in final_cols})
            rows_out += 1

        print(f"\nDone! {rows_in} rows read, {rows_out} rows written.")
        in_size  = os.path.getsize(in_path)  / 1024 / 1024
        out_size = os.path.getsize(out_path) / 1024 / 1024
        print(f"File size: {in_size:.1f} MB → {out_size:.1f} MB "
              f"({100 * out_size / in_size:.0f}% of original)")


if __name__ == "__main__":
    main()
