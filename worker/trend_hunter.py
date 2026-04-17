import os
import requests
import logging
import time
from pytrends.request import TrendReq
from dotenv import load_dotenv

# Setup logging
logging.basicConfig(level=logging.INFO, format='%(asctime)s - %(levelname)s - %(message)s')
logger = logging.getLogger(__name__)

load_dotenv()

BACKEND_API_URL = os.getenv("BACKEND_API_URL", "http://localhost:8000/api")
INTERNAL_SECRET = os.getenv("WORKER_SECRET", "worker-secret-token")

# Keyword Filtering lists
FOOD_KEYWORDS = [
    "trà", "sữa", "bánh", "lẩu", "mì", "bún", "phở", "cà phê", "kem", "gà", "vịt", "bò", 
    "ốc", "nướng", "chiên", "xào", "gỏi", "cuốn", "xôi", "chè", "nước", "sinh tố", "cơm"
]

EXCLUDE_KEYWORDS = [
    "cách làm", "hướng dẫn", "phim", "game", "âm nhạc", "tin tức", "thời tiết", "xổ số", "vietlott"
]

def is_food_related(query):
    query = query.lower()
    # Check if any food keyword is in the query
    has_food = any(kw in query for kw in FOOD_KEYWORDS)
    # Check if any exclude keyword is in the query
    is_excluded = any(kw in query for kw in EXCLUDE_KEYWORDS)
    return has_food and not is_excluded

def get_rising_trends():
    """
    Fetch rising food trends in Vietnam.
    """
    try:
        pytrends = TrendReq(hl='vi-VN', tz=420) # 420 is GMT+7 (Vietnam)
        
        logger.info("Fetching trending searches for Vietnam...")
        # Get real-time trending searches (last 24-48h)
        # However, trending_searches only returns Top 20 general.
        # Let's try to get related queries for a broad term like "món ăn"
        kw_list = ["món ăn hot", "đồ uống hot"]
        pytrends.build_payload(kw_list, cat=71, timeframe='now 7-d', geo='VN')
        
        related_queries = pytrends.related_queries()
        
        suggestions = []
        for kw in kw_list:
            if kw in related_queries and 'rising' in related_queries[kw] and related_queries[kw]['rising'] is not None:
                rising_df = related_queries[kw]['rising']
                for index, row in rising_df.iterrows():
                    query = row['query']
                    score = row['value']
                    if is_food_related(query):
                        suggestions.append({
                            "item_name": query.title(),
                            "trend_score": 100 if score == 'Breakout' else min(int(score), 500),
                            "source_url": f"https://www.google.com/search?q={query.replace(' ', '+')}",
                            "recommendation_reason": "Đang có xu hướng tăng trưởng bứt phá."
                        })
        
        # Fallback: Check general trending searches in VN
        if not suggestions:
            logger.info("No specific food rising trends. Checking general trending searches...")
            trending_searches_df = pytrends.trending_searches(pn='vietnam')
            for index, row in trending_searches_df.iterrows():
                query = row[0]
                if is_food_related(query):
                    suggestions.append({
                        "item_name": query.title(),
                        "trend_score": 90, # Default high score for top trending
                        "source_url": f"https://www.google.com/search?q={query.replace(' ', '+')}",
                        "recommendation_reason": "Đang nằm trong Top tìm kiếm tại Việt Nam."
                    })

        
        # Deduplicate and take top 5
        seen = set()
        unique_suggestions = []
        for s in suggestions:
            if s['item_name'] not in seen:
                unique_suggestions.append(s)
                seen.add(s['item_name'])
        
        return unique_suggestions[:5]
        
    except Exception as e:
        logger.error(f"Error fetching trends: {e}")
        return []

def sync_to_backend(trends):
    if not trends:
        logger.warning("No trends to sync.")
        return
    
    logger.info(f"Syncing {len(trends)} trends to backend...")
    try:
        headers = {
            "X-Internal-Secret": INTERNAL_SECRET,
            "Content-Type": "application/json"
        }
        response = requests.post(f"{BACKEND_API_URL}/internal/trends/sync", json={"trends": trends}, headers=headers)
        
        if response.status_code == 200:
            logger.info("Successfully synced trends to backend.")
        else:
            logger.error(f"Failed to sync trends. Status: {response.status_code}, Response: {response.text}")
            
    except Exception as e:
        logger.error(f"Error connecting to backend: {e}")

if __name__ == "__main__":
    logger.info("Starting Trend Hunter...")
    trends = get_rising_trends()
    
    if trends:
        for t in trends:
            logger.info(f"Discovered Trend: {t['item_name']} (Score: {t['trend_score']})")
        sync_to_backend(trends)
    else:
        logger.info("No new food trends discovered today.")
