<?php

namespace App\Livewire\Systems;

use App\Models\System;
use Flux\Flux;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Systems')]
class Index extends Component
{
    use WithPagination;

    public string $search = '';

    public ?int $editingId = null;

    #[Validate('required|string|max:255')]
    public string $name = '';

    #[Validate('nullable|string|max:255')]
    public string $base_url = '';

    #[Validate('nullable|string|max:2000')]
    public string $description = '';

    public bool $is_active = true;

    public ?System $deleting = null;

    /**
     * Reset pagination whenever the search term changes.
     */
    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function systems(): Collection
    {
        return System::query()
            ->withCount('endpoints')
            ->when($this->search, fn ($query) => $query->where('name', 'like', "%{$this->search}%")
                ->orWhere('slug', 'like', "%{$this->search}%"))
            ->orderBy('name')
            ->get();
    }


    public function create(): void
    {
        $this->reset(['editingId', 'name', 'base_url', 'description']);
        $this->is_active = true;
        $this->reset();

        Flux::modal('system-form')->show();
    }

    public function edit(int $systemId): void
    {
        $system = System::findOrFail($systemId);

        $this->editingId = $system->id;
        $this->name = $system->name;
        $this->base_url = (string) $system->base_url;
        $this->description = (string) $system->description;
        $this->is_active = $system->is_active;
        $this->reset();

        Flux::modal('system-form')->show();
    }

    // public function reset()
    // {
    //     $this->resetErrorBag();
    //     $this->resetValidationAttributes();
    // }


    public function save(): void
    {
        $validated = $this->validate();
        $validated['base_url'] = $validated['base_url'] ?: null;
        $validated['description'] = $validated['description'] ?: null;

        if ($this->editingId) {
            System::findOrFail($this->editingId)->update($validated);
            Flux::toast(variant: 'success', text: __('System updated.'));
        } else {
            System::create($validated);
            Flux::toast(variant: 'success', text: __('System registered.'));
        }

        unset($this->systems);
        Flux::modal('system-form')->close();
    }


    public function confirmDelete(int $systemId): void
    {
        $this->deleting = System::findOrFail($systemId);

        Flux::modal('confirm-system-deletion')->show();
    }


    public function delete(): void
    {
        $this->deleting?->delete();
        $this->deleting = null;

        unset($this->systems);
        Flux::modal('confirm-system-deletion')->close();
        Flux::toast(variant: 'success', text: __('System deleted.'));
    }
}
