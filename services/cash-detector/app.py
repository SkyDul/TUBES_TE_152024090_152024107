from fastapi import FastAPI, UploadFile, File, Form
from ultralytics import YOLO
import cv2
import numpy as np
import time
import random

app = FastAPI(title="Real Cash Detector Service")

# 1. Load otak AI yang tadi Anda training
model = YOLO("best.pt")

@app.post("/detect")
async def detect_cash(order_id: str = Form(...), file: UploadFile = File(...)):
    # 2. Baca gambar dari PHP
    contents = await file.read()
    nparr = np.frombuffer(contents, np.uint8)
    img = cv2.imdecode(nparr, cv2.IMREAD_COLOR)

    # 3. Jalankan AI YOLOv8
    results = model.predict(img, conf=0.5)

    verdict = "uncertain"
    confidence = 0.0
    notes = "Tidak ada uang terdeteksi"

    # 4. Ambil hasil deteksi (jika ada kotak yang terdeteksi)
    if len(results[0].boxes) > 0:
        best_box = results[0].boxes[0]
        class_id = int(best_box.cls)
        # Ambil nama label (misal: "100k", "Asli", atau "Palsu")
        class_name = results[0].names[class_id].lower()
        confidence = float(best_box.conf)

        notes = f"Terdeteksi: {class_name}"

        # ==========================================
        # LOGIKA ASLI / PALSU (SESUAIKAN DENGAN LABEL ANDA)
        # ==========================================
        if "palsu" in class_name or "fake" in class_name:
            verdict = "counterfeit"
        elif "asli" in class_name or "genuine" in class_name:
            verdict = "genuine"
        else:
            # Jika dataset Anda cuma menebak nominal (misal labelnya "100k")
            # Kita anggap 'genuine' sementara waktu
            verdict = "genuine" 

    ref = f"AI-{int(time.time())}-{random.randint(1000, 9999)}"

    # 5. Kembalikan hasil ke PHP
    return {
        "success": True,
        "data": {
            "verdict": verdict,
            "confidence": round(confidence, 4),
            "detection_ref": ref,
            "notes": notes,
        }
    }