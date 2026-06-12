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
  placeholder?: string
  className?: string
  minDate?: Date
  maxDate?: Date
}

export const AdminDateInput = forwardRef<DatePicker, AdminDateInputProps>(
  ({ value, onChange, placeholder = 'dd/mm/yyyy', className, minDate, maxDate }, ref) => {
    // Parse value YYYY-MM-DD to local Date object
    let selectedDate: Date | null = null
    if (value) {
      const parts = value.split('-')
      if (parts.length === 3) {
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
      const day = String(date.getDate()).padStart(2, '0')
      onChange(`${year}-${month}-${day}`)
    }

    return (
      <DatePicker
        ref={ref}
        selected={selectedDate}
        onChange={handleChange}
        dateFormat="dd/MM/yyyy"
        placeholderText={placeholder}
        locale="vi"
        isClearable
        showYearDropdown
        showMonthDropdown
        dropdownMode="select"
        className={cn(className)}
        wrapperClassName="w-full"
        minDate={minDate}
        maxDate={maxDate}
        portalId="admin-datepicker-portal"
      />
    )
  }
)

AdminDateInput.displayName = 'AdminDateInput'
