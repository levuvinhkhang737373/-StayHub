export interface CompressImageOptions {
  maxWidth?: number
  maxSizeKb?: number
  quality?: number
}

const DEFAULT_MAX_WIDTH = 1920
const DEFAULT_MAX_SIZE_KB = 800
const DEFAULT_QUALITY = 0.8

export async function compressImage(file: File, options: CompressImageOptions = {}): Promise<File> {
  if (!file.type.startsWith('image/')) {
    return file
  }

  const maxWidth = options.maxWidth ?? DEFAULT_MAX_WIDTH
  const maxSizeKb = options.maxSizeKb ?? DEFAULT_MAX_SIZE_KB
  const quality = options.quality ?? DEFAULT_QUALITY

  if (file.size <= maxSizeKb * 1024 && file.type === 'image/jpeg') {
    return file
  }

  const image = await loadBrowserImage(file)
  const scale = Math.min(1, maxWidth / image.width)
  const canvas = document.createElement('canvas')
  canvas.width = Math.max(1, Math.round(image.width * scale))
  canvas.height = Math.max(1, Math.round(image.height * scale))

  const context = canvas.getContext('2d')
  if (!context) {
    return file
  }

  context.drawImage(image, 0, 0, canvas.width, canvas.height)

  let compressedBlob = await canvasToBlob(canvas, quality)
  let nextQuality = quality

  while (compressedBlob.size > maxSizeKb * 1024 && nextQuality > 0.45) {
    nextQuality -= 0.08
    compressedBlob = await canvasToBlob(canvas, nextQuality)
  }

  return new File([compressedBlob], replaceExtension(file.name), {
    type: 'image/jpeg',
    lastModified: Date.now(),
  })
}

function loadBrowserImage(file: File): Promise<HTMLImageElement> {
  return new Promise((resolve, reject) => {
    const url = URL.createObjectURL(file)
    const image = new Image()

    image.onload = () => {
      URL.revokeObjectURL(url)
      resolve(image)
    }

    image.onerror = () => {
      URL.revokeObjectURL(url)
      reject(new Error('Không thể đọc ảnh đã chọn.'))
    }

    image.src = url
  })
}

function canvasToBlob(canvas: HTMLCanvasElement, quality: number): Promise<Blob> {
  return new Promise((resolve, reject) => {
    canvas.toBlob(
      (blob) => {
        if (blob) {
          resolve(blob)
          return
        }

        reject(new Error('Không thể nén ảnh.'))
      },
      'image/jpeg',
      quality,
    )
  })
}

function replaceExtension(fileName: string): string {
  const baseName = fileName.replace(/\.[^.]+$/, '') || 'meter-photo'
  return `${baseName}.jpg`
}
