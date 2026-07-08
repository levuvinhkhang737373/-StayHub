from collections.abc import AsyncIterator
from contextlib import asynccontextmanager
import logging

logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s - %(name)s - %(levelname)s - %(message)s",
    handlers=[
        logging.FileHandler("error.log"),
        logging.StreamHandler()
    ]
)

from fastapi import FastAPI, File, HTTPException, UploadFile
import cv2
from deepface import DeepFace
import numpy as np

FACE_MODEL = "Facenet"
DETECTOR_BACKEND = "opencv"
MIN_FACE_SIZE = 70
MIN_SECONDARY_FACE_AREA_RATIO = 0.8
MIN_SECONDARY_FACE_SIZE = 120
MIN_BRIGHTNESS = 38
MAX_BRIGHTNESS = 220
MIN_BLUR_SCORE = 28
MIN_ANTISPOOF_SCORE = 0.55
MAX_EMBEDDING_DISTANCE = 0.5
MIN_LIVENESS_MOVEMENT = 0.006
MIN_LIVENESS_SCALE_CHANGE = 0.015
@asynccontextmanager
async def lifespan(_app: FastAPI) -> AsyncIterator[None]:
    yield


app = FastAPI(title="StayHub AI Service", version="5.0", lifespan=lifespan)


def load_image_from_upload(upload_file: UploadFile):
    contents = upload_file.file.read()
    nparr = np.frombuffer(contents, np.uint8)
    image = cv2.imdecode(nparr, cv2.IMREAD_COLOR)
    if image is None:
        raise ValueError("Ảnh không hợp lệ.")

    return image


def validate_image_quality(image):
    gray = cv2.cvtColor(image, cv2.COLOR_BGR2GRAY)
    brightness = float(np.mean(gray))
    blur_score = float(cv2.Laplacian(gray, cv2.CV_64F).var())

    if brightness < MIN_BRIGHTNESS:
        raise ValueError("Ảnh quá tối, vui lòng tăng ánh sáng trước mặt.")
    if brightness > MAX_BRIGHTNESS:
        raise ValueError("Ảnh quá sáng hoặc bị ngược sáng, vui lòng đổi góc camera.")
    if blur_score < MIN_BLUR_SCORE:
        raise ValueError("Ảnh bị mờ, vui lòng lau camera và giữ mặt ổn định.")

    return round(brightness, 2), round(blur_score, 2)


def detection_area(detection):
    facial_area = detection.get("facial_area") or {}
    width = int(facial_area.get("w") or 0)
    height = int(facial_area.get("h") or 0)

    return width * height


def extract_real_face(image):
    detections = DeepFace.extract_faces(
        img_path=image,
        detector_backend=DETECTOR_BACKEND,
        enforce_detection=True,
        anti_spoofing=True,
    )

    detections = [detection for detection in detections if detection_area(detection) > 0]
    if len(detections) == 0:
        raise ValueError("Không tìm thấy khuôn mặt trong ảnh.")

    detections = sorted(detections, key=detection_area, reverse=True)
    detection = detections[0]
    facial_area = detection.get("facial_area") or {}
    width = int(facial_area.get("w") or 0)
    height = int(facial_area.get("h") or 0)

    if width < MIN_FACE_SIZE or height < MIN_FACE_SIZE:
        raise ValueError("Khuôn mặt quá nhỏ, vui lòng đưa camera lại gần hơn.")

    main_area = width * height
    secondary_faces = [
        other_detection
        for other_detection in detections[1:]
        if detection_area(other_detection) >= main_area * MIN_SECONDARY_FACE_AREA_RATIO
        and int((other_detection.get("facial_area") or {}).get("w") or 0) >= MIN_SECONDARY_FACE_SIZE
        and int((other_detection.get("facial_area") or {}).get("h") or 0) >= MIN_SECONDARY_FACE_SIZE
    ]

    if len(secondary_faces) > 0:
        raise ValueError("Phát hiện nhiều hơn một khuôn mặt rõ trong khung hình, vui lòng chỉ để một người trước camera.")

    if detection.get("is_real") is False:
        raise ValueError("Phát hiện ảnh tĩnh, màn hình hoặc khuôn mặt giả mạo.")

    antispoof_score = detection.get("antispoof_score")
    if antispoof_score is not None and float(antispoof_score) < MIN_ANTISPOOF_SCORE:
        raise ValueError("Điểm chống giả mạo không đạt, vui lòng dùng khuôn mặt thật trong môi trường đủ sáng.")

    return facial_area, round(float(antispoof_score or 1), 4)


def extract_embedding(image):
    result = DeepFace.represent(
        img_path=image,
        model_name=FACE_MODEL,
        enforce_detection=True,
        detector_backend=DETECTOR_BACKEND,
    )

    if not result or not isinstance(result, list) or not result[0].get("embedding"):
        raise ValueError("Không thể trích xuất khuôn mặt.")

    return np.array(result[0]["embedding"], dtype=np.float32)


