// Lightweight shared cache + in-flight dedupe for GET requests.
// Keeps a very short TTL to smooth out rapid navigation without stale data.

type CacheEntry = { timestamp: number; response: any }

const DEFAULT_TTL = 3000 // 3s is enough to absorb bursts when switching modules
const cache = new Map<string, CacheEntry>()
const pending = new Map<string, Promise<any>>()

export function buildKey(path: string, params: Record<string, any> = {}): string {
  const normalizedParams = Object.entries(params)
    .filter(([, v]) => v !== undefined && v !== null)
    .sort(([a], [b]) => a.localeCompare(b))
    .map(([k, v]) => `${k}:${v}`)
    .join('|')
  return `${path}?${normalizedParams}`
}

export function withCacheAndDedupe<T>(
  key: string,
  requester: () => Promise<T>,
  ttl: number = DEFAULT_TTL
): Promise<T> {
  const cached = cache.get(key)
  if (cached && Date.now() - cached.timestamp < ttl) {
    return Promise.resolve(cached.response)
  }

  const inflight = pending.get(key)
  if (inflight) return inflight as Promise<T>

  const requestPromise = requester()
    .then((response) => {
      cache.set(key, { timestamp: Date.now(), response })
      pending.delete(key)
      return response
    })
    .catch((error) => {
      pending.delete(key)
      throw error
    })

  pending.set(key, requestPromise as Promise<any>)
  return requestPromise as Promise<T>
}

export function clearRequestCache() {
  cache.clear()
  pending.clear()
}
