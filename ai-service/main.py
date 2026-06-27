from collections.abc import AsyncIterator
from contextlib import asynccontextmanager
import base64
import json
import os
import re
from urllib.parse import quote, urlparse, urlunparse
import time
from typing import Any

from fastapi import FastAPI, File, HTTPException, UploadFile
import cv2
from deepface import DeepFace
import numpy as np
import requests
from pydantic import BaseModel, Field


FACE_MODEL = "Facenet"
DETECTOR_BACKEND = "opencv"
MIN_FACE_SIZE = 70
MIN_SECONDARY_FACE_AREA_RATIO = 0.8
MIN_SECONDARY_FACE_SIZE = 120
MIN_BRIGHTNESS = 45
MAX_BRIGHTNESS = 220
MIN_BLUR_SCORE = 35
MIN_ANTISPOOF_SCORE = 0.65
MAX_EMBEDDING_DISTANCE = 0.5
MIN_LIVENESS_MOVEMENT = 0.006
MIN_LIVENESS_SCALE_CHANGE = 0.015
SECURITY_CAMERA_SOURCE_SNAPSHOT = 1
SECURITY_CAMERA_SOURCE_MJPEG = 2
SECURITY_CAMERA_SOURCE_RTSP = 3
RISK_LEVEL_CODES = {
    "safe": 1,
    "warning": 2,
    "danger": 3,
    "critical": 4,
}


@asynccontextmanager
async def lifespan(_app: FastAPI) -> AsyncIterator[None]:
    yield


app = FastAPI(title="StayHub AI Service", version="5.0", lifespan=lifespan)


class FireSafetyStreamRequest(BaseModel):
    camera_id: int | None = None
    building_id: int | None = None
    camera_name: str | None = None
    location: str | None = None
    source_type: int = SECURITY_CAMERA_SOURCE_MJPEG
    stream_url: str
    username: str | None = None
    password: str | None = None
    frame_count: int = Field(default=3, ge=1, le=6)
    window_seconds: int = Field(default=2, ge=1, le=60)


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


def process_images(images):
    if len(images) < 2:
        raise ValueError("Cần ít nhất 2 khung hình để kiểm tra chống fake.")

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

    movement = validate_liveness(motions)
    average_embedding = np.mean(np.stack(embeddings), axis=0)

    return [float(value) for value in average_embedding], frames, round(float(max_distance), 4), movement


@app.get("/health")
def health():
    return {"status": True, "service": "stayhub-ai", "face_model": FACE_MODEL, "fire_model": fire_vision_model()}


@app.post("/api/v1/extract")
def extract_face(files: list[UploadFile] = File(None), file: UploadFile | None = File(None)):
    try:
        uploads = files or ([file] if file else [])
        images = [load_image_from_upload(upload) for upload in uploads]
        embedding, frames, max_distance, movement = process_images(images)

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
            "model": FACE_MODEL,
        }
    except ValueError as error:
        raise HTTPException(status_code=422, detail=translate_face_error(str(error)))
    except Exception as error:
        raise HTTPException(status_code=422, detail=translate_face_error(str(error)))


def fire_vision_model():
    return os.getenv("FIRE_VISION_MODEL") or os.getenv("OMNIROUTE_VISION_MODEL") or "kc/openai/gpt-4o"


def omniroute_base_url():
    return os.getenv("OMNIROUTE_BASE_URL", "http://host.docker.internal:20128/v1").rstrip("/")


def omniroute_api_key():
    return os.getenv("OMNIROUTE_API_KEY", "")


def normalize_stream_url(stream_url: str):
    replacements = [
        ("/video", "/photo.jpg"),
        ("/mjpeg", "/photo.jpg"),
        ("/live", "/photo.jpg"),
    ]
    for suffix, snapshot_suffix in replacements:
        if stream_url.rstrip("/").endswith(suffix):
            return stream_url.rstrip("/")[: -len(suffix)] + snapshot_suffix

    return stream_url