def cosine_distance(first, second):
    denominator = np.linalg.norm(first) * np.linalg.norm(second)
    if denominator == 0:
        return 1.0

    return float(1 - np.dot(first, second) / denominator)


def face_motion(facial_area, image):
    image_height, image_width = image.shape[:2]
    x = float(facial_area.get("x") or 0)
    y = float(facial_area.get("y") or 0)
    width = float(facial_area.get("w") or 0)
    height = float(facial_area.get("h") or 0)
    center_x = (x + width / 2) / image_width
    center_y = (y + height / 2) / image_height
    scale = (width * height) / float(image_width * image_height)

    return center_x, center_y, scale


def validate_liveness(motions):
    if len(motions) < 2:
        return 0

    movements = []
    for index in range(1, len(motions)):
        center_movement = abs(motions[index][0] - motions[index - 1][0]) + abs(motions[index][1] - motions[index - 1][1])
        scale_change = abs(motions[index][2] - motions[index - 1][2])
        movements.append(max(center_movement, scale_change / MIN_LIVENESS_SCALE_CHANGE * MIN_LIVENESS_MOVEMENT))

    movement = max(movements)

    if movement < MIN_LIVENESS_MOVEMENT:
        raise ValueError("Chưa nhận đủ chuyển động, vui lòng xoay nhẹ mặt hoặc đưa mặt gần/xa camera một chút rồi quét lại.")

    return round(float(movement), 4)


def translate_face_error(message):
    lower_message = message.lower()

    if "face could not be detected" in lower_message or "face could not" in lower_message:
        return "Không tìm thấy khuôn mặt rõ ràng, vui lòng đưa mặt vào giữa khung và thử lại."
    if "numpy array" in lower_message:
        return "Không thể xử lý ảnh từ camera, vui lòng thử lại với ánh sáng rõ hơn."
    if "anti spoof" in lower_message or "spoof" in lower_message:
        return "Không vượt qua kiểm tra chống giả mạo, vui lòng dùng khuôn mặt thật trong môi trường đủ sáng."
    if "please confirm" in lower_message or "enforce_detection" in lower_message:
        return "Không tìm thấy khuôn mặt rõ ràng, vui lòng đưa mặt vào giữa khung và thử lại."

    return message


def process_images(images, require_liveness=False):
    if len(images) < 1:
        raise ValueError("Bật camera để nhận diện khuôn mặt.")
    if require_liveness and len(images) < 2:
        raise ValueError("Bật camera để kiểm tra chống fake.")

    embeddings = []
    motions = []
    frames = []

    for image in images:
        brightness, blur_score = validate_image_quality(image)
        facial_area, antispoof_score = extract_real_face(image)
        embedding = extract_embedding(image)
        embeddings.append(embedding)
        motions.append(face_motion(facial_area, image))
        frames.append({
            "brightness": brightness,
            "blur_score": blur_score,
            "antispoof_score": antispoof_score,
            "facial_area": facial_area,
        })

    distances = [cosine_distance(embeddings[0], embedding) for embedding in embeddings[1:]]
    max_distance = max(distances) if distances else 0
    if max_distance > MAX_EMBEDDING_DISTANCE:
        raise ValueError("Các khung hình không cùng một khuôn mặt, vui lòng thử lại.")

    movement = validate_liveness(motions) if require_liveness else 0
    average_embedding = np.mean(np.stack(embeddings), axis=0)

    return [float(value) for value in average_embedding], frames, round(float(max_distance), 4), movement


@app.get("/health")
def health():
    return {"status": True, "service": "stayhub-ai", "face_model": FACE_MODEL}


@app.post("/api/v1/extract")
def extract_face(
    files: list[UploadFile] = File(None),
    file: UploadFile | None = File(None),
    require_liveness: bool = False,
):
    try:
        uploads = files or ([file] if file else [])
        images = [load_image_from_upload(upload) for upload in uploads]
        embedding, frames, max_distance, movement = process_images(images, require_liveness=require_liveness)

        return {
            "embedding": embedding,
            "embedding_size": len(embedding),
            "face_count": 1,
            "frame_count": len(frames),
            "frames": frames,
            "max_embedding_distance": max_distance,
            "liveness_movement": movement,
            "detector_backend": DETECTOR_BACKEND,
            "antispoofing": True,
            "motion_liveness_required": require_liveness,
            "model": FACE_MODEL,
        }
    except ValueError as error:
        raise HTTPException(status_code=422, detail=translate_face_error(str(error)))
    except Exception as error:
        raise HTTPException(status_code=422, detail=translate_face_error(str(error)))


if __name__ == "__main__":
    import uvicorn

    uvicorn.run("main:app", host="0.0.0.0", port=8000, reload=True)
