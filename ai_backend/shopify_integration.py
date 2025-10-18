"""
Shopify API Integration Module
Handles communication with Laravel API for Shopify data
"""

import httpx
import logging
from typing import Optional, Dict, Any, List

logger = logging.getLogger(__name__)

# Laravel API configuration
LARAVEL_API_URL = "https://ai-chat.support/api"
LARAVEL_TIMEOUT = 10.0

async def query_shopify_data(
    shop_domain: str,
    query: str,
    query_type: str = "auto"
) -> Optional[Dict[str, Any]]:
    """
    Query Shopify data via Laravel API
    
    Args:
        shop_domain: Shopify shop domain (e.g., "store.myshopify.com")
        query: User's query text
        query_type: Type of query - "auto", "products", "order", "shop_info"
    
    Returns:
        Dict with 'success', 'data', 'formatted_text', 'query_type' keys
        None if request fails
    """
    try:
        async with httpx.AsyncClient(timeout=LARAVEL_TIMEOUT) as client:
            response = await client.post(
                f"{LARAVEL_API_URL}/shopify/query",
                json={
                    "shop_domain": shop_domain,
                    "query": query,
                    "query_type": query_type
                }
            )
            
            if response.status_code == 200:
                result = response.json()
                logger.info(
                    f"Shopify API success: shop={shop_domain} "
                    f"type={result.get('query_type')} "
                    f"has_data={result.get('success')}"
                )
                return result
            elif response.status_code == 404:
                logger.warning(f"Shop not connected: {shop_domain}")
                return None
            else:
                logger.error(
                    f"Shopify API error: {response.status_code} - {response.text}"
                )
                return None
                
    except httpx.TimeoutException:
        logger.error(f"Shopify API timeout for shop: {shop_domain}")
        return None
    except Exception as e:
        logger.error(f"Shopify API exception: {str(e)}")
        return None


async def get_shop_info(shop_domain: str) -> Optional[Dict[str, Any]]:
    """
    Get shop information directly
    
    Args:
        shop_domain: Shopify shop domain
    
    Returns:
        Shop info dict or None
    """
    try:
        async with httpx.AsyncClient(timeout=LARAVEL_TIMEOUT) as client:
            response = await client.get(
                f"{LARAVEL_API_URL}/shopify/shop/{shop_domain}"
            )
            
            if response.status_code == 200:
                result = response.json()
                return result.get('data')
            else:
                logger.error(f"Failed to get shop info: {response.status_code}")
                return None
                
    except Exception as e:
        logger.error(f"Failed to get shop info: {str(e)}")
        return None


def detect_shopify_query(query: str) -> bool:
    """
    Detect if a user query might need Shopify data
    
    Args:
        query: User's query text
    
    Returns:
        True if query appears to need Shopify data
    """
    query_lower = query.lower()
    
    # Keywords that indicate Shopify data is needed
    shopify_keywords = [
        # Products
        'product', 'products', 'item', 'items', 'sell', 'selling',
        'buy', 'buying', 'purchase', 'price', 'cost', 'catalog',
        'available', 'availability', 'stock', 'in stock', 'out of stock',
        'inventory',
        
        # Orders
        'order', 'orders', 'bought', 'purchased', 'my order',
        'order status', 'order number', '#',
        
        # Shipping
        'ship', 'shipping', 'delivery', 'track', 'tracking',
        'shipment', 'delivered', 'when will', 'where is',
        
        # Store info
        'store', 'shop', 'contact', 'phone', 'email',
        'address', 'location', 'hours', 'open',
        
        # General commerce
        'payment', 'checkout', 'cart', 'add to cart'
    ]
    
    return any(keyword in query_lower for keyword in shopify_keywords)


def format_shopify_context(shopify_result: Dict[str, Any]) -> str:
    """
    Format Shopify API result for LLM context
    
    Args:
        shopify_result: Result from query_shopify_data()
    
    Returns:
        Formatted text for LLM context
    """
    if not shopify_result or not shopify_result.get('success'):
        return ""
    
    formatted = shopify_result.get('formatted_text', '')
    
    if formatted:
        return f"\n\n=== LIVE STORE DATA ===\n{formatted}\n=== END STORE DATA ===\n"
    
    return ""


async def get_shop_domain_for_org(org_slug: str) -> Optional[str]:
    """
    Get Shopify shop domain for an organization
    
    This queries Laravel to find the Integration record for the org
    and returns the shop_domain.
    
    Args:
        org_slug: Organization slug
    
    Returns:
        Shop domain (e.g., "store.myshopify.com") or None
    """
    try:
        # Query Laravel for organization's Shopify integration
        async with httpx.AsyncClient(timeout=5.0) as client:
            # This endpoint needs to be created in Laravel
            # For now, we'll use a direct database query approach via a new endpoint
            response = await client.get(
                f"{LARAVEL_API_URL}/organizations/{org_slug}/shopify-domain"
            )
            
            if response.status_code == 200:
                result = response.json()
                return result.get('shop_domain')
            else:
                logger.warning(
                    f"No Shopify integration for org: {org_slug}"
                )
                return None
                
    except Exception as e:
        logger.error(f"Failed to get shop domain for {org_slug}: {str(e)}")
        return None


# Export all functions
__all__ = [
    'query_shopify_data',
    'get_shop_info',
    'detect_shopify_query',
    'format_shopify_context',
    'get_shop_domain_for_org',
]
