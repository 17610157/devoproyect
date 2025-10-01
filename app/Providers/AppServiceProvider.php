<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        // Registra el componente sin depender de autodetección
        if (class_exists(\App\Livewire\UploadsTable::class)) {
            Livewire::component('uploads-table', \App\Livewire\UploadsTable::class);
        } elseif (class_exists(\App\Http\Livewire\UploadsTable::class)) {
            Livewire::component('uploads-table', \App\Http\Livewire\UploadsTable::class);
        }
    }
}
