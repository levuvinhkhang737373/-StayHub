export const FACE_CAPTURE_MAX_SIZE = 800
export const FACE_CAPTURE_QUALITY = 0.92
export const FACE_CAPTURE_FRAME_DELAY_MS = 650

export const FACE_REGISTRATION_STEPS = [
  {
    title: 'Nhìn thẳng vào camera',
    description: 'Giữ mặt ở giữa khung hình.',
  },
  {
    title: 'Xoay nhẹ mặt sang một bên',
    description: 'Xoay nhẹ để hệ thống kiểm tra chuyển động thật.',
  },
  {
    title: 'Đưa mặt gần hoặc xa camera nhẹ',
    description: 'Di chuyển nhẹ để hoàn tất đăng ký an toàn.',
  },
] as const

export function getFaceCaptureSize(videoWidth: number, videoHeight: number) {
  const scale = Math.min(1, FACE_CAPTURE_MAX_SIZE / Math.max(videoWidth, videoHeight))

  return {
    width: Math.round(videoWidth * scale),
    height: Math.round(videoHeight * scale),
  }
}

export async function delayFaceCapture(ms = FACE_CAPTURE_FRAME_DELAY_MS) {
  await new Promise((resolve) => window.setTimeout(resolve, ms))
}

export async function captureFaceImageFromVideo(video: HTMLVideoElement): Promise<Blob> {
  if (video.videoWidth === 0 || video.videoHeight === 0) {
    throw new Error('Camera chưa sẵn sàng, vui lòng chờ camera rõ nét rồi thử lại.')
  }

  const size = getFaceCaptureSize(video.videoWidth, video.videoHeight)
  const canvas = document.createElement('canvas')
  canvas.width = size.width
  canvas.height = size.height
  const context = canvas.getContext('2d')

  if (!context) {
    throw new Error('Không thể xử lý ảnh từ camera.')
  }

  context.drawImage(video, 0, 0, canvas.width, canvas.height)

  return new Promise((resolve, reject) => {
    canvas.toBlob((blob) => {
      if (!blob) {
        reject(new Error('Không thể chụp ảnh khuôn mặt.'))
        return
      }

      resolve(blob)
    }, 'image/jpeg', FACE_CAPTURE_QUALITY)
  })
}
