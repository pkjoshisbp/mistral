#!/usr/bin/env python3

import requests
import json

# Check what's in Qdrant by making a search request
def check_qdrant_collection(collection_name):
    try:
        # Make a request to FastAPI to search for all data types
        search_url = "http://localhost:8111/qdrant/search"
        
        # Test with different queries to see what types of data exist
        queries = [
            "WhatsApp",
            "integration",
            "service",
            "API",
            "refund",  # We know this exists from logs
        ]
        
        for query in queries:
            payload = {
                "collection": collection_name,
                "query": query,
                "limit": 10
            }
            
            print(f"\n=== Searching for: {query} ===")
            response = requests.post(search_url, json=payload)
            
            if response.status_code == 200:
                results = response.json()
                print(f"Found {len(results.get('results', []))} results")
                
                for i, result in enumerate(results.get('results', [])):
                    payload_data = result.get('payload', {})
                    print(f"  {i+1}. Score: {result.get('score', 0):.3f}")
                    print(f"     Type: {payload_data.get('data_type', 'unknown')}")
                    print(f"     Item ID: {payload_data.get('item_id', 'N/A')}")
                    print(f"     Title: {payload_data.get('title', 'N/A')}")
                    print(f"     Content: {payload_data.get('content', '')[:100]}...")
            else:
                print(f"Error: {response.status_code} - {response.text}")
    
    except Exception as e:
        print(f"Error checking Qdrant: {e}")

if __name__ == "__main__":
    print("Checking Qdrant data for ai-chat-support collection...")
    check_qdrant_collection("ai-chat-support")
