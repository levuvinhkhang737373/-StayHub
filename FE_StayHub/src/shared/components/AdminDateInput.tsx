import { forwardRef } from 'react'
import DatePicker, { registerLocale } from 'react-datepicker'
import { vi } from 'date-fns/locale'
import { cn } from '../lib/utils/cn'

import 'react-datepicker/dist/react-datepicker.css'
import './admin-date-input.css'

registerLocale('vi', vi)

export interface AdminDateInputProps {
  value?: string | null
  onChange: (value: string) => void
  mode?: 'date' | 'month'
  placeholder?: string
  className?: string
  minDate?: Date
  maxDate?: Date
  disabled?: boolean
}

export const AdminDateInput = forwardRef<DatePicker, AdminDateInputProps>(
  ({ value, onChange, mode = 'date', placeholder, className, minDate, maxDate, disabled }, ref) => {
    const isMonthPicker = mode === 'month'
    const displayPlaceholder = placeholder ?? (isMonthPicker ? 'MM/yyyy' : 'dd/MM/yyyy')

    let selectedDate: Date | null = null
    if (value) {
      const parts = value.split('-')
      if (isMonthPicker && parts.length >= 2) {
        selectedDate = new Date(Number(parts[0]), Number(parts[1]) - 1, 1)
      } else if (parts.length === 3) {
        selectedDate = new Date(Number(parts[0]), Number(parts[1]) - 1, Number(parts[2]))
      } else {
        selectedDate = new Date(value)
      }
    }

    const handleChange = (date: Date | null) => {
      if (!date) {
        onChange('')
        return
      }

      const year = date.getFullYear()
      const month = String(date.getMonth() + 1).padStart(2, '0')
      if (isMonthPicker) {
        onChange(`${year}-${month}`)
        return
      }

      const day = String(date.getDate()).padStart(2, '0')
      onChange(`${year}-${month}-${day}`)
    }

    return (
      <DatePicker
        ref={ref}
        selected={selectedDate}
        onChange={handleChange}
        dateFormat={isMonthPicker ? 'MM/yyyy' : 'dd/MM/yyyy'}
        placeholderText={displayPlaceholder}
        locale="vi"
        isClearable={!disabled}
        showMonthYearPicker={isMonthPicker}
        showYearDropdown={!isMonthPicker}
        showMonthDropdown={!isMonthPicker}
        dropdownMode="select"
        className={cn(className)}
        wrapperClassName="w-full"
        minDate={minDate}
        maxDate={maxDate}
        disabled={disabled}
        portalId="admin-datepicker-portal"
      />
    )
  }
)

AdminDateInput.displayName = 'AdminDateInput'
