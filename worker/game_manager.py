import subprocess
import requests
import os

from dotenv import load_dotenv
load_dotenv()

class GameManager:
    def __init__(self):
        # Dynamic URL from environment
        backend_api_base = os.getenv("BACKEND_API_URL", "http://localhost:8000/api")
        self.backend_url = f"{backend_api_base}/vouchers/check-eligibility"

    def start_game(self, phone: str, customer_id: str = None):
        try:
            print(f"[BACKGROUND] Checking eligibility for phone: {phone}, customer_id: {customer_id}")
            # Xác thực khách có hợp lệ (hạng bạc, chưa có voucher 3% nào khác)
            payload = {"phone": phone}
            if customer_id:
                payload["customer_id"] = customer_id

            response = requests.post(self.backend_url, json=payload, headers={"X-Internal-Secret": "worker-secret-token"}, timeout=15)
            data = response.json()
            
            if data.get('success'):
                tenant_id = data.get('tenant_id', '')
                
                # Chạy File Game bằng chính trình thông dịch hiện tại
                import sys
                script_path = os.path.join(os.path.dirname(__file__), 'mini_game.py')
                args = [sys.executable, script_path, str(phone), str(tenant_id)]
                if customer_id:
                    args.append(str(customer_id))
                
                print(f"[BACKGROUND] Executing: {' '.join(args)}")
                process = subprocess.Popen(args, stdout=sys.stdout, stderr=sys.stderr)
                
                print(f"[BACKGROUND] Game process started with PID: {process.pid}")
                return {"success": True, "message": "Game đã được mở lên!"}
            else:
                print(f"[BACKGROUND] Eligibility failed: {data.get('message')}")
                return {"success": False, "message": data.get('message', 'Không đủ điều kiện tham gia game.')}
        except Exception as e:
            return {"success": False, "message": f"Lỗi kết nối tới Server: {str(e)}"}

game_manager = GameManager()
