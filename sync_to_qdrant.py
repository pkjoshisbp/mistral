#!/usr/bin/env python3
import requests
import json
import mysql.connector
import uuid

# Database config
DB_CONFIG = {
    'host': '127.0.0.1',
    'user': 'c1mistral',
    'password': 'a4BTyLFt@hU5b',
    'database': 'c1mistral'
}

# API endpoints
EMBEDDING_URL = "http://localhost:8111/embed"
QDRANT_URL = "http://127.0.0.1:6333"

def get_embedding(text):
    """Get embedding for text"""
    response = requests.post(EMBEDDING_URL, json={"text": text})
    if response.status_code == 200:
        data = response.json()
        return data.get('embedding')
    return None

def sync_organization_data():
    """Sync organization FAQ data to Qdrant"""
    
    # Connect to database
    conn = mysql.connector.connect(**DB_CONFIG)
    cursor = conn.cursor(dictionary=True)
    
    # Get organizations
    cursor.execute("SELECT id, name, slug FROM organizations")
    organizations = cursor.fetchall()
    
    for org in organizations:
        org_id = org['id']
        org_slug = org['slug']
        
        print(f"Syncing data for {org['name']} (slug: {org_slug})")
        
        # Create collection for this organization
        collection_resp = requests.put(f"{QDRANT_URL}/collections/{org_slug}", json={
            "vectors": {
                "size": 768,
                "distance": "Cosine"
            }
        })
        
        if collection_resp.status_code in [200, 409]:  # 200 = created, 409 = already exists
            print(f"Collection {org_slug} ready")
        else:
            print(f"Failed to create collection {org_slug}: {collection_resp.status_code}")
            continue
        
        # Get FAQs for this organization
        cursor.execute("SELECT * FROM organization_faqs WHERE organization_id = %s AND is_active = 1", (org_id,))
        faqs = cursor.fetchall()
        
        print(f"Found {len(faqs)} FAQs")
        
        # Sync FAQs to Qdrant
        for faq in faqs:
            # Create combined text for embedding
            combined_text = f"Question: {faq['question']} Answer: {faq['answer']}"
            
            # Get embedding
            embedding = get_embedding(combined_text)
            if not embedding:
                print(f"Failed to get embedding for FAQ {faq['id']}")
                continue
            
            # Create point for Qdrant
            point_id = str(uuid.uuid4())
            point_data = {
                "points": [{
                    "id": point_id,
                    "vector": embedding,
                    "payload": {
                        "type": "faq",
                        "question": faq['question'],
                        "answer": faq['answer'],
                        "category": faq['category'],
                        "organization_id": org_id,
                        "faq_id": faq['id']
                    }
                }]
            }
            
            # Insert to Qdrant
            insert_resp = requests.put(f"{QDRANT_URL}/collections/{org_slug}/points", json=point_data)
            if insert_resp.status_code == 200:
                print(f"Synced FAQ: {faq['question'][:50]}...")
            else:
                print(f"Failed to sync FAQ {faq['id']}: {insert_resp.status_code}")
    
    cursor.close()
    conn.close()
    print("Sync completed!")

if __name__ == "__main__":
    sync_organization_data()
