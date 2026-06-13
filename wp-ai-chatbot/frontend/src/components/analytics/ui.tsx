// Shared presentational primitives for the Analytics dashboard: Card,
// SectionTitle, KpiTile and the trend chip. Styled with admin design tokens.
import { type ReactNode } from 'react'
import { cn } from '@/lib/utils'

export function Card({
  children,
  title,
  subtitle,
  className,
  pad = 'p-6',
}: {
  children: ReactNode
  title?: string
  subtitle?: string
  className?: string
  pad?: string
}) {
  return (
    <div className={cn('rounded-[16px] border border-line bg-surface', pad, className)}>
      {(title || subtitle) && (
        <div className="mb-4">
          {title && <h3 className="text-[16px] font-semibold tracking-[-0.2px] text-ink">{title}</h3>}
          {subtitle && <div className="mt-[3px] text-[13px] text-muted-2">{subtitle}</div>}
        </div>
      )}
      {children}
    </div>
  )
}

export function SectionTitle({ children }: { children: ReactNode }) {
  return (
    <h2 className="mb-4 mt-1 text-[13px] font-bold uppercase tracking-[0.4px] text-muted-2">{children}</h2>
  )
}

export function TrendChip({ value, invert = false, suffix = '' }: { value: number; invert?: boolean; suffix?: string }) {
  const up = value >= 0
  const good = invert ? !up : up
  return (
    <span
      className={cn(
        'inline-flex items-center gap-[3px] rounded-full px-2 py-[3px] text-[12.5px] font-semibold tabular-nums',
        good ? 'bg-success-soft text-success-ink' : 'bg-danger-soft text-danger-ink'
      )}
    >
      <span className="text-[10px]">{up ? '▲' : '▼'}</span>
      {Math.abs(value).toFixed(1)}%{suffix}
    </span>
  )
}

export function KpiTile({
  label,
  value,
  sub,
  delta,
  deltaInvert = false,
}: {
  label: string
  value: string
  sub?: string
  delta?: number | null
  deltaInvert?: boolean
}) {
  return (
    <div className="flex flex-col">
      <div className="mb-2 text-[13px] font-medium text-muted">{label}</div>
      <div className="text-[28px] font-bold leading-none tracking-[-0.8px] tabular-nums text-ink">{value}</div>
      {sub && <div className="mt-1.5 text-[12.5px] text-muted-2">{sub}</div>}
      {delta != null && (
        <div className="mt-2.5">
          <TrendChip value={delta} invert={deltaInvert} />
        </div>
      )}
    </div>
  )
}
