import { useCallback, useEffect, useState } from 'react'
import type { ClearCartIntent, Message } from './useChat'
import { applyCartUpdate, requestClearCart } from '../lib/cart'

export type ClearCartStatus = 'pending' | 'clearing' | 'cleared' | 'cancelled' | 'error'

const STATUS_KEY = 'wpaic_clear_cart_status'

function loadStatuses(): Record<string, ClearCartStatus> {
  try {
    const stored = sessionStorage.getItem(STATUS_KEY)
    if (!stored) return {}
    const parsed = JSON.parse(stored) as Record<string, ClearCartStatus>
    return parsed && typeof parsed === 'object' ? parsed : {}
  } catch {
    return {}
  }
}

// Persist every recorded status (the map only ever holds non-'pending' values, since
// 'pending' is the absence of an entry). This means a reload during an in-flight
// 'clearing' restores as 'clearing' rather than re-opening the popup — important
// because clearing is not idempotent (re-confirming a partial removal would remove
// more units).
function persistStatuses(statuses: Record<string, ClearCartStatus>): void {
  try {
    sessionStorage.setItem(STATUS_KEY, JSON.stringify(statuses))
  } catch {
    // Storage full or unavailable, ignore
  }
}

// Cleared when a new conversation starts so handled tool-call ids do not accumulate
// across conversations in a long-lived tab.
export function clearStoredClearCartStatuses(): void {
  sessionStorage.removeItem(STATUS_KEY)
}

export interface ClearCartDialogCopy {
  title: string
  description: string
  confirmLabel: string
}

export interface ClearCartController {
  pending: ClearCartIntent | null
  pendingDialog: ClearCartDialogCopy | null
  statuses: Record<string, ClearCartStatus>
  confirm: () => void
  cancel: () => void
}

// Build the confirmation popup copy for a clear-cart intent. Lives with the
// controller so ChatWidget just renders {pendingDialog} without local plumbing.
function buildClearCartDialog(intent: ClearCartIntent): ClearCartDialogCopy {
  if (intent.clearAll) {
    const totalQuantity = intent.items.reduce((sum, item) => sum + item.removeQuantity, 0)
    const itemsLabel =
      totalQuantity > 0 ? `all ${totalQuantity} item${totalQuantity === 1 ? '' : 's'}` : 'everything'
    return {
      title: 'Clear your cart?',
      description: `This removes ${itemsLabel} from your cart.`,
      confirmLabel: 'Clear cart',
    }
  }

  const names = intent.items
    .map((item) => {
      const name = item.name || 'item'
      return item.removeAll ? name : `${item.removeQuantity} × ${name}`
    })
    .join(', ')
  return {
    title: intent.items.length === 1 ? 'Remove from cart?' : 'Remove items from cart?',
    description: names ? `This removes ${names} from your cart.` : 'This removes the selected items from your cart.',
    confirmLabel: 'Remove',
  }
}

/**
 * Drives the clear-cart confirmation flow. The chatbot emits a clear_cart intent;
 * this exposes the first unhandled intent (to render the widget-level popup) plus a
 * per-intent status map (to render the inline result badge). The cart mutation runs
 * only after the shopper confirms.
 */
export function useClearCart(messages: Message[]): ClearCartController {
  const [statuses, setStatuses] = useState<Record<string, ClearCartStatus>>(loadStatuses)

  useEffect(() => {
    persistStatuses(statuses)
  }, [statuses])

  const intents: ClearCartIntent[] = []
  for (const message of messages) {
    if (message.clearCartIntents) intents.push(...message.clearCartIntents)
  }

  const pending = intents.find((intent) => statuses[intent.toolCallId] === undefined) ?? null

  const confirm = useCallback(() => {
    if (!pending) return
    const intent = pending

    const wcAjaxUrl = window.wpaicConfig?.wcAjaxUrl
    if (!wcAjaxUrl) {
      setStatuses((prev) => ({ ...prev, [intent.toolCallId]: 'error' }))
      return
    }

    setStatuses((prev) => ({ ...prev, [intent.toolCallId]: 'clearing' }))

    const items = intent.clearAll
      ? undefined
      : intent.items.map((item) => ({ productId: item.productId, quantity: item.removeQuantity }))
    requestClearCart(items, wcAjaxUrl)
      .then((data) => {
        applyCartUpdate(data)
        setStatuses((prev) => ({ ...prev, [intent.toolCallId]: 'cleared' }))
      })
      .catch(() => {
        setStatuses((prev) => ({ ...prev, [intent.toolCallId]: 'error' }))
      })
  }, [pending])

  const cancel = useCallback(() => {
    if (!pending) return
    setStatuses((prev) => ({ ...prev, [pending.toolCallId]: 'cancelled' }))
  }, [pending])

  return {
    pending,
    pendingDialog: pending ? buildClearCartDialog(pending) : null,
    statuses,
    confirm,
    cancel,
  }
}
