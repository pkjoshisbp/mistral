#!/usr/bin/env python3
import requests
import json

# Qdrant connection
QDRANT_URL = "http://127.0.0.1:6333"

def migrate_collection():
    # First, get all points from the old collection
    print("Fetching points from gupta_diagnostics...")
    resp = requests.get(f"{QDRANT_URL}/collections/gupta_diagnostics/points?limit=1000")
    
    if resp.status_code != 200:
        print(f"Failed to fetch points: {resp.status_code}")
        print(resp.text)
        return
    
    data = resp.json()
    points = data.get('result', {}).get('points', [])
    print(f"Found {len(points)} points")
    
    if not points:
        print("No points to migrate")
        return
    
    # Create new collection with hyphen
    print("Creating gupta-diagnostics collection...")
    create_resp = requests.put(f"{QDRANT_URL}/collections/gupta-diagnostics", json={
        "vectors": {
            "size": 768,
            "distance": "Cosine"
        }
    })
    
    if create_resp.status_code not in [200, 409]:  # 409 = already exists
        print(f"Failed to create collection: {create_resp.status_code}")
        print(create_resp.text)
        return
    
    print("Collection created successfully")
    
    # Insert points into new collection
    print("Inserting points into new collection...")
    for i, point in enumerate(points):
        insert_data = {
            "points": [{
                "id": point["id"],
                "vector": point["vector"],
                "payload": point["payload"]
            }]
        }
        
        insert_resp = requests.put(f"{QDRANT_URL}/collections/gupta-diagnostics/points", json=insert_data)
        
        if insert_resp.status_code != 200:
            print(f"Failed to insert point {i}: {insert_resp.status_code}")
            print(insert_resp.text)
        else:
            print(f"Inserted point {i+1}/{len(points)}")
    
    print("Migration completed!")

if __name__ == "__main__":
    migrate_collection()
