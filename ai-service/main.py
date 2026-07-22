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
MIN_ANTISPOOF_SCORE = 0.40
MAX_EMBEDDING_DISTANCE = 0.5
MIN_LIVENESS_MOVEMENT = 0.006
MIN_LIVENESS_SCALE_CHANGE = 0.015


FACE_ERROR_MESSAGES = {
    "invalid_image": "Ảnh không hợp lệ. Vui lòng thử lại.",
    "face_not_found": "Không tìm thấy khuôn mặt rõ ràng. Vui lòng đưa mặt vào giữa khung hình.",
    "face_not_clear": "Khuôn mặt chưa đủ rõ. Vui lòng tăng ánh sáng, lau camera và đưa mặt lại gần hơn.",
    "multiple_faces": "Phát hiện nhiều khuôn mặt. Vui lòng chỉ để một người trong khung hình.",
    "not_real_face": "Không vượt qua kiểm tra khuôn mặt thật. Vui lòng dùng khuôn mặt trực tiếp trước camera.",
    "need_motion": "Chưa nhận đủ chuyển động. Vui lòng xoay nhẹ mặt hoặc đưa mặt gần/xa camera một chút rồi quét lại.",
    "different_faces": "Các khung hình không cùng một khuôn mặt. Vui lòng chỉ để một người đăng ký FaceID.",
    "need_two_frames": "Cần ít nhất 2 khung hình để kiểm tra khuôn mặt thật.",
    "camera_required": "Bật camera để nhận diện khuôn mặt.",
}


# Class Exception tùy chỉnh để xử lý và quy đổi mã lỗi xác thực khuôn mặt ra thông báo tiếng Việt
class FaceValidationError(ValueError):
    def __init__(self, code):
        self.code = code
        super().__init__(FACE_ERROR_MESSAGES[code])


# Quản lý vòng đời (lifespan) của ứng dụng FastAPI khi khởi động và tắt service
@asynccontextmanager
async def lifespan(_app: FastAPI) -> AsyncIterator[None]:
    yield


app = FastAPI(title="StayHub AI Service", version="5.0", lifespan=lifespan)


# Đọc dữ liệu từ file upload và chuyển đổi thành ảnh OpenCV (numpy array BGR)
def load_image_from_upload(upload_file: UploadFile):
    contents = upload_file.file.read()
    nparr = np.frombuffer(contents, np.uint8)
    image = cv2.imdecode(nparr, cv2.IMREAD_COLOR)
    if image is None:
        raise FaceValidationError("invalid_image")

    return image


# Kiểm tra chất lượng ảnh: độ sáng (brightness) và độ sắc nét/độ mờ (blur score)
def validate_image_quality(image):
    gray = cv2.cvtColor(image, cv2.COLOR_BGR2GRAY)
    brightness = float(np.mean(gray))
    blur_score = float(cv2.Laplacian(gray, cv2.CV_64F).var())

    if brightness < MIN_BRIGHTNESS:
        raise FaceValidationError("face_not_clear")
    if brightness > MAX_BRIGHTNESS:
        raise FaceValidationError("face_not_clear")
    if blur_score < MIN_BLUR_SCORE:
        raise FaceValidationError("face_not_clear")

    return round(brightness, 2), round(blur_score, 2)


# Tính diện tích (width * height) của khung khuôn mặt phát hiện được
def detection_area(detection):
    facial_area = detection.get("facial_area") or {}
    width = int(facial_area.get("w") or 0)
    height = int(facial_area.get("h") or 0)

    return width * height


# Trích xuất khuôn mặt chính, kiểm tra kích thước tối thiểu, chống 2 mặt trong 1 ảnh và kiểm tra giả mạo (anti-spoofing)
def extract_real_face(image, anti_spoofing=True):
    detections = DeepFace.extract_faces(
        img_path=image,
        detector_backend=DETECTOR_BACKEND,
        enforce_detection=True,
    )

    detections = [detection for detection in detections if detection_area(detection) > 0]
    if len(detections) == 0:
        raise FaceValidationError("face_not_found")

    detections = sorted(detections, key=detection_area, reverse=True)
    detection = detections[0]
    facial_area = detection.get("facial_area") or {}
    width = int(facial_area.get("w") or 0)
    height = int(facial_area.get("h") or 0)

    if width < MIN_FACE_SIZE or height < MIN_FACE_SIZE:
        raise FaceValidationError("face_not_clear")

    main_area = width * height
    secondary_faces = [
        other_detection
        for other_detection in detections[1:]
        if detection_area(other_detection) >= main_area * MIN_SECONDARY_FACE_AREA_RATIO
        and int((other_detection.get("facial_area") or {}).get("w") or 0) >= MIN_SECONDARY_FACE_SIZE
        and int((other_detection.get("facial_area") or {}).get("h") or 0) >= MIN_SECONDARY_FACE_SIZE
    ]

    if len(secondary_faces) > 0:
        raise FaceValidationError("multiple_faces")

    if anti_spoofing:
        is_real = detection.get("is_real")
        antispoof_score = detection.get("antispoof_score")
        print(f"DEBUG: is_real={is_real}, antispoof_score={antispoof_score}, MIN_ANTISPOOF_SCORE={MIN_ANTISPOOF_SCORE}", flush=True)
        if is_real is False:
            raise FaceValidationError("not_real_face")

        if antispoof_score is not None and float(antispoof_score) < MIN_ANTISPOOF_SCORE:
            raise FaceValidationError("not_real_face")

    antispoof_score = detection.get("antispoof_score")
    return facial_area, round(float(antispoof_score or 1), 4)


