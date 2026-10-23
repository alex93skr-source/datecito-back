<?php

use App\Http\Controllers\Api\V1\ActivityController;
use App\Http\Controllers\Api\V1\Admin\AuditController;
use App\Http\Controllers\Api\V1\Admin\BillingController;
use App\Http\Controllers\Api\V1\Admin\ConnectionController as AdminConnectionController;
use App\Http\Controllers\Api\V1\Admin\DashboardController;
use App\Http\Controllers\Api\V1\Admin\DatecitoController as AdminDatecitoController;
use App\Http\Controllers\Api\V1\Admin\EventController as AdminEventController;
use App\Http\Controllers\Api\V1\Admin\HereController as AdminHereController;
use App\Http\Controllers\Api\V1\Admin\ReportController;
use App\Http\Controllers\Api\V1\Admin\SafeSessionController as AdminSafeSessionController;
use App\Http\Controllers\Api\V1\Admin\SessionController;
use App\Http\Controllers\Api\V1\Admin\SettingsController;
use App\Http\Controllers\Api\V1\Admin\StaffController;
use App\Http\Controllers\Api\V1\Admin\UserActionController;
use App\Http\Controllers\Api\V1\Admin\UserController;
use App\Http\Controllers\Api\V1\Admin\UserNoteController;
use App\Http\Controllers\Api\V1\Admin\VerificationController as AdminVerificationController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CatalogController;
use App\Http\Controllers\Api\V1\ConnectionController;
use App\Http\Controllers\Api\V1\DatecitoController;
use App\Http\Controllers\Api\V1\DiscoverController;
use App\Http\Controllers\Api\V1\EventController;
use App\Http\Controllers\Api\V1\HereController;
use App\Http\Controllers\Api\V1\LikesController;
use App\Http\Controllers\Api\V1\MeController;
use App\Http\Controllers\Api\V1\MembershipController;
use App\Http\Controllers\Api\V1\MessageController;
use App\Http\Controllers\Api\V1\PersonController;
use App\Http\Controllers\Api\V1\PhotoController;
use App\Http\Controllers\Api\V1\SafeSessionController;
use App\Http\Controllers\Api\V1\SafetyController;
use App\Http\Controllers\Api\V1\SwipeController;
use App\Http\Controllers\Api\V1\VerificationController;
use Illuminate\Support\Facades\Route;

/*
| API de Datecito v1. Todas las rutas responden JSON.
|
| Capas de acceso:
|  - Públicas (con límite de intentos): registro, login, recuperación.
|  - Autenticadas: perfil propio, verificación de correo, membresía, eventos.
|  - + correo verificado: seguridad (siempre gratis) y perfil.
|  - + membresía activa (o lanzamiento gratuito): Descubrir, chat, datecitos, Aquí y ahora.
|  - CRM (prefijo admin): rol moderator o admin; algunas acciones sólo admin.
*/

