# Railway собирает бэкенд по этому Dockerfile (приоритет над автоопределением)
FROM python:3.12-slim

WORKDIR /app

COPY backend/requirements.txt ./
RUN pip install --no-cache-dir -r requirements.txt

COPY backend/ ./

# Flask слушает $PORT (Railway задаёт сам), бот — в фоновом потоке
CMD ["python", "bot.py"]
