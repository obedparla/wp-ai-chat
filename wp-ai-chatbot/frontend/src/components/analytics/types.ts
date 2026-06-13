// Shape of the localized `window.wpaicAnalytics` blob produced by
// WPAIC_Analytics::get_dashboard_data() in PHP. Keep in sync with that method.

export interface RangeOption {
  value: string
  label: string
  url: string
}

export interface AnalyticsRange {
  preset: string
  label: string
  caption: string
  comparable: boolean
  prevLabel: string
  options: RangeOption[]
}

export interface FunnelStage {
  key: string
  label: string
  value: number
}

export interface SeriesPoint {
  label: string
  date: string
  revenue: number
  orders: number
  conversations: number
}

export interface ProductRow {
  name: string
  count: number
  revenue: number
}

export interface SearchRow {
  query: string
  count: number
}

export interface Heatmap {
  dow: string[]
  data: number[][]
  max: number
}

export interface Deltas {
  revenue: number | null
  orders: number | null
  conversations: number | null
  convRate: number | null
  aov: number | null
}

export interface AnalyticsData {
  woocommerceActive: boolean
  currency: { symbol: string; code: string }
  range: AnalyticsRange
  hasData: boolean
  totals: { revenue: number; orders: number; conversations: number }
  storeRevenue: number
  pctOfStore: number
  botAov: number
  storeAov: number
  itemsAdded: number
  avgMessages: number
  convRate: number
  handoffs: number
  selfService: number
  funnel: FunnelStage[]
  series: SeriesPoint[]
  topProducts: ProductRow[]
  topSearches: SearchRow[]
  missedSearches: SearchRow[]
  heat: Heatmap
  deltas: Deltas
}

declare global {
  interface Window {
    wpaicAnalytics?: AnalyticsData
  }
}
