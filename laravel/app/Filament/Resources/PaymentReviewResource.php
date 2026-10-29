<?php
namespace App\Filament\Resources;
use App\Models\PaymentReview;
use App\Services\PaymentResolution;
use Filament\Resources\Resource;
use Filament\Tables\{Table,Columns\TextColumn,Actions\Action,Filters\SelectFilter};
use Filament\Forms\Components\{Select,TextInput,Textarea,Checkbox};
class PaymentReviewResource extends Resource {
 protected static ?string $model=PaymentReview::class;
 protected static ?string $navigationLabel='Payment cases';
 protected static ?string $navigationIcon='heroicon-o-exclamation-triangle';
 public static function table(Table $t):Table{return $t->columns([TextColumn::make('id'),TextColumn::make('provider_ref')->searchable(),TextColumn::make('booking_id')->limit(8),TextColumn::make('amount')->money('ETB'),TextColumn::make('reason'),TextColumn::make('status')->badge(),TextColumn::make('resolution')->wrap(),TextColumn::make('evidence_reference')])->filters([SelectFilter::make('status')->options(['OPEN'=>'Open','RESOLVED'=>'Resolved'])])->actions([
  Action::make('resolve')->visible(fn(PaymentReview $r)=>$r->status==='OPEN')->form([
   Select::make('action')->options(['STORE_CREDIT'=>'Customer agreed to store credit','EXTERNAL_REFUND'=>'Cash refund independently verified','NO_CHARGE'=>'Provider confirms no charge'])->required(),
   TextInput::make('evidence')->label('Provider query or refund evidence reference')->minLength(5)->maxLength(255)->required(),
   Textarea::make('notes')->minLength(20)->maxLength(2000)->required(),
   Checkbox::make('confirmed')->label('I verified the provider status and the customer remedy. This screen does not send a cash refund.')->accepted()->required()
  ])->action(fn(PaymentReview $record,array $data)=>app(PaymentResolution::class)->resolve(auth()->user(),$record,$data['action'],$data['evidence'],$data['notes']))
  ])->defaultSort('created_at','desc');}
 public static function getPages():array{return ['index'=>PaymentReviewResource\Pages\ListPaymentReviews::route('/')];}
}
