<?php

use App\Http\Controllers\AiChatController;
use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', [AiChatController::class, 'index'])
        ->middleware('permission:ai-chatbot.view|clients.view|users.view')
        ->name('dashboard');

    Route::middleware('permission:ai-chatbot.view')->group(function () {
        Route::post('/ai-chat', [AiChatController::class, 'chat'])->name('ai-chat');
        Route::post('/ai-chat/stream', [AiChatController::class, 'stream'])->name('ai-chat.stream');
        Route::post('/ai-conversations', [AiChatController::class, 'store'])->name('ai-conversations.store');
        Route::get('/ai-conversations/{conversation}', [AiChatController::class, 'show'])->name('ai-conversations.show');
        Route::delete('/ai-conversations/{conversation}', [AiChatController::class, 'destroy'])->name('ai-conversations.destroy');
    });

    Route::get('/analytics', [AnalyticsController::class, 'index'])
        ->middleware('permission:clients.view|invoices.view|users.view')
        ->name('analytics.index');

    Route::get('/invoices/{invoice}/pdf', [InvoiceController::class, 'pdf'])
        ->middleware('permission:invoices.view|ai-chatbot.view')
        ->name('invoices.pdf');

    Route::middleware('permission:clients.view')->group(function () {
        Route::get('/clients', [ClientController::class, 'index'])->name('clients.index');
    });
    Route::middleware('permission:clients.create')->group(function () {
        Route::get('/clients/create', [ClientController::class, 'create'])->name('clients.create');
        Route::post('/clients', [ClientController::class, 'store'])->name('clients.store');
    });
    Route::middleware('permission:clients.update')->group(function () {
        Route::get('/clients/{client}/edit', [ClientController::class, 'edit'])->name('clients.edit');
        Route::put('/clients/{client}', [ClientController::class, 'update'])->name('clients.update');
    });
    Route::delete('/clients/{client}', [ClientController::class, 'destroy'])
        ->middleware('permission:clients.delete')
        ->name('clients.destroy');

    Route::middleware('permission:projects.view')->group(function () {
        Route::get('/projects', [ProjectController::class, 'index'])->name('projects.index');
    });
    Route::middleware('permission:projects.create')->group(function () {
        Route::get('/projects/create', [ProjectController::class, 'create'])->name('projects.create');
        Route::post('/projects', [ProjectController::class, 'store'])->name('projects.store');
    });
    Route::middleware('permission:projects.update')->group(function () {
        Route::get('/projects/{project}/edit', [ProjectController::class, 'edit'])->name('projects.edit');
        Route::put('/projects/{project}', [ProjectController::class, 'update'])->name('projects.update');
    });
    Route::delete('/projects/{project}', [ProjectController::class, 'destroy'])
        ->middleware('permission:projects.delete')
        ->name('projects.destroy');

    Route::middleware('permission:invoices.view')->group(function () {
        Route::get('/invoices', [InvoiceController::class, 'index'])->name('invoices.index');
    });
    Route::middleware('permission:invoices.create')->group(function () {
        Route::get('/invoices/create', [InvoiceController::class, 'create'])->name('invoices.create');
        Route::post('/invoices', [InvoiceController::class, 'store'])->name('invoices.store');
    });
    Route::middleware('permission:invoices.update')->group(function () {
        Route::get('/invoices/{invoice}/edit', [InvoiceController::class, 'edit'])->name('invoices.edit');
        Route::put('/invoices/{invoice}', [InvoiceController::class, 'update'])->name('invoices.update');
    });
    Route::delete('/invoices/{invoice}', [InvoiceController::class, 'destroy'])
        ->middleware('permission:invoices.delete')
        ->name('invoices.destroy');

    Route::middleware('permission:payments.view')->group(function () {
        Route::get('/payments', [PaymentController::class, 'index'])->name('payments.index');
    });
    Route::middleware('permission:payments.create')->group(function () {
        Route::get('/payments/create', [PaymentController::class, 'create'])->name('payments.create');
        Route::post('/payments', [PaymentController::class, 'store'])->name('payments.store');
    });
    Route::middleware('permission:payments.update')->group(function () {
        Route::get('/payments/{payment}/edit', [PaymentController::class, 'edit'])->name('payments.edit');
        Route::put('/payments/{payment}', [PaymentController::class, 'update'])->name('payments.update');
    });
    Route::delete('/payments/{payment}', [PaymentController::class, 'destroy'])
        ->middleware('permission:payments.delete')
        ->name('payments.destroy');

    Route::middleware('permission:users.view')->group(function () {
        Route::get('/users', [UserController::class, 'index'])->name('users.index');
    });
    Route::middleware('permission:users.create')->group(function () {
        Route::get('/users/create', [UserController::class, 'create'])->name('users.create');
        Route::post('/users', [UserController::class, 'store'])->name('users.store');
    });
    Route::middleware('permission:users.update')->group(function () {
        Route::get('/users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
        Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
    });
    Route::delete('/users/{user}', [UserController::class, 'destroy'])
        ->middleware('permission:users.delete')
        ->name('users.destroy');
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
