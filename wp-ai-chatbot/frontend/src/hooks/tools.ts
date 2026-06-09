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

// Human-readable progress label per tool. Every tool the backend can call is
// listed so the UI never surfaces a raw tool name; unknown future tools fall back
// to a friendly generic label in getToolProgressMessage.
export const TOOL_PROGRESS_LABELS: Record<string, string> = {
  search_products: 'Searching products...',
  get_popular_products: 'Loading best sellers...',
  get_product_details: 'Loading product details...',
  get_categories: 'Loading categories...',
  get_cart_contents: 'Checking your cart...',
  compare_products: 'Comparing products...',
  get_order_status: 'Looking up your order...',
  get_checkout_action: 'Getting checkout ready...',
  add_to_cart: 'Adding to cart...',
  clear_cart: 'Updating your cart...',
  get_shipping_info: 'Checking shipping...',
  create_handoff_request: 'Connecting you to support...',
  query_custom_data: 'Looking that up...',
  search_site_content: 'Searching the site...',
  get_page_content: 'Reading the page...',
}
