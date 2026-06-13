// Analytics dashboard — the approved "Executive / editorial" layout, skinned
// with the admin design tokens. Three states: a live commerce dashboard, a
// chat-only variant when WooCommerce is inactive, and an empty state for stores
// with no activity in the selected range.
import { type ReactNode } from 'react'
import { type AnalyticsData } from './types'
import { makeFormatters, type Formatters } from './format'
import { Card, SectionTitle, KpiTile, TrendChip } from './ui'
import { LineArea, Bars, ListPanel, Funnel, Heatmap, Donut, type ListItem } from './charts'

export default function AnalyticsApp({ data }: { data: AnalyticsData }) {
  const f = makeFormatters(data.currency)

  if (!data.hasData) return <EmptyState data={data} f={f} />
  if (!data.woocommerceActive) return <ChatOnlyDashboard data={data} f={f} />
  return <Dashboard data={data} f={f} />
}

function toSearchItems(rows: { query: string; count: number }[]): ListItem[] {
  return rows.map((r) => ({ label: r.query, value: r.count }))
}

function Dashboard({ data, f }: { data: AnalyticsData; f: Formatters }) {
  const { totals, deltas, range } = data
  const revSeries = data.series.map((d) => ({ label: d.label, sub: d.date, value: d.revenue }))
  const convSeries = data.series.map((d) => ({ label: d.label, sub: d.date, value: d.conversations }))

  return (
    <div>
      {/* HERO */}
      <div className="mb-4 rounded-[18px] border border-line bg-surface p-8">
        <div className="grid grid-cols-1 items-center gap-10 lg:grid-cols-[0.85fr_1.15fr]">
          <div>
            <div className="text-[13px] font-semibold uppercase tracking-[0.4px] text-accent">Revenue driven</div>
            <div className="my-3 text-[clamp(2.5rem,6vw,4rem)] font-bold leading-none tracking-[-2.4px] tabular-nums text-ink">
              {f.usd(totals.revenue)}
            </div>
            {range.comparable && deltas.revenue != null && (
              <div className="flex items-center gap-3">
                <TrendChip value={deltas.revenue} />
                <span className="text-[13.5px] text-muted-2">{range.prevLabel}</span>
              </div>
            )}
            <div className="mt-7 border-t border-line-2 pt-[22px]">
              <div className="mb-2.5 flex items-baseline justify-between">
                <span className="text-[14px] font-medium text-muted">Share of store revenue</span>
                <span className="text-[22px] font-bold tabular-nums text-ink">{f.pct(data.pctOfStore)}</span>
              </div>
              <div className="h-3 overflow-hidden rounded-[7px] bg-accent-soft">
                <div className="h-full rounded-[7px] bg-accent transition-[width] duration-500" style={{ width: data.pctOfStore + '%' }} />
              </div>
              <div className="mt-2 flex justify-between text-[12.5px] text-muted-2">
                <span>
                  <b className="text-ink">{f.usd(totals.revenue)}</b> via chatbot
                </span>
                <span>{f.usd(data.storeRevenue)} total store</span>
              </div>
            </div>
          </div>
          <div>
            <div className="mb-2 text-[13px] font-semibold text-muted">Revenue over time</div>
            <LineArea data={revSeries} height={224} fmt={f.usd} ariaLabel="Revenue over time" />
          </div>
        </div>
      </div>

      {/* SECONDARY KPIS */}
      <div className="mb-8 grid grid-cols-2 gap-4 sm:grid-cols-3 xl:grid-cols-5">
        <Card pad="p-[22px]">
          <KpiTile label="Orders driven" value={f.num(totals.orders)} delta={deltas.orders} />
        </Card>
        <Card pad="p-[22px]">
          <KpiTile label="Conversion rate" value={f.pct(data.convRate)} delta={deltas.convRate} sub="orders ÷ conversations" />
        </Card>
        <Card pad="p-[22px]">
          <KpiTile label="Average order value" value={f.usdc(data.botAov)} delta={deltas.aov} sub={`store ${f.usdc(data.storeAov)}`} />
        </Card>
        <Card pad="p-[22px]">
          <KpiTile label="Items added to cart" value={f.num(data.itemsAdded)} />
        </Card>
        <Card pad="p-[22px]">
          <KpiTile label="Conversations" value={f.num(totals.conversations)} delta={deltas.conversations} sub={`${data.avgMessages} avg messages`} />
        </Card>
      </div>

      {/* FUNNEL */}
      <SectionTitle>Conversion funnel</SectionTitle>
      <div className="mb-8">
        <Card title="Shopper journey" subtitle="Distinct conversations reaching each stage">
          <Funnel data={data.funnel} />
        </Card>
      </div>

      {/* LEADERBOARDS */}
      <SectionTitle>What shoppers want</SectionTitle>
      <div className="mb-4 grid grid-cols-1 gap-4 lg:grid-cols-2">
        <Card title="Top products" subtitle="Added to cart via chatbot">
          {data.topProducts.length ? (
            <ListPanel items={data.topProducts.map((p) => ({ label: p.name, value: p.count }))} fmt={f.num} />
          ) : (
            <EmptyHint icon={bagIcon}>Products recommended by the chatbot will rank here.</EmptyHint>
          )}
        </Card>
        <Card title="Top searches" subtitle="Most-run queries">
          {data.topSearches.length ? (
            <ListPanel items={toSearchItems(data.topSearches)} fmt={f.num} />
          ) : (
            <EmptyHint>Shopper search queries will show up once chats begin.</EmptyHint>
          )}
        </Card>
      </div>

      <div className="mb-8 grid grid-cols-1 gap-4 lg:grid-cols-2">
        <Card title="Missed searches" subtitle="Queries that returned no results — catalog gaps">
          {data.missedSearches.length ? (
            <ListPanel items={toSearchItems(data.missedSearches)} fmt={f.num} barClass="bg-warn-ink" trackClass="bg-warn-soft" />
          ) : (
            <EmptyHint>No empty-handed searches in this range — your catalog is covering shopper intent.</EmptyHint>
          )}
        </Card>
        <Card title="Conversations" subtitle="Volume per day">
          <Bars data={convSeries} height={172} fmt={(v) => `${f.num(v)} chats`} ariaLabel="Conversations per day" />
        </Card>
      </div>

      {/* BUSIEST TIMES */}
      <Card title="Busiest times" subtitle="Conversations by day of week and hour">
        <div className="mt-2">
          <Heatmap heat={data.heat} />
        </div>
      </Card>
    </div>
  )
}

