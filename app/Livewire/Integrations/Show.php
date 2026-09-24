<?php

namespace App\Livewire\Integrations;

use App\Actions\RunIntegration;
use App\Models\FieldMapping;
use App\Models\Integration;
use App\Models\IntegrationRun;
use App\Models\ValueMap;
use App\Support\Mapping\RecordFilter;
use App\Support\Mapping\Transforms;
use App\Support\SchemaFieldExtractor;
use Cron\CronExpression;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;
use Throwable;

class Show extends Component
{
    use WithPagination;

    public Integration $integration;

    // Field-mapping form
    public ?int $editingId = null;

    public string $source_field = '';

    public string $target_field = '';

    /** @var array<int, array{type: string, param: string}> */
    public array $steps = [];

    public bool $is_required = false;

    public bool $skip_if_empty = false;

    public ?FieldMapping $deleting = null;

    // Schedule
    public string $schedule_cron = '';

    // Records & delivery
    public bool $is_bulk = false;

    public string $source_collection_path = '';

    public string $bulk_mode = 'per_item';

    public string $batch_size = '';

    public string $target_wrapper_path = '';

    public string $response_collection_path = '';

    public string $response_id_path = '';

    public string $source_key_field = '';

    public string $target_key_field = '';

    public bool $skip_synced = true;

    public bool $resend_on_change = false;

    // Record filters
    /** @var array<int, array{field: string, operator: string, value: string}> */
    public array $filters = [];

    // Preview
    public string $preview_json = '';

    /** @var array<string, mixed>|null */
    public ?array $preview = null;

    public ?string $preview_error = null;

    // Synced-records browser
    public string $recordStatus = '';

    public string $recordSearch = '';

    public ?int $viewingRunId = null;

    #[Title('Field mapping')]
    public function mount(Integration $integration): void
    {
        $integration->load(['sourceConnection.system', 'sourceEndpoint', 'targetConnection.system', 'targetEndpoint']);

        $this->integration = $integration;
        $this->fillSettingsFromIntegration();
    }

    protected function fillSettingsFromIntegration(): void
    {
        $i = $this->integration;

        $this->schedule_cron = (string) $i->schedule_cron;
        $this->is_bulk = $i->is_bulk;
        $this->source_collection_path = (string) $i->source_collection_path;
        $this->bulk_mode = $i->bulk_mode;
        $this->batch_size = (string) $i->batch_size;
        $this->target_wrapper_path = (string) $i->target_wrapper_path;
        $this->response_collection_path = (string) $i->response_collection_path;
        $this->response_id_path = (string) $i->response_id_path;
        $this->source_key_field = (string) $i->source_key_field;
        $this->target_key_field = (string) $i->target_key_field;
        $this->skip_synced = $i->skip_synced;
        $this->resend_on_change = $i->resend_on_change;
        $this->filters = collect($i->record_filters ?? [])
            ->map(fn ($f) => ['field' => (string) ($f['field'] ?? ''), 'operator' => (string) ($f['operator'] ?? 'equals'), 'value' => (string) ($f['value'] ?? '')])
            ->all();
    }

    #[Computed]
    public function mappings(): Collection
    {
        return $this->integration->fieldMappings()->get();
    }

    #[Computed]
    public function runs(): LengthAwarePaginator
    {
        return $this->integration->runs()->paginate(15, pageName: 'runsPage');
    }

    #[Computed]
    public function records(): LengthAwarePaginator
    {
        return $this->integration->records()
            ->when($this->recordStatus, fn ($q) => $q->where('status', $this->recordStatus))
            ->when($this->recordSearch, fn ($q) => $q->where(fn ($q) => $q
                ->where('source_key', 'like', "%{$this->recordSearch}%")
                ->orWhere('target_key', 'like', "%{$this->recordSearch}%")))
            ->latest('updated_at')
            ->paginate(15, pageName: 'recordsPage');
    }

    /**
     * @return array<string, int>
     */
    #[Computed]
    public function recordCounts(): array
    {
        return $this->integration->records()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->all();
    }

    #[Computed]
    public function viewingRun(): ?IntegrationRun
    {
        return $this->viewingRunId ? $this->integration->runs()->find($this->viewingRunId) : null;
    }

