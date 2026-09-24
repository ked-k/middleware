<?php

namespace App\Livewire\AuthProfiles;

use App\Models\AuthProfile;
use App\Models\System;
use Flux\Flux;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;

class Index extends Component
{
    public System $system;

    public ?int $editingId = null;

    #[Validate('required|string|max:255')]
    public string $name = '';

    #[Validate('required|in:none,api_key,basic,bearer,oauth2')]
    public string $type = 'none';

    // api_key
    public string $header_name = 'X-API-Key';

    public string $api_key = '';

    // basic
    public string $username = '';

    public string $password = '';

    // bearer / oauth2
    public string $token = '';

    public string $client_id = '';

    public string $client_secret = '';

    public string $token_url = '';

    public ?AuthProfile $deleting = null;

    #[Title('Auth Profiles')]
    public function mount(System $system): void
    {
        $this->system = $system;
    }

    #[Computed]
    public function authProfiles(): Collection
    {
        return $this->system->authProfiles()->orderBy('name')->get();
    }

    /**
     * Open the modal to register a new auth profile.
     */
    public function create(): void
    {
        $this->reset([
            'editingId', 'name', 'header_name', 'api_key', 'username',
            'password', 'token', 'client_id', 'client_secret', 'token_url',
        ]);
        $this->type = 'none';
        $this->header_name = 'X-API-Key';
        $this->resetValidation();

        Flux::modal('auth-profile-form')->show();
    }

    /**
     * Open the modal pre-filled to edit an existing auth profile. Secret
     * values are intentionally left blank; leave them empty to keep the
     * stored value unchanged.
     */
    public function edit(int $authProfileId): void
    {
        $profile = $this->system->authProfiles()->findOrFail($authProfileId);

        $this->reset([
            'header_name', 'api_key', 'username', 'password',
            'token', 'client_id', 'client_secret', 'token_url',
        ]);

        $this->editingId = $profile->id;
        $this->name = $profile->name;
        $this->type = $profile->type;
        $this->header_name = $profile->credentials['header_name'] ?? 'X-API-Key';
        $this->username = $profile->credentials['username'] ?? '';
        $this->resetValidation();

        Flux::modal('auth-profile-form')->show();
    }

    /**
     * Persist the create/edit form, merging in previously stored secrets
     * when a secret field is left blank during an edit.
     */
    public function save(): void
    {
        $this->validate();

        $existing = $this->editingId
            ? $this->system->authProfiles()->findOrFail($this->editingId)
            : null;

        $previous = $existing?->credentials ?? [];

        $credentials = match ($this->type) {
            'api_key' => [
                'header_name' => $this->header_name ?: 'X-API-Key',
                'api_key' => $this->api_key ?: ($previous['api_key'] ?? ''),
            ],
            'basic' => [
                'username' => $this->username,
                'password' => $this->password ?: ($previous['password'] ?? ''),
            ],
            'bearer' => [
                'token' => $this->token ?: ($previous['token'] ?? ''),
            ],
            'oauth2' => [
                'client_id' => $this->client_id ?: ($previous['client_id'] ?? ''),
                'client_secret' => $this->client_secret ?: ($previous['client_secret'] ?? ''),
                'token_url' => $this->token_url ?: ($previous['token_url'] ?? ''),
            ],
            default => [],
        };

        $data = [
            'name' => $this->name,
            'type' => $this->type,
            'credentials' => $credentials,
        ];

        if ($existing) {
            $existing->update($data);
            Flux::toast(variant: 'success', text: __('Auth profile updated.'));
        } else {
            $this->system->authProfiles()->create($data);
            Flux::toast(variant: 'success', text: __('Auth profile created.'));
        }

        unset($this->authProfiles);
        Flux::modal('auth-profile-form')->close();
    }

    /**
     * Ask for confirmation before deleting an auth profile.
     */
    public function confirmDelete(int $authProfileId): void
    {
        $this->deleting = $this->system->authProfiles()->findOrFail($authProfileId);

        Flux::modal('confirm-auth-profile-deletion')->show();
    }

    /**
     * Delete the auth profile. Connections using it fall back to no auth.
     */
    public function delete(): void
    {
        $this->deleting?->delete();
        $this->deleting = null;

        unset($this->authProfiles);
        Flux::modal('confirm-auth-profile-deletion')->close();
        Flux::toast(variant: 'success', text: __('Auth profile deleted.'));
    }
}
