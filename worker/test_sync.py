import requests
import os
from dotenv import load_dotenv

load_dotenv()

BACKEND_API_URL = "http://localhost:8000/api"
INTERNAL_SECRET = "worker-secret-token"

trends = [
    {
        "item_name": "Trà Sữa Đất Sét",
        "trend_score": 500,
        "source_url": "https://www.google.com/search?q=trà+sữa+đất+sét",
        "recommendation_reason": "Đang làm mưa làm gió trên TikTok với hàng triệu lượt xem."
    },
    {
        "item_name": "Bánh Mì Muối Ớt Khổng Lồ",
        "trend_score": 350,
        "source_url": "https://www.google.com/search?q=bánh+mì+muối+ớt+khổng+lồ",
        "recommendation_reason": "Món ăn đường phố đang quay trở lại mạnh mẽ tại Sài Gòn."
    },
    {
        "item_name": "Cà Phê Muối Kem Dừa",
        "trend_score": 420,
        "source_url": "https://www.google.com/search?q=cà+phê+muối+kem+dừa",
        "recommendation_reason": "Sự kết hợp độc đáo giữa vị mặn và vị béo ngậy."
    },
    {
        "item_name": "Lẩu Thái Topping Hải Sản Khô",
        "trend_score": 280,
        "source_url": "https://www.google.com/search?q=lẩu+thái+hải+sản+khô",
        "recommendation_reason": "Biến tấu mới lạ từ món lẩu truyền thống."
    },
    {
        "item_name": "Kem Cuộn Thái Lan Mix Trái Cây",
        "trend_score": 150,
        "source_url": "https://www.google.com/search?q=kem+cuộn+thái+lan",
        "recommendation_reason": "Món tráng miệng giải nhiệt cực hot trong mùa hè này."
    }
]

def test_sync():
    headers = {
        "X-Internal-Secret": INTERNAL_SECRET,
        "Content-Type": "application/json"
    }
    response = requests.post(f"{BACKEND_API_URL}/internal/trends/sync", json={"trends": trends}, headers=headers)
    print(f"Status: {response.status_code}")
    print(f"Response: {response.text}")

if __name__ == "__main__":
    test_sync()