def stream_url_candidates(stream_url: str, candidate_limit: int | None = None):
    cleaned = stream_url.strip().rstrip("/")
    if not cleaned:
        return []

    candidates = []
    if re.match(r"^https?://[^/]+$", cleaned):
        candidates.extend([
            cleaned + "/photo.jpg",
            cleaned + "/photo",
            cleaned + "/snapshot.jpg",
            cleaned + "/snapshot",
            cleaned + "/shot.jpg",
            cleaned + "/shot",
            cleaned + "/image.jpg",
            cleaned + "/image",
            cleaned + "/jpg",
            cleaned + "/cam.jpg",
            cleaned + "/capture",
            cleaned + "/video",
            cleaned + "/mjpeg",
            cleaned + "/mjpg",
            cleaned + "/stream.mjpg",
            cleaned + "/stream",
            cleaned + "/live",
            cleaned + "/videostream.cgi",
            cleaned + "/axis-cgi/mjpg/video.cgi",
        ])

    candidates.extend([cleaned, normalize_stream_url(cleaned)])

    unique_candidates = []
    for candidate in candidates:
        if candidate not in unique_candidates:
            unique_candidates.append(candidate)

    if candidate_limit:
        return unique_candidates[:candidate_limit]

    return unique_candidates


def auth_for_request(username: str | None, password: str | None):
    if username and password:
        return username, password
    return None


def is_http_stream(stream_url: str):
    return urlparse(stream_url).scheme.lower() in {"http", "https"}


def stream_url_with_basic_auth(stream_url: str, username: str | None = None, password: str | None = None):
    parsed = urlparse(stream_url)
    if not username or not password or parsed.username or parsed.scheme.lower() not in {"rtsp", "http", "https"}:
        return stream_url

    credentials = quote(username, safe="") + ":" + quote(password, safe="")
    host = parsed.hostname or ""
    if ":" in host and not host.startswith("["):
        host = f"[{host}]"
    if parsed.port:
        host = f"{host}:{parsed.port}"

    return urlunparse((parsed.scheme, f"{credentials}@{host}", parsed.path, parsed.params, parsed.query, parsed.fragment))


def camera_error_message(error: Exception):
    message = str(error)
    lower_message = message.lower()

    if "missing dependencies for socks support" in lower_message:
        return "URL camera không hợp lệ hoặc bị hiểu nhầm thành proxy. Hãy nhập dạng http://192.168.x.x:8081 hoặc rtsp://user:pass@ip/stream."

    if "401" in message or "authorization required" in lower_message or "unauthorized" in lower_message:
        return "Camera yêu cầu Basic Auth. Hãy nhập username/password của app iPhone hoặc tắt auth trong app."

    if "403" in message or "forbidden" in lower_message:
        return "Camera từ chối truy cập. Hãy kiểm tra app iPhone có bật quyền truy cập LAN/HTTP stream và đúng username/password."

    if "404" in message or "not found" in lower_message:
        return "Không tìm thấy endpoint ảnh/stream của camera. Hãy nhập URL gốc app iPhone, ví dụ http://192.168.x.x:8081, hoặc URL snapshot/video đúng trong app."

    if "failed to establish a new connection" in lower_message or "connection refused" in lower_message:
        return "Không kết nối được camera. Hãy kiểm tra iPhone đang mở app camera, đúng IP/port và cùng mạng với laptop."

    if "timed out" in lower_message or "timeout" in lower_message:
        return "Camera phản hồi quá chậm. Hãy kiểm tra Wi-Fi/hotspot, giữ app iPhone mở màn hình và thử lại."

    if "no route to host" in lower_message or "network is unreachable" in lower_message:
        return "Laptop/Docker không thấy iPhone trong mạng LAN. Nếu Wi-Fi trường chặn LAN, hãy bật hotspot iPhone cho laptop kết nối."

    return message