# Trích xuất vectơ đặc trưng (embedding) đại diện cho khuôn mặt sử dụng DeepFace.represent
def extract_embedding(image):
    result = DeepFace.represent(
        img_path=image,
        model_name=FACE_MODEL,
        enforce_detection=True,
        detector_backend=DETECTOR_BACKEND,
    )

    if not result or not isinstance(result, list) or not result[0].get("embedding"):
        raise FaceValidationError("face_not_found")

    return np.array(result[0]["embedding"], dtype=np.float32)


# Tính khoảng cách Cosine giữa 2 vectơ embedding để đo độ tương đồng giữa 2 khuôn mặt
def cosine_distance(first, second):
    denominator = np.linalg.norm(first) * np.linalg.norm(second)
    if denominator == 0:
        return 1.0

    return float(1 - np.dot(first, second) / denominator)


# Tính tọa độ tâm chuẩn hóa (center_x, center_y) và tỷ lệ diện tích (scale) của khuôn mặt để theo dõi chuyển động
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


# Kiểm tra tính sống (liveness) dựa trên sự di chuyển và thay đổi khoảng cách/kích thước khuôn mặt giữa các frame
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
        raise FaceValidationError("need_motion")

    return round(float(movement), 4)


# Dịch thông báo lỗi tiếng Anh mặc định của DeepFace/OpenCV sang tiếng Việt cho người dùng
def translate_face_error(message):
    lower_message = message.lower()

    if "face could not be detected" in lower_message or "face could not" in lower_message:
        return FACE_ERROR_MESSAGES["face_not_found"]
    if "numpy array" in lower_message:
        return FACE_ERROR_MESSAGES["face_not_clear"]
    if "anti spoof" in lower_message or "spoof" in lower_message:
        return FACE_ERROR_MESSAGES["not_real_face"]
    if "please confirm" in lower_message or "enforce_detection" in lower_message:
        return FACE_ERROR_MESSAGES["face_not_found"]

    return message


# Quy trình xử lý danh sách ảnh: kiểm tra chất lượng, liveness, anti-spoofing và tính embedding trung bình
def process_images(images, require_liveness=False):
    if len(images) < 1:
        raise FaceValidationError("camera_required")
    if require_liveness and len(images) < 2:
        raise FaceValidationError("need_two_frames")

    embeddings = []
    motions = []
    frames = []

    for index, image in enumerate(images):
        brightness, blur_score = validate_image_quality(image)
        # Run anti-spoofing on the first frame only during liveness registration checks.
        anti_spoofing = True if not require_liveness else (index == 0)
        facial_area, antispoof_score = extract_real_face(image, anti_spoofing=anti_spoofing)
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
        raise FaceValidationError("different_faces")

    movement = validate_liveness(motions) if require_liveness else 0
    average_embedding = np.mean(np.stack(embeddings), axis=0)

    return [float(value) for value in average_embedding], frames, round(float(max_distance), 4), movement


# API Check health cho AI service
@app.get("/health")
def health():
    return {"status": True, "service": "stayhub-ai", "face_model": FACE_MODEL}


# API Endpoint nhận ảnh và trích xuất vectơ embedding khuôn mặt
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
        import traceback
        traceback.print_exc()
        raise HTTPException(status_code=422, detail=translate_face_error(str(error)))
    except Exception as error:
        import traceback
        traceback.print_exc()
        raise HTTPException(status_code=422, detail=translate_face_error(str(error)))


if __name__ == "__main__":
    import uvicorn

    uvicorn.run("main:app", host="0.0.0.0", port=8000, reload=True)
