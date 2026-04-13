FROM node:20-alpine

# Thiết lập thư mục làm việc
WORKDIR /app

# Cập nhật npm
RUN npm install -g npm@latest

# Cài đặt dependencies và chạy dev server
CMD sh -c "npm install && npm run dev -- --host 0.0.0.0 --port 5173"
