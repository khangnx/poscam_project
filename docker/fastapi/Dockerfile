FROM python:3.11-slim

# Cài đặt ffmpeg cho xử lý stream, và các thư viện cần thiết cho OpenCV / Pygame
RUN apt-get update && apt-get install -y \
    ffmpeg \
    libsm6 \
    libxext6 \
    libsdl2-2.0-0 \
    libsdl2-image-2.0-0 \
    libsdl2-mixer-2.0-0 \
    libsdl2-ttf-2.0-0 \
    libfreetype6-dev \
    libportmidi-dev \
    libswscale-dev \
    libavformat-dev \
    libavcodec-dev \
    && rm -rf /var/lib/apt/lists/*

# Thiết lập thư mục làm việc
WORKDIR /app

# Copy requirements và cài đặt dependencies
COPY worker/requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt

# Chạy server FastAPI bằng Uvicorn và bật debug/hot reload
CMD ["uvicorn", "main:app", "--host", "0.0.0.0", "--port", "8000", "--reload"]
