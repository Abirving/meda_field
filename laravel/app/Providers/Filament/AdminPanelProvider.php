<?php
namespace App\Providers\Filament;
use Filament\{Panel,PanelProvider};
use Filament\Support\Colors\Color;
use Filament\Http\Middleware\Authenticate;
use Illuminate\Cookie\Middleware\{EncryptCookies,AddQueuedCookiesToResponse};
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
class AdminPanelProvider extends PanelProvider {
 public function panel(Panel $panel):Panel {
  return $panel->default()->id('admin')->path('admin')->login()->brandName('Meda Pass · Operations')->colors(['primary'=>Color::Emerald])
   ->discoverResources(in:app_path('Filament/Resources'),for:'App\\Filament\\Resources')->pages([\Filament\Pages\Dashboard::class])
   ->widgets([\App\Filament\Widgets\OperationsStats::class])
   ->middleware([EncryptCookies::class,AddQueuedCookiesToResponse::class,StartSession::class,\Illuminate\Session\Middleware\AuthenticateSession::class,ShareErrorsFromSession::class,VerifyCsrfToken::class,SubstituteBindings::class,\Filament\Http\Middleware\DisableBladeIconComponents::class,\Filament\Http\Middleware\DispatchServingFilamentEvent::class])
   ->databaseTransactions()
   ->authMiddleware([Authenticate::class,\App\Http\Middleware\SessionGuard::class,\App\Http\Middleware\RequireMfa::class],isPersistent:true);
 }
}
