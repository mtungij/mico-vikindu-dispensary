<div class="space-y-6">
    <x-card>
        <div class="grid gap-3 md:grid-cols-4">
            <div><p class="text-xs text-slate-500">Patient</p><p class="font-semibold">{{ $order->patient?->fullName() }}</p></div>
            <div><p class="text-xs text-slate-500">Order</p><p>{{ $order->order_number }}</p></div>
            <div><p class="text-xs text-slate-500">Clinical notes</p><p>{{ $order->clinical_notes }}</p></div>
            <div><p class="text-xs text-slate-500">Diagnosis</p><p>{{ $order->provisional_diagnosis }}</p></div>
        </div>
    </x-card>

    <div class="grid gap-6 xl:grid-cols-[18rem_1fr]">
        <x-card>
            <h3 class="mb-3 font-semibold">Tests</h3>
            @foreach($order->items as $item)
                @if($this->eligibleForEntry($item))
                    <button type="button" wire:key="result-item-{{ $item->id }}" wire:click="selectItem({{ $item->id }})" class="mb-2 block w-full rounded-md p-2 text-left text-sm {{ $itemId === $item->id ? 'bg-primary text-white' : 'bg-slate-100 dark:bg-slate-800' }}">
                        {{ $item->test_name_snapshot }}
                        <span class="block text-xs">{{ $item->sample?->sample_number }} · Ingiza Matokeo</span>
                    </button>
                @else
                    <div wire:key="result-item-{{ $item->id }}" class="mb-2 block w-full rounded-md bg-slate-100 p-2 text-left text-sm opacity-60 dark:bg-slate-800">
                        {{ $item->test_name_snapshot }}
                        <span class="block text-xs">{{ $item->sample?->sample_number ?? 'Sampuli haijakusanywa' }} · {{ $item->result_status ?? $item->status }}</span>
                    </div>
                @endif
            @endforeach
        </x-card>

        <x-card>
            @if($selectedItem && $selectedItem->laboratoryTest)
                <form wire:submit.prevent="submitForVerification" class="space-y-4" wire:key="result-form-{{ $selectedItem->id }}">
                    <h3 class="font-semibold">{{ $selectedItem->laboratoryTest->name }}</h3>

                    @if($errors->any())
                        <div id="result-validation-summary" role="alert" tabindex="-1" class="rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-700">
                            <p class="font-semibold">Rekebisha matokeo yafuatayo:</p>
                            <ul class="mt-1 list-disc pl-5">@foreach($errors->all() as $message)<li>{{ $message }}</li>@endforeach</ul>
                        </div>
                    @endif

                    @php($parameters = $selectedItem->laboratoryTest->parameters->where('is_active', true)->where('is_heading', false))
                    @php($fields = $parameters->isEmpty() ? collect([(object) ['id' => null, 'name' => $selectedItem->laboratoryTest->name, 'result_type' => $selectedItem->laboratoryTest->result_type, 'allowed_values' => null, 'unit' => $selectedItem->laboratoryTest->unit, 'default_reference_range' => $selectedItem->laboratoryTest->default_reference_range, 'is_required' => true]]) : $parameters)

                    @foreach($fields as $parameter)
                        @php($key = (string) ($parameter->id ?? 'main'))
                        @php($field = "values.$key.value")
                        @php($type = $parameter->result_type->value ?? $parameter->result_type)
                        <div wire:key="result-parameter-{{ $selectedItem->id }}-{{ $key }}" data-result-field="{{ $field }}" class="grid gap-2 md:grid-cols-4">
                            <label for="result-{{ $selectedItem->id }}-{{ $key }}" class="py-2 text-sm font-medium">
                                {{ $parameter->name }} @if($parameter->is_required ?? true)<span class="text-red-600">*</span>@endif
                            </label>
                            <div>
                                @if($type === 'positive_negative')
                                    <x-select-input id="result-{{ $selectedItem->id }}-{{ $key }}" wire:model="values.{{ $key }}.value" :class="$errors->has($field) ? 'border-red-500 ring-red-500' : ''"><option value="">Chagua...</option><option value="positive">Positive</option><option value="negative">Negative</option></x-select-input>
                                @elseif($type === 'reactive_non_reactive')
                                    <x-select-input id="result-{{ $selectedItem->id }}-{{ $key }}" wire:model="values.{{ $key }}.value" :class="$errors->has($field) ? 'border-red-500 ring-red-500' : ''"><option value="">Chagua...</option><option value="reactive">Reactive</option><option value="non_reactive">Non-reactive</option></x-select-input>
                                @elseif($type === 'detected_not_detected')
                                    <x-select-input id="result-{{ $selectedItem->id }}-{{ $key }}" wire:model="values.{{ $key }}.value" :class="$errors->has($field) ? 'border-red-500 ring-red-500' : ''"><option value="">Chagua...</option><option value="detected">Detected</option><option value="not_detected">Not detected</option></x-select-input>
                                @elseif($type === 'choice')
                                    <x-select-input id="result-{{ $selectedItem->id }}-{{ $key }}" wire:model="values.{{ $key }}.value" :class="$errors->has($field) ? 'border-red-500 ring-red-500' : ''"><option value="">Chagua...</option>@foreach(($parameter->allowed_values ?? []) as $option)<option value="{{ $option }}">{{ $option }}</option>@endforeach</x-select-input>
                                @elseif($type === 'boolean')
                                    <x-select-input id="result-{{ $selectedItem->id }}-{{ $key }}" wire:model="values.{{ $key }}.value" :class="$errors->has($field) ? 'border-red-500 ring-red-500' : ''"><option value="">Chagua...</option><option value="1">Yes</option><option value="0">No</option></x-select-input>
                                @elseif($type === 'long_text')
                                    <x-textarea id="result-{{ $selectedItem->id }}-{{ $key }}" wire:model="values.{{ $key }}.value" rows="3" :class="$errors->has($field) ? 'border-red-500 ring-red-500' : ''" />
                                @else
                                    <x-text-input id="result-{{ $selectedItem->id }}-{{ $key }}" type="{{ in_array($type, ['date', 'time'], true) ? $type : 'text' }}" inputmode="{{ $type === 'numeric' ? 'decimal' : 'text' }}" wire:model="values.{{ $key }}.value" placeholder="Result" :class="$errors->has($field) ? 'border-red-500 ring-red-500' : ''" />
                                @endif
                                <x-input-error :messages="$errors->get($field)" />
                            </div>
                            <span class="py-2 text-sm">{{ $parameter->unit }}</span>
                            <span class="py-2 text-sm text-slate-500">{{ $parameter->default_reference_range }}</span>
                        </div>
                    @endforeach

                    <x-textarea wire:model="comments" rows="3" placeholder="Comments" />

                    @if(in_array($selectedItem->result_status, ['pending_verification', 'verified', 'released'], true))
                        <div class="rounded-md bg-blue-50 p-3 text-sm text-blue-700">Matokeo haya tayari yametumwa kwa uthibitisho ({{ $selectedItem->result_status }}).</div>
                    @else
                        <div class="flex gap-2">
                            <x-secondary-button wire:click="saveDraft" wire:loading.attr="disabled" wire:target="saveDraft,submitForVerification">
                                <span wire:loading.remove wire:target="saveDraft">Save Draft</span><span wire:loading wire:target="saveDraft">Inahifadhi...</span>
                            </x-secondary-button>
                            <button type="submit" wire:loading.attr="disabled" wire:target="submitForVerification" class="inline-flex items-center justify-center rounded-md bg-primary px-4 py-2 text-sm font-semibold text-white disabled:opacity-60">
                                <span wire:loading.remove wire:target="submitForVerification">Submit for Verification</span>
                                <span wire:loading wire:target="submitForVerification">Inatuma...</span>
                            </button>
                        </div>
                    @endif
                </form>
            @else
                <p class="text-sm text-slate-500">Hakuna kipimo chenye sampuli iliyokubaliwa ambacho kipo tayari kuingiziwa matokeo.</p>
            @endif
        </x-card>
    </div>

    @script
        <script>
            $wire.on('laboratory-validation-failed', (event) => requestAnimationFrame(() => {
                const field = event.field;
                const target = field ? document.querySelector(`[data-result-field="${CSS.escape(field)}"]`) : null;
                (target ?? document.getElementById('result-validation-summary'))?.scrollIntoView({ behavior: 'smooth', block: 'center' });
                target?.querySelector('input, select, textarea')?.focus({ preventScroll: true });
            }))
        </script>
    @endscript
</div>
