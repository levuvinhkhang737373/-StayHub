import { useEffect, useId, useMemo, useRef, useState, useCallback } from 'react'
import { createPortal } from 'react-dom'
import { Check, ChevronDown, Search, Plus } from 'lucide-react'
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
  searchable?: boolean
  menuMinWidth?: number
  wrapLabel?: boolean
  footerAction?: {
    label: string
    onClick: () => void
  }
}

const toneClassNames: Record<NonNullable<AdminSelectOption['tone']>, string> = {
  default: 'bg-[#efe2cf]/70 text-[#8b5e34]',
  success: 'bg-[#0f766e]/12 text-[#0f5f59]',
  warning: 'bg-[#f3c56b]/22 text-[#8a4f18]',
  danger: 'bg-rose-100 text-rose-700',
}

export function AdminSelect({ value, options, onChange, placeholder = 'Chọn giá trị', className, disabled = false, invalid = false, id, ariaDescribedBy, menuPlacement = 'bottom', searchable, menuMinWidth, wrapLabel = false, footerAction }: AdminSelectProps) {
  const wrapperRef = useRef<HTMLDivElement>(null)
  const buttonRef = useRef<HTMLButtonElement>(null)
  const [isOpen, setIsOpen] = useState(false)
  const [searchTerm, setSearchTerm] = useState('')
  const generatedId = useId()
  
  const selectedIndex = useMemo(() => options.findIndex((option) => String(option.value) === String(value)), [options, value])
  const selectedOption = selectedIndex >= 0 ? options[selectedIndex] : undefined
  const listboxId = id ? `${id}-listbox` : `admin-select-${generatedId.replace(/:/g, '')}`
  const [coords, setCoords] = useState<{ top: number; left: number; width: number } | null>(null)

  const isSearchEnabled = searchable ?? options.length > 5

  const filteredOptions = useMemo(() => {
    if (!searchTerm.trim()) return options
    const term = searchTerm.toLowerCase().trim()
    return options.filter((option) =>
      option.label.toLowerCase().includes(term)
    )
  }, [options, searchTerm])

  const [activeIndex, setActiveIndex] = useState(selectedIndex >= 0 ? selectedIndex : 0)

  const updateCoords = useCallback(() => {
    if (buttonRef.current) {
      const rect = buttonRef.current.getBoundingClientRect()
      setCoords({
        top: menuPlacement === 'top' ? rect.top : rect.bottom,
        left: rect.left,
        width: rect.width,
      })
    }
  }, [menuPlacement])

  useEffect(() => {
    if (isOpen) {
      updateCoords()
      window.addEventListener('resize', updateCoords)
      window.addEventListener('scroll', updateCoords, true)
    }
    return () => {
      window.removeEventListener('resize', updateCoords)
      window.removeEventListener('scroll', updateCoords, true)
    }
  }, [isOpen, updateCoords])

  useEffect(() => {
    function handlePointerDown(event: PointerEvent) {
      const portalEl = document.getElementById(listboxId)
      if (
        !wrapperRef.current?.contains(event.target as Node) &&
        (!portalEl || !portalEl.contains(event.target as Node))
      ) {
        setIsOpen(false)
        setSearchTerm('')
      }
    }

    document.addEventListener('pointerdown', handlePointerDown)
    return () => document.removeEventListener('pointerdown', handlePointerDown)
  }, [listboxId])

  function chooseOption(option: AdminSelectOption) {
    onChange(option.value)
    setIsOpen(false)
    setSearchTerm('')
  }

  function moveActive(offset: number) {
    if (filteredOptions.length === 0) return
    setActiveIndex((current) => (current + offset + filteredOptions.length) % filteredOptions.length)
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
      if (isOpen && filteredOptions[activeIndex]) {
        chooseOption(filteredOptions[activeIndex])
        return
      }
      setActiveIndex(selectedIndex >= 0 ? selectedIndex : 0)
      setIsOpen(true)
      return
    }

    if (event.key === 'Escape') {
      event.preventDefault()
      setIsOpen(false)
      setSearchTerm('')
    }
  }

  const menuStyle = useMemo<React.CSSProperties>(() => {
    if (!coords) {
      return {
        position: 'fixed',
        visibility: 'hidden',
        top: '-9999px',
        left: '-9999px',
      }
    }
    const viewportPadding = 12
    const menuWidth = Math.min(Math.max(coords.width, menuMinWidth ?? coords.width), window.innerWidth - viewportPadding * 2)
    const menuLeft = Math.min(Math.max(coords.left, viewportPadding), window.innerWidth - menuWidth - viewportPadding)

    return {
      position: 'fixed',
      left: `${menuLeft}px`,
      width: `${menuWidth}px`,
      zIndex: 9999,
      ...(menuPlacement === 'top'
        ? { bottom: `${window.innerHeight - coords.top + 8}px` }
        : { top: `${coords.top + 8}px` }),
    }
  }, [coords, menuMinWidth, menuPlacement])

  return (
    <div ref={wrapperRef} className={cn('relative min-w-0', className)}>
      <button
        ref={buttonRef}
        id={id}
        type="button"
        disabled={disabled}
        aria-haspopup="listbox"
        aria-expanded={isOpen}
        aria-controls={listboxId}
        aria-invalid={invalid || undefined}
        aria-describedby={ariaDescribedBy}
        onClick={() => {
          if (isOpen) {
            setIsOpen(false)
            setSearchTerm('')
            return
          }
          setActiveIndex(selectedIndex >= 0 ? selectedIndex : 0)
          updateCoords()
          setIsOpen(true)
        }}
        onKeyDown={handleKeyDown}
        className={cn(
          'group flex min-h-12 w-full items-center justify-between gap-3 rounded-2xl border bg-[#fffaf1] px-4 py-3 text-left text-sm font-bold text-[#3d2a18] shadow-sm shadow-[#6b3f1d]/5 outline-none transition-all duration-200',
          'border-[#3d2a18]/10 hover:border-[#f3c56b]/45 hover:bg-[#fff7e8] focus:border-[#f3c56b] focus:ring-4 focus:ring-[#f3c56b]/20',
          isOpen && 'border-[#f3c56b]/60 bg-[#fff7e8] ring-4 ring-[#f3c56b]/18',
          invalid && 'border-rose-300 bg-rose-50 focus:border-rose-400 focus:ring-rose-100',
          disabled && 'cursor-not-allowed bg-[#efe2cf]/45 text-[#3d2a18]',
        )}
      >
        <span className={cn('min-w-0 flex-1', wrapLabel ? 'whitespace-normal break-words leading-5' : 'truncate')}>{selectedOption?.label || placeholder}</span>
        <span className="flex items-center gap-2">
          {selectedOption?.tone && selectedOption.tone !== 'default' && <span className={cn('h-2.5 w-2.5 rounded-full', toneClassNames[selectedOption.tone])} />}
          {!disabled && <ChevronDown className={cn('h-4 w-4 shrink-0 text-[#a65f16] transition-transform duration-200 group-hover:text-[#8a4f18]', isOpen && 'rotate-180')} />}
        </span>
      </button>

      {isOpen && !disabled && createPortal(
        <div
          id={listboxId}
          role="listbox"
          style={menuStyle}
          className="max-h-72 flex flex-col rounded-[1.35rem] border border-[#3d2a18]/10 bg-[#fffaf1]/98 p-1.5 text-sm shadow-2xl shadow-[#24170d]/18 backdrop-blur-xl animate-in fade-in zoom-in-95 duration-100"
        >
          <div className="pointer-events-none absolute inset-0 rounded-[1.35rem] bg-[radial-gradient(circle_at_15%_0%,rgba(243,197,107,0.2),transparent_34%),linear-gradient(135deg,rgba(255,255,255,0.75),rgba(255,250,241,0.25))]" />
          
          {isSearchEnabled && (
            <div className="relative mb-2 shrink-0 px-1 pt-1 z-10">
              <Search className="absolute left-4 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-[#a65f16]" />
              <input
                type="text"
                placeholder="Tìm kiếm..."
                value={searchTerm}
                onChange={(e) => {
                  setSearchTerm(e.target.value)
                  setActiveIndex(0)
                }}
                onClick={(e) => e.stopPropagation()}
                autoFocus
                className="h-9 w-full rounded-xl border border-[#3d2a18]/10 bg-white/70 pl-9 pr-3 text-xs font-bold text-[#3d2a18] outline-none placeholder:text-[#8b5e34]/55 focus:border-[#f3c56b] focus:ring-2 focus:ring-[#f3c56b]/20"
              />
            </div>
          )}

          <div className="relative space-y-1 overflow-y-auto flex-1 min-h-0">
            {filteredOptions.map((option, index) => {
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
                  <span className="min-w-0 flex-1">
                    <span className={cn('block font-black tracking-tight', wrapLabel ? 'whitespace-normal break-words leading-5' : 'truncate')}>{option.label}</span>
                    {option.description && <span className={cn('mt-0.5 block text-xs font-semibold', wrapLabel ? 'whitespace-normal break-words leading-5' : 'truncate', isSelected ? 'text-[#f8e8c8]/72' : 'text-[#8b5e34]/65')}>{option.description}</span>}
                  </span>
                  <span className="flex shrink-0 items-center gap-2">
                    {option.tone && option.tone !== 'default' && <span className={cn('h-2.5 w-2.5 rounded-full', toneClassNames[option.tone])} />}
                    {isSelected && <Check className="h-4 w-4 text-[#f3c56b]" />}
                  </span>
                </button>
              )
            })}
            {filteredOptions.length === 0 && (
              <div className="py-6 text-center text-xs font-bold text-[#8b5e34]/60">
                Không tìm thấy kết quả
              </div>
            )}
          </div>
          {footerAction && (
            <div className="border-t border-[#3d2a18]/10 mt-1.5 pt-1.5 px-1.5 pb-1 z-10 shrink-0">
              <button
                type="button"
                onClick={(e) => {
                  e.stopPropagation()
                  setIsOpen(false)
                  setSearchTerm('')
                  footerAction.onClick()
                }}
                className="flex w-full items-center justify-center gap-1.5 rounded-xl bg-[#24170d] px-3 py-2.5 text-center text-xs font-black uppercase tracking-wider text-[#f3c56b] transition hover:bg-[#3d2a18]"
              >
                <Plus className="h-3.5 w-3.5" /> {footerAction.label}
              </button>
            </div>
          )}
        </div>,
        document.body
      )}
    </div>
  )
}
