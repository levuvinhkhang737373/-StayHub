export const GENDER_MALE = 1
export const GENDER_FEMALE = 2

export const GENDER_POLICY_MIXED = 1
export const GENDER_POLICY_MALE = 2
export const GENDER_POLICY_FEMALE = 3

export const GENDER_POLICY_ERROR_MESSAGE = 'Giới tính khách thuê không phù hợp với chính sách giới tính của tòa nhà.'

export function buildingAllowsTenantGender(genderPolicy: number | null | undefined, tenantGender: number | string | null | undefined) {
  const gender = Number(tenantGender)
  if (![GENDER_MALE, GENDER_FEMALE].includes(gender)) return false

  const policy = Number(genderPolicy ?? GENDER_POLICY_MIXED)
  if (policy === GENDER_POLICY_MIXED) return true
  if (policy === GENDER_POLICY_MALE) return gender === GENDER_MALE
  if (policy === GENDER_POLICY_FEMALE) return gender === GENDER_FEMALE

  return false
}