function ChatOnlyDashboard({ data, f }: { data: AnalyticsData; f: Formatters }) {
  const { totals, deltas } = data
  const convSeries = data.series.map((d) => ({ label: d.label, sub: d.date, value: d.conversations }))

  return (
    <div>
      <div className="mb-4 flex items-start gap-3 rounded-[16px] border border-line bg-accent-soft px-5 py-4 text-accent-ink">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" className="mt-0.5 shrink-0">
          <circle cx="12" cy="12" r="9" />
          <path d="M12 8v4M12 16h.01" />
        </svg>
        <div className="text-[13.5px] leading-relaxed">
          WooCommerce isn’t active, so revenue and order metrics are hidden. Showing conversation, search and support activity.
        </div>
      </div>

      <div className="mb-8 grid grid-cols-2 gap-4 lg:grid-cols-4">
        <Card pad="p-[22px]">
          <KpiTile label="Conversations" value={f.num(totals.conversations)} delta={deltas.conversations} sub={`${data.avgMessages} avg messages`} />
        </Card>
        <Card pad="p-[22px]">
          <KpiTile label="Self-service rate" value={f.pct(data.selfService)} sub="resolved without a handoff" />
        </Card>
        <Card pad="p-[22px]">
          <KpiTile label="Handoffs" value={f.num(data.handoffs)} />
        </Card>
        <Card pad="p-[22px]">
          <KpiTile label="Avg messages" value={String(data.avgMessages)} sub="per conversation" />
        </Card>
      </div>

      <div className="mb-8">
        <Card title="Conversations" subtitle="Volume per day">
          <Bars data={convSeries} height={200} fmt={(v) => `${f.num(v)} chats`} ariaLabel="Conversations per day" />
        </Card>
      </div>

      <SectionTitle>What shoppers want</SectionTitle>
      <div className="mb-8 grid grid-cols-1 gap-4 lg:grid-cols-2">
        <Card title="Top searches" subtitle="Most-run queries">
          {data.topSearches.length ? (
            <ListPanel items={toSearchItems(data.topSearches)} fmt={f.num} />
          ) : (
            <EmptyHint>Shopper search queries will show up once chats begin.</EmptyHint>
          )}
        </Card>
        <Card title="Missed searches" subtitle="Queries that returned no results — content gaps">
          {data.missedSearches.length ? (
            <ListPanel items={toSearchItems(data.missedSearches)} fmt={f.num} barClass="bg-warn-ink" trackClass="bg-warn-soft" />
          ) : (
            <EmptyHint>No empty-handed searches in this range.</EmptyHint>
          )}
        </Card>
      </div>

      <Card title="Busiest times" subtitle="Conversations by day of week and hour">
        <div className="mt-2">
          <Heatmap heat={data.heat} />
        </div>
      </Card>
    </div>
  )
}