def remember_camera_error(current_error: Exception | None, next_error: Exception):
    if current_error is None:
        return next_error

    current_message = str(current_error).lower()
    next_message = str(next_error).lower()

    if "401" in next_message or "authorization" in next_message or "unauthorized" in next_message:
        return next_error

    if "401" in current_message or "authorization" in current_message or "unauthorized" in current_message:
        return current_error

    return next_error


def camera_request_timeout(remaining_seconds: float | None = None):
    connect_timeout = float(os.getenv("FIRE_CAMERA_CONNECT_TIMEOUT", "1.5"))
    read_timeout = float(os.getenv("FIRE_CAMERA_READ_TIMEOUT", "2.5"))

    if remaining_seconds is not None:
        connect_timeout = max(0.5, min(connect_timeout, remaining_seconds))
        read_timeout = max(0.5, min(read_timeout, remaining_seconds))

    return connect_timeout, read_timeout


def frame_to_base64(image, max_width=768, quality=75):
    height, width = image.shape[:2]
    if width > max_width:
        ratio = max_width / float(width)
        image = cv2.resize(image, (max_width, int(height * ratio)), interpolation=cv2.INTER_AREA)

    success, encoded = cv2.imencode(".jpg", image, [int(cv2.IMWRITE_JPEG_QUALITY), quality])
    if not success:
        raise ValueError("Không thể mã hóa frame camera.")

    return base64.b64encode(encoded.tobytes()).decode("utf-8")


def load_snapshot_frame(
    stream_url: str,
    username: str | None = None,
    password: str | None = None,
    max_seconds: float | None = None,
    candidate_limit: int | None = None,
):
    last_error = None
    deadline = time.time() + max_seconds if max_seconds else None

    for candidate_url in stream_url_candidates(stream_url, candidate_limit):
        if deadline and time.time() >= deadline:
            break

        response = None
        try:
            remaining = deadline - time.time() if deadline else None
            response = requests.get(
                candidate_url,
                auth=auth_for_request(username, password),
                timeout=camera_request_timeout(remaining),
                stream=False,
            )
            response.raise_for_status()
            image_array = np.frombuffer(response.content, np.uint8)
            image = cv2.imdecode(image_array, cv2.IMREAD_COLOR)
            if image is not None:
                return image
            last_error = ValueError(f"{candidate_url} không trả về ảnh hợp lệ.")
        except Exception as error:
            last_error = error
        finally:
            if response is not None:
                response.close()

    raise ValueError(f"Không tìm được snapshot ảnh từ URL camera. {camera_error_message(last_error) if last_error else ''}")


def load_mjpeg_frame(
    stream_url: str,
    username: str | None = None,
    password: str | None = None,
    max_seconds: float | None = None,
    candidate_limit: int | None = None,
):
    last_error = None
    deadline = time.time() + max_seconds if max_seconds else None

    for candidate_url in stream_url_candidates(stream_url, candidate_limit):
        if deadline and time.time() >= deadline:
            break

        response = None
        try:
            remaining = deadline - time.time() if deadline else None
            response = requests.get(
                candidate_url,
                auth=auth_for_request(username, password),
                timeout=camera_request_timeout(remaining),
                stream=True,
            )
            response.raise_for_status()
            buffer = b""
            stream_deadline = deadline or (time.time() + 10)
            for chunk in response.iter_content(chunk_size=4096):
                if time.time() > stream_deadline:
                    raise TimeoutError(f"Quá thời gian lấy frame từ {candidate_url}.")

                if not chunk:
                    continue

                buffer += chunk
                start = buffer.find(b"\xff\xd8")
                end = buffer.find(b"\xff\xd9")
                if start >= 0 and end > start:
                    jpg = buffer[start : end + 2]
                    image_array = np.frombuffer(jpg, np.uint8)
                    image = cv2.imdecode(image_array, cv2.IMREAD_COLOR)
                    if image is not None:
                            return image

                if len(buffer) > 2_000_000:
                    buffer = buffer[-200_000:]
        except Exception as error:
            last_error = error
        finally:
            if response is not None:
                response.close()

    raise ValueError(f"Không lấy được JPEG frame từ MJPEG stream. {camera_error_message(last_error) if last_error else ''}")


