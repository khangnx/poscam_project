FROM python:3.11-slim

# Cài đặt ffmpeg cho xử lý stream, và các thư viện cần thiết cho OpenCV
RUN apt-get update && apt-get install -y \
    ffmpeg \
    libsm6 \
    libxext6 \
    && rm -rf /var/lib/apt/lists/*

# Thiết lập thư mục làm việc
WORKDIR /app

# Cài đặt trước các thư viện Python theo yêu cầu (ngay cả khi chưa có requirements.txt)
RUN pip install --no-cache-dir \
    fastapi \
    uvicorn[standard] \
    opencv-python-headless \
    python-escpos \
    redis

# Chạy server FastAPI bằng Uvicorn và bật debug/hot reload
CMD ["uvicorn", "main:app", "--host", "0.0.0.0", "--port", "8000", "--reload"]
