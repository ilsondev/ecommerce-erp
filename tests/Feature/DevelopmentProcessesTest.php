<?php

use Illuminate\Foundation\DevCommands;

test('the development command starts the scheduler alongside the server and frontend', function () {
    $commands = collect(DevCommands::commands())->keyBy('name');

    expect($commands->get('scheduler')['command'])->toBe('php artisan schedule:work');
    expect($commands->has('server'))->toBeTrue();
    expect($commands->has('vite'))->toBeTrue();
    expect($commands->has('queue'))->toBeFalse();
});
