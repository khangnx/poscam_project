import argparse
import hashlib
import hmac
import json
import requests
from urllib.parse import urlencode

# IMPORTANT: Chỉnh sửa các KEY này thành KEY của bạn trên PayOS để test
CLIENT_ID = "your_client_id"
API_KEY = "your_api_key"
CHECKSUM_KEY = "your_checksum_key"

WEBHOOK_URL = "http://localhost:8000/api/webhooks/payment"

def calculate_signature(data: dict, checksum_key: str) -> str:
    # Build a flat dictionary sorted alphabetically by key as per standard Webhook signature
    sorted_keys = sorted(data.keys())
    signature_data = []
    
    for key in sorted_keys:
        val = data[key]
        if isinstance(val, (dict, list)):
            continue # Usually skip nested or stringify, simplified here 
        signature_data.append(f"{key}={val}")
        
    signature_string = "&".join(signature_data)
    
    signature = hmac.new(
        checksum_key.encode('utf-8'),
        signature_string.encode('utf-8'),
        hashlib.sha256
    ).hexdigest()
    
    return signature

def simulate_webhook(order_id: int, amount: int):
    # Dummy data structure resembling PayOS Webhook
    timestamp = "1691234567"
    
    webhook_data = {
        "orderCode": order_id,
        "amount": amount,
        "description": f"SHOPPAY {order_id}",
        "accountNumber": "1234567890",
        "reference": f"SIMULATE_{order_id}",
        "transactionDateTime": "2023-10-10 10:10:10",
        "currency": "VND",
        "paymentLinkId": "link123"
    }
    
    # Calculate signature
    signature = calculate_signature(webhook_data, CHECKSUM_KEY)
    webhook_data["signature"] = signature
    
    payload = {
        "code": "00",
        "desc": "success",
        "data": webhook_data,
        "signature": signature 
    }
    
    print(f"Sending Mock Webhook for Order {order_id} with Amount {amount}...")
    try:
        response = requests.post(WEBHOOK_URL, json=payload, headers={"Content-Type": "application/json"})
        print(f"Status Code: {response.status_code}")
        print("Response JSON:")
        try:
            print(json.dumps(response.json(), indent=2))
        except:
            print(response.text)
    except Exception as e:
        print(f"Request failed: {e}")

if __name__ == "__main__":
    parser = argparse.ArgumentParser(description="Simulate PayOS Webhook")
    parser.add_argument("--order_id", type=int, required=True, help="ID của đơn hàng (VD: 1, 2, ...)")
    parser.add_argument("--amount", type=int, default=100000, help="Số tiền thanh toán")
    
    args = parser.parse_args()
    simulate_webhook(args.order_id, args.amount)