    /**
     * id => name, for the lookup step select and the mappings table.
     *
     * @return array<int, string>
     */
    #[Computed]
    public function valueMaps(): array
    {
        return ValueMap::query()->orderBy('name')->pluck('name', 'id')->all();
    }

    #[Computed]
    public function nextRunAt(): ?Carbon
    {
        if (blank($this->integration->schedule_cron) || ! CronExpression::isValidExpression($this->integration->schedule_cron)) {
            return null;
        }

        return Carbon::instance((new CronExpression($this->integration->schedule_cron))->getNextRunDate());
    }

    /**
     * Source paths as each record sees them: relative to the list for bulk
     * integrations, with top-level values offered as "$root.…".
     */
    #[Computed]
    public function sourceFieldSuggestions(): array
    {
        $paths = SchemaFieldExtractor::extractPaths($this->integration->sourceEndpoint->response_schema);

        return $this->integration->is_bulk
            ? SchemaFieldExtractor::relativeTo($paths, $this->integration->source_collection_path)
            : $paths;
    }

    /**
     * Target paths relative to one record — batch wrappers stripped.
     */
    #[Computed]
    public function targetFieldSuggestions(): array
    {
        $paths = SchemaFieldExtractor::extractPaths($this->integration->targetEndpoint->request_schema);

        return filled($this->integration->target_wrapper_path)
            ? SchemaFieldExtractor::relativeTo($paths, $this->integration->target_wrapper_path, includeRoot: false)
            : $paths;
    }

    // ---------------------------------------------------------------------
    // Field mappings
    // ---------------------------------------------------------------------

    public function create(): void
    {
        $this->reset(['editingId', 'source_field', 'target_field', 'is_required', 'skip_if_empty']);
        $this->steps = [];
        $this->resetValidation();

        Flux::modal('field-mapping-form')->show();
    }

    public function edit(int $mappingId): void
    {
        $mapping = $this->integration->fieldMappings()->findOrFail($mappingId);

        $this->editingId = $mapping->id;
        $this->source_field = (string) $mapping->source_field;
        $this->target_field = $mapping->target_field;
        $this->steps = collect($mapping->transforms ?? [])
            ->map(fn ($s) => ['type' => (string) $s['type'], 'param' => (string) ($s['param'] ?? '')])
            ->all();
        $this->is_required = $mapping->is_required;
        $this->skip_if_empty = $mapping->skip_if_empty;
        $this->resetValidation();

        Flux::modal('field-mapping-form')->show();
    }

    public function addStep(): void
    {
        $this->steps[] = ['type' => 'trim', 'param' => ''];
    }

    public function removeStep(int $index): void
    {
        unset($this->steps[$index]);
        $this->steps = array_values($this->steps);
    }

    public function moveStep(int $index, int $direction): void
    {
        $target = $index + $direction;

        if (! isset($this->steps[$index], $this->steps[$target])) {
            return;
        }

        [$this->steps[$index], $this->steps[$target]] = [$this->steps[$target], $this->steps[$index]];
    }

    public function save(): void
    {
        $this->validate([
            'source_field' => 'nullable|string|max:255',
            'target_field' => 'required|string|max:255',
            'steps' => 'array',
            'steps.*.type' => ['required', Rule::in(array_keys(Transforms::TYPES))],
            'steps.*.param' => 'nullable|string|max:2000',
        ]);

        $steps = collect($this->steps)->map(fn ($s) => [
            'type' => $s['type'],
            'param' => Transforms::TYPES[$s['type']][1] !== null && $s['param'] !== '' ? $s['param'] : null,
        ])->values()->all();

        foreach ($steps as $i => $step) {
            if (in_array($step['type'], ['lookup', 'template', 'constant', 'replace'], true) && $step['param'] === null) {
                $this->addError("steps.{$i}.param", __('This step needs a value.'));

                return;
            }
        }

        $producesOwnValue = collect($steps)->contains(fn ($s) => in_array($s['type'], ['constant', 'template'], true));

        if ($this->source_field === '' && ! $producesOwnValue) {
            $this->addError('source_field', __('Pick a source field, or add a Constant or Template step.'));

            return;
        }

        $data = [
            'source_field' => $this->source_field ?: null,
            'target_field' => $this->target_field,
            'transforms' => $steps,
            'is_required' => $this->is_required,
            'skip_if_empty' => $this->skip_if_empty,
        ];

        if ($this->editingId) {
            $this->integration->fieldMappings()->findOrFail($this->editingId)->update($data);
            Flux::toast(variant: 'success', text: __('Field mapping updated.'));
        } else {
            $data['sort_order'] = $this->integration->fieldMappings()->max('sort_order') + 1;
            $this->integration->fieldMappings()->create($data);
            Flux::toast(variant: 'success', text: __('Field mapping added.'));
        }

        $this->integration->unsetRelation('fieldMappings');
        unset($this->mappings);
        Flux::modal('field-mapping-form')->close();
    }

