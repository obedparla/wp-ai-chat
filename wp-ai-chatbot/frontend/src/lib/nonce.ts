// Nonce handling that survives full-page caching. The page bakes a `wp_rest`
// nonce into the config, but caching plugins freeze it; once it ages past the
// nonce tick (12-24h) the REST endpoints 403 for cached anonymous visitors.
// `fetchWithNonce` injects the current nonce and, on a 403, fetches a fresh one
// from the uncached `/nonce` endpoint and retries the request once.

let currentNonce: string | null = null

function getApiUrl(): string | undefined {
  return window.wpaicConfig?.apiUrl
}

function getNonce(): string {
  return currentNonce ?? window.wpaicConfig?.nonce ?? ''
}

export async function refreshNonce(): Promise<string | null> {
  const apiUrl = getApiUrl()
  if (!apiUrl) return null
  try {
    const res = await fetch(`${apiUrl}/nonce`, {
      headers: { Accept: 'application/json' },
      credentials: 'same-origin',
      cache: 'no-store',
    })
    if (!res.ok) return null
    const data = (await res.json()) as { nonce?: unknown }
    if (typeof data.nonce === 'string' && data.nonce) {
      currentNonce = data.nonce
      return data.nonce
    }
    return null
  } catch {
    return null
  }
}

export const fetchWithNonce: typeof fetch = async (input, init) => {
  const send = (nonce: string): Promise<Response> => {
    const headers = new Headers(init?.headers)
    if (nonce) headers.set('X-WP-Nonce', nonce)
    return fetch(input, { ...init, headers })
  }

  const res = await send(getNonce())
  if (res.status !== 403) return res

  const fresh = await refreshNonce()
  return fresh ? send(fresh) : res
}