def load_quick_http_frame(stream_url: str, username: str | None = None, password: str | None = None, max_seconds: float = 6):
    last_error = None
    deadline = time.time() + max_seconds

    for candidate_url in stream_url_candidates(stream_url):
        if time.time() >= deadline:
            break

        remaining = max(0.5, deadline - time.time())
        response = None
        try:
            response = requests.get(
                candidate_url,
                auth=auth_for_request(username, password),
                timeout=camera_request_timeout(remaining),
                stream=True,
            )
            response.raise_for_status()

            content_type = response.headers.get("content-type", "").lower()
            if "image" in content_type:
                body = response.content
                image_array = np.frombuffer(body, np.uint8)
                image = cv2.imdecode(image_array, cv2.IMREAD_COLOR)
                if image is not None:
                    return image, candidate_url

            buffer = b""
            for chunk in response.iter_content(chunk_size=4096):
                if time.time() >= deadline:
                    raise TimeoutError(f"Quá thời gian lấy frame từ {candidate_url}.")

                if not chunk:
                    continue

                buffer += chunk
                start = buffer.find(b"\xff\xd8")
                end = buffer.find(b"\xff\xd9")
                if start >= 0 and end > start:
                    jpg = buffer[start : end + 2]
                    image_array = np.frombuffer(jpg, np.uint8)
                    image = cv2.imdecode(image_array, cv2.IMREAD_COLOR)
                    if image is not None:
                        return image, candidate_url

                if len(buffer) > 2_000_000:
                    buffer = buffer[-200_000:]
        except Exception as error:
            last_error = remember_camera_error(last_error, error)
        finally:
            if response is not None:
                response.close()

    raise ValueError(camera_error_message(last_error) if last_error else "Không lấy được frame từ camera HTTP/iPhone.")


def load_snapshot_frame_legacy(stream_url: str, username: str | None = None, password: str | None = None):
    response = requests.get(
        normalize_stream_url(stream_url),
        auth=auth_for_request(username, password),
        timeout=8,
        stream=False,
    )
    response.raise_for_status()
    image_array = np.frombuffer(response.content, np.uint8)
    image = cv2.imdecode(image_array, cv2.IMREAD_COLOR)
    if image is None:
        raise ValueError("URL snapshot không trả về ảnh hợp lệ.")

    return image


def load_stream_frames(payload: FireSafetyStreamRequest):
    frames = []
    stream_url = stream_url_with_basic_auth(str(payload.stream_url), payload.username, payload.password)

    if payload.source_type == SECURITY_CAMERA_SOURCE_SNAPSHOT:
        for _ in range(payload.frame_count):
            frames.append(load_snapshot_frame(stream_url, payload.username, payload.password, max_seconds=12))
            if len(frames) < payload.frame_count:
                time.sleep(max(0.1, payload.window_seconds / max(payload.frame_count - 1, 1)))
        return frames

    if is_http_stream(stream_url):
        last_error = None
        for _ in range(payload.frame_count):
            try:
                frame, _ = load_quick_http_frame(stream_url, payload.username, payload.password, max_seconds=10)
                frames.append(frame)
                if len(frames) < payload.frame_count:
                    time.sleep(max(0.1, payload.window_seconds / max(payload.frame_count - 1, 1)))
            except Exception as error:
                last_error = error
                break

        if frames:
            return frames

        raise ValueError(f"Không lấy được frame. {last_error}")

    capture = cv2.VideoCapture(stream_url)
    if not capture.isOpened():
        snapshot_error = None
        try:
            frames = []
            for _ in range(payload.frame_count):
                try:
                    frames.append(load_snapshot_frame(stream_url, payload.username, payload.password, max_seconds=10))
                except Exception:
                    frames.append(load_mjpeg_frame(stream_url, payload.username, payload.password, max_seconds=10))
                if len(frames) < payload.frame_count:
                    time.sleep(max(0.1, payload.window_seconds / max(payload.frame_count - 1, 1)))
            return frames
        except Exception as error:
            snapshot_error = error
        raise ValueError(f"Không mở được stream camera. {snapshot_error}")

    try:
        deadline = time.time() + max(payload.window_seconds, 1) + 8
        sample_delay = max(0.1, payload.window_seconds / max(payload.frame_count, 1))
        last_sample_at = 0.0

        while len(frames) < payload.frame_count and time.time() < deadline:
            ok, frame = capture.read()
            if not ok or frame is None:
                time.sleep(0.1)
                continue

            if time.time() - last_sample_at >= sample_delay:
                frames.append(frame)
                last_sample_at = time.time()
    finally:
        capture.release()

    if not frames:
        raise ValueError("Không lấy được frame nào từ camera.")

    return frames


