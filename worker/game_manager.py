import subprocess
import requests
import os

class GameManager:
    def __init__(self):
        # Địa chỉ nội bộ docker của Laravel
        self.backend_url = "http://web/api/vouchers/check-eligibility"

    def start_game(self, phone: str):
        try:
            # Xác thực khách có hợp lệ (hạng bạc, chưa có voucher 3% nào khác)
            response = requests.post(self.backend_url, json={"phone": phone}, headers={"X-Internal-Secret": "worker-secret-token"}, timeout=15)
            data = response.json()
            
            if data.get('success'):
                tenant_id = data.get('tenant_id', '')
                
                # Gọi script game python hoàn toàn độc lập (không block fastAPI)
                script_path = os.path.join(os.path.dirname(__file__), 'mini_game.py')
                
                # Popen chạy non-blocking, redirect output về stdout/stderr của container
                import sys
                subprocess.Popen(["python", script_path, str(phone), str(tenant_id)], stdout=sys.stdout, stderr=sys.stderr)
                
                return {"success": True, "message": "Game đã được mở lên!"}
            else:
                return {"success": False, "message": data.get('message', 'Không đủ điều kiện tham gia game.')}
        except Exception as e:
            return {"success": False, "message": f"Lỗi kết nối tới Server: {str(e)}"}

game_manager = GameManager()
