<?php

namespace App\Livewire\ValueMaps;

use App\Models\ValueMap;
use App\Support\Mapping\Lookup;
use Flux\Flux;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Lookup tables')]
class Index extends Component
{
    public string $search = '';

    public ?int $editingId = null;

    public string $name = '';

    public string $description = '';

    public string $entries_text = '';

    public string $fallback = 'fail';

    public string $fallback_value = '';

    public bool $case_insensitive = true;

    public ?ValueMap $deleting = null;

    #[Computed]
    public function valueMaps(): Collection
    {
        return ValueMap::query()
            ->when($this->search, fn ($q) => $q->where('name', 'like', "%{$this->search}%"))
            ->orderBy('name')
            ->get();
    }

    public function create(): void
    {
        $this->reset(['editingId', 'name', 'description', 'entries_text', 'fallback_value']);
        $this->fallback = 'fail';
        $this->case_insensitive = true;
        $this->resetValidation();

        Flux::modal('value-map-form')->show();
    }

    public function edit(int $id): void
    {
        $map = ValueMap::findOrFail($id);

        $this->editingId = $map->id;
        $this->name = $map->name;
        $this->description = (string) $map->description;
        $this->entries_text = $map->entriesAsText();
        $this->fallback = $map->fallback;
        $this->fallback_value = (string) $map->fallback_value;
        $this->case_insensitive = $map->case_insensitive;
        $this->resetValidation();

        Flux::modal('value-map-form')->show();
    }

    public function save(): void
    {
        $this->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('value_maps', 'name')->ignore($this->editingId)],
            'description' => 'nullable|string|max:2000',
            'entries_text' => 'required|string',
            'fallback' => ['required', Rule::in(Lookup::FALLBACKS)],
            'fallback_value' => 'nullable|string|max:255|required_if:fallback,default',
        ]);

        $entries = ValueMap::parseEntries($this->entries_text);

        if ($entries === []) {
            $this->addError('entries_text', __('Add at least one "source => target" line.'));

            return;
        }

        $data = [
            'name' => $this->name,
            'description' => $this->description ?: null,
            'entries' => $entries,
            'fallback' => $this->fallback,
            'fallback_value' => $this->fallback === 'default' ? $this->fallback_value : null,
            'case_insensitive' => $this->case_insensitive,
        ];

        if ($this->editingId) {
            ValueMap::findOrFail($this->editingId)->update($data);
            Flux::toast(variant: 'success', text: __('Lookup table updated.'));
        } else {
            ValueMap::create($data);
            Flux::toast(variant: 'success', text: __('Lookup table created.'));
        }

        unset($this->valueMaps);
        Flux::modal('value-map-form')->close();
    }

    public function confirmDelete(int $id): void
    {
        $this->deleting = ValueMap::findOrFail($id);

        Flux::modal('confirm-value-map-deletion')->show();
    }

    public function delete(): void
    {
        if ($this->deleting && ($uses = $this->deleting->usageCount()) > 0) {
            Flux::modal('confirm-value-map-deletion')->close();
            Flux::toast(variant: 'danger', text: __('Still used by :count field mapping(s) — remove it from those first.', ['count' => $uses]));

            return;
        }

        $this->deleting?->delete();
        $this->deleting = null;

        unset($this->valueMaps);
        Flux::modal('confirm-value-map-deletion')->close();
        Flux::toast(variant: 'success', text: __('Lookup table deleted.'));
    }
}