def fire_safety_prompt(camera_name: str | None, location: str | None):
    return f"""
Bạn là hệ thống AI hỗ trợ cảnh báo cháy cho khu trọ/ký túc xá StayHub.
Hãy phân tích các frame camera liên tiếp. Camera: {camera_name or 'không rõ'}, vị trí: {location or 'không rõ'}.

Nhiệm vụ:
- Phát hiện lửa thật, đám cháy, khói bất thường, tàn thuốc/điếu thuốc/hành vi hút thuốc.
- Phân biệt ánh đèn, màn hình màu cam, phản chiếu, bóng mờ để giảm báo giả.
- Nếu chỉ thấy người/vật bình thường thì trả safe.

Chỉ trả JSON hợp lệ, không markdown:
{{
  "risk_level": "safe|warning|danger|critical",
  "detected_fire": true/false,
  "detected_smoke": true/false,
  "detected_smoking": true/false,
  "confidence": 0.0-1.0,
  "summary": "mô tả ngắn bằng tiếng Việt",
  "recommended_action": "hành động đề xuất ngắn bằng tiếng Việt",
  "evidence": ["bằng chứng nhìn thấy trong frame"]
}}
Quy tắc mức độ:
- critical: thấy lửa/khói rõ, hoặc dấu hiệu cháy lan.
- danger: thấy khói/lửa nhỏ hoặc hút thuốc rất rõ ở khu vực cấm.
- warning: nghi ngờ nhưng chưa đủ chắc.
- safe: không thấy nguy cơ.
""".strip()


def parse_ai_json(content: str):
    text = content.strip()
    text = re.sub(r"^```(?:json)?\s*", "", text)
    text = re.sub(r"\s*```$", "", text)
    match = re.search(r"\{.*\}", text, re.S)
    if match:
        text = match.group(0)
    return json.loads(text)


def parse_omniroute_response(response: requests.Response):
    text = response.content.decode("utf-8", errors="replace").strip()
    if not text:
        raise ValueError("OmniRoute trả về response rỗng.")

    if not text.startswith("data:"):
        return response.json()

    content_parts = []
    final_payload = None

    for line in text.splitlines():
        line = line.strip()
        if not line.startswith("data:"):
            continue

        data = line.removeprefix("data:").strip()
        if not data or data == "[DONE]":
            continue

        try:
            chunk = json.loads(data)
        except json.JSONDecodeError:
            continue

        final_payload = chunk
        choice = (chunk.get("choices") or [{}])[0]
        delta = choice.get("delta") or {}
        message = choice.get("message") or {}
        content = delta.get("content") or message.get("content")
        if content:
            content_parts.append(content)

    if not content_parts:
        raise ValueError("OmniRoute không trả nội dung phân tích.")

    return {
        "choices": [{"message": {"content": "".join(content_parts)}}],
        "stream_payload": final_payload,
    }