Route::prefix('v1')->name('api.v1.')->group(function (): void {
    Route::get('catalog', CatalogController::class)->name('catalog');

    Route::prefix('auth')->name('auth.')->middleware('throttle:auth')->group(function (): void {
        Route::post('register', [AuthController::class, 'register'])->name('register');
        Route::post('login', [AuthController::class, 'login'])->name('login');
        Route::post('password/forgot', [AuthController::class, 'forgotPassword'])->name('password.forgot');
        Route::post('password/reset', [AuthController::class, 'resetPassword'])->name('password.reset');
    });

    Route::middleware(['auth:sanctum', 'account.active', 'throttle:api'])->group(function (): void {
        Route::post('auth/logout', [AuthController::class, 'logout'])->name('auth.logout');
        Route::post('auth/email/verify', [AuthController::class, 'verifyEmail'])->middleware('throttle:codes')->name('auth.email.verify');
        Route::post('auth/email/resend', [AuthController::class, 'resendCode'])->middleware('throttle:codes')->name('auth.email.resend');

        Route::get('me', [MeController::class, 'show'])->name('me.show');
        Route::delete('me', [MeController::class, 'destroy'])->name('me.destroy');
        Route::get('membership', [MembershipController::class, 'show'])->name('membership.show');
        Route::post('membership/purchases', [MembershipController::class, 'purchase'])->middleware('throttle:purchases')->name('membership.purchase');
        // Eventos de comportamiento enviados por la app (pantallas, acciones de interfaz).
        Route::post('events', [EventController::class, 'store'])->middleware('throttle:events')->name('events.store');

        Route::middleware('email.verified')->group(function (): void {
            // Perfil propio
            Route::patch('me', [MeController::class, 'update'])->name('me.update');
            Route::put('me/settings', [MeController::class, 'updateSettings'])->name('me.settings');
            Route::put('me/filters', [MeController::class, 'updateFilters'])->name('me.filters');
            Route::put('me/location', [MeController::class, 'updateLocation'])->name('me.location');
            Route::put('me/password', [MeController::class, 'changePassword'])->name('me.password');
            Route::get('me/photos', [PhotoController::class, 'index'])->name('photos.index');
            Route::post('me/photos', [PhotoController::class, 'store'])->middleware('throttle:uploads')->name('photos.store');
            Route::put('me/photos/order', [PhotoController::class, 'reorder'])->name('photos.reorder');
            Route::delete('me/photos/{photo}', [PhotoController::class, 'destroy'])->name('photos.destroy');
            Route::get('verification', [VerificationController::class, 'show'])->name('verification.show');
            Route::post('verification', [VerificationController::class, 'store'])->middleware('throttle:uploads')->name('verification.store');

            // Seguridad: nunca depende de la membresía.
            Route::get('blocks', [SafetyController::class, 'blocks'])->name('blocks.index');
            Route::post('blocks', [SafetyController::class, 'block'])->name('blocks.store');
            Route::delete('blocks/{user}', [SafetyController::class, 'unblock'])->name('blocks.destroy');
            Route::post('reports', [SafetyController::class, 'report'])->middleware('throttle:reports')->name('reports.store');
            Route::get('trusted-contacts', [SafetyController::class, 'contacts'])->name('contacts.index');
            Route::post('trusted-contacts', [SafetyController::class, 'addContact'])->name('contacts.store');
            Route::delete('trusted-contacts/{contact}', [SafetyController::class, 'removeContact'])->name('contacts.destroy');
            Route::get('safe-sessions/current', [SafeSessionController::class, 'current'])->name('safe.current');
            Route::post('safe-sessions', [SafeSessionController::class, 'store'])->name('safe.store');
            Route::post('safe-sessions/{session}/check-in', [SafeSessionController::class, 'checkIn'])->name('safe.check-in');
            Route::post('safe-sessions/{session}/help', [SafeSessionController::class, 'help'])->name('safe.help');
            Route::post('safe-sessions/{session}/end', [SafeSessionController::class, 'end'])->name('safe.end');

            // Quién te dio like: sin membresía sólo se ve cuántas personas son (siluetas).
            Route::get('likes', LikesController::class)->name('likes');

            // Experiencia completa (membresía o lanzamiento gratuito).
            Route::middleware('membership')->group(function (): void {
                Route::get('discover', DiscoverController::class)->name('discover');
                Route::post('swipes', [SwipeController::class, 'store'])->middleware('throttle:swipes')->name('swipes.store');
                Route::get('people/{user}', [PersonController::class, 'show'])->name('people.show');
                Route::get('activity', ActivityController::class)->name('activity');

                Route::get('connections', [ConnectionController::class, 'index'])->name('connections.index');
                Route::get('connections/{connection}', [ConnectionController::class, 'show'])->name('connections.show');
                Route::delete('connections/{connection}', [ConnectionController::class, 'destroy'])->name('connections.destroy');
                Route::get('connections/{connection}/messages', [MessageController::class, 'index'])->name('messages.index');
                Route::post('connections/{connection}/messages', [MessageController::class, 'store'])->middleware('throttle:messages')->name('messages.store');
                Route::post('connections/{connection}/datecitos', [DatecitoController::class, 'store'])->name('datecitos.store');

                Route::get('datecitos', [DatecitoController::class, 'index'])->name('datecitos.index');
                Route::get('datecitos/{datecito}', [DatecitoController::class, 'show'])->name('datecitos.show');
                Route::post('datecitos/{datecito}/confirm', [DatecitoController::class, 'confirm'])->name('datecitos.confirm');
                Route::post('datecitos/{datecito}/cancel', [DatecitoController::class, 'cancel'])->name('datecitos.cancel');
                Route::post('datecitos/{datecito}/feedback', [DatecitoController::class, 'feedback'])->name('datecitos.feedback');

                Route::get('here', [HereController::class, 'current'])->name('here.current');
                Route::post('here', [HereController::class, 'store'])->name('here.store');
                Route::delete('here', [HereController::class, 'destroy'])->name('here.destroy');
                Route::get('here/people', [HereController::class, 'people'])->name('here.people');
                Route::post('here/greets', [HereController::class, 'greet'])->middleware('throttle:swipes')->name('here.greet');
                Route::post('here/hidden', [HereController::class, 'hide'])->name('here.hide');
            });

            // CRM / moderación (rol moderator o admin; ver Gates "moderate" y "administer").
            Route::prefix('admin')->name('admin.')->middleware('can:moderate')->group(function (): void {
                Route::get('me', [SessionController::class, 'show'])->name('me');

                // Métricas
                Route::get('dashboard/overview', [DashboardController::class, 'overview'])->name('dashboard.overview');
                Route::get('dashboard/series', [DashboardController::class, 'series'])->name('dashboard.series');
                Route::get('dashboard/multi-series', [DashboardController::class, 'multiSeries'])->name('dashboard.multi-series');
                Route::get('dashboard/funnel', [DashboardController::class, 'funnel'])->name('dashboard.funnel');
                Route::get('dashboard/retention', [DashboardController::class, 'retention'])->name('dashboard.retention');
                Route::get('dashboard/breakdowns', [DashboardController::class, 'breakdowns'])->name('dashboard.breakdowns');
                Route::get('dashboard/live', [DashboardController::class, 'live'])->name('dashboard.live');

                // Personas
                Route::get('users', [UserController::class, 'index'])->name('users.index');
                Route::get('users/export', [UserController::class, 'export'])->name('users.export');
                Route::get('tags', [UserController::class, 'tags'])->name('tags.index');
                Route::get('users/{user}', [UserController::class, 'show'])->name('users.show');
                Route::get('users/{user}/events', [UserController::class, 'events'])->name('users.events');
                Route::get('users/{user}/connections', [UserController::class, 'connections'])->name('users.connections');
                Route::get('users/{user}/swipes', [UserController::class, 'swipes'])->name('users.swipes');
                Route::get('users/{user}/reports', [UserController::class, 'reports'])->name('users.reports');
                Route::get('users/{user}/billing', [UserController::class, 'billing'])->name('users.billing');
                Route::get('users/{user}/audit', [UserController::class, 'audit'])->name('users.audit');

                Route::patch('users/{user}', [UserActionController::class, 'update'])->name('users.update');
                Route::put('users/{user}/status', [UserActionController::class, 'status'])->name('users.status');
                Route::post('users/{user}/verification', [UserActionController::class, 'verification'])->name('users.verification');
                Route::delete('users/{user}/photos/{photo}', [UserActionController::class, 'removePhoto'])->name('users.photos.destroy');
                Route::post('users/{user}/logout', [UserActionController::class, 'logout'])->name('users.logout');
                Route::put('users/{user}/tags', [UserActionController::class, 'tags'])->name('users.tags');
                Route::post('users/{user}/notes', [UserNoteController::class, 'store'])->name('users.notes.store');
                Route::patch('notes/{note}', [UserNoteController::class, 'update'])->name('notes.update');
                Route::delete('notes/{note}', [UserNoteController::class, 'destroy'])->name('notes.destroy');

                Route::middleware('can:administer')->group(function (): void {
                    Route::put('users/{user}/role', [UserActionController::class, 'role'])->name('users.role');
                    Route::post('users/{user}/super-likes', [UserActionController::class, 'superLikes'])->name('users.super-likes');
                    Route::post('users/{user}/membership', [UserActionController::class, 'grantMembership'])->name('users.membership.grant');
                    Route::delete('users/{user}/membership', [UserActionController::class, 'revokeMembership'])->name('users.membership.revoke');
                    Route::delete('users/{user}', [UserActionController::class, 'destroy'])->name('users.destroy');
                });

                // Moderación
                Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
                Route::get('reports/{report}', [ReportController::class, 'show'])->name('reports.show');
                Route::patch('reports/{report}', [ReportController::class, 'update'])->name('reports.update');
                Route::get('verifications', [AdminVerificationController::class, 'index'])->name('verifications.index');
                Route::post('verifications/{verification}', [AdminVerificationController::class, 'review'])->name('verifications.review');

                // Actividad de la comunidad
                Route::get('connections', [AdminConnectionController::class, 'index'])->name('connections.index');
                Route::get('connections/{connection}', [AdminConnectionController::class, 'show'])->name('connections.show');
                Route::get('connections/{connection}/messages', [AdminConnectionController::class, 'messages'])->name('connections.messages');
                Route::post('connections/{connection}/end', [AdminConnectionController::class, 'end'])->name('connections.end');
                Route::get('datecitos', [AdminDatecitoController::class, 'index'])->name('datecitos.index');
                Route::get('datecitos/{datecito}', [AdminDatecitoController::class, 'show'])->name('datecitos.show');
                Route::get('here/sessions', [AdminHereController::class, 'index'])->name('here.index');
                Route::get('safe-sessions', [AdminSafeSessionController::class, 'index'])->name('safe-sessions.index');

                // Monetización
                Route::get('billing/overview', [BillingController::class, 'overview'])->name('billing.overview');
                Route::get('billing/subscriptions', [BillingController::class, 'subscriptions'])->name('billing.subscriptions');
                Route::get('billing/purchases', [BillingController::class, 'purchases'])->name('billing.purchases');

                // Comportamiento
                Route::get('events', [AdminEventController::class, 'index'])->name('events.index');
                Route::get('events/names', [AdminEventController::class, 'names'])->name('events.names');
                Route::get('events/performance', [AdminEventController::class, 'performance'])->name('events.performance');

                // Equipo y sistema
                Route::get('audit', [AuditController::class, 'index'])->name('audit.index');
                Route::get('staff', [StaffController::class, 'index'])->name('staff.index');
                Route::get('settings', [SettingsController::class, 'show'])->name('settings.show');
            });
        });
    });
});
