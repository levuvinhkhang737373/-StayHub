import argparse
import asyncio
from types import SimpleNamespace

from main import frame_to_base64, load_quick_http_frame, load_stream_frames, is_http_stream


async def main():
    parser = argparse.ArgumentParser(description="Camera")
    parser.add_argument("url", help="URL camera, ví dụ http://192.168.x.x:8081 hoặc rtsp://user:pass@ip/stream")
    parser.add_argument("--username", default=None, help="Username nếu camera/app bật Basic Auth")
    parser.add_argument("--password", default=None, help="Password nếu camera/app bật Basic Auth")
    parser.add_argument("--source-type", type=int, default=2, choices=[1, 2, 3], help="1=snapshot, 2=http/mjpeg, 3=rtsp")
    parser.add_argument("--output", default="/tmp/stayhub-camera-test.jpg", help="File ảnh preview")
    args = parser.parse_args()

    if is_http_stream(args.url):
        frame, used_url = await load_quick_http_frame(args.url, args.username, args.password, max_seconds=8)
    else:
        used_url = args.url
        payload = SimpleNamespace(
            stream_url=args.url,
            username=args.username,
            password=args.password,
            source_type=args.source_type,
            frame_count=1,
            window_seconds=1,
        )
        frame = (await load_stream_frames(payload))[-1]

    image_base64 = frame_to_base64(frame, max_width=960, quality=82)
    import base64
    with open(args.output, "wb") as file:
        file.write(base64.b64decode(image_base64))

    height, width = frame.shape[:2]
    print("OK")
    print(f"resolved_url={used_url}")
    print(f"size={width}x{height}")
    print(f"preview={args.output}")


if __name__ == "__main__":
    asyncio.run(main())
