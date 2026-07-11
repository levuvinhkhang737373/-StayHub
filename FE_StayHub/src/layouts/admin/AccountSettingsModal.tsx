import { useCallback, useEffect, useRef, useState } from 'react'
import { createPortal } from 'react-dom'
import { AnimatePresence, motion } from 'motion/react'
import { CheckCircle, Lock, ScanFace, Trash2, User, X } from 'lucide-react'
import { changeAdminPassword, deleteAdminFaceId, registerAdminFaceId, updateAdminProfile } from '../../features/admin/auth/services/admin-auth.service'
import { useAdminSession } from '../../features/admin/auth/hooks/use-admin-session'
import { captureFaceImageFromVideo, delayFaceCapture, FACE_REGISTRATION_STEPS } from '../../features/admin/auth/utils/face-capture'
import { cn } from '../../shared/lib/utils/cn'
import { resolveAssetUrl } from '../../shared/lib/utils/asset-url'
import { validateChangePasswordForm, validateDeleteFaceIdPassword, validateProfileForm, type ChangePasswordErrors, type ChangePasswordForm, type ProfileFormErrors } from './account-settings.validation'

interface AccountSettingsModalProps {
  isOpen: boolean
  onClose: () => void
}

type AccountSettingsTab = 'info' | 'password' | 'face'

const initialPasswordForm: ChangePasswordForm = {
  currentPassword: '',
  newPassword: '',
  newPasswordConfirmation: '',
}

const tabs: Array<{ id: AccountSettingsTab; label: string; icon: React.ElementType }> = [
  { id: 'info', label: 'Thông tin cá nhân', icon: User },
  { id: 'password', label: 'Mật khẩu', icon: Lock },
  { id: 'face', label: 'Đăng ký khuôn mặt', icon: ScanFace },
]

