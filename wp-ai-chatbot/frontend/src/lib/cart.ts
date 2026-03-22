export interface CartUpdateResponse {
  success?: boolean
  error?: boolean
  fragments?: Record<string, string>
  cart_hash?: string
}

type JQueryTriggerTarget = {
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