def call_omniroute_fire_vision(payload: FireSafetyStreamRequest, frames: list[Any]):
    api_key = omniroute_api_key()
    if not api_key:
        raise ValueError("Thiếu OMNIROUTE_API_KEY.")

    frame_images = [frame_to_base64(frame) for frame in frames]
    content = [{"type": "text", "text": fire_safety_prompt(payload.camera_name, payload.location)}]
    content.extend(
        {
            "type": "image_url",
            "image_url": {"url": f"data:image/jpeg;base64,{image}", "detail": "low"},
        }
        for image in frame_images
    )

    response = requests.post(
        omniroute_base_url() + "/chat/completions",
        headers={"Authorization": f"Bearer {api_key}", "Content-Type": "application/json"},
        json={
            "model": fire_vision_model(),
            "temperature": 0,
            "max_tokens": 350,
            "response_format": {"type": "json_object"},
            "messages": [{"role": "user", "content": content}],
        },
        timeout=int(os.getenv("FIRE_AI_TIMEOUT", "90")),
    )
    response.raise_for_status()
    raw = parse_omniroute_response(response)
    message = raw.get("choices", [{}])[0].get("message", {})
    ai_payload = parse_ai_json(message.get("content", "{}"))
    risk_level = str(ai_payload.get("risk_level", "safe")).lower()

    if risk_level not in RISK_LEVEL_CODES:
        risk_level = "warning"

    confidence = float(ai_payload.get("confidence") or 0)
    return {
        "risk_level": risk_level,
        "risk_level_code": RISK_LEVEL_CODES[risk_level],
        "detected_fire": bool(ai_payload.get("detected_fire")),
        "detected_smoke": bool(ai_payload.get("detected_smoke")),
        "detected_smoking": bool(ai_payload.get("detected_smoking")),
        "confidence": max(0, min(1, confidence)),
        "summary": ai_payload.get("summary") or "AI chưa có mô tả.",
        "recommended_action": ai_payload.get("recommended_action") or "Tiếp tục theo dõi camera.",
        "evidence": ai_payload.get("evidence") if isinstance(ai_payload.get("evidence"), list) else [],
        "model": fire_vision_model(),
        "raw_provider_payload": raw,
    }


@app.post("/api/v1/fire-safety/analyze-stream")
def analyze_fire_safety_stream(payload: FireSafetyStreamRequest):
    try:
        frames = load_stream_frames(payload)
        result = call_omniroute_fire_vision(payload, frames)
        result["frame_count"] = len(frames)
        result["snapshot_base64"] = frame_to_base64(frames[-1], max_width=960, quality=82)
        result["camera_id"] = payload.camera_id
        result["building_id"] = payload.building_id
        return result
    except requests.HTTPError as error:
        detail = error.response.text[:500] if error.response is not None else str(error)
        raise HTTPException(status_code=422, detail=f"OmniRoute không phân tích được camera: {detail}")
    except Exception as error:
        raise HTTPException(status_code=422, detail=camera_error_message(error))


@app.post("/api/v1/fire-safety/test-stream")
def test_fire_safety_stream(payload: FireSafetyStreamRequest):
    try:
        if is_http_stream(str(payload.stream_url)):
            frame, used_url = load_quick_http_frame(str(payload.stream_url), payload.username, payload.password, max_seconds=6)
            frames = [frame]
        else:
            used_url = str(payload.stream_url)
            test_payload = payload.model_copy(update={"frame_count": 1, "window_seconds": 1})
            frames = load_stream_frames(test_payload)

        if not frames:
            raise ValueError("Không lấy được frame nào từ camera.")

        height, width = frames[-1].shape[:2]
        return {
            "success": True,
            "message": "Đã lấy được frame từ camera.",
            "camera_id": payload.camera_id,
            "building_id": payload.building_id,
            "width": width,
            "height": height,
            "resolved_stream_url": used_url,
            "snapshot_base64": frame_to_base64(frames[-1], max_width=640, quality=76),
        }
    except Exception as error:
        raise HTTPException(status_code=422, detail=camera_error_message(error))


if __name__ == "__main__":
    import uvicorn

    uvicorn.run("main:app", host="0.0.0.0", port=8000, reload=True)
