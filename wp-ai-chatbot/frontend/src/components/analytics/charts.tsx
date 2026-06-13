// SVG chart primitives for the Analytics dashboard, ported from the approved
// design. Geometry is computed from the measured container width; brand color
// flows in via `currentColor` (set a `text-accent` class on the wrapper) so
// the charts stay on the admin token palette. Hover tooltips on every chart.
import {
  useState,
  useRef,
  useEffect,
  useCallback,
  useId,
  type ReactNode,
  type RefObject,
  type MouseEvent as ReactMouseEvent,
} from 'react'
import { cn } from '@/lib/utils'

const GRID = 'rgba(21,22,26,0.06)'

function useMeasure(): [RefObject<HTMLDivElement>, number] {
  const ref = useRef<HTMLDivElement>(null)
  const [width, setWidth] = useState(0)
  useEffect(() => {
    if (!ref.current) return
    const observer = new ResizeObserver((entries) => setWidth(entries[0].contentRect.width))
    observer.observe(ref.current)
    setWidth(ref.current.getBoundingClientRect().width)
    return () => observer.disconnect()
  }, [])
  return [ref, width]
}

function ChartTip({ x, y, children }: { x: number; y: number; children: ReactNode }) {
  return (
    <div
      className="pointer-events-none absolute z-20 rounded-lg bg-ink px-2.5 py-[7px] text-[12px] leading-snug whitespace-nowrap text-white tabular-nums shadow-[0_6px_20px_rgba(0,0,0,.22)]"
      style={{ left: x, top: y, transform: 'translate(-50%, -112%)' }}
    >
      {children}
    </div>
  )
}

interface LinePoint {
  label: string
  sub?: string
  value: number
}

export function LineArea({
  data,
  height = 200,
  fmt = (v) => String(v),
}: {
  data: LinePoint[]
  height?: number
  fmt?: (value: number) => string
}) {
  const [ref, width] = useMeasure()
  const [hi, setHi] = useState<number | null>(null)
  const gid = useId().replace(/[:]/g, '')
  const padX = 8
  const padTop = 18
  const padBot = 26
  const innerH = height - padTop - padBot
  const max = Math.max(1, ...data.map((d) => d.value)) * 1.12
  const x = (i: number) => padX + (i * (width - padX * 2)) / Math.max(1, data.length - 1)
  const y = (v: number) => padTop + innerH - (v / (max || 1)) * innerH
  const points = data.map((d, i) => [x(i), y(d.value)] as const)
  const line = points.map((p, i) => (i ? 'L' : 'M') + p[0].toFixed(1) + ' ' + p[1].toFixed(1)).join(' ')
  const area =
    line + ` L${x(data.length - 1).toFixed(1)} ${padTop + innerH} L${x(0).toFixed(1)} ${padTop + innerH} Z`
  const labelStep = Math.max(1, Math.ceil(data.length / 8))

  const onMove = useCallback(
    (e: ReactMouseEvent<SVGSVGElement>) => {
      const rect = e.currentTarget.getBoundingClientRect()
      const mx = e.clientX - rect.left
      let best = 0
      let bestDist = Infinity
      for (let i = 0; i < data.length; i++) {
        const dist = Math.abs(x(i) - mx)
        if (dist < bestDist) {
          bestDist = dist
          best = i
        }
      }
      setHi(best)
    },
    // eslint-disable-next-line react-hooks/exhaustive-deps
    [width, data.length]
  )

  return (
    <div ref={ref} className="relative w-full text-accent">
      {width > 0 && (
        <svg
          width={width}
          height={height}
          className="block overflow-visible"
          onMouseMove={onMove}
          onMouseLeave={() => setHi(null)}
        >
          <defs>
            <linearGradient id={gid} x1="0" y1="0" x2="0" y2="1">
              <stop offset="0%" stopColor="currentColor" stopOpacity="0.22" />
              <stop offset="100%" stopColor="currentColor" stopOpacity="0" />
            </linearGradient>
          </defs>
          {[0.25, 0.5, 0.75, 1].map((g) => (
            <line key={g} x1={padX} x2={width - padX} y1={padTop + innerH * g} y2={padTop + innerH * g} stroke={GRID} strokeWidth="1" />
          ))}
          <path d={area} fill={`url(#${gid})`} />
          <path d={line} fill="none" stroke="currentColor" strokeWidth="2.25" strokeLinejoin="round" strokeLinecap="round" />
          {data.map((d, i) =>
            i % labelStep === 0 || i === data.length - 1 ? (
              <text key={i} x={x(i)} y={height - 6} textAnchor="middle" fontSize="11" fill="#9b9ea6">
                {d.label}
              </text>
            ) : null
          )}
          {hi != null && (
            <g>
              <line x1={points[hi][0]} x2={points[hi][0]} y1={padTop - 6} y2={padTop + innerH} stroke="currentColor" strokeOpacity="0.25" strokeWidth="1" />
              <circle cx={points[hi][0]} cy={points[hi][1]} r="5" fill="currentColor" stroke="#fff" strokeWidth="2" />
            </g>
          )}
        </svg>
      )}
      {hi != null && (
        <ChartTip x={points[hi][0]} y={points[hi][1]}>
          <div className="text-[11px] opacity-70">{data[hi].sub || data[hi].label}</div>
          <div className="font-semibold">{fmt(data[hi].value)}</div>
        </ChartTip>
      )}
    </div>
  )
}

