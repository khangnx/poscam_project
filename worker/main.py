import time
import json
import urllib.request
import urllib.error
from fastapi import FastAPI, HTTPException
from fastapi.responses import StreamingResponse, JSONResponse
from fastapi.middleware.cors import CORSMiddleware
from pydantic import BaseModel
import threading
import cv2

# Import từ các module đã tách bạch
from camera_manager import stream_manager
from printer_manager import PrintJob, ThermalPrinterManager

# --- FastAPI Setup & Security ---
app = FastAPI(
    title="Shop Operations Worker",
    description="FastAPI Service for RTSP Camera Streaming & Thermal Printing",
    version="1.0.0"
)

app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

# Khởi tạo Printer Manager
printer_manager = ThermalPrinterManager()

# --- Endpoint Camera Stream ---
def get_rtsp_url(camera_id: str) -> str:
    # URL to the internal Laravel API, available inside docker network
    url = f"http://web/api/internal/cameras/{camera_id}/stream-info"
    try:
        req = urllib.request.Request(url)
        with urllib.request.urlopen(req, timeout=5) as response:
            data = json.loads(response.read().decode())
            if data.get('success'):
                return data['data']['rtsp_url']
            else:
                return None
    except Exception as e:
        print(f"Error fetching camera {camera_id} info: {e}")
        return None

def generate_frames(camera_id: str, rtsp_url: str):
    # Lấy camera từ stream_manager (Singleton)
    cam = stream_manager.get_camera(camera_id, rtsp_url)

    while True:
        frame = cam.get_frame()
        if frame is not None:
            yield (b'--frame\r\n'
                   b'Content-Type: image/jpeg\r\n\r\n' + frame + b'\r\n')
        else:
            time.sleep(0.01)

@app.get("/stream/{camera_id}")
def stream_video(camera_id: str):
    """
    RTSP Streaming Endpoint.
    Phát luồng ảnh MJPEG (đã encode).
    Vu 3 Frontend chỉ cần thẻ <img> để hiển thị.
    """
    rtsp_url = get_rtsp_url(camera_id)
    if not rtsp_url:
        raise HTTPException(status_code=404, detail="Camera not found or inactive")

    return StreamingResponse(
        generate_frames(camera_id, rtsp_url),
        media_type="multipart/x-mixed-replace; boundary=frame"
    )

# --- Endpoint Thermal Print ---
@app.post("/api/print")
def print_receipt(job: PrintJob):
    """
    Nhận JSON list các item và in ra hóa đơn qua máy in nhiệt.
    """
    try:
        success = printer_manager.print_receipt(job)
        if success:
            return {"status": "success", "message": "In hóa đơn thành công!"}
    except Exception as e:
        return JSONResponse(status_code=500, content={"status": "error", "message": str(e)})

# --- Health Check ---
@app.get("/health")
def health_check():
    """Endpoint giúp Docker kiểm tra FastAPI container có đang hoạt động."""
    return {"status": "healthy", "service": "FastAPI Thermal Print & RTSP Video"}

class CheckCameraRequest(BaseModel):
    rtsp_url: str

import os

def check_camera_stream_worker(rtsp_url: str, result: list):
    try:
        # Prevent OpenCV from hanging for 30s. We set FFMPEG timeouts (in microseconds)
        os.environ["OPENCV_FFMPEG_CAPTURE_OPTIONS"] = "rtsp_transport;tcp|stimeout;3000000|timeout;3000000"
        
        # Use cv2 to quickly check the stream
        cap = cv2.VideoCapture(rtsp_url, cv2.CAP_FFMPEG)
        if cap.isOpened():
            ret, frame = cap.read()
            if ret and frame is not None:
                result.append(True)
                return
        result.append(False)
    except Exception as e:
        result.append(False)
    finally:
        try:
            if 'cap' in locals() and cap is not None:
                cap.release()
        except:
            pass

@app.post("/api/camera/check")
def check_camera_connection(request: CheckCameraRequest):
    """
    Check if an RTSP URL is available by trying to capture a frame.
    Limit execution time to 3 seconds.
    """
    result = []
    thread = threading.Thread(target=check_camera_stream_worker, args=(request.rtsp_url, result))
    thread.daemon = True
    thread.start()
    thread.join(timeout=3)
    
    if thread.is_alive():
        # Thered hung, it's a timeout
        return {"success": False, "message": "Connection timed out"}
        
    if result and result[0] is True:
        return {"success": True, "message": "Connection successful"}
        
    return {"success": False, "message": "Could not capture video frame"}

# --- Endpoint Mini Game Đua xe ---
from game_manager import game_manager

class GameRequest(BaseModel):
    phone: str

@app.post("/api/game/start")
def start_mini_game(request: GameRequest):
    """
    Kích hoạt game cho khách hàng từ xa (mở cửa sổ UI nội bộ trên máy POS)
    """
    result = game_manager.start_game(request.phone)
    return result