function EmptyState({ data, f }: { data: AnalyticsData; f: Formatters }) {
  return (
    <div>
      <div className="mb-4 flex items-center gap-[18px] rounded-[16px] border border-line bg-[linear-gradient(135deg,var(--color-accent-soft),#fff_70%)] px-[26px] py-[22px]">
        <div className="flex h-[46px] w-[46px] shrink-0 items-center justify-center rounded-[12px] bg-accent-soft text-accent">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round">
            <circle cx="12" cy="12" r="9" />
            <path d="M12 7v5l3 2" />
          </svg>
        </div>
        <div className="flex-1">
          <div className="text-[16px] font-semibold text-ink">Collecting your first insights</div>
          <div className="mt-[3px] text-[13.5px] leading-relaxed text-muted">
            Your chatbot is live, but there isn’t enough activity in this range to report on yet. Metrics appear here automatically as shoppers chat, search, and check out.
          </div>
        </div>
      </div>

      <div className="mb-4 grid grid-cols-1 gap-4 lg:grid-cols-[1.75fr_1fr]">
        <div className="rounded-[18px] border border-line bg-surface p-7">
          <div className="text-[13px] font-semibold uppercase tracking-[0.3px] text-muted-2">Revenue driven</div>
          <div className="my-3 text-[56px] font-bold leading-none tracking-[-2px] text-line">{f.usd(0)}</div>
          <EmptyChart />
        </div>
        <div className="flex flex-col items-center justify-center rounded-[18px] border border-line bg-surface p-7 text-center">
          <div className="mb-[18px] text-[13px] font-semibold text-muted">Share of store revenue</div>
          <Donut value={0} label="—" colorClass="text-line" trackClass="text-line-2" ariaLabel="Share of store revenue: no data yet" />
          <div className="mt-[18px] text-[13px] text-muted-2">No attributed orders yet</div>
        </div>
      </div>

      <div className="mb-4 grid grid-cols-2 gap-4 lg:grid-cols-4">
        <Card pad="p-[22px]"><EmptyTile label="Orders driven" unit="order_completed events" /></Card>
        <Card pad="p-[22px]"><EmptyTile label="Conversion rate" unit="orders ÷ conversations" /></Card>
        <Card pad="p-[22px]"><EmptyTile label="Average order value" unit="revenue ÷ orders" /></Card>
        <Card pad="p-[22px]"><EmptyTile label="Items added" unit="add-to-cart events" /></Card>
      </div>

      <div className="mb-4 grid grid-cols-1 gap-4 lg:grid-cols-[1.5fr_1fr]">
        <Card title="Conversion funnel" subtitle="Conversations → products → cart → checkout → order">
          <div className="mt-1 flex flex-col gap-3.5">
            {data.funnel.map((stage) => (
              <div key={stage.key}>
                <div className="mb-1.5 flex justify-between">
                  <span className="text-[13.5px] font-medium text-muted">{stage.label}</span>
                  <span className="text-[13.5px] font-bold text-line">0</span>
                </div>
                <div className="h-[30px] rounded-[7px] bg-line-2" />
              </div>
            ))}
          </div>
        </Card>
        <div className="flex flex-col gap-4">
          <Card title="Top products">
            <EmptyHint icon={bagIcon}>Products recommended by the chatbot will rank here.</EmptyHint>
          </Card>
          <Card title="Top searches">
            <EmptyHint>Shopper search queries will show up once chats begin.</EmptyHint>
          </Card>
        </div>
      </div>

      <Card title="Busiest times" subtitle="Conversations by day of week and hour">
        <div className="mt-2 flex flex-col gap-1">
          {data.heat.dow.map((d) => (
            <div key={d} className="flex items-center gap-2">
              <div className="w-[30px] shrink-0 text-[11.5px] text-line">{d}</div>
              <div className="grid flex-1 grid-cols-[repeat(24,minmax(0,1fr))] gap-[3px]">
                {Array.from({ length: 24 }).map((_, h) => (
                  <div key={h} className="aspect-square rounded-[3px] bg-line-2" />
                ))}
              </div>
            </div>
          ))}
        </div>
      </Card>
    </div>
  )
}

