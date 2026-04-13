from fastapi import APIRouter

router = APIRouter()

@router.get("/")
def list_streams():
    """Lấy danh sách các luồng camera RTSP đang hoạt động"""
    return {"message": "Mock: Danh sách luồng camera RTSP", "data": []}

@router.get("/{camera_id}")
def get_stream(camera_id: int):
    """Lấy stream cụ thể của camera_id (thường trả về luồng HLS/WebSocket)"""
    return {"message": f"View stream for camera {camera_id}"}
