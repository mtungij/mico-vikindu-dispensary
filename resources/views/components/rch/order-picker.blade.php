@props(['model', 'options', 'placeholder', 'searchLabel', 'emptyMessage'])

@if ($options->isEmpty())
    <p class="text-sm text-slate-500 dark:text-slate-400">{{ $emptyMessage }}</p>
@else
    <div
        class="min-w-0 space-y-3"
        x-data="{
            selected: $wire.entangle(@js($model)),
            search: '',
            has(id) {
                return this.selected.some(value => String(value) === id);
            },
            remove(id) {
                this.selected = this.selected.filter(value => String(value) !== id);
            },
            matches(name) {
                return name.toLocaleLowerCase().includes(this.search.trim().toLocaleLowerCase());
            },
        }"
    >
        <div class="flex flex-wrap items-center justify-between gap-2">
            <x-secondary-button
                type="button"
                x-ref="trigger"
                aria-haspopup="dialog"
                aria-controls="{{ $model }}-dialog"
                class="min-h-11 max-w-full text-left"
                x-on:click="search = ''; $refs.dialog.showModal(); $refs.options.scrollTop = 0"
            >
                {{ $placeholder }}
                <x-lucide-chevron-down class="size-4 shrink-0" aria-hidden="true" />
            </x-secondary-button>
            <span class="text-sm text-slate-600 dark:text-slate-300" role="status" x-text="selected.length + ' selected'"></span>
        </div>

        <ul x-show="selected.length" x-cloak class="space-y-2">
            @foreach ($options as $id => $option)
                <li
                    wire:key="{{ $model }}-selected-{{ $id }}"
                    data-option-id="{{ $id }}"
                    x-show="has($el.dataset.optionId)"
                    class="flex flex-wrap items-center justify-between gap-x-3 gap-y-1 rounded-md border border-slate-200 px-3 py-2 text-sm dark:border-slate-700"
                >
                    <div class="min-w-0 flex-1 wrap-anywhere">
                        <p class="font-medium">{{ $option['name'] }}</p>
                        <p class="text-slate-600 dark:text-slate-300">{{ $option['price'] }}</p>
                    </div>
                    <x-secondary-button
                        type="button"
                        class="min-h-11"
                        aria-label="Remove {{ $option['name'] }}"
                        x-on:click="remove($el.closest('li').dataset.optionId); $refs.trigger.focus()"
                    >Remove</x-secondary-button>
                </li>
            @endforeach
        </ul>

        <dialog
            wire:ignore.self
            id="{{ $model }}-dialog"
            x-ref="dialog"
            aria-labelledby="{{ $model }}-title"
            x-on:keydown.escape.prevent.stop="$refs.dialog.close()"
            x-on:close="$refs.trigger.focus({ preventScroll: true })"
            class="fixed inset-0 m-auto w-[calc(100%-2rem)] max-w-lg rounded-lg border border-slate-200 bg-white text-slate-900 shadow-xl backdrop:bg-slate-950/60 dark:border-slate-700 dark:bg-card-dark dark:text-slate-100"
        >
            <div class="flex h-[min(36rem,calc(100dvh-2.25rem))] flex-col overflow-y-auto overscroll-contain">
                <div class="shrink-0 space-y-2 border-b border-slate-200 p-4 dark:border-slate-700">
                    <h4 id="{{ $model }}-title" class="font-semibold">{{ $placeholder }}</h4>
                    <x-input-label for="{{ $model }}-search" :value="$searchLabel" />
                    <x-text-input id="{{ $model }}-search" type="search" class="max-sm:text-base" x-model="search" x-on:keydown.enter.prevent autocomplete="off" autofocus />
                </div>

                <div x-ref="options" class="min-h-0 flex-1 overflow-y-auto overscroll-contain px-4 py-2">
                    <fieldset class="min-w-0 space-y-1">
                        <legend class="sr-only">{{ $placeholder }}</legend>
                        @foreach ($options as $id => $option)
                            <label
                                wire:key="{{ $model }}-option-{{ $id }}"
                                data-option-name="{{ $option['name'] }}"
                                x-show="matches($el.dataset.optionName)"
                                class="flex min-h-11 cursor-pointer items-center gap-3 rounded-md px-2 py-2 text-sm hover:bg-slate-100 dark:hover:bg-slate-800"
                            >
                                <x-checkbox
                                    class="shrink-0"
                                    value="{{ $id }}"
                                    x-model="selected"
                                />
                                <span class="min-w-0 wrap-anywhere">
                                    <span class="block font-medium">{{ $option['name'] }}</span>
                                    <span class="block text-slate-600 dark:text-slate-300">{{ $option['price'] }}</span>
                                </span>
                            </label>
                        @endforeach
                    </fieldset>
                    <p
                        x-show="search.trim() && !Array.from($refs.options.querySelectorAll('[data-option-name]')).some(label => matches(label.dataset.optionName))"
                        class="px-2 py-3 text-sm text-slate-600 dark:text-slate-300"
                        role="status"
                    >No matching options.</p>
                </div>

                <div class="flex shrink-0 flex-wrap items-center justify-between gap-2 border-t border-slate-200 p-4 dark:border-slate-700">
                    <span class="text-sm text-slate-600 dark:text-slate-300" role="status" x-text="selected.length + ' selected'"></span>
                    <x-primary-button type="button" class="min-h-11" x-on:click="$refs.dialog.close()">Done</x-primary-button>
                </div>
            </div>
        </dialog>

        {{ $slot }}
    </div>
@endif
