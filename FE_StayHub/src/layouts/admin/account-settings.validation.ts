export function validateDeleteFaceIdPassword(password: string): string | null {
  if (password.trim().length < 6) {
    return 'Vui lòng nhập mật khẩu hiện tại để xóa FaceID.'
  }

  return null
}