export function Bars({
  data,
  height = 200,
  fmt = (v) => String(v),
}: {
  data: LinePoint[]
  height?: number
  fmt?: (value: number) => string
}) {
  const [ref, width] = useMeasure()
  const [hi, setHi] = useState<number | null>(null)
  const padTop = 16
  const padBot = 26
  const gap = 0.32
  const innerH = height - padTop - padBot
  const max = Math.max(1, ...data.map((d) => d.value)) * 1.1
  const slot = width / Math.max(1, data.length)
  const bw = slot * (1 - gap)
  const barHeight = (v: number) => (v / (max || 1)) * innerH
  const labelStep = Math.max(1, Math.ceil(data.length / 12))

  return (
    <div ref={ref} className="relative w-full text-accent">
      {width > 0 && (
        <svg width={width} height={height} className="block">
          {data.map((d, i) => {
            const bx = i * slot + (slot - bw) / 2
            const h = barHeight(d.value)
            const on = hi === i
            return (
              <g key={i} onMouseEnter={() => setHi(i)} onMouseLeave={() => setHi(null)}>
                <rect x={bx} y={padTop} width={bw} height={innerH} fill="transparent" />
                <rect
                  x={bx}
                  y={padTop + innerH - h}
                  width={bw}
                  height={h}
                  rx={5}
                  fill="currentColor"
                  className="transition-opacity duration-100"
                  opacity={on || hi == null ? 1 : 0.45}
                />
                {i % labelStep === 0 || i === data.length - 1 ? (
                  <text x={bx + bw / 2} y={height - 6} textAnchor="middle" fontSize="11" fill="#9b9ea6">
                    {d.label}
                  </text>
                ) : null}
              </g>
            )
          })}
        </svg>
      )}
      {hi != null && width > 0 && (
        <ChartTip x={hi * slot + slot / 2} y={padTop + innerH - barHeight(data[hi].value)}>
          <div className="text-[11px] opacity-70">{data[hi].sub || data[hi].label}</div>
          <div className="font-semibold">{fmt(data[hi].value)}</div>
        </ChartTip>
      )}
    </div>
  )
}

export interface ListItem {
  label: string
  value: number
}

export function ListPanel({
  items,
  fmt = (v) => String(v),
  barClass = 'bg-accent',
  trackClass = 'bg-accent-soft',
}: {
  items: ListItem[]
  fmt?: (value: number) => string
  barClass?: string
  trackClass?: string
}) {
  const max = Math.max(1, ...items.map((d) => d.value))
  return (
    <div className="flex flex-col gap-[13px]">
      {items.map((d, i) => (
        <div key={i} className="flex items-center gap-3.5">
          <div className="w-[150px] shrink-0 truncate text-[13.5px] text-ink-2">{d.label}</div>
          <div className={cn('h-[9px] flex-1 overflow-hidden rounded-[5px]', trackClass)}>
            <div className={cn('h-full rounded-[5px] transition-[width] duration-500', barClass)} style={{ width: ((d.value / max) * 100).toFixed(1) + '%' }} />
          </div>
          <div className="w-[54px] shrink-0 text-right text-[13.5px] font-semibold tabular-nums text-ink">{fmt(d.value)}</div>
        </div>
      ))}
    </div>
  )
}

