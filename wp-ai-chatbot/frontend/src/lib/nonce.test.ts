import { describe, it, expect, vi, beforeEach } from 'vitest'

// Fresh module per test so the cached nonce doesn't leak across cases.
async function loadModule() {
  vi.resetModules()
  return await import('./nonce')
}

function response(status: number, body: unknown): Response {
  return {
    status,
    ok: status >= 200 && status < 300,
    json: async () => body,
  } as unknown as Response
}

describe('fetchWithNonce', () => {
  beforeEach(() => {
    window.wpaicConfig = { apiUrl: '/wp-json/wpaic/v1', nonce: 'baked', greeting: '' }
  })

  it('injects the current nonce and passes a non-403 response straight through', async () => {
    const { fetchWithNonce } = await loadModule()
    const fetchMock = vi.fn().mockResolvedValue(response(200, {}))
    vi.stubGlobal('fetch', fetchMock)

    const res = await fetchWithNonce('/wp-json/wpaic/v1/chat/stream', { method: 'POST' })

    expect(res.status).toBe(200)
    expect(fetchMock).toHaveBeenCalledTimes(1)
    expect(new Headers(fetchMock.mock.calls[0][1].headers).get('X-WP-Nonce')).toBe('baked')
  })

  it('refreshes the nonce from /nonce and retries once on a 403', async () => {
    const { fetchWithNonce } = await loadModule()
    const fetchMock = vi
      .fn()
      .mockResolvedValueOnce(response(403, { code: 'rest_cookie_invalid_nonce' }))
      .mockResolvedValueOnce(response(200, { nonce: 'fresh' }))
      .mockResolvedValueOnce(response(200, {}))
    vi.stubGlobal('fetch', fetchMock)

    const res = await fetchWithNonce('/wp-json/wpaic/v1/chat/stream', { method: 'POST' })

    expect(res.status).toBe(200)
    expect(fetchMock).toHaveBeenCalledTimes(3)
    expect(String(fetchMock.mock.calls[1][0])).toContain('/wp-json/wpaic/v1/nonce')
    expect(new Headers(fetchMock.mock.calls[2][1].headers).get('X-WP-Nonce')).toBe('fresh')
  })

  it('returns the original 403 when the nonce refresh fails', async () => {
    const { fetchWithNonce } = await loadModule()
    const fetchMock = vi
      .fn()
      .mockResolvedValueOnce(response(403, {}))
      .mockResolvedValueOnce(response(500, {}))
    vi.stubGlobal('fetch', fetchMock)

    const res = await fetchWithNonce('/wp-json/wpaic/v1/chat/stream', { method: 'POST' })

    expect(res.status).toBe(403)
    expect(fetchMock).toHaveBeenCalledTimes(2)
  })
})
