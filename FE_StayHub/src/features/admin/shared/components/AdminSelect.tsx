import { useEffect, useMemo, useRef, useState } from 'react'
import { Check, ChevronDown } from 'lucide-react'
import { cn } from '../../../../shared/lib/utils/cn'

export interface AdminSelectOption {
  value: string | number
  label: string
  description?: string
  tone?: 'default' | 'success' | 'warning' | 'danger'
}

interface AdminSelectProps {
  value: string | number
  options: AdminSelectOption[]
  onChange: (value: string | number) => void
  placeholder?: string
  className?: string
  disabled?: boolean
  invalid?: boolean
  id?: string
  ariaDescribedBy?: string
  menuPlacement?: 'bottom' | 'top'
}

const toneClassNames: Record<NonNullable<AdminSelectOption['tone']>, string> = {
  default: 'bg-[#efe2cf]/70 text-[#8b5e34]',
  success: 'bg-[#0f766e]/12 text-[#0f5f59]',
  warning: 'bg-[#f3c56b]/22 text-[#8a4f18]',
  danger: 'bg-rose-100 text-rose-700',
}

export function AdminSelect({ value, options, onChange, placeholder = 'Chọn giá trị', className, disabled = false, invalid = false, id, ariaDescribedBy, menuPlacement = 'bottom' }: AdminSelectProps) {
  const wrapperRef = useRef<HTMLDivElement>(null)
  const [isOpen, setIsOpen] = useState(false)
  const selectedIndex = useMemo(() => options.findIndex((option) => String(option.value) === String(value)), [options, value])
  const [activeIndex, setActiveIndex] = useState(selectedIndex >= 0 ? selectedIndex : 0)
  const selectedOption = selectedIndex >= 0 ? options[selectedIndex] : undefined
  const listboxId = id ? `${id}-listbox` : undefined

  useEffect(() => {
    function handlePointerDown(event: PointerEvent) {
      if (!wrapperRef.current?.contains(event.target as Node)) setIsOpen(false)
    }

    document.addEventListener('pointerdown', handlePointerDown)
    return () => document.removeEventListener('pointerdown', handlePointerDown)
  }, [])

  function chooseOption(option: AdminSelectOption) {
    onChange(option.value)
    setIsOpen(false)
  }

  function moveActive(offset: number) {
    if (options.length === 0) return
    setActiveIndex((current) => (current + offset + options.length) % options.length)
  }

  function handleKeyDown(event: React.KeyboardEvent<HTMLButtonElement>) {
    if (disabled) return

    if (event.key === 'ArrowDown') {
      event.preventDefault()
      if (!isOpen) {
        setActiveIndex(selectedIndex >= 0 ? selectedIndex : 0)
        setIsOpen(true)
        return
      }
      moveActive(1)
      return
    }

    if (event.key === 'ArrowUp') {
      event.preventDefault()
      if (!isOpen) {
        setActiveIndex(selectedIndex >= 0 ? selectedIndex : 0)
        setIsOpen(true)
        return
      }
      moveActive(-1)
      return
    }

    if (event.key === 'Enter' || event.key === ' ') {
      event.preventDefault()
      if (isOpen && options[activeIndex]) {
        chooseOption(options[activeIndex])
        return
      }
      setActiveIndex(selectedIndex >= 0 ? selectedIndex : 0)
      setIsOpen(true)
      return
    }

    if (event.key === 'Escape') {
      event.preventDefault()
      setIsOpen(false)
    }
  }

  return (
    <div ref={wrapperRef} className={cn('relative min-w-0', className)}>
      <button
        id={id}
        type="button"
        disabled={disabled}
        aria-haspopup="listbox"
        aria-expanded={isOpen}
        aria-controls={listboxId}
        aria-invalid={invalid || undefined}
        aria-describedby={ariaDescribedBy}
        onClick={() => {
          setActiveIndex(selectedIndex >= 0 ? selectedIndex : 0)
          setIsOpen((current) => !current)
        }}
        onKeyDown={handleKeyDown}
        className={cn(
          'group flex min-h-12 w-full items-center justify-between gap-3 rounded-2xl border bg-[#fffaf1] px-4 py-3 text-left text-sm font-bold text-[#3d2a18] shadow-sm shadow-[#6b3f1d]/5 outline-none transition-all duration-200',
          'border-[#3d2a18]/10 hover:border-[#f3c56b]/45 hover:bg-[#fff7e8] focus:border-[#f3c56b] focus:ring-4 focus:ring-[#f3c56b]/20',
          isOpen && 'border-[#f3c56b]/60 bg-[#fff7e8] ring-4 ring-[#f3c56b]/18',
          invalid && 'border-rose-300 bg-rose-50 focus:border-rose-400 focus:ring-rose-100',
          disabled && 'cursor-not-allowed bg-[#efe2cf]/45 text-[#8b5e34]/45 opacity-70',
        )}
      >
        <span className="min-w-0 flex-1 truncate">{selectedOption?.label || placeholder}</span>
        <span className="flex items-center gap-2">
          {selectedOption?.tone && selectedOption.tone !== 'default' && <span className={cn('h-2.5 w-2.5 rounded-full', toneClassNames[selectedOption.tone])} />}
          <ChevronDown className={cn('h-4 w-4 shrink-0 text-[#a65f16] transition-transform duration-200 group-hover:text-[#8a4f18]', isOpen && 'rotate-180')} />
        </span>
      </button>

      {isOpen && !disabled && (
        <div
          id={listboxId}
          role="listbox"
          className={cn(
            'absolute left-0 right-0 z-[70] max-h-72 overflow-y-auto rounded-[1.35rem] border border-[#3d2a18]/10 bg-[#fffaf1]/98 p-1.5 text-sm shadow-2xl shadow-[#24170d]/18 backdrop-blur-xl',
            menuPlacement === 'top' ? 'bottom-[calc(100%+0.5rem)]' : 'top-[calc(100%+0.5rem)]',
          )}
        >
          <div className="pointer-events-none absolute inset-0 rounded-[1.35rem] bg-[radial-gradient(circle_at_15%_0%,rgba(243,197,107,0.2),transparent_34%),linear-gradient(135deg,rgba(255,255,255,0.75),rgba(255,250,241,0.25))]" />
          <div className="relative space-y-1">
            {options.map((option, index) => {
              const isSelected = String(option.value) === String(value)
              const isActive = activeIndex === index

              return (
                <button
                  key={`${option.value}-${option.label}`}
                  type="button"
                  role="option"
                  aria-selected={isSelected}
                  onMouseEnter={() => setActiveIndex(index)}
                  onClick={() => chooseOption(option)}
                  className={cn(
                    'flex w-full items-center justify-between gap-3 rounded-2xl px-3.5 py-3 text-left transition-all duration-150',
                    isSelected ? 'bg-[#24170d] text-[#fff4df] shadow-lg shadow-[#24170d]/12' : 'text-[#6f6254] hover:bg-[#f3c56b]/16 hover:text-[#24170d]',
                    isActive && !isSelected && 'bg-[#f3c56b]/14 text-[#24170d]',
                  )}
                >
                  <span className="min-w-0">
                    <span className="block truncate font-black tracking-tight">{option.label}</span>
                    {option.description && <span className={cn('mt-0.5 block truncate text-xs font-semibold', isSelected ? 'text-[#f8e8c8]/72' : 'text-[#8b5e34]/65')}>{option.description}</span>}
                  </span>
                  <span className="flex shrink-0 items-center gap-2">
                    {option.tone && option.tone !== 'default' && <span className={cn('h-2.5 w-2.5 rounded-full', toneClassNames[option.tone])} />}
                    {isSelected && <Check className="h-4 w-4 text-[#f3c56b]" />}
                  </span>
                </button>
              )
            })}
          </div>
        </div>
      )}
    </div>
  )
}