export function AccountSettingsModal({ isOpen, onClose }: AccountSettingsModalProps) {
  const [activeTab, setActiveTab] = useState<AccountSettingsTab>('info')
  const [isCameraOpen, setIsCameraOpen] = useState(false)
  const [isFaceSubmitting, setIsFaceSubmitting] = useState(false)
  const [isCameraReady, setIsCameraReady] = useState(false)
  const [hasFaceRegistrationStarted, setHasFaceRegistrationStarted] = useState(false)
  const [faceRegistrationStep, setFaceRegistrationStep] = useState(0)
  const [faceMessage, setFaceMessage] = useState<string | null>(null)
  const [faceError, setFaceError] = useState<string | null>(null)
  const [currentPassword, setCurrentPassword] = useState('')
  const [passwordForm, setPasswordForm] = useState<ChangePasswordForm>(initialPasswordForm)
  const [passwordErrors, setPasswordErrors] = useState<ChangePasswordErrors>({})
  const [passwordMessage, setPasswordMessage] = useState<string | null>(null)
  const [passwordError, setPasswordError] = useState<string | null>(null)
  const [isPasswordSubmitting, setIsPasswordSubmitting] = useState(false)
  const [fullName, setFullName] = useState('')
  const [phone, setPhone] = useState('')
  const [email, setEmail] = useState('')
  const [profileErrors, setProfileErrors] = useState<ProfileFormErrors>({})
  const [infoMessage, setInfoMessage] = useState<string | null>(null)
  const [infoError, setInfoError] = useState<string | null>(null)
  const [isInfoSubmitting, setIsInfoSubmitting] = useState(false)
  const [avatarFile, setAvatarFile] = useState<File | null>(null)
  const [avatarPreview, setAvatarPreview] = useState<string | null>(null)
  const [isImageError, setIsImageError] = useState(false)
  const avatarInputRef = useRef<HTMLInputElement | null>(null)
  const videoRef = useRef<HTMLVideoElement | null>(null)
  const streamRef = useRef<MediaStream | null>(null)
  const isFaceRegistrationOpenRef = useRef(false)
  const { session, saveSession } = useAdminSession()
  const admin = session?.admin
  const hasRegisteredFaceId = admin?.has_faceid === true || Boolean(admin?.image_path_faceid)

  useEffect(() => {
    if (isOpen && admin) {
      setFullName(admin.full_name || '')
      setPhone(admin.phone || '')
      setEmail(admin.email || '')
      setProfileErrors({})
      setInfoMessage(null)
      setInfoError(null)
      setAvatarFile(null)
      setAvatarPreview(null)
      setIsImageError(false)
    }
  }, [isOpen, admin])

  const handleAvatarChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    if (e.target.files && e.target.files[0]) {
      const file = e.target.files[0]
      setAvatarFile(file)
      setAvatarPreview(URL.createObjectURL(file))
      setIsImageError(false)
    }
  }

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
      setFaceError(null)
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
      setFaceError('Không mở được camera, vui lòng cấp quyền camera cho trình duyệt.')
    }
  }, [waitForCameraReady])

  useEffect(() => {
    if (!isOpen || activeTab !== 'face' || !isCameraOpen) return

    const timer = window.setTimeout(() => {
      void startCamera()
    }, 0)

    return () => {
      window.clearTimeout(timer)
      stopCamera()
    }
  }, [activeTab, isCameraOpen, isOpen, startCamera, stopCamera])

  function resetPasswordForm() {
    setPasswordForm(initialPasswordForm)
    setPasswordErrors({})
    setPasswordMessage(null)
    setPasswordError(null)
  }

  function closeModal() {
    isFaceRegistrationOpenRef.current = false
    stopCamera()
    setIsCameraOpen(false)
    setHasFaceRegistrationStarted(false)
    setFaceRegistrationStep(0)
    setCurrentPassword('')
    resetPasswordForm()
    onClose()
  }



  async function captureFaceImage(): Promise<Blob> {
    const video = videoRef.current
    if (!isCameraReady || !video || video.videoWidth === 0 || video.videoHeight === 0) {
      throw new Error('Camera chưa sẵn sàng, vui lòng chờ camera rõ nét rồi thử lại.')
    }

    return captureFaceImageFromVideo(video)
  }

  async function captureFaceImages(): Promise<Blob[]> {
    const images: Blob[] = []

    for (let index = 0; index < FACE_REGISTRATION_STEPS.length; index += 1) {
      setFaceRegistrationStep(index)
      images.push(await captureFaceImage())
      if (index < FACE_REGISTRATION_STEPS.length - 1) {
        await delayFaceCapture()
      }
    }

    return images
  }

  async function handleRegisterFaceId() {
    isFaceRegistrationOpenRef.current = true
    setHasFaceRegistrationStarted(true)
    setFaceRegistrationStep(0)
    setIsFaceSubmitting(true)
    setFaceError(null)
    setFaceMessage(null)

    try {
      const images = await captureFaceImages()
      if (!isFaceRegistrationOpenRef.current) return
      const response = await registerAdminFaceId(images)
      isFaceRegistrationOpenRef.current = false
      setFaceError(null)
      setFaceMessage(response.message || 'Đăng ký FaceID thành công.')
      setIsCameraOpen(false)
      saveSession(response.result)
    } catch (error) {
      setFaceError(error instanceof Error ? error.message : 'Chưa thể đăng ký FaceID. Vui lòng thử lại.')
    } finally {
      isFaceRegistrationOpenRef.current = false
      setHasFaceRegistrationStarted(false)
      setIsFaceSubmitting(false)
      setFaceRegistrationStep(0)
    }
  }

  async function handleDeleteFaceId() {
    const passwordError = validateDeleteFaceIdPassword(currentPassword)

    if (passwordError) {
      setFaceError(passwordError)
      return
    }

    setIsFaceSubmitting(true)
    setFaceError(null)
    setFaceMessage(null)

    try {
      const response = await deleteAdminFaceId(currentPassword.trim())
      saveSession(response.result)
      setFaceMessage(response.message)
      setCurrentPassword('')
      setIsCameraOpen(false)
    } catch (error) {
      setFaceError(error instanceof Error ? error.message : 'Xóa FaceID thất bại, vui lòng thử lại.')
    } finally {
      setIsFaceSubmitting(false)
    }
  }

  function updatePasswordField(field: keyof ChangePasswordForm, value: string) {
    setPasswordForm((current) => ({ ...current, [field]: value }))
    setPasswordErrors((current) => ({ ...current, [field]: undefined }))
    setPasswordError(null)
    setPasswordMessage(null)
  }

  async function handleChangePassword() {
    const errors = validateChangePasswordForm(passwordForm)

    setPasswordErrors(errors)
    setPasswordError(null)
    setPasswordMessage(null)

    if (Object.keys(errors).length > 0) {
      return
    }

    setIsPasswordSubmitting(true)

    try {
      const response = await changeAdminPassword({
        current_password: passwordForm.currentPassword.trim(),
        new_password: passwordForm.newPassword,
        new_password_confirmation: passwordForm.newPasswordConfirmation,
      })

      saveSession(response.result)
      setPasswordForm(initialPasswordForm)
      setPasswordMessage(response.message)
    } catch (error) {
      setPasswordError(error instanceof Error ? error.message : 'Đổi mật khẩu thất bại, vui lòng thử lại.')
    } finally {
      setIsPasswordSubmitting(false)
    }
  }

  async function handleSaveProfile() {
    const errors = validateProfileForm({ fullName, email, phone })

    setProfileErrors(errors)
    setInfoError(null)
    setInfoMessage(null)

    if (Object.keys(errors).length > 0) {
      return
    }

    setIsInfoSubmitting(true)

    try {
      const response = await updateAdminProfile({
        full_name: fullName.trim(),
        email: email.trim(),
        phone: phone.trim() || undefined,
        avatar: avatarFile,
      })

      saveSession(response.result)
      setInfoMessage(response.message || 'Cập nhật thông tin cá nhân thành công')
      setAvatarFile(null)
      setAvatarPreview(null)
    } catch (error) {
      if (error && typeof error === 'object' && 'validationErrors' in error) {
        const validationErrors = error.validationErrors as Record<string, string[]> | null
        setProfileErrors({
          fullName: validationErrors?.full_name?.[0],
          phone: validationErrors?.phone?.[0],
          email: validationErrors?.email?.[0],
        })
      }

      setInfoError(error instanceof Error ? error.message : 'Cập nhật thông tin cá nhân thất bại, vui lòng thử lại.')
    } finally {
      setIsInfoSubmitting(false)
    }
  }

  function updateProfileField(field: keyof ProfileFormErrors, value: string) {
    if (field === 'fullName') {
      setFullName(value)
    }

    if (field === 'phone') {
      setPhone(value.replace(/[^0-9]/g, ''))
    }

    if (field === 'email') {
      setEmail(value)
    }

    setProfileErrors((current) => ({ ...current, [field]: undefined }))
    setInfoError(null)
    setInfoMessage(null)
  }

  const modal = (
    <AnimatePresence>
      {isOpen && (
        <div className="fixed inset-0 z-1000 flex items-center justify-center p-4">
          <motion.div initial={{ opacity: 0 }} animate={{ opacity: 1 }} exit={{ opacity: 0 }} onClick={closeModal} className="absolute inset-0 bg-[#24170d]/68 backdrop-blur-md" />
          <motion.div
            initial={{ opacity: 0, y: 18, scale: 0.96 }}
            animate={{ opacity: 1, y: 0, scale: 1 }}
            exit={{ opacity: 0, y: 10, scale: 0.96 }}
            transition={{ duration: 0.2, ease: 'easeOut' }}
            className="relative z-10 flex max-h-[calc(100dvh-2rem)] w-full max-w-3xl flex-col overflow-hidden rounded-4xl border border-[#3d2a18]/10 bg-[#fffaf1] text-[#24170d] shadow-2xl shadow-[#24170d]/35"
          >
            <div className="relative overflow-hidden border-b border-[#3d2a18]/10 bg-[#24170d] px-6 py-5 text-[#fff4df]">
              <div className="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_16%_12%,rgba(243,197,107,0.24),transparent_28%),radial-gradient(circle_at_86%_18%,rgba(15,118,110,0.22),transparent_30%)]" />
              <div className="relative flex items-center justify-between gap-4">
                <div className="flex min-w-0 items-center gap-3">
                  <div className="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl border border-[#f8e8c8]/12 bg-[#f8e8c8]/10 text-[#f3c56b] shadow-sm">
                    <User className="h-5 w-5" />
                  </div>
                  <div className="min-w-0">
                    <h2 className="text-xl font-black tracking-tight text-[#fff4df]">Cài đặt Tài khoản</h2>
                    <p className="mt-0.5 truncate text-[10px] font-black uppercase tracking-[0.24em] text-[#f3c56b]">{admin?.full_name || admin?.username || 'Admin'}</p>
                  </div>
                </div>
                <button type="button" onClick={closeModal} aria-label="Đóng cài đặt tài khoản" className="inline-flex h-11 w-11 shrink-0 cursor-pointer items-center justify-center rounded-2xl border border-[#f8e8c8]/12 bg-[#f8e8c8]/10 text-[#f8e8c8]/75 transition hover:bg-rose-50 hover:text-rose-700 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-[#f3c56b]/20">
                  <X className="h-5 w-5" />
                </button>
              </div>
            </div>

            <div className="flex gap-2 overflow-x-auto border-b border-[#3d2a18]/10 bg-[#fff7e8]/78 px-6 pt-2">
              {tabs.map((tab) => (
                <button
                  key={tab.id}
                  type="button"
                  onClick={() => setActiveTab(tab.id)}
                  className={cn('flex min-h-12 shrink-0 cursor-pointer items-center gap-2 border-b-2 px-1 pb-3 pt-2 text-sm font-black transition-colors focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-[#f3c56b]/20', activeTab === tab.id ? 'border-[#24170d] text-[#24170d]' : 'border-transparent text-[#8b5e34]/60 hover:text-[#3d2a18]')}
                >
                  <tab.icon className="h-4 w-4" />
                  {tab.label}
                </button>
              ))}
            </div>

            <div className="custom-scrollbar overflow-y-auto bg-[#fffaf1] p-6 md:p-8">
              <AnimatePresence mode="wait">
                {activeTab === 'info' && (
                  <motion.div key="info" initial={{ opacity: 0, y: 10 }} animate={{ opacity: 1, y: 0 }} exit={{ opacity: 0, y: -10 }} className="space-y-5">
                    {infoError ? <div className="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-bold text-rose-700">{infoError}</div> : null}
                    {infoMessage ? <div className="rounded-2xl border border-[#0f766e]/20 bg-[#0f766e]/10 px-4 py-3 text-sm font-bold text-[#0f5f59]">{infoMessage}</div> : null}

                    <div className="mb-6 flex items-center gap-6">
                      <div className="flex h-24 w-24 items-center justify-center overflow-hidden rounded-full border-4 border-[#fffaf1] bg-[#efe2cf]/70 shadow-lg shadow-[#6b3f1d]/12">
                        {avatarPreview ? (
                          <img src={avatarPreview} alt="Avatar Preview" className="h-full w-full object-cover" />
                        ) : (admin?.avatar_url && !isImageError) ? (
                          <img
                            src={resolveAssetUrl(admin.avatar_url)}
                            alt="Avatar"
                            className="h-full w-full object-cover"
                            onError={() => setIsImageError(true)}
                          />
                        ) : (
                          <User className="h-10 w-10 text-[#8b5e34]" />
                        )}
                      </div>
                      <button
                        type="button"
                        onClick={() => avatarInputRef.current?.click()}
                        className="cursor-pointer rounded-2xl border border-[#3d2a18]/10 bg-[#fff7e8] px-4 py-2 text-sm font-black text-[#3d2a18] transition-colors hover:bg-[#f3c56b]/15 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-[#f3c56b]/20"
                      >
                        Đổi Avatar
                      </button>
                      <input
                        type="file"
                        ref={avatarInputRef}
                        accept="image/*"
                        className="hidden"
                        onChange={handleAvatarChange}
                      />
                    </div>
                    <div className="grid grid-cols-1 gap-5 md:grid-cols-2">
                      <div>
                        <label className="mb-2 block text-[11px] font-black uppercase tracking-wider text-[#8b5e34]/70">Họ và Tên</label>
                        <input
                          type="text"
                          value={fullName}
                          onChange={(e) => updateProfileField('fullName', e.target.value)}
                          className={cn('w-full rounded-2xl border bg-[#fff7e8] px-4 py-2.5 text-[#24170d] outline-none transition focus:ring-4 focus:ring-[#f3c56b]/20', profileErrors.fullName ? 'border-rose-300 focus:border-rose-400' : 'border-[#3d2a18]/10 focus:border-[#f3c56b]')}
                        />
                        {profileErrors.fullName ? <p className="mt-2 text-xs font-bold text-rose-600">{profileErrors.fullName}</p> : null}
                      </div>
                      <div>
                        <label className="mb-2 block text-[11px] font-black uppercase tracking-wider text-[#8b5e34]/70">Số điện thoại</label>
                        <input
                          type="text"
                          value={phone}
                          maxLength={10}
                          onChange={(e) => updateProfileField('phone', e.target.value)}
                          className={cn('w-full rounded-2xl border bg-[#fff7e8] px-4 py-2.5 text-[#24170d] outline-none transition focus:ring-4 focus:ring-[#f3c56b]/20', profileErrors.phone ? 'border-rose-300 focus:border-rose-400' : 'border-[#3d2a18]/10 focus:border-[#f3c56b]')}
                        />
                        {profileErrors.phone ? <p className="mt-2 text-xs font-bold text-rose-600">{profileErrors.phone}</p> : null}
                      </div>
                      <div className="md:col-span-2">
                        <label className="mb-2 block text-[11px] font-black uppercase tracking-wider text-[#8b5e34]/70">Email</label>
                        <input
                          type="email"
                          value={email}
                          onChange={(e) => updateProfileField('email', e.target.value)}
                          autoComplete="email"
                          className={cn('w-full rounded-2xl border bg-[#fff7e8] px-4 py-2.5 text-[#24170d] outline-none transition focus:ring-4 focus:ring-[#f3c56b]/20', profileErrors.email ? 'border-rose-300 focus:border-rose-400' : 'border-[#3d2a18]/10 focus:border-[#f3c56b]')}
                        />
                        {profileErrors.email ? <p className="mt-2 text-xs font-bold text-rose-600">{profileErrors.email}</p> : null}
                      </div>
                    </div>
                  </motion.div>
                )}

                {activeTab === 'password' && (
                  <motion.div key="password" initial={{ opacity: 0, y: 10 }} animate={{ opacity: 1, y: 0 }} exit={{ opacity: 0, y: -10 }} className="space-y-5">
                    {passwordError ? <div className="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-bold text-rose-700">{passwordError}</div> : null}
                    {passwordMessage ? <div className="rounded-2xl border border-[#0f766e]/20 bg-[#0f766e]/10 px-4 py-3 text-sm font-bold text-[#0f5f59]">{passwordMessage}</div> : null}

                    <div>
                      <label className="mb-2 block text-[11px] font-black uppercase tracking-wider text-[#8b5e34]/70">Mật khẩu hiện tại</label>
                      <input
                        type="password"
                        value={passwordForm.currentPassword}
                        onChange={(event) => updatePasswordField('currentPassword', event.target.value)}
                        placeholder="Nhập mật khẩu hiện tại"
                        autoComplete="current-password"
                        className={cn('w-full rounded-2xl border bg-[#fff7e8] px-4 py-2.5 text-[#24170d] outline-none transition focus:ring-4 focus:ring-[#f3c56b]/20', passwordErrors.currentPassword ? 'border-rose-300 focus:border-rose-400' : 'border-[#3d2a18]/10 focus:border-[#f3c56b]')}
                      />
                      {passwordErrors.currentPassword ? <p className="mt-2 text-xs font-bold text-rose-600">{passwordErrors.currentPassword}</p> : null}
                    </div>
                    <div className="border-t border-[#3d2a18]/10 pt-4">
                      <label className="mb-2 block text-[11px] font-black uppercase tracking-wider text-[#8b5e34]/70">Mật khẩu mới</label>
                      <input
                        type="password"
                        value={passwordForm.newPassword}
                        onChange={(event) => updatePasswordField('newPassword', event.target.value)}
                        placeholder="Nhập mật khẩu mới"
                        autoComplete="new-password"
                        className={cn('w-full rounded-2xl border bg-[#fff7e8] px-4 py-2.5 text-[#24170d] outline-none transition focus:ring-4 focus:ring-[#f3c56b]/20', passwordErrors.newPassword ? 'border-rose-300 focus:border-rose-400' : 'border-[#3d2a18]/10 focus:border-[#f3c56b]')}
                      />
                      {passwordErrors.newPassword ? <p className="mt-2 text-xs font-bold text-rose-600">{passwordErrors.newPassword}</p> : null}

                      <label className="mb-2 mt-5 block text-[11px] font-black uppercase tracking-wider text-[#8b5e34]/70">Xác nhận mật khẩu mới</label>
                      <input
                        type="password"
                        value={passwordForm.newPasswordConfirmation}
                        onChange={(event) => updatePasswordField('newPasswordConfirmation', event.target.value)}
                        placeholder="Nhập lại mật khẩu mới"
                        autoComplete="new-password"
                        className={cn('w-full rounded-2xl border bg-[#fff7e8] px-4 py-2.5 text-[#24170d] outline-none transition focus:ring-4 focus:ring-[#f3c56b]/20', passwordErrors.newPasswordConfirmation ? 'border-rose-300 focus:border-rose-400' : 'border-[#3d2a18]/10 focus:border-[#f3c56b]')}
                      />
                      {passwordErrors.newPasswordConfirmation ? <p className="mt-2 text-xs font-bold text-rose-600">{passwordErrors.newPasswordConfirmation}</p> : null}
                    </div>
                  </motion.div>
                )}

                {activeTab === 'face' && (
                  <motion.div key="face" initial={{ opacity: 0, y: 10 }} animate={{ opacity: 1, y: 0 }} exit={{ opacity: 0, y: -10 }} className="flex flex-col items-center justify-center py-6">
                    <div className={cn('relative mb-6 flex rounded-full', isCameraOpen ? 'h-72 w-72 items-center justify-center bg-[#24170d] p-1.5 shadow-2xl shadow-[#24170d]/25 ring-1 ring-[#3d2a18]/10' : 'h-48 w-48 flex-col items-center justify-center overflow-hidden border-4 border-dashed p-3', hasRegisteredFaceId ? 'border-[#0f766e] bg-[#0f766e]/10' : 'border-[#f3c56b] bg-[#f3c56b]/12')}>
                      {isCameraOpen ? (
                        <>
                          <video ref={videoRef} playsInline muted className="h-full w-full rounded-full object-cover" />
                          <div className="pointer-events-none absolute inset-1.5 rounded-full ring-2 ring-[#f3c56b]/85 ring-offset-4 ring-offset-[#fffaf1]" />
                          <div className="pointer-events-none absolute left-10 top-10 h-10 w-10 rounded-tl-3xl border-l-4 border-t-4 border-[#f3c56b]" />
                          <div className="pointer-events-none absolute right-10 top-10 h-10 w-10 rounded-tr-3xl border-r-4 border-t-4 border-[#f3c56b]" />
                          <div className="pointer-events-none absolute bottom-10 left-10 h-10 w-10 rounded-bl-3xl border-b-4 border-l-4 border-[#f3c56b]" />
                          <div className="pointer-events-none absolute bottom-10 right-10 h-10 w-10 rounded-br-3xl border-b-4 border-r-4 border-[#f3c56b]" />
                          <div className="pointer-events-none absolute inset-8 rounded-full border border-white/20" />
                        </>
                      ) : (
                        <ScanFace className={cn('relative z-10 h-16 w-16', hasRegisteredFaceId ? 'text-[#0f766e]' : 'text-[#a65f16]')} />
                      )}
                      {isCameraOpen && !isCameraReady ? <div className="absolute inset-1.5 flex items-center justify-center rounded-full bg-[#24170d]/70 px-6 text-center text-xs font-black text-[#fff4df]">Đang làm nét...</div> : null}
                    </div>
                    <h3 className="mb-4 text-lg font-black tracking-tight text-[#24170d]">{hasRegisteredFaceId ? 'FaceID đã được đăng ký' : 'Đăng ký khuôn mặt FaceID'}</h3>
                    {!hasRegisteredFaceId && isCameraOpen ? <p className="mb-4 text-center text-sm font-semibold text-[#6f6254]">{hasFaceRegistrationStarted ? FACE_REGISTRATION_STEPS[faceRegistrationStep]?.title : 'Nhìn thẳng camera rồi xoay nhẹ mặt khi quét. Nếu chưa đạt, bạn có thể thử lại ngay.'}</p> : null}

                    {!hasRegisteredFaceId && faceError ? <div className="mb-4 w-full rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-bold text-rose-700">{faceError}</div> : null}
                    {!hasRegisteredFaceId && faceMessage ? <div className="mb-4 w-full rounded-2xl border border-[#0f766e]/20 bg-[#0f766e]/10 px-4 py-3 text-sm font-bold text-[#0f5f59]">{faceMessage}</div> : null}

                    {hasRegisteredFaceId ? (
                      <div className="flex w-full max-w-sm flex-col gap-3">
                        <div>
                          <label className="mb-2 block text-[11px] font-black uppercase tracking-wider text-[#8b5e34]/70">Mật khẩu hiện tại</label>
                          <input
                            type="password"
                            value={currentPassword}
                            onChange={(event) => setCurrentPassword(event.target.value)}
                            placeholder="Nhập mật khẩu hiện tại"
                            autoComplete="current-password"
                            className="w-full rounded-2xl border border-[#3d2a18]/10 bg-[#fff7e8] px-4 py-2.5 text-[#24170d] outline-none transition focus:border-[#f3c56b] focus:ring-4 focus:ring-[#f3c56b]/20"
                          />
                        </div>
                        <button type="button" onClick={handleDeleteFaceId} disabled={isFaceSubmitting || Boolean(validateDeleteFaceIdPassword(currentPassword))} className="flex cursor-pointer items-center justify-center gap-2 rounded-2xl border border-rose-200 px-6 py-3 font-bold text-rose-600 transition-colors hover:bg-rose-50 disabled:cursor-not-allowed disabled:bg-[#efe2cf] disabled:text-[#8b5e34]/50">
                          <Trash2 className="h-5 w-5" /> {isFaceSubmitting ? 'Đang xóa FaceID...' : 'Xóa FaceID'}
                        </button>
                      </div>
                    ) : (
                      <div className="flex flex-wrap justify-center gap-3">
                        {!isCameraOpen ? (
                          <button type="button" onClick={() => setIsCameraOpen(true)} className="flex cursor-pointer items-center gap-2 rounded-2xl bg-[#0f766e] px-8 py-3 font-bold text-white shadow-lg shadow-[#0f766e]/20 transition-colors hover:bg-[#0f5f59] focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-[#0f766e]/20">
                            <ScanFace className="h-5 w-5" /> Bắt đầu quét khuôn mặt
                          </button>
                        ) : (
                          <button type="button" onClick={handleRegisterFaceId} disabled={hasFaceRegistrationStarted || !isCameraReady} className="flex cursor-pointer items-center gap-2 rounded-2xl bg-[#0f766e] px-8 py-3 font-bold text-white shadow-lg shadow-[#0f766e]/20 transition-colors hover:bg-[#0f5f59] focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-[#0f766e]/20 disabled:cursor-not-allowed disabled:bg-[#c8bba6] disabled:text-[#7c6d5b]">
                            <CheckCircle className="h-5 w-5" /> {hasFaceRegistrationStarted ? `Đang quét bước ${faceRegistrationStep + 1}/${FACE_REGISTRATION_STEPS.length}...` : isCameraReady ? (faceError ? 'Thử lại đăng ký FaceID' : 'Bắt đầu đăng ký FaceID') : 'Đang chuẩn bị camera...'}
                          </button>
                        )}
                      </div>
                    )}
                  </motion.div>
                )}
              </AnimatePresence>
            </div>

            <div className="flex shrink-0 justify-end gap-3 border-t border-[#3d2a18]/10 bg-[#fff7e8]/78 p-6">
              <button type="button" onClick={closeModal} className="cursor-pointer rounded-2xl px-6 py-2.5 font-black text-[#6f6254] transition-colors hover:bg-[#efe2cf] focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-[#3d2a18]/10">
                Đóng
              </button>
              {activeTab === 'info' && (
                <button type="button" onClick={handleSaveProfile} disabled={isInfoSubmitting} className="flex cursor-pointer items-center gap-2 rounded-2xl bg-[#24170d] px-6 py-2.5 text-sm font-black text-[#fff4df] shadow-md shadow-[#24170d]/18 transition hover:bg-[#3d2a18] focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-[#a65f16]/20 disabled:cursor-not-allowed disabled:bg-[#efe2cf] disabled:text-[#8b5e34]/50">
                  <CheckCircle className="h-4 w-4" /> {isInfoSubmitting ? 'Đang lưu...' : 'Lưu thay đổi'}
                </button>
              )}
              {activeTab === 'password' && (
                <button type="button" onClick={handleChangePassword} disabled={isPasswordSubmitting} className="flex cursor-pointer items-center gap-2 rounded-2xl bg-[#24170d] px-6 py-2.5 text-sm font-black text-[#fff4df] shadow-md shadow-[#24170d]/18 transition hover:bg-[#3d2a18] focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-[#a65f16]/20 disabled:cursor-not-allowed disabled:bg-[#c8bba6] disabled:text-[#7c6d5b]">
                  <CheckCircle className="h-4 w-4" /> {isPasswordSubmitting ? 'Đang đổi mật khẩu...' : 'Đổi mật khẩu'}
                </button>
              )}
            </div>
          </motion.div>
        </div>
      )}
    </AnimatePresence>
  )

  return createPortal(modal, document.body)
}