    public function moveUp(int $mappingId): void
    {
        $this->swapOrder($mappingId, -1);
    }

    public function moveDown(int $mappingId): void
    {
        $this->swapOrder($mappingId, 1);
    }

    protected function swapOrder(int $mappingId, int $direction): void
    {
        $mappings = $this->integration->fieldMappings()->get();
        $index = $mappings->search(fn ($m) => $m->id === $mappingId);
        $swapWith = $mappings->get($index + $direction);

        if ($swapWith === null) {
            return;
        }

        $current = $mappings->get($index);

        [$currentOrder, $swapOrder] = [$current->sort_order, $swapWith->sort_order];

        // Equal sort orders (e.g. seeded rows) wouldn't move — nudge them apart.
        if ($currentOrder === $swapOrder) {
            $swapOrder = $currentOrder + $direction;
        }

        $current->update(['sort_order' => $swapOrder]);
        $swapWith->update(['sort_order' => $currentOrder]);

        unset($this->mappings);
    }

    public function confirmDelete(int $mappingId): void
    {
        $this->deleting = $this->integration->fieldMappings()->findOrFail($mappingId);

        Flux::modal('confirm-mapping-deletion')->show();
    }

    public function delete(): void
    {
        $this->deleting?->delete();
        $this->deleting = null;

        unset($this->mappings);
        Flux::modal('confirm-mapping-deletion')->close();
        Flux::toast(variant: 'success', text: __('Field mapping removed.'));
    }

    // ---------------------------------------------------------------------
    // Settings
    // ---------------------------------------------------------------------

    public function saveSchedule(): void
    {
        $this->validate(['schedule_cron' => 'nullable|string|max:255']);

        if ($this->schedule_cron !== '' && ! CronExpression::isValidExpression($this->schedule_cron)) {
            $this->addError('schedule_cron', __('That is not a valid cron expression.'));

            return;
        }

        $this->integration->update(['schedule_cron' => $this->schedule_cron ?: null]);

        unset($this->nextRunAt);
        Flux::toast(variant: 'success', text: __('Schedule saved.'));
    }

    /**
     * Save how records are read, identified, batched and acknowledged.
     */
    public function saveDeliverySettings(): void
    {
        $this->validate([
            'source_collection_path' => 'nullable|string|max:255',
            'bulk_mode' => 'required|in:per_item,single_request',
            'batch_size' => 'nullable|integer|min:1|max:10000',
            'target_wrapper_path' => 'nullable|string|max:255',
            'response_collection_path' => 'nullable|string|max:255',
            'response_id_path' => 'nullable|string|max:255',
            'source_key_field' => 'nullable|string|max:255',
            'target_key_field' => 'nullable|string|max:255',
        ]);

        $this->integration->update([
            'is_bulk' => $this->is_bulk,
            'source_collection_path' => $this->source_collection_path ?: null,
            'bulk_mode' => $this->bulk_mode,
            'batch_size' => $this->batch_size !== '' ? (int) $this->batch_size : null,
            'target_wrapper_path' => $this->target_wrapper_path ?: null,
            'response_collection_path' => $this->response_collection_path ?: null,
            'response_id_path' => $this->response_id_path ?: null,
            'source_key_field' => $this->source_key_field ?: null,
            'target_key_field' => $this->target_key_field ?: null,
            'skip_synced' => $this->skip_synced,
            'resend_on_change' => $this->resend_on_change,
        ]);

        unset($this->sourceFieldSuggestions, $this->targetFieldSuggestions);
        Flux::toast(variant: 'success', text: __('Delivery settings saved.'));
    }