function EmptyChart() {
  return (
    <div className="flex h-[156px] flex-col items-center justify-center gap-2 rounded-[12px] border-[1.5px] border-dashed border-line bg-[repeating-linear-gradient(45deg,#fafafa,#fafafa_10px,#fff_10px,#fff_20px)]">
      <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="#c9c9c6" strokeWidth="1.7" strokeLinecap="round" strokeLinejoin="round">
        <path d="M3 3v16a1 1 0 001 1h16M7 14l3-3 3 2 4-5" />
      </svg>
      <span className="text-[13px] text-muted-2">No revenue recorded yet</span>
    </div>
  )
}

function EmptyTile({ label, unit }: { label: string; unit: string }) {
  return (
    <div>
      <div className="mb-2.5 text-[13px] font-medium text-muted">{label}</div>
      <div className="text-[28px] font-bold leading-none tracking-[-0.8px] text-line">—</div>
      <div className="mt-2 text-[12px] text-muted-2">{unit}</div>
    </div>
  )
}

const searchIcon = (
  <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#b5b5b1" strokeWidth="1.7" strokeLinecap="round" strokeLinejoin="round">
    <circle cx="11" cy="11" r="7" />
    <path d="M21 21l-4-4" />
  </svg>
)

const bagIcon = (
  <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#b5b5b1" strokeWidth="1.7" strokeLinecap="round" strokeLinejoin="round">
    <path d="M6 2L3 6v14a1 1 0 001 1h16a1 1 0 001-1V6l-3-4zM3 6h18M16 10a4 4 0 01-8 0" />
  </svg>
)

function EmptyHint({ children, icon = searchIcon }: { children: ReactNode; icon?: ReactNode }) {
  return (
    <div className="flex flex-col items-center justify-center gap-2.5 px-2 py-[34px] text-center">
      <div aria-hidden="true" className="flex h-[42px] w-[42px] items-center justify-center rounded-[12px] bg-canvas">
        {icon}
      </div>
      <span className="max-w-[240px] text-[13px] leading-relaxed text-muted-2">{children}</span>
    </div>
  )
}
