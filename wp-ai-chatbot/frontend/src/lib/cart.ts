export interface CartUpdateResponse {
  success?: boolean
  error?: boolean
  fragments?: Record<string, string>
  cart_hash?: string
}

interface JQueryTriggerTarget {
  trigger: (eventName: string, params?: unknown[]) => void
}

type JQueryFactory = (target: unknown) => JQueryTriggerTarget

function replaceFragment(selector: string, html: string): void {
  let elements: NodeListOf<Element>

  try {
    elements = document.querySelectorAll(selector)
  } catch {
    return
  }

  if (elements.length === 0) return

  const template = document.createElement('template')
  template.innerHTML = html.trim()
  const replacement = template.content.firstElementChild
  if (!replacement) return

  elements.forEach((element) => {
    element.replaceWith(replacement.cloneNode(true))
  })
}

export function hasCartUpdateError(response: CartUpdateResponse): boolean {
  return response.error === true || response.success === false
}

/**
 * Chat session id (set by useChat). Sent with cart requests so the backend can
 * record the real outcome of a chat-initiated cart change in the conversation
 * transcript. Null outside an active chat session.
 */
function getChatSessionId(): string | null {
  try {
    return sessionStorage.getItem('wpaic_session_id')
  } catch {
    return null
  }
}

export interface AddToCartRequest {
  productId: number
  variationId?: number
  quantity?: number
  /** Variation attribute selections, keyed by their `attribute_*` param name. */
  attributes?: Record<string, string>
}

export async function requestAddToCart(
  params: AddToCartRequest,
  wcAjaxUrl: string
): Promise<CartUpdateResponse> {
  const search = new URLSearchParams({
    action: 'woocommerce_ajax_add_to_cart',
    product_id: String(params.productId),
    quantity: String(params.quantity && params.quantity > 0 ? params.quantity : 1),
  })

  if (params.variationId && params.variationId > 0) {
    search.set('variation_id', String(params.variationId))
  }

  if (params.attributes) {
    for (const [key, value] of Object.entries(params.attributes)) {
      search.set(key, value)
    }
  }

  const sessionId = getChatSessionId()
  if (sessionId) {
    search.set('wpaic_session_id', sessionId)
  }

  const response = await fetch(`${wcAjaxUrl}?${search.toString()}`, {
    method: 'POST',
    credentials: 'same-origin',
  })

  if (!response.ok) {
    throw new Error(`Add to cart failed with status ${response.status}`)
  }

  const data = (await response.json()) as CartUpdateResponse

  if (hasCartUpdateError(data)) {
    throw new Error('Add to cart was rejected')
  }

  return data
}

export interface ClearCartRequestItem {
  productId: number
  quantity: number
}

/**
 * Remove items from the cart, or empty it entirely when `items` is omitted/empty.
 * Each item's `quantity` is how many units to remove. Hits the wpaic_clear_cart
 * admin-ajax handler and returns the mini-cart fragments.
 */
export async function requestClearCart(
  items: ClearCartRequestItem[] | undefined,
  wcAjaxUrl: string
): Promise<CartUpdateResponse> {
  const search = new URLSearchParams({ action: 'wpaic_clear_cart' })

  if (items && items.length > 0) {
    search.set(
      'items',
      JSON.stringify(items.map((item) => ({ product_id: item.productId, quantity: item.quantity })))
    )
  }

  const sessionId = getChatSessionId()
  if (sessionId) {
    search.set('wpaic_session_id', sessionId)
  }

  const response = await fetch(`${wcAjaxUrl}?${search.toString()}`, {
    method: 'POST',
    credentials: 'same-origin',
  })

  if (!response.ok) {
    throw new Error(`Clear cart failed with status ${response.status}`)
  }

  const data = (await response.json()) as CartUpdateResponse

  if (hasCartUpdateError(data)) {
    throw new Error('Clear cart was rejected')
  }

  return data
}

/**
 * Fire-and-forget: report that the shopper dismissed a clear/remove confirmation
 * popup, so the admin transcript records the outcome. No-op without a session id.
 */
export function reportCartCancelled(action: 'clear' | 'remove', wcAjaxUrl: string): void {
  const sessionId = getChatSessionId()
  if (!sessionId) return

  const search = new URLSearchParams({
    action: 'wpaic_cart_cancelled',
    cart_action: action,
    wpaic_session_id: sessionId,
  })

  void fetch(`${wcAjaxUrl}?${search.toString()}`, {
    method: 'POST',
    credentials: 'same-origin',
  }).catch(() => {
    // Outcome logging is best-effort; never surface errors to the shopper.
  })
}

export function applyCartUpdate(response: CartUpdateResponse): void {
  const fragments = response.fragments ?? {}

  Object.entries(fragments).forEach(([selector, html]) => {
    replaceFragment(selector, html)
  })

  const jQuery = (window as typeof window & { jQuery?: JQueryFactory }).jQuery
  if (typeof jQuery === 'function') {
    jQuery(document.body).trigger('added_to_cart', [fragments, response.cart_hash ?? '', false])
  }
}
