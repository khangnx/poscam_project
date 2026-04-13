from fastapi import FastAPI
from pydantic import BaseModel
import subprocess
import os
import uvicorn
from fastapi.middleware.cors import CORSMiddleware
import platform

app = FastAPI(title="Local Game Agent")

app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

from typing import Optional

class GameRequest(BaseModel):
    phone: str
    customer_id: Optional[int] = None
    tenant_id: Optional[int] = None

@app.post("/api/game/start")
def start_game_on_host(request: GameRequest):
    try:
        # Đường dẫn tới file game
        # File mini_game.py nằm trong thư mục worker
        current_dir = os.path.dirname(os.path.abspath(__file__))
        script_path = os.path.join(current_dir, 'worker', 'mini_game.py')
        
        # Chạy file Python trên hệ điều hành host
        # Args: phone, tenant_id, customer_id
        args = [str(request.phone), str(request.tenant_id), str(request.customer_id or "")]
        
        if platform.system() == "Windows":
            subprocess.Popen(["python", script_path] + args, creationflags=subprocess.CREATE_NEW_CONSOLE)
        else:
            subprocess.Popen(["python3", script_path] + args)
            
        return {"success": True, "message": "Game đã kích hoạt trên màn hình Desktop!"}
    except Exception as e:
        return {"success": False, "message": f"Không thể kích hoạt Python cục bộ: {str(e)}"}

if __name__ == "__main__":
    print("Starting Game Agent on POS (Port 8001)...")
    uvicorn.run(app, host="127.0.0.1", port=8001)
