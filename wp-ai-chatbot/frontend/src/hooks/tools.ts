// Canonical registry of tool names the frontend treats specially: product-card
// extraction and progress labels. The backend exposes additional tools that flow
// through as generic dynamic-tool parts; those are intentionally not enumerated
// here and fall back to the default progress label.
export type ToolName =
  | 'search_products'
  | 'get_product_details'
  | 'get_popular_products'
  | 'compare_products'
  | 'get_categories'
  | 'get_checkout_action'

const PRODUCT_TOOLS: ReadonlySet<ToolName> = new Set([
  'search_products',
  'get_product_details',
  'get_popular_products',
])

export function isProductTool(name: string): boolean {
  return PRODUCT_TOOLS.has(name as ToolName)
}

export const TOOL_PROGRESS_LABELS: Record<string, string> = {
  search_products: 'Searching products...',
  get_popular_products: 'Loading best sellers...',
  get_product_details: 'Loading product details...',
  get_categories: 'Loading categories...',
}
