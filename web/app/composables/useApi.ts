/**
 * Thin wrapper around $fetch that points at the Laravel API.
 *
 *   const api = useApi()
 *   const { data } = await api<{ data: Bookmark[] }>('/bookmarks', { query: { page: 2 } })
 */
export function useApi() {
  const base = useRuntimeConfig().public.apiBase

  return $fetch.create({
    baseURL: base,
    headers: { Accept: 'application/json' },
  })
}

export interface Tag {
  id: number
  name: string
  slug: string
  bookmarks_count: number
}

export interface Bookmark {
  id: number
  title: string
  url: string
  domain: string | null
  description: string | null
  is_pinned: boolean
  created_at: string
  tags: Tag[]
}

export interface Paginated<T> {
  data: T[]
  meta: {
    current_page: number
    per_page: number
    total: number
    last_page: number
  }
}
