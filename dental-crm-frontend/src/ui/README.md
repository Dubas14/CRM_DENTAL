## UI Layer (Tailwind-first, kit-ready)

Базові компоненти живуть у цьому каталозі та не містять бізнес-логіки. Усі стилі через Tailwind/SCSS util-класи й токени. Мета — легко замінити реалізацію на Ant Design Vue / Naive UI без змін у фічах.

### Токени
- `tokens.ts`: кольори/spacing/radius/розміри, підтримка `.light` / `.dark`.

### Компоненти
- `Button.vue` — props: `variant`, `size`, `block`, `loading`, `disabled`, `type`; emits: `click`.
- `Drawer.vue` — props: `modelValue`, `title`, `width`, `closable`, `closeOnEsc`, `closeOnOutside`; emits: `update:modelValue`, `close`.
- `Tabs.vue` — props: `modelValue`, `tabs: { id, label, badge? }[]`; emits: `update:modelValue`, `change`.
- `Dropdown.vue` — props: `items: { id, label, icon? }[]`, `placement`; emits: `select(id)`.
- `Avatar.vue` — props: `src`, `alt`, `size`, `fallbackText`.
- `Badge.vue` — props: `variant`, `small`.

### Адаптери
- `adapters/index.ts`: інтерфейси для майбутнього меппінгу на Ant/Naive.

### Правила використання
- Компоненти приймають тільки props / emits, без викликів API, router, store.
- Бізнес-компоненти імпортують лише цей шар (не напряму UI-кити).
- Заборонено тягнути Tailwind-класи у сторінки для базових елементів (кнопки, бейджі, drawer, таби, dropdown) — використовуйте UI-компоненти.

