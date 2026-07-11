import importlib.util
from pathlib import Path
from unittest import TestCase, main as unittest_main
from unittest.mock import patch

import numpy as np

MODULE_PATH = Path(__file__).resolve().parents[1] / "main.py"
spec = importlib.util.spec_from_file_location("stayhub_ai_main", MODULE_PATH)
ai_main = importlib.util.module_from_spec(spec)
spec.loader.exec_module(ai_main)


def image():
    return np.full((240, 320, 3), 120, dtype=np.uint8)


def embedding(value):
    return np.full(128, value, dtype=np.float32)


def facial_area():
    return {"x": 100, "y": 60, "w": 120, "h": 130}


class FaceProcessingTest(TestCase):
    def test_login_mode_accepts_single_real_frame_without_motion_check(self):
        with (
            patch.object(ai_main, "validate_image_quality", return_value=(120.0, 42.0)),
            patch.object(ai_main, "extract_real_face", return_value=(facial_area(), 0.7)) as extract_real_face,
            patch.object(ai_main, "extract_embedding", return_value=embedding(0.2)),
        ):
            vector, frames, max_distance, movement = ai_main.process_images([image()], require_liveness=False)

        self.assertEqual(len(vector), 128)
        self.assertEqual(len(frames), 1)
        self.assertEqual(max_distance, 0)
        self.assertEqual(movement, 0)
        extract_real_face.assert_called_once()

    def test_register_mode_still_requires_two_frames_for_liveness(self):
        with self.assertRaisesRegex(ValueError, "Cần ít nhất 2 khung hình"):
            ai_main.process_images([image()], require_liveness=True)


    def test_register_mode_accepts_three_frames_with_enough_motion(self):
        frames = [image(), image(), image()]
        areas = [
            {"x": 100, "y": 60, "w": 120, "h": 130},
            {"x": 108, "y": 60, "w": 120, "h": 130},
            {"x": 108, "y": 60, "w": 128, "h": 138},
        ]

        with (
            patch.object(ai_main, "validate_image_quality", return_value=(120.0, 42.0)),
            patch.object(ai_main, "extract_real_face", side_effect=[(area, 0.8) for area in areas]),
            patch.object(ai_main, "extract_embedding", side_effect=[embedding(0.2), embedding(0.2), embedding(0.2)]),
        ):
            vector, processed_frames, max_distance, movement = ai_main.process_images(frames, require_liveness=True)

        self.assertEqual(len(vector), 128)
        self.assertEqual(len(processed_frames), 3)
        self.assertEqual(max_distance, 0)
        self.assertGreaterEqual(movement, ai_main.MIN_LIVENESS_MOVEMENT)

    def test_register_mode_rejects_static_frames_without_motion(self):
        frames = [image(), image(), image()]

        with (
            patch.object(ai_main, "validate_image_quality", return_value=(120.0, 42.0)),
            patch.object(ai_main, "extract_real_face", return_value=(facial_area(), 0.8)),
            patch.object(ai_main, "extract_embedding", side_effect=[embedding(0.2), embedding(0.2), embedding(0.2)]),
        ):
            with self.assertRaisesRegex(ValueError, "Chưa nhận đủ chuyển động"):
                ai_main.process_images(frames, require_liveness=True)

    def test_register_mode_rejects_different_faces_between_frames(self):
        frames = [image(), image(), image()]

        with (
            patch.object(ai_main, "validate_image_quality", return_value=(120.0, 42.0)),
            patch.object(ai_main, "extract_real_face", side_effect=[
                (facial_area(), 0.8),
                ({"x": 108, "y": 60, "w": 120, "h": 130}, 0.8),
                ({"x": 108, "y": 60, "w": 128, "h": 138}, 0.8),
            ]),
            patch.object(ai_main, "extract_embedding", side_effect=[embedding(0.2), embedding(-0.2), embedding(0.2)]),
        ):
            with self.assertRaisesRegex(ValueError, "Các khung hình không cùng một khuôn mặt"):
                ai_main.process_images(frames, require_liveness=True)

    def test_static_image_detection_is_still_enforced_in_fast_login(self):
        with (
            patch.object(ai_main, "validate_image_quality", return_value=(120.0, 42.0)),
            patch.object(ai_main, "extract_real_face", side_effect=ValueError("Phát hiện ảnh tĩnh")),
        ):
            with self.assertRaisesRegex(ValueError, "Phát hiện ảnh tĩnh"):
                ai_main.process_images([image()], require_liveness=False)


if __name__ == "__main__":
    unittest_main()
