<?php
namespace App\Filament\Resources;
use App\Models\{Booking,Field};
use Filament\Resources\Resource;
use Filament\Tables\{Table,Columns\TextColumn,Filters\SelectFilter};
use Illuminate\Database\Eloquent\Builder;
class BookingResource extends Resource {
 protected static ?string $model=Booking::class;protected static ?string $navigationIcon='heroicon-o-ticket';
 public static function getEloquentQuery():Builder{return parent::getEloquentQuery()->whereIn('field_id',Field::visibleTo(auth()->user())->select('id'))->with('field','user');}
 public static function table(Table $table):Table{return $table->columns([TextColumn::make('id')->limit(8)->searchable(),TextColumn::make('field.name'),TextColumn::make('team')->searchable(),TextColumn::make('start_time')->dateTime('d M Y H:i')->timezone('Africa/Addis_Ababa')->sortable(),TextColumn::make('status')->badge(),TextColumn::make('amount')->money('ETB'),TextColumn::make('credit_applied')->money('ETB'),TextColumn::make('telebirr_amount')->money('ETB')])->filters([SelectFilter::make('status')->options(array_combine(['PENDING_PAYMENT','PAID','CHECKED_IN','CANCELLED','EXPIRED','COMPLETED'],['On hold','Paid','Checked in','Cancelled','Expired','Completed']))])->defaultSort('start_time','desc');}
 public static function getPages():array{return ['index'=>BookingResource\Pages\ListBookings::route('/')];}
}
