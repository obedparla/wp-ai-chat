import { useEffect, useState } from 'react'
import type { AddToCartIntent } from './useChat'
import { applyCartUpdate, requestAddToCart, CartUpdateResponse } from '../lib/cart'

export type AddToCartStatus = 'adding' | 'added' | 'error'

const STATUS_KEY = 'wpaic_add_to_cart_status'

function loadStatuses(): Record<string, AddToCartStatus> {
  try {
    const stored = sessionStorage.getItem(STATUS_KEY)
    if (!stored) return {}
    const parsed = JSON.parse(stored) as Record<string, AddToCartStatus>
    return parsed && typeof parsed === 'object' ? parsed : {}
  } catch {
    return {}
  }
}

// Persisted before the request fires ('adding') and again with the terminal result,
// so a page load can never replay an add: an intent with any recorded status already
// executed in a previous page life and re-running it would silently double the cart.
function persistStatus(toolCallId: string, status: AddToCartStatus): void {
  try {
    const statuses = loadStatuses()
    statuses[toolCallId] = status
    sessionStorage.setItem(STATUS_KEY, JSON.stringify(statuses))
  } catch {
    // Storage full or unavailable, ignore
  }
}

// Tool-call ids rehydrated from the persisted chat history (hooks/useChat.ts marks
// them before restoring messages). Adds execute only for intents that arrive on a
// live SSE stream in this page's lifetime; restored intents already ran before the
// reload. Belt and braces alongside the persisted status map in case a status write
// ever failed.
const restoredToolCallIds = new Set<string>()

export function markAddToCartToolCallsRestored(toolCallIds: string[]): void {
  for (const toolCallId of toolCallIds) {
    restoredToolCallIds.add(toolCallId)
  }
}

// Cleared when a new conversation starts so handled tool-call ids do not accumulate
// across conversations in a long-lived tab. Also resets the restored-id guard.
export function clearStoredAddToCartStatuses(): void {
  sessionStorage.removeItem(STATUS_KEY)
  restoredToolCallIds.clear()
}

// Dedupe the network call across re-renders and StrictMode remounts: each unique
// tool call adds to the cart exactly once, no matter how often the message rerenders.
const inFlight = new Map<string, Promise<CartUpdateResponse>>()

function resolveInitialStatus(toolCallId: string): AddToCartStatus {
  const stored = loadStatuses()[toolCallId]
  if (stored === 'added' || stored === 'error') return stored
  if (inFlight.has(toolCallId)) return 'adding'
  // A restored intent, or one persisted as 'adding' in a previous page life (the page
  // unloaded mid-request): never re-fire, show the already-executed outcome.
  if (stored === 'adding' || restoredToolCallIds.has(toolCallId)) return 'added'
  return window.wpaicConfig?.wcAjaxUrl ? 'adding' : 'error'
}

function shouldExecuteIntent(toolCallId: string): boolean {
  if (restoredToolCallIds.has(toolCallId)) return false
  const stored = loadStatuses()[toolCallId]
  if (stored === 'added' || stored === 'error') return false
  // 'adding' without an in-flight promise means a previous page life fired the
  // request; re-firing could double the cart.
  if (stored === 'adding' && !inFlight.has(toolCallId)) return false
  return true
}

/**
 * Executes an add_to_cart intent against WooCommerce exactly once and reports its
 * status for the inline badge. Intents rehydrated from sessionStorage (or with a
 * persisted status) render their stored badge and never re-fire the cart request.
 */
export function useAddToCart(intent: AddToCartIntent): AddToCartStatus {
  const [status, setStatus] = useState<AddToCartStatus>(() =>
    resolveInitialStatus(intent.toolCallId)
  )

  useEffect(() => {
    const wcAjaxUrl = window.wpaicConfig?.wcAjaxUrl
    if (!wcAjaxUrl || !shouldExecuteIntent(intent.toolCallId)) return

    let cancelled = false

    let request = inFlight.get(intent.toolCallId)
    if (!request) {
      persistStatus(intent.toolCallId, 'adding')
      request = requestAddToCart(
        {
          productId: intent.productId,
          variationId: intent.variationId,
          quantity: intent.quantity,
        },
        wcAjaxUrl
      )
      inFlight.set(intent.toolCallId, request)
    }

    request
      .then((data) => {
        persistStatus(intent.toolCallId, 'added')
        if (cancelled) return
        applyCartUpdate(data)
        setStatus('added')
      })
      .catch(() => {
        persistStatus(intent.toolCallId, 'error')
        if (!cancelled) setStatus('error')
      })

    return () => {
      cancelled = true
    }
  }, [intent.toolCallId, intent.productId, intent.variationId, intent.quantity])

  return status
}
