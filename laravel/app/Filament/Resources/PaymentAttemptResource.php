<?php
namespace App\Filament\Resources;
use App\Models\PaymentAttempt;
use App\Services\Payments;
use Filament\Resources\Resource;
use Filament\Tables\{Table,Columns\TextColumn,Actions\Action};
class PaymentAttemptResource extends Resource {
 protected static ?string $model=PaymentAttempt::class;
 protected static ?string $navigationLabel='Checkout attempts';
 protected static ?string $navigationIcon='heroicon-o-arrow-path';
 public static function table(Table $table):Table{return $table->columns([
  TextColumn::make('merchant_order_id')->searchable(),TextColumn::make('booking_id')->limit(8),
  TextColumn::make('amount')->money('ETB'),TextColumn::make('state')->badge(),
  TextColumn::make('last_checked_at')->dateTime()->timezone('Africa/Addis_Ababa'),
  TextColumn::make('query_count'),TextColumn::make('last_error'),
  TextColumn::make('created_at')->dateTime()->timezone('Africa/Addis_Ababa')
  ])->actions([Action::make('query')->label('Check provider status')->requiresConfirmation()
   ->visible(fn(PaymentAttempt $record)=>in_array($record->state,['REQUESTED','UNKNOWN','PENDING'],true))
   ->action(function(PaymentAttempt $record){abort_unless(auth()->user()->active&&auth()->user()->role==='SUPER_ADMIN',403);app(Payments::class)->reconcile($record);\Filament\Notifications\Notification::make()->title('Status check completed. Review the current state and last error.')->send();})
  ])->defaultSort('created_at','desc');}
 public static function getPages():array{return ['index'=>PaymentAttemptResource\Pages\ListPaymentAttempts::route('/')];}
}
