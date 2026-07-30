<script setup lang="ts">
import type { Bookmark, Paginated, Tag } from '~/composables/useApi'

const api = useApi()

const search = ref('')
const activeTag = ref<string | null>(null)
const page = ref(1)

const bookmarks = ref<Bookmark[]>([])
const meta = ref({ current_page: 1, per_page: 20, total: 0, last_page: 1 })
const tags = ref<Tag[]>([])

const loading = ref(false)
const error = ref<string | null>(null)
const elapsedMs = ref<number | null>(null)

async function loadBookmarks() {
  loading.value = true
  error.value = null

  const startedAt = performance.now()

  try {
    const response = await api<Paginated<Bookmark>>('/bookmarks', {
      query: {
        search: search.value || undefined,
        tag: activeTag.value || undefined,
        page: page.value,
      },
    })

    bookmarks.value = response.data
    meta.value = response.meta
  }
  catch (e: unknown) {
    error.value = e instanceof Error ? e.message : 'Something went wrong'
    bookmarks.value = []
  }
  finally {
    elapsedMs.value = Math.round(performance.now() - startedAt)
    loading.value = false
  }
}

async function loadTags() {
  const response = await api<{ data: Tag[] }>('/tags')
  tags.value = response.data
}

function selectTag(slug: string | null) {
  activeTag.value = slug
  page.value = 1
  loadBookmarks()
}

function goToPage(next: number) {
  page.value = Math.min(Math.max(1, next), meta.value.last_page)
  loadBookmarks()
}

// Debounce so we do not fire a request on every keystroke.
let searchTimer: ReturnType<typeof setTimeout> | undefined

watch(search, () => {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => {
    page.value = 1
    loadBookmarks()
  }, 300)
})

onMounted(() => {
  loadTags()
  loadBookmarks()
})

const formatDate = (iso: string) =>
  new Date(iso).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' })
</script>

<template>
  <div class="layout">
    <aside class="sidebar">
      <h2>Tags</h2>
      <ul class="tag-list">
        <li>
          <button :class="{ active: activeTag === null }" @click="selectTag(null)">
            <span>All bookmarks</span>
          </button>
        </li>
        <li v-for="tag in tags" :key="tag.id">
          <button :class="{ active: activeTag === tag.slug }" @click="selectTag(tag.slug)">
            <span>{{ tag.name }}</span>
            <span class="count">{{ tag.bookmarks_count }}</span>
          </button>
        </li>
      </ul>
    </aside>

    <main class="content">
      <div class="toolbar">
        <input
          v-model="search"
          type="search"
          class="search"
          placeholder="Search titles and URLs…"
        >
      </div>

      <p class="status">
        <span v-if="loading">Loading…</span>
        <span v-else-if="error" class="error">{{ error }}</span>
        <span v-else>
          {{ meta.total.toLocaleString() }} bookmarks
          <span v-if="elapsedMs !== null" class="timing">· {{ elapsedMs }} ms</span>
        </span>
      </p>

      <ul class="results">
        <li v-for="bookmark in bookmarks" :key="bookmark.id" class="card">
          <div class="card-head">
            <a :href="bookmark.url" target="_blank" rel="noopener" class="title">
              {{ bookmark.title }}
            </a>
            <span v-if="bookmark.is_pinned" class="pin" title="Pinned">Pinned</span>
          </div>

          <p class="meta-line">
            {{ bookmark.domain }} · {{ formatDate(bookmark.created_at) }}
          </p>

          <p v-if="bookmark.description" class="description">
            {{ bookmark.description }}
          </p>

          <ul class="chips">
            <li v-for="tag in bookmark.tags" :key="tag.id" class="chip">
              {{ tag.name }}
            </li>
          </ul>
        </li>
      </ul>

      <p v-if="!loading && bookmarks.length === 0" class="empty">
        Nothing matches that.
      </p>

      <nav v-if="meta.last_page > 1" class="pager">
        <button :disabled="meta.current_page <= 1" @click="goToPage(meta.current_page - 1)">
          Previous
        </button>
        <span>Page {{ meta.current_page }} of {{ meta.last_page.toLocaleString() }}</span>
        <button :disabled="meta.current_page >= meta.last_page" @click="goToPage(meta.current_page + 1)">
          Next
        </button>
      </nav>
    </main>
  </div>
</template>
