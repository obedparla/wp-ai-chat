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