    public function addFilter(): void
    {
        $this->filters[] = ['field' => '', 'operator' => 'equals', 'value' => ''];
    }

    public function removeFilter(int $index): void
    {
        unset($this->filters[$index]);
        $this->filters = array_values($this->filters);
    }

    public function saveFilters(): void
    {
        $this->validate([
            'filters' => 'array',
            'filters.*.field' => 'required|string|max:255',
            'filters.*.operator' => ['required', Rule::in(array_keys(RecordFilter::OPERATORS))],
            'filters.*.value' => 'nullable|string|max:1000',
        ]);

        $this->integration->update(['record_filters' => array_values($this->filters) ?: null]);

        Flux::toast(variant: 'success', text: __('Record filters saved.'));
    }

    // ---------------------------------------------------------------------
    // Preview, run, retry
    // ---------------------------------------------------------------------

    public function openPreview(): void
    {
        $this->preview = null;
        $this->preview_error = null;

        Flux::modal('preview')->show();
    }

    /**
     * Map records without sending anything — against the pasted JSON if
     * given, otherwise against a live call to the source endpoint.
     */
    public function runPreview(): void
    {
        $this->preview = null;
        $this->preview_error = null;

        $payload = null;

        if (trim($this->preview_json) !== '') {
            $payload = json_decode($this->preview_json, true);

            if (! is_array($payload)) {
                $this->preview_error = __('That is not valid JSON: :error', ['error' => json_last_error_msg()]);

                return;
            }
        }

        try {
            $this->preview = app(RunIntegration::class)->preview($this->integration->fresh(), $payload);
        } catch (Throwable $e) {
            $this->preview_error = $e->getMessage();
        }
    }

    public function run(): void
    {
        $result = app(RunIntegration::class)->execute($this->integration->fresh(), trigger: 'manual');

        $this->afterRun($result, __('Integration ran successfully.'), __('Integration run failed: :error'));
    }

    public function retry(int $runId): void
    {
        $previous = $this->integration->runs()->findOrFail($runId);

        $result = app(RunIntegration::class)->execute($this->integration->fresh(), trigger: 'retry', attempt: $previous->attempt + 1);

        $this->afterRun($result, __('Retry succeeded.'), __('Retry failed: :error'));
    }

    protected function afterRun(IntegrationRun $result, string $ok, string $failed): void
    {
        $this->integration->refresh();
        unset($this->runs, $this->records, $this->recordCounts);

        $stats = $result->response_payload ?? [];
        $summary = isset($stats['sent'])
            ? __(':sent sent, :succeeded delivered, :invalid invalid, :skipped already synced.', [
                'sent' => $stats['sent'], 'succeeded' => $stats['succeeded'],
                'invalid' => $stats['invalid'] ?? 0, 'skipped' => $stats['already_synced'] ?? 0,
            ])
            : '';

        Flux::toast(
            variant: match ($result->status) {
                'success' => 'success',
                'partial' => 'warning',
                default => 'danger',
            },
            text: $result->status === 'failed'
                ? str_replace(':error', (string) $result->error, $failed)
                : trim($ok.' '.$summary),
        );
    }

    public function showRun(int $runId): void
    {
        $this->viewingRunId = $runId;
        unset($this->viewingRun);

        Flux::modal('run-details')->show();
    }

    /**
     * Drop a record from the ledger so the next run sends it again.
     */
    public function forgetRecord(int $recordId): void
    {
        $this->integration->records()->whereKey($recordId)->delete();

        unset($this->records, $this->recordCounts);
        Flux::toast(variant: 'success', text: __('Record will be sent again on the next run.'));
    }

    public function updatedRecordStatus(): void
    {
        $this->resetPage('recordsPage');
    }

    public function updatedRecordSearch(): void
    {
        $this->resetPage('recordsPage');
    }
}
