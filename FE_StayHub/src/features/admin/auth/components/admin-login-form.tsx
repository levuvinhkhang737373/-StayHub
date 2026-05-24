import { useCallback, useEffect, useMemo, useRef, useState } from 'react'
import { Eye, LockKeyhole, ScanFace, UserRound, X } from 'lucide-react'
import { loginAdmin, loginAdminWithFace } from '../services/admin-auth.service'
import type { AdminLoginResult } from '../types/admin-auth.model'
import { hasAdminLoginErrors, validateAdminLoginForm, type AdminLoginFormErrors } from '../validation/admin-login.validation'

interface AdminLoginFormProps {
  onLoginSuccess: (payload: AdminLoginResult) => Promise<void>
}

export function AdminLoginForm({ onLoginSuccess }: AdminLoginFormProps) {
  const [username, setUsername] = useState('')
  const [password, setPassword] = useState('')
  const [isSubmitting, setIsSubmitting] = useState(false)
  const [isFaceOpen, setIsFaceOpen] = useState(false)
  const [isCameraReady, setIsCameraReady] = useState(false)
  const [hasFaceScanStarted, setHasFaceScanStarted] = useState(false)
  const [errorMessage, setErrorMessage] = useState<string | null>(null)
  const [formErrors, setFormErrors] = useState<AdminLoginFormErrors>({})
  const [faceErrorMessage, setFaceErrorMessage] = useState<string | null>(null)
  const [successMessage, setSuccessMessage] = useState<string | null>(null)
  const videoRef = useRef<HTMLVideoElement | null>(null)
  const streamRef = useRef<MediaStream | null>(null)
  const isFaceOpenRef = useRef(false)

  const isDisabled = useMemo(() => {
    return hasAdminLoginErrors(validateAdminLoginForm({ username, password })) || isSubmitting
  }, [isSubmitting, password, username])

  const waitForCameraReady = useCallback(async (video: HTMLVideoElement) => {
    const startedAt = Date.now()

    while (Date.now() - startedAt < 8000) {
      if (video.readyState >= HTMLMediaElement.HAVE_METADATA && video.videoWidth > 0 && video.videoHeight > 0) {
        await new Promise((resolve) => window.setTimeout(resolve, 500))
        return
      }

      await new Promise((resolve) => window.setTimeout(resolve, 150))
    }

    throw new Error('Camera chưa sẵn sàng, vui lòng thử lại.')
  }, [])

  const stopCamera = useCallback(() => {
    streamRef.current?.getTracks().forEach((track) => track.stop())
    streamRef.current = null
    setIsCameraReady(false)
  }, [])

  const startCamera = useCallback(async () => {
    try {
      setFaceErrorMessage(null)
      setIsCameraReady(false)
      const stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user', width: { ideal: 1280 }, height: { ideal: 720 } }, audio: false })
      streamRef.current = stream

      if (videoRef.current) {
        videoRef.current.srcObject = stream
        await videoRef.current.play()
        await waitForCameraReady(videoRef.current)
        setIsCameraReady(true)
      }
    } catch {
      setIsCameraReady(false)
      setFaceErrorMessage('Không mở được camera, vui lòng cấp quyền camera cho trình duyệt.')
    }
  }, [waitForCameraReady])

  useEffect(() => {
    isFaceOpenRef.current = isFaceOpen

    if (!isFaceOpen) return

    const timer = window.setTimeout(() => {
      void startCamera()
    }, 0)

    return () => {
      window.clearTimeout(timer)
      stopCamera()
    }
  }, [isFaceOpen, startCamera, stopCamera])

  async function handleSubmit(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault()
    const nextErrors = validateAdminLoginForm({ username, password })
    setFormErrors(nextErrors)

    if (hasAdminLoginErrors(nextErrors)) {
      setErrorMessage('Vui lòng kiểm tra lại thông tin đăng nhập.')
      return
    }

    setIsSubmitting(true)
    setErrorMessage(null)
    setSuccessMessage(null)

    try {
      const response = await loginAdmin({
        username: username.trim(),
        password: password.trim(),
      })

      await onLoginSuccess(response.result)
      setSuccessMessage(response.message)
    } catch (error) {
      const nextMessage = error instanceof Error ? error.message : 'Đăng nhập thất bại, vui lòng thử lại.'
      setErrorMessage(nextMessage)
    } finally {
      setIsSubmitting(false)
    }
  }



  async function captureFaceImage(): Promise<Blob> {
    const video = videoRef.current
    if (!isCameraReady || !video || video.videoWidth === 0 || video.videoHeight === 0) {
      throw new Error('Camera chưa sẵn sàng, vui lòng chờ camera rõ nét rồi thử lại.')
    }

    const canvas = document.createElement('canvas')
    canvas.width = video.videoWidth
    canvas.height = video.videoHeight
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
      }, 'image/jpeg', 0.96)
    })
  }

  async function captureFaceImages(): Promise<Blob[]> {
    const images: Blob[] = []

    for (let index = 0; index < 2; index += 1) {
      images.push(await captureFaceImage())
      if (index < 1) {
        await new Promise((resolve) => window.setTimeout(resolve, 350))
      }
    }

    return images
  }

  async function handleFaceLogin() {
    setHasFaceScanStarted(true)
    setFaceErrorMessage(null)
    setSuccessMessage(null)

    while (isFaceOpenRef.current) {
      try {
        const images = await captureFaceImages()
        const response = await loginAdminWithFace(images)
        await onLoginSuccess(response.result)
        setSuccessMessage(response.message)
        setIsFaceOpen(false)
        return
      } catch (error) {
        const nextMessage = error instanceof Error ? error.message : 'Đăng nhập FaceID thất bại, vui lòng thử lại.'
        setFaceErrorMessage(nextMessage)
        await new Promise((resolve) => window.setTimeout(resolve, 700))
      }
    }

  }

  return (
    <section className="relative flex h-full w-full flex-col overflow-hidden rounded-[2.5rem] border border-[#3d2a18]/10 bg-[#fffaf1]/80 p-6 text-left text-[#24170d] shadow-2xl shadow-[#6b3f1d]/10 backdrop-blur-2xl sm:p-8 lg:p-10">
      <div className="pointer-events-none absolute inset-x-0 top-0 h-2 bg-gradient-to-r from-[#a65f16] via-[#f3c56b] to-[#0f766e]" />
      <div className="pointer-events-none absolute -right-20 -top-24 h-56 w-56 rounded-full bg-[#f3c56b]/30 blur-3xl" />
      <div className="pointer-events-none absolute -bottom-24 left-8 h-48 w-48 rounded-full bg-[#0f766e]/10 blur-3xl" />

      <div className="relative mb-8">
        <h1 className="whitespace-nowrap text-4xl font-black leading-none tracking-[-0.07em] text-[#24170d] sm:text-5xl xl:text-6xl">Đăng nhập quản trị</h1>
      </div>

      <form className="relative space-y-5" onSubmit={handleSubmit}>
        <div>
          <label className="mb-2 block text-sm font-black text-[#3d2a18]" htmlFor="username">
            Tên đăng nhập
          </label>
          <div className="relative">
            <UserRound className="pointer-events-none absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-[#a65f16]" />
            <input
              id="username"
              value={username}
              onChange={(event) => {
                setUsername(event.target.value)
                setFormErrors((current) => ({ ...current, username: undefined }))
              }}
              placeholder="Nhập tên đăng nhập"
              autoComplete="username"
              aria-invalid={!!formErrors.username}
              className="h-13 w-full rounded-2xl border border-[#3d2a18]/10 bg-white/75 px-12 text-sm font-bold text-[#24170d] outline-none transition placeholder:text-[#9d8e7a] hover:border-[#a65f16]/30 focus:border-[#a65f16] focus:bg-white focus:ring-4 focus:ring-[#f3c56b]/25"
            />
          </div>
          {formErrors.username ? <p className="mt-2 text-xs font-black text-rose-700" role="alert">{formErrors.username}</p> : null}
        </div>

        <div>
          <label className="mb-2 block text-sm font-black text-[#3d2a18]" htmlFor="password">
            Mật khẩu
          </label>
          <div className="relative">
            <LockKeyhole className="pointer-events-none absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-[#a65f16]" />
            <input
              id="password"
              type="password"
              value={password}
              onChange={(event) => {
                setPassword(event.target.value)
                setFormErrors((current) => ({ ...current, password: undefined }))
              }}
              placeholder="Nhập mật khẩu"
              autoComplete="current-password"
              aria-invalid={!!formErrors.password}
              className="h-13 w-full rounded-2xl border border-[#3d2a18]/10 bg-white/75 px-12 text-sm font-bold text-[#24170d] outline-none transition placeholder:text-[#9d8e7a] hover:border-[#a65f16]/30 focus:border-[#a65f16] focus:bg-white focus:ring-4 focus:ring-[#f3c56b]/25"
            />
          </div>
          {formErrors.password ? <p className="mt-2 text-xs font-black text-rose-700" role="alert">{formErrors.password}</p> : null}
        </div>

        {errorMessage ? <div className="rounded-2xl border border-rose-900/10 bg-rose-50 px-4 py-3 text-sm font-bold leading-6 text-rose-800" role="alert">{errorMessage}</div> : null}

        {successMessage ? <div className="rounded-2xl border border-emerald-900/10 bg-emerald-50 px-4 py-3 text-sm font-bold leading-6 text-emerald-800" role="status">{successMessage}</div> : null}

        <button
          type="submit"
          disabled={isDisabled}
          className="group inline-flex h-13 w-full cursor-pointer items-center justify-center rounded-2xl bg-[#24170d] px-4 text-base font-black text-[#fff4df] shadow-xl shadow-[#24170d]/20 transition duration-200 hover:-translate-y-0.5 hover:bg-[#3d2a18] hover:shadow-[#a65f16]/20 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-[#a65f16]/25 disabled:translate-y-0 disabled:cursor-not-allowed disabled:bg-[#c8bba6] disabled:text-[#7c6d5b] disabled:shadow-none"
        >
          {isSubmitting ? 'Đang đăng nhập...' : 'Mở bảng điều khiển'}
        </button>

        <button
          type="button"
          onClick={() => setIsFaceOpen(true)}
          className="inline-flex h-13 w-full cursor-pointer items-center justify-center gap-2 rounded-2xl border border-[#0f766e]/20 bg-[#0f766e]/10 px-4 text-base font-black text-[#0f5f59] transition duration-200 hover:border-[#0f766e]/35 hover:bg-[#0f766e]/15 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-[#0f766e]/15"
        >
          <ScanFace className="h-5 w-5" /> Đăng nhập bằng Face ID
        </button>
      </form>

      <div className="pointer-events-none relative mt-8 hidden flex-1 items-end lg:flex">
        <div className="relative h-56 w-full overflow-hidden rounded-[2rem] border border-[#3d2a18]/10 bg-[#fff7e8] shadow-2xl shadow-[#6b3f1d]/15">
          <div className="absolute inset-0 bg-[radial-gradient(circle_at_78%_18%,rgba(243,197,107,0.75),transparent_19%),radial-gradient(circle_at_18%_88%,rgba(15,118,110,0.18),transparent_30%),linear-gradient(135deg,rgba(255,255,255,0.75),rgba(239,226,207,0.45))]" />
          <div className="absolute left-8 top-8 h-20 w-20 rounded-[1.75rem] bg-[#24170d] shadow-xl shadow-[#24170d]/20" />
          <div className="absolute left-14 top-14 h-20 w-20 rounded-[1.75rem] border border-[#a65f16]/25 bg-[#f3c56b]/70 backdrop-blur-sm" />
          <div className="absolute bottom-8 left-8 right-8 h-20 rounded-[1.75rem] border border-[#3d2a18]/10 bg-white/55 backdrop-blur-md" />
          <div className="absolute bottom-16 left-14 h-3 w-32 rounded-full bg-[#24170d]/18" />
          <div className="absolute bottom-12 left-14 h-3 w-48 rounded-full bg-[#0f766e]/20" />
          <div className="absolute -right-8 bottom-5 h-44 w-44 rotate-12 rounded-[2rem] border border-[#0f766e]/20" />
          <div className="absolute right-12 top-10 h-28 w-28 rounded-full border border-[#a65f16]/20" />
        </div>
      </div>

      {isFaceOpen ? (
        <div className="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-[#24170d]/70 p-4 backdrop-blur-md">
          <div className="relative w-full max-w-lg overflow-hidden rounded-[2.5rem] border border-[#3d2a18]/10 bg-[#fffaf1] text-[#24170d] shadow-2xl shadow-[#24170d]/35">
            <div className="pointer-events-none absolute inset-x-0 top-0 h-2 bg-gradient-to-r from-[#0f766e] via-[#f3c56b] to-[#a65f16]" />
            <div className="pointer-events-none absolute -right-16 -top-20 h-52 w-52 rounded-full bg-[#0f766e]/12 blur-3xl" />
            <div className="relative flex items-start justify-between border-b border-[#3d2a18]/10 px-5 py-5 sm:px-6">
              <div className="pr-4">
                <p className="text-xs font-black uppercase tracking-[0.3em] text-[#0f766e]">Biometric Keycard</p>
                <h2 className="mt-2 text-3xl font-black tracking-tight text-[#24170d]">Đăng nhập Face ID</h2>
                <p className="mt-1 text-sm font-semibold leading-5 text-[#6f6254]">Nhìn thẳng rồi xoay nhẹ mặt khi quét để chống ảnh giả.</p>
              </div>
              <button
                type="button"
                aria-label="Đóng đăng nhập Face ID"
                onClick={() => {
                  isFaceOpenRef.current = false
                  setIsFaceOpen(false)
                  setHasFaceScanStarted(false)
                  setFaceErrorMessage(null)
                }}
                className="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl border border-[#3d2a18]/10 bg-white/65 text-[#6f6254] transition hover:bg-rose-50 hover:text-rose-700 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-rose-900/10"
              >
                <X className="h-5 w-5" />
              </button>
            </div>
            <div className="relative space-y-5 p-5 sm:p-6">
              <div className="flex justify-center">
                <div className="relative flex aspect-square w-full max-w-[320px] items-center justify-center rounded-[2rem] bg-[#24170d] p-2 shadow-2xl shadow-[#24170d]/25 ring-1 ring-[#3d2a18]/10">
                  <video ref={videoRef} playsInline muted className="h-full w-full rounded-3xl object-cover" />
                  <div className="pointer-events-none absolute inset-2 rounded-3xl ring-2 ring-[#f3c56b]" />
                  <div className="pointer-events-none absolute left-7 top-7 h-12 w-12 rounded-tl-3xl border-l-4 border-t-4 border-[#f3c56b]" />
                  <div className="pointer-events-none absolute right-7 top-7 h-12 w-12 rounded-tr-3xl border-r-4 border-t-4 border-[#f3c56b]" />
                  <div className="pointer-events-none absolute bottom-7 left-7 h-12 w-12 rounded-bl-3xl border-b-4 border-l-4 border-[#f3c56b]" />
                  <div className="pointer-events-none absolute bottom-7 right-7 h-12 w-12 rounded-br-3xl border-b-4 border-r-4 border-[#f3c56b]" />
                  <div className="pointer-events-none absolute left-8 right-8 top-1/2 h-px bg-gradient-to-r from-transparent via-[#f3c56b] to-transparent" />
                  {!isCameraReady ? <div className="absolute inset-2 flex items-center justify-center rounded-3xl bg-[#24170d]/75 px-6 text-center text-sm font-black text-[#fff4df]">Đang làm nét camera...</div> : null}
                </div>
              </div>
              {faceErrorMessage ? <div className="rounded-2xl border border-rose-900/10 bg-rose-50 px-4 py-3 text-sm font-bold leading-6 text-rose-800" role="alert">{faceErrorMessage}</div> : null}
              {hasFaceScanStarted ? (
                <div className="inline-flex min-h-13 w-full items-center justify-center gap-2 rounded-2xl border border-[#0f766e]/20 bg-[#0f766e]/10 px-4 py-3 text-center font-black text-[#0f5f59]">
                  <Eye className="h-5 w-5 animate-pulse" /> Đang quét đến khi đăng nhập thành công...
                </div>
              ) : (
                <button
                  type="button"
                  onClick={handleFaceLogin}
                  disabled={!isCameraReady}
                  className="inline-flex h-13 w-full cursor-pointer items-center justify-center gap-2 rounded-2xl bg-[#0f766e] px-4 font-black text-white shadow-xl shadow-[#0f766e]/20 transition duration-200 hover:-translate-y-0.5 hover:bg-[#0f5f59] focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-[#0f766e]/20 disabled:translate-y-0 disabled:cursor-not-allowed disabled:bg-[#c8bba6] disabled:text-[#7c6d5b] disabled:shadow-none"
                >
                  <ScanFace className="h-5 w-5" /> {isCameraReady ? 'Quét và đăng nhập' : 'Đang chuẩn bị camera...'}
                </button>
              )}
            </div>
          </div>
        </div>
      ) : null}
    </section>
  )
}
