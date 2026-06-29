import { type ReactNode } from 'react'
import { cn } from '../../../../../shared/lib/utils/cn'
import { STATUS_ACTIVE, STATUS_CANCELLED } from '../../utils/contract.helpers'

export function MetricCard({ label, value, tone }: { label: string; value: number; tone: 'neutral' | 'emerald' | 'amber' | 'teal' }) {
  const toneClassNames = {
    neutral: 'border-[#f8e8c8]/12 bg-[#f8e8c8]/10 text-[#fff4df]',
    emerald: 'border-[#0f766e]/35 bg-[#0f766e]/16 text-[#c8fff4]',
    amber: 'border-[#f3c56b]/35 bg-[#f3c56b]/18 text-[#fff4df]',
    teal: 'border-cyan-200/25 bg-cyan-100/10 text-cyan-50',
  }[tone]

  return (
    <div className={cn('rounded-3xl border px-4 py-3 backdrop-blur', toneClassNames)}>
      <p className="text-[10px] font-black uppercase tracking-[0.18em] opacity-65">{label}</p>
      <p className="mt-1 text-3xl font-black tracking-tight tabular-nums">{value}</p>
    </div>
  )
}

export function IconButton({
  title,
  disabled,
  danger,
  success,
  onClick,
  children,
}: {
  title: string
  disabled?: boolean
  danger?: boolean
  success?: boolean
  onClick: () => void
  children: ReactNode
}) {
  return (
    <button
      type="button"
      disabled={disabled}
      onClick={onClick}
      className={cn(
        'inline-flex h-10 w-10 items-center justify-center rounded-xl border border-[#3d2a18]/10 bg-[#fffaf1] text-[#8b5e34] shadow-sm transition focus:outline-none focus:ring-4 active:scale-95 disabled:cursor-not-allowed disabled:opacity-45',
        danger
          ? 'hover:border-rose-200 hover:bg-rose-50 hover:text-rose-600 focus:ring-rose-100'
          : success
            ? 'hover:border-[#0f766e]/25 hover:bg-[#0f766e]/10 hover:text-[#0f5f59] focus:ring-[#0f766e]/10'
            : 'hover:border-[#3d2a18]/25 hover:bg-[#f3c56b]/15 hover:text-[#24170d] focus:ring-[#3d2a18]/10'
      )}
      title={title}
      aria-label={title}
    >
      {children}
    </button>
  )
}

export function DetailTile({ label, value }: { label: string; value?: ReactNode }) {
  return (
    <div className="min-w-0 rounded-2xl border border-[#3d2a18]/10 bg-[#fffaf1] p-3 shadow-sm">
      <p className="text-[10px] font-black uppercase tracking-[0.18em] text-[#8b5e34]/60">{label}</p>
      <div className="mt-1 break-words text-sm font-black text-[#24170d]">{value ?? '—'}</div>
    </div>
  )
}

export function StatusBadge({ status, label }: { status: number; label: string }) {
  const className =
    Number(status) === STATUS_ACTIVE
      ? 'border-[#0f766e]/20 bg-[#0f766e]/10 text-[#0f5f59]'
      : Number(status) === STATUS_CANCELLED
        ? 'border-rose-200 bg-rose-50 text-rose-700'
        : 'border-[#f3c56b]/45 bg-[#f3c56b]/18 text-[#8a4f18]'

  return (
    <span className={cn('inline-flex items-center justify-center whitespace-nowrap rounded-full border px-3 py-1 text-xs font-black shadow-sm', className)}>
      {label}
    </span>
  )
}

export function FieldError({ message }: { message?: string }) {
  if (!message) return null
  return <p className="mt-1.5 px-1 text-xs font-bold text-rose-600">{message}</p>
}
