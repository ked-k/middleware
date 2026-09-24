<?php

use App\Livewire\AuthProfiles\Index as AuthProfilesIndex;
use App\Livewire\Connections\Index as ConnectionsIndex;
use App\Livewire\Integrations\Index as IntegrationsIndex;
use App\Livewire\Integrations\Show as IntegrationsShow;
use App\Livewire\Systems\Index as SystemsIndex;
use App\Livewire\Systems\Show as SystemsShow;
use App\Livewire\ValueMaps\Index as ValueMapsIndex;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');

    Route::livewire('systems', SystemsIndex::class)->name('systems.index');
    Route::livewire('systems/{system}', SystemsShow::class)->name('systems.show');
    Route::livewire('systems/{system}/auth-profiles', AuthProfilesIndex::class)->name('auth-profiles.index');
    Route::livewire('systems/{system}/connections', ConnectionsIndex::class)->name('connections.index');

    Route::livewire('integrations', IntegrationsIndex::class)->name('integrations.index');
    Route::livewire('integrations/{integration}', IntegrationsShow::class)->name('integrations.show');

    Route::livewire('lookup-tables', ValueMapsIndex::class)->name('value-maps.index');
});

require __DIR__.'/settings.php';
