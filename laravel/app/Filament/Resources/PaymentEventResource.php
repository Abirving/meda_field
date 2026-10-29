<?php
namespace App\Filament\Resources;
use App\Models\PaymentEvent;
use Filament\Resources\Resource;
use Filament\Tables\{Table,Columns\TextColumn,Filters\SelectFilter};
class PaymentEventResource extends Resource {
 protected static ?string $model=PaymentEvent::class;protected static ?string $navigationLabel='Payment events';protected static ?string $navigationIcon='heroicon-o-banknotes';
 public static function table(Table $table):Table{return $table->columns([TextColumn::make('provider_ref')->searchable(),TextColumn::make('booking_id')->limit(8),TextColumn::make('amount')->money('ETB'),TextColumn::make('status')->badge(),TextColumn::make('created_at')->dateTime()->timezone('Africa/Addis_Ababa')])->filters([SelectFilter::make('status')->options(['REVIEW_REQUIRED'=>'Manual review required','SUCCESS'=>'Success','FAILED'=>'Failed'])])->defaultSort('created_at','desc');}
 public static function getPages():array{return ['index'=>PaymentEventResource\Pages\ListPaymentEvents::route('/')];}
}