export function Funnel({ data }: { data: FunnelStageDatum[] }) {
  const top = Math.max(1, data[0]?.value ?? 0)
  return (
    <div className="flex flex-col gap-0.5">
      {data.map((stage, i) => {
        const pctOfTop = (stage.value / top) * 100
        const prev = i ? data[i - 1].value : stage.value
        const drop = i && prev > 0 ? (1 - stage.value / prev) * 100 : 0
        return (
          <div key={stage.key} className="relative">
            <div className="mb-1.5 flex items-baseline justify-between">
              <span className="text-[13.5px] font-medium text-ink-2">{stage.label}</span>
              <span className="text-[13.5px] font-bold tabular-nums text-ink">
                {stage.value.toLocaleString()}
                <span className="ml-2 font-medium text-muted-2">{pctOfTop.toFixed(0)}%</span>
              </span>
            </div>
            <div className="h-[30px] overflow-hidden rounded-[7px] bg-line-2">
              <div
                className="h-full rounded-[7px] bg-accent transition-[width] duration-500 ease-[cubic-bezier(.2,.7,.3,1)]"
                style={{ width: pctOfTop.toFixed(1) + '%' }}
              />
            </div>
            {i > 0 && drop > 0 && <div className="mt-1 text-[11px] text-muted-2">↓ {drop.toFixed(0)}% drop-off</div>}
          </div>
        )
      })}
    </div>
  )
}

export interface FunnelStageDatum {
  key: string
  label: string
  value: number
}

export function Heatmap({ heat }: { heat: { dow: string[]; data: number[][]; max: number } }) {
  const [hi, setHi] = useState<{ d: number; h: number; v: number } | null>(null)
  const hours = Array.from({ length: 24 }, (_, h) => h)
  const labelHours = [0, 6, 12, 18, 23]
  const max = Math.max(1, heat.max)
  const hourLabel = (h: number) => (h === 0 ? '12a' : h === 12 ? '12p' : h < 12 ? h + 'a' : h - 12 + 'p')
  const hourFull = (h: number) => (h === 0 ? '12am' : h < 12 ? h + 'am' : h === 12 ? '12pm' : h - 12 + 'pm')

  return (
    <div className="relative">
      <div className="flex flex-col gap-1">
        {heat.data.map((row, di) => (
          <div key={di} className="flex items-center gap-2">
            <div className="w-[30px] shrink-0 text-[11.5px] text-muted-2">{heat.dow[di]}</div>
            <div className="grid flex-1 grid-cols-[repeat(24,minmax(0,1fr))] gap-[3px]">
              {row.map((v, h) => (
                <div
                  key={h}
                  onMouseEnter={() => setHi({ d: di, h, v })}
                  onMouseLeave={() => setHi(null)}
                  className="aspect-square rounded-[3px] transition-transform duration-100"
                  style={{
                    background:
                      v === 0
                        ? 'var(--color-accent-soft)'
                        : `color-mix(in oklab, var(--color-accent) ${Math.round((0.18 + 0.82 * (v / max)) * 100)}%, var(--color-accent-soft))`,
                    transform: hi && hi.d === di && hi.h === h ? 'scale(1.25)' : 'none',
                  }}
                />
              ))}
            </div>
          </div>
        ))}
      </div>
      <div className="mt-[7px] flex pl-[38px]">
        <div className="grid flex-1 grid-cols-24 gap-[3px]">
          {hours.map((h) => (
            <div key={h} className="text-center text-[10px] text-muted-2">
              {labelHours.includes(h) ? hourLabel(h) : ''}
            </div>
          ))}
        </div>
      </div>
      {hi && (
        <div className="pointer-events-none absolute left-1/2 top-[-4px] -translate-x-1/2 -translate-y-full rounded-lg bg-ink px-2.5 py-1.5 text-[12px] whitespace-nowrap text-white shadow-[0_6px_20px_rgba(0,0,0,.22)]">
          {heat.dow[hi.d]} {hourFull(hi.h)} · <b>{hi.v}</b> chats
        </div>
      )}
    </div>
  )
}

export function Donut({
  value,
  size = 150,
  stroke = 18,
  label,
  colorClass = 'text-accent',
  trackClass = 'text-line',
}: {
  value: number
  size?: number
  stroke?: number
  label: string
  colorClass?: string
  trackClass?: string
}) {
  const r = (size - stroke) / 2
  const c = 2 * Math.PI * r
  const off = c * (1 - value / 100)
  return (
    <div className="relative shrink-0" style={{ width: size, height: size }}>
      <svg width={size} height={size} className="-rotate-90">
        <circle cx={size / 2} cy={size / 2} r={r} fill="none" strokeWidth={stroke} stroke="currentColor" className={trackClass} />
        <circle
          cx={size / 2}
          cy={size / 2}
          r={r}
          fill="none"
          strokeWidth={stroke}
          stroke="currentColor"
          className={cn('transition-[stroke-dashoffset] duration-700', colorClass)}
          strokeDasharray={c}
          strokeDashoffset={off}
          strokeLinecap="round"
        />
      </svg>
      <div className="absolute inset-0 flex flex-col items-center justify-center">
        <div className="text-[1.4rem] font-bold leading-none tracking-tight text-ink">{label}</div>
      </div>
    </div>
  )
}
