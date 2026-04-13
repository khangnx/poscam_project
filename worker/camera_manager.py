import cv2
import time
import threading
import logging

logging.basicConfig(level=logging.INFO)
logger = logging.getLogger("CameraManager")

class CameraStream:
    def __init__(self, camera_id: str, rtsp_url: str):
        self.camera_id = camera_id
        self.rtsp_url = rtsp_url
        self.cap = None
        self.latest_frame = None
        self.lock = threading.Lock()
        self.running = False
        self.worker_thread = None
        self.last_accessed = time.time()
        self.fail_count = 0

    def start(self):
        if self.running: 
            return
        self.running = True
        self.last_accessed = time.time()
        self.fail_count = 0
        self.worker_thread = threading.Thread(target=self._update, daemon=True)
        self.worker_thread.start()
        logger.info(f"Started stream for camera {self.camera_id}")

    def stop(self):
        self.running = False
        if self.cap:
            self.cap.release()
        logger.info(f"Stopped stream for camera {self.camera_id}")

    def _update(self):
        self.cap = cv2.VideoCapture(self.rtsp_url)
        # Tối ưu buffer delay cho RTSP
        self.cap.set(cv2.CAP_PROP_BUFFERSIZE, 1)

        while self.running:
            if self.cap and self.cap.isOpened():
                ret, frame = self.cap.read()
                if ret:
                    self.fail_count = 0
                    # Nén ở chất lượng 70 để giảm băng thông
                    ret_jpeg, jpeg = cv2.imencode('.jpg', frame, [int(cv2.IMWRITE_JPEG_QUALITY), 70])
                    if ret_jpeg:
                        with self.lock:
                            self.latest_frame = jpeg.tobytes()
                else:
                    self.fail_count += 1
                    logger.warning(f"Failed to read frame from camera {self.camera_id}. Fail count: {self.fail_count}")
                    # Anti-hang: Khởi động lại sau 10 lỗi liên tiếp
                    if self.fail_count > 10:
                        logger.error(f"Restarting connection for camera {self.camera_id} due to multiple read failures")
                        self.cap.release()
                        time.sleep(1)
                        self.cap = cv2.VideoCapture(self.rtsp_url)
                        self.cap.set(cv2.CAP_PROP_BUFFERSIZE, 1)
                        self.fail_count = 0
                    time.sleep(0.1)
            else:
                logger.error(f"Connection lost for camera {self.camera_id}. Reconnecting...")
                time.sleep(2)
                self.cap = cv2.VideoCapture(self.rtsp_url)
                self.cap.set(cv2.CAP_PROP_BUFFERSIZE, 1)

    def get_frame(self):
        self.last_accessed = time.time()
        with self.lock:
            return self.latest_frame

class StreamManager:
    def __init__(self):
        self.cameras = {}
        self.lock = threading.Lock()
        # Thread dọn dẹp các stream không ai xem
        self.gc_thread = threading.Thread(target=self._garbage_collector, daemon=True)
        self.gc_thread.start()

    def get_camera(self, camera_id: str, rtsp_url: str) -> CameraStream:
        with self.lock:
            if camera_id not in self.cameras:
                cam = CameraStream(camera_id, rtsp_url)
                self.cameras[camera_id] = cam
                cam.start()
            return self.cameras[camera_id]

    def _garbage_collector(self):
        """
        Khối GC chạy 10s một lần. 
        Nếu camera không có ai get_frame() trong 30s, tự động tắt để tiết kiệm tài nguyên.
        """
        while True:
            time.sleep(10)
            current_time = time.time()
            with self.lock:
                to_remove = []
                for cid, cam in self.cameras.items():
                    if current_time - cam.last_accessed > 30:
                        logger.info(f"Camera {cid} inactive for 30s. Disconnecting (Lazy Load).")
                        cam.stop()
                        to_remove.append(cid)
                
                for cid in to_remove:
                    del self.cameras[cid]

# Instance Singleton
stream_manager = StreamManager()
