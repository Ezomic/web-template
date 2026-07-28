<?php

declare(strict_types=1);

arch('strict types everywhere')
    ->expect('App')
    ->toUseStrictTypes();

arch('no debugging left behind')
    ->expect(['dd', 'dump', 'ray', 'var_dump', 'var_export', 'print_r'])
    ->not->toBeUsed();

arch('no Livewire or Filament')
    ->expect('App')
    ->not->toUse(['Livewire', 'Filament']);

arch('controllers extend the base controller')
    ->expect('App\Http\Controllers')
    ->toExtend('App\Http\Controllers\Controller')
    ->ignoring('App\Http\Controllers\Controller');

arch('concrete controllers are final')
    ->expect('App\Http\Controllers')
    ->classes()
    ->toBeFinal()
    ->ignoring('App\Http\Controllers\Controller');

arch('actions expose a handle method')
    ->expect('App\Actions')
    ->toHaveMethod('handle')
    ->ignoring('App\Actions\Fortify');

arch('models extend Eloquent')
    ->expect('App\Models')
    ->toExtend('Illuminate\Database\Eloquent\Model');

arch('form requests extend the framework request')
    ->expect('App\Http\Requests')
    ->toExtend('Illuminate\Foundation\Http\FormRequest');
