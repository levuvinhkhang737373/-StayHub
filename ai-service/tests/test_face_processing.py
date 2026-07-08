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

    def test_static_image_detection_is_still_enforced_in_fast_login(self):
        with (
            patch.object(ai_main, "validate_image_quality", return_value=(120.0, 42.0)),
            patch.object(ai_main, "extract_real_face", side_effect=ValueError("Phát hiện ảnh tĩnh")),
        ):
            with self.assertRaisesRegex(ValueError, "Phát hiện ảnh tĩnh"):
                ai_main.process_images([image()], require_liveness=False)


if __name__ == "__main__":
    unittest_main()
